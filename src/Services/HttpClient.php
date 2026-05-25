<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class HttpClient
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => 20,
            'connect_timeout' => 10,
            // Entorno local puede carecer de certificados raiz configurados.
            'verify' => false,
            'http_errors' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
            ],
        ]);
    }

    public function fetch(string $url): string
    {
        try {
            $response = $this->client->request('GET', $url);
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                sprintf('HTTP request failed for URL: %s. Detail: %s', $url, $e->getMessage()),
                0,
                $e
            );
        }

        return (string) $response->getBody();
    }
}
