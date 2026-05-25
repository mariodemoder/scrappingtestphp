<?php

declare(strict_types=1);

namespace Tests;

use App\DTO\Ticket;
use App\Services\TicketFormatter;
use PHPUnit\Framework\TestCase;

final class TicketFormatterTest extends TestCase
{
    public function testGroupBySectionRowPriceAggregatesAndSorts(): void
    {
        $formatter = new TicketFormatter();

        $rows = $formatter->groupBySectionRowPrice([
            new Ticket('B', '3', 95.0),
            new Ticket('A', '10', 110.0),
            new Ticket('A', '10', 110.0),
            new Ticket('A', '2', 90.0),
        ]);

        self::assertCount(3, $rows);
        self::assertSame('A', $rows[0]['section']);
        self::assertSame('2', $rows[0]['row']);
        self::assertSame(90.0, $rows[0]['price']);
        self::assertSame(1, $rows[0]['count']);

        self::assertSame('A', $rows[1]['section']);
        self::assertSame('10', $rows[1]['row']);
        self::assertSame(110.0, $rows[1]['price']);
        self::assertSame(2, $rows[1]['count']);
    }
}
