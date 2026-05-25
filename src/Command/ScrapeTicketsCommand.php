<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'scrape', description: 'Scrape tickets from a supported event URL')]
final class ScrapeTicketsCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED, 'Event URL from SeatGeek or VividSeats');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = (string) $input->getArgument('url');

        $output->writeln('<info>Bootstrap ready.</info>');
        $output->writeln(sprintf('Received URL: %s', $url));
        $output->writeln('<comment>Provider integration starts in phase 2.</comment>');

        return Command::SUCCESS;
    }
}
