<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\TicketProviderInterface;
use App\DTO\Ticket;
use App\Exceptions\ProviderException;
use App\Services\HttpClient;
use JsonException;
use Symfony\Component\DomCrawler\Crawler;

final class SeatGeekProvider implements TicketProviderInterface
{
    public function __construct(private readonly ?HttpClient $httpClient = null)
    {
    }

    public function supports(string $url): bool
    {
        return str_contains(strtolower($url), 'seatgeek.com');
    }

    /**
     * @return list<Ticket>
     */
    public function getTickets(string $url): array
    {
        if (!$this->supports($url)) {
            throw new ProviderException('SeatGeekProvider does not support this URL.');
        }

        $client = $this->httpClient ?? new HttpClient();
        try {
            $html = $client->fetch($url);
        } catch (\RuntimeException $e) {
            throw new ProviderException(
                'SeatGeek request blocked or failed. This may be caused by anti-bot protection (403) or network/TLS issues.',
                0,
                $e
            );
        }

        return $this->extractTicketsFromHtml($html);
    }

    /**
     * @return list<Ticket>
     */
    private function extractTicketsFromHtml(string $html): array
    {
        $payload = $this->extractNextDataPayload($html);
        $tickets = [];

        foreach ($this->findListingCandidates($payload) as $candidate) {
            $ticket = $this->mapCandidateToTicket($candidate);
            if ($ticket !== null) {
                $tickets[] = $ticket;
            }
        }

        if ($tickets === []) {
            throw new ProviderException('No ticket listings found in SeatGeek payload.');
        }

        return $tickets;
    }

    /**
     * @return array<mixed>
     */
    private function extractNextDataPayload(string $html): array
    {
        $crawler = new Crawler($html);

        $node = $crawler->filterXPath('//script[@id="__NEXT_DATA__"]');
        if ($node->count() === 0) {
            throw new ProviderException('SeatGeek __NEXT_DATA__ script not found.');
        }

        $json = trim($node->text());
        if ($json === '') {
            throw new ProviderException('SeatGeek __NEXT_DATA__ payload is empty.');
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ProviderException('SeatGeek __NEXT_DATA__ is not valid JSON.', 0, $e);
        }

        if (!is_array($decoded)) {
            throw new ProviderException('SeatGeek payload has unexpected structure.');
        }

        return $decoded;
    }

    /**
     * @param array<mixed> $node
     * @return list<array<string,mixed>>
     */
    private function findListingCandidates(array $node): array
    {
        $candidates = [];
        $this->walkNode($node, $candidates);

        return $candidates;
    }

    /**
     * @param mixed $value
     * @param list<array<string,mixed>> $candidates
     */
    private function walkNode(mixed $value, array &$candidates): void
    {
        if (!is_array($value)) {
            return;
        }

        if ($this->isList($value)) {
            foreach ($value as $item) {
                if (is_array($item) && $this->looksLikeListing($item)) {
                    $candidates[] = $item;
                }

                $this->walkNode($item, $candidates);
            }

            return;
        }

        foreach ($value as $child) {
            $this->walkNode($child, $candidates);
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
    private function looksLikeListing(array $candidate): bool
    {
        $section = $this->firstString($candidate, ['section', 'section_name', 'sectionName', 'display_section_name']);
        $price = $this->firstPrice($candidate, ['price', 'listing_price', 'current_price', 'lowest_price']);

        return $section !== null && $price !== null;
    }

    /**
     * @param array<string,mixed> $candidate
     */
    private function mapCandidateToTicket(array $candidate): ?Ticket
    {
        $section = $this->firstString($candidate, ['section', 'section_name', 'sectionName', 'display_section_name']);
        $row = $this->firstString($candidate, ['row', 'row_name', 'rowName', 'display_row_name']) ?? 'N/A';
        $price = $this->firstPrice($candidate, ['price', 'listing_price', 'current_price', 'lowest_price']);

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
}
