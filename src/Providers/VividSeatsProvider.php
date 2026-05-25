<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\TicketProviderInterface;
use App\DTO\Ticket;
use App\Exceptions\ProviderException;
use App\Services\HttpClient;
use JsonException;
use Symfony\Component\DomCrawler\Crawler;

final class VividSeatsProvider implements TicketProviderInterface
{
    public function __construct(private readonly ?HttpClient $httpClient = null)
    {
    }

    public function supports(string $url): bool
    {
        return str_contains(strtolower($url), 'vividseats.com');
    }

    /**
     * @return list<Ticket>
     */
    public function getTickets(string $url): array
    {
        if (!$this->supports($url)) {
            throw new ProviderException('VividSeatsProvider does not support this URL.');
        }

        $client = $this->httpClient ?? new HttpClient();

        try {
            $html = $client->fetch($url);
        } catch (\RuntimeException $e) {
            throw new ProviderException(
                'VividSeats request blocked or failed. The site may redirect to a tracking page or apply anti-bot protection.',
                0,
                $e
            );
        }

        if (stripos($html, 'doubleclick.net/activityi') !== false) {
            throw new ProviderException('VividSeats response was redirected to a tracking page instead of the event page.');
        }

        $payload = $this->extractJsonPayload($html);
        $tickets = $this->extractTicketsFromPayload($payload);

        if ($tickets === []) {
            throw new ProviderException('No ticket listings found in VividSeats payload.');
        }

        return $tickets;
    }

    /**
     * @return array<mixed>
     */
    private function extractJsonPayload(string $html): array
    {
        $crawler = new Crawler($html);
        $candidates = [
            '//script[@id="__NEXT_DATA__"]',
            '//script[contains(text(), "__INITIAL_STATE__")]',
        ];

        foreach ($candidates as $xpath) {
            $node = $crawler->filterXPath($xpath);
            if ($node->count() === 0) {
                continue;
            }

            $text = trim($node->text());
            if ($text === '') {
                continue;
            }

            $json = $this->extractJsonFromScriptText($text);
            if ($json === null) {
                continue;
            }

            try {
                $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new ProviderException(
            'VividSeats response did not expose a usable ticket payload. The page may be redirecting to tracking or applying anti-bot protection.'
        );
    }

    /**
     * @return list<Ticket>
     */
    private function extractTicketsFromPayload(array $payload): array
    {
        $tickets = [];
        $this->walkNode($payload, $tickets);

        return $tickets;
    }

    /**
     * @param mixed $value
     * @param list<Ticket> $tickets
     */
    private function walkNode(mixed $value, array &$tickets): void
    {
        if (!is_array($value)) {
            return;
        }

        if ($this->isList($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    $ticket = $this->mapCandidateToTicket($item);
                    if ($ticket !== null) {
                        $tickets[] = $ticket;
                    }
                }

                $this->walkNode($item, $tickets);
            }

            return;
        }

        foreach ($value as $child) {
            $this->walkNode($child, $tickets);
        }
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        $index = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $index) {
                return false;
            }

            $index++;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $candidate
     */
    private function mapCandidateToTicket(array $candidate): ?Ticket
    {
        $section = $this->firstString($candidate, ['section', 'section_name', 'sectionName', 'display_section_name', 'seat_section']);
        $row = $this->firstString($candidate, ['row', 'row_name', 'rowName', 'display_row_name', 'seat_row']) ?? 'N/A';
        $price = $this->firstPrice($candidate, ['price', 'listing_price', 'current_price', 'lowest_price', 'face_value']);

        if ($section === null || $price === null) {
            return null;
        }

        return new Ticket(
            section: $section,
            row: $row,
            price: $price,
        );
    }

    /**
     * @param array<string,mixed> $source
     * @param list<string> $keys
     */
    private function firstString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $source
     * @param list<string> $keys
     */
    private function firstPrice(array $source, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];

            if (is_int($value) || is_float($value)) {
                $price = (float) $value;
                return $price > 0 ? $price : null;
            }

            if (is_string($value)) {
                $normalized = str_replace([',', '$'], '', trim($value));
                if (is_numeric($normalized)) {
                    $price = (float) $normalized;
                    return $price > 0 ? $price : null;
                }
            }

            if (is_array($value)) {
                foreach (['amount', 'value', 'total', 'display'] as $nestedKey) {
                    if (!array_key_exists($nestedKey, $value)) {
                        continue;
                    }

                    $nested = $value[$nestedKey];
                    if (is_int($nested) || is_float($nested)) {
                        $price = (float) $nested;
                        return $price > 0 ? $price : null;
                    }

                    if (is_string($nested)) {
                        $normalizedNested = str_replace([',', '$'], '', trim($nested));
                        if (is_numeric($normalizedNested)) {
                            $price = (float) $normalizedNested;
                            return $price > 0 ? $price : null;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function extractJsonFromScriptText(string $text): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        if ($text[0] === '{' || $text[0] === '[') {
            return $text;
        }

        $prefix = 'window.__INITIAL_STATE__ = ';
        if (str_starts_with($text, $prefix)) {
            $text = substr($text, strlen($prefix));
            $text = rtrim($text, " ;\n\r\t");
            return $text !== '' ? $text : null;
        }

        return null;
    }
}
