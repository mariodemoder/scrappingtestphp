<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Ticket;

interface TicketProviderInterface
{
    public function supports(string $url): bool;

    /**
     * @return list<Ticket>
     */
    public function getTickets(string $url): array;
}
