<?php

declare(strict_types=1);

namespace Tests;

use App\Providers\SeatGeekProvider;
use App\Services\HttpClient;
use PHPUnit\Framework\TestCase;

final class SeatGeekProviderTest extends TestCase
{
    public function testSupportsDomain(): void
    {
        $provider = new SeatGeekProvider();

        self::assertTrue($provider->supports('https://seatgeek.com/aladdin-tickets/theater/2026-07-15-2-pm/18119434'));
        self::assertFalse($provider->supports('https://www.vividseats.com/example'));
    }

    public function testExtractsTicketsFromNextDataPayload(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head></head>
            <body>
            <script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"listings":[{"section":"A","row":"12","price":120},{"section":"A","row":"12","price":130},{"section":"B","row":"3","price":"95"}]}}}</script>
            </body>
            </html>
            HTML;

        $provider = new SeatGeekProvider(new FakeHttpClient($html));
        $tickets = $provider->getTickets('https://seatgeek.com/aladdin-tickets/theater/2026-07-15-2-pm/18119434');

        self::assertCount(3, $tickets);
        self::assertSame('A', $tickets[0]->section);
        self::assertSame('12', $tickets[0]->row);
        self::assertSame(120.0, $tickets[0]->price);
    }
}

final class FakeHttpClient extends HttpClient
{
    public function __construct(private readonly string $html)
    {
    }

    public function fetch(string $url): string
    {
        return $this->html;
    }
}
