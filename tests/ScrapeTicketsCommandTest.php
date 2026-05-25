<?php

declare(strict_types=1);

namespace Tests;

use App\Command\ScrapeTicketsCommand;
use App\Contracts\TicketProviderInterface;
use App\DTO\Ticket;
use App\Services\TicketFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ScrapeTicketsCommandTest extends TestCase
{
    public function testScrapeCommandRendersTableForSupportedUrl(): void
    {
        $command = new ScrapeTicketsCommand(
            new StubTicketProvider(true, [
                new Ticket('A', '12', 120.0),
                new Ticket('A', '12', 120.0),
            ]),
            new StubTicketProvider(false, []),
            new TicketFormatter(),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'url' => 'https://seatgeek.com/aladdin-tickets/theater/2026-07-15-2-pm/18119434',
        ]);

        self::assertSame(0, $exitCode);
        $output = $tester->getDisplay();
        self::assertStringContainsString('Tickets agrupados por sector, fila y precio', $output);
        self::assertStringContainsString('A', $output);
        self::assertStringContainsString('12', $output);
        self::assertStringContainsString('120.00', $output);
        self::assertStringContainsString('Total de tickets procesados: 2', $output);
    }

    public function testScrapeCommandRejectsUnsupportedUrl(): void
    {
        $command = new ScrapeTicketsCommand(
            new StubTicketProvider(false, []),
            new StubTicketProvider(false, []),
            new TicketFormatter(),
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'url' => 'https://example.com/event',
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Proveedor no soportado', $tester->getDisplay());
    }
}

final class StubTicketProvider implements TicketProviderInterface
{
    /**
     * @param list<Ticket> $tickets
     */
    public function __construct(
        private readonly bool $supports,
        private readonly array $tickets,
    ) {
    }

    public function supports(string $url): bool
    {
        return $this->supports;
    }

    /**
     * @return list<Ticket>
     */
    public function getTickets(string $url): array
    {
        return $this->tickets;
    }
}
