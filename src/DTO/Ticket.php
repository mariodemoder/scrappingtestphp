<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class Ticket
{
    public function __construct(
        public string $section,
        public string $row,
        public float $price,
    ) {
    }
}
