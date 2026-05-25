<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\Ticket;

final class TicketFormatter
{
    /**
     * @param list<Ticket> $tickets
     * @return list<array{section:string,row:string,price:float,count:int}>
     */
    public function groupBySectionRowPrice(array $tickets): array
    {
        $groups = [];

        foreach ($tickets as $ticket) {
            $key = sprintf('%s|%s|%.2f', $ticket->section, $ticket->row, $ticket->price);

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'section' => $ticket->section,
                    'row' => $ticket->row,
                    'price' => $ticket->price,
                    'count' => 0,
                ];
            }

            $groups[$key]['count']++;
        }

        $rows = array_values($groups);

        usort(
            $rows,
            static function (array $a, array $b): int {
                $sectionCompare = strnatcasecmp($a['section'], $b['section']);
                if ($sectionCompare !== 0) {
                    return $sectionCompare;
                }

                $rowCompare = strnatcasecmp($a['row'], $b['row']);
                if ($rowCompare !== 0) {
                    return $rowCompare;
                }

                return $a['price'] <=> $b['price'];
            }
        );

        return $rows;
    }
}
