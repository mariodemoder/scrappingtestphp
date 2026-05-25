<?php

declare(strict_types=1);

namespace Tests;

use App\Providers\VividSeatsProvider;
use App\Services\HttpClient;
use PHPUnit\Framework\TestCase;

final class VividSeatsProviderTest extends TestCase
{
    public function testSupportsDomain(): void
    {
        $provider = new VividSeatsProvider();

        self::assertTrue($provider->supports('https://www.vividseats.com/hamilton-tickets-new-york-richard-rodgers-theatre-new-york-6-23-2026/production/6204797'));
        self::assertFalse($provider->supports('https://seatgeek.com/example'));
    }

    public function testExtractsTicketsFromNextDataPayload(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head></head>
            <body>
            <script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"inventory":[{"section":"Orchestra","row":"A","price":215},{"section":"Mezzanine","row":"B","price":"180"}]}}}</script>
            </body>
            </html>
            HTML;

        $provider = new VividSeatsProvider(new FakeVividSeatsHttpClient($html));
        $tickets = $provider->getTickets('https://www.vividseats.com/hamilton-tickets-new-york-richard-rodgers-theatre-new-york-6-23-2026/production/6204797');

        self::assertCount(2, $tickets);
        self::assertSame('Orchestra', $tickets[0]->section);
        self::assertSame('A', $tickets[0]->row);
        self::assertSame(215.0, $tickets[0]->price);
    }

    public function testThrowsControlledErrorWhenHtmlIsTrackingRedirect(): void
    {
        $html = '<html><body>https://9213422.fls.doubleclick.net/activityi;dc_pre=abc</body></html>';
        $provider = new VividSeatsProvider(new FakeVividSeatsHttpClient($html));

        $this->expectException(\App\Exceptions\ProviderException::class);
        $this->expectExceptionMessage('redirected to a tracking page');

        $provider->getTickets('https://www.vividseats.com/hamilton-tickets-new-york-richard-rodgers-theatre-new-york-6-23-2026/production/6204797');
    }
}

final class FakeVividSeatsHttpClient extends HttpClient
{
    public function __construct(private readonly string $html)
    {
    }

    public function fetch(string $url): string
    {
        return $this->html;
    }
}
