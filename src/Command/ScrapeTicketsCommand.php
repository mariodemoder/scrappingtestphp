<?php

declare(strict_types=1);

namespace App\Command;

use App\Contracts\TicketProviderInterface;
use App\Exceptions\ProviderException;
use App\Providers\SeatGeekProvider;
use App\Providers\VividSeatsProvider;
use App\Services\TicketFormatter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(name: 'scrape', description: 'Scrape tickets from a supported event URL')]
final class ScrapeTicketsCommand extends Command
{
    public function __construct(
        private readonly TicketProviderInterface $seatGeekProvider = new SeatGeekProvider(),
        private readonly TicketProviderInterface $vividSeatsProvider = new VividSeatsProvider(),
        private readonly TicketFormatter $formatter = new TicketFormatter(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED, 'Event URL from SeatGeek or VividSeats');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = (string) $input->getArgument('url');

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $io->error('URL invalida. Debes proporcionar una URL completa de evento.');
            return Command::FAILURE;
        }

        $provider = $this->resolveProvider($url);
        if ($provider === null) {
            $io->error('Proveedor no soportado. Usa una URL de SeatGeek o VividSeats.');
            return Command::FAILURE;
        }

        try {
            $tickets = $provider->getTickets($url);
        } catch (ProviderException $e) {
            $io->error(sprintf('Error de proveedor: %s', $e->getMessage()));
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error(sprintf('Error inesperado: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        if ($tickets === []) {
            $io->warning('No se encontraron tickets para el evento.');
            return Command::SUCCESS;
        }

        $rows = $this->formatter->groupBySectionRowPrice($tickets);

        $table = new Table($output);
        $table->setHeaders(['Section', 'Row', 'Price', 'Count']);

        foreach ($rows as $row) {
            $table->addRow([
                $row['section'],
                $row['row'],
                number_format($row['price'], 2, '.', ''),
                (string) $row['count'],
            ]);
        }

        $io->title('Tickets agrupados por sector, fila y precio');
        $table->render();
        $io->success(sprintf('Total de tickets procesados: %d', count($tickets)));

        return Command::SUCCESS;
    }

    private function resolveProvider(string $url): ?TicketProviderInterface
    {
        foreach ([$this->seatGeekProvider, $this->vividSeatsProvider] as $provider) {
            if ($provider->supports($url)) {
                return $provider;
            }
        }

        return null;
    }
}
