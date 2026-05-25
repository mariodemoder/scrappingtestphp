<?php

declare(strict_types=1);

namespace Tests;

use App\DTO\Ticket;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testTicketDtoHoldsValues(): void
    {
        $ticket = new Ticket('A', '12', 120.00);

        self::assertSame('A', $ticket->section);
        self::assertSame('12', $ticket->row);
        self::assertSame(120.00, $ticket->price);
    }
}
