<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:scrape:all',
    description: 'Scrapes and persists all supported racing series schedules.',
)]
final class ScrapeAllSchedulesCommand extends Command
{
    /**
     * @var array<string, string>
     */
    private const array SCRAPER_COMMANDS = [
        'Formula 1' => 'app:scrape:f1',
        'Formula 2' => 'app:scrape:f2',
        'Formula 3' => 'app:scrape:f3',
        'MotoGP' => 'app:scrape:motogp',
        'Moto2' => 'app:scrape:moto2',
        'Moto3' => 'app:scrape:moto3',
        'WorldSBK' => 'app:scrape:wsbk',
    ];

    protected function configure(): void
    {
        $this
            ->addOption('year', null, InputOption::VALUE_REQUIRED, 'Season year to scrape.', '2026')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $year = filter_var($input->getOption('year'), FILTER_VALIDATE_INT);

        if ($year === false) {
            $io->error('The --year option must be an integer.');

            return Command::INVALID;
        }

        $application = $this->getApplication();

        if ($application === null) {
            $io->error('The scraper commands are unavailable.');

            return Command::FAILURE;
        }

        $failedSeries = [];

        foreach (self::SCRAPER_COMMANDS as $series => $commandName) {
            $io->section(sprintf('Scraping %s', $series));

            try {
                $commandInput = new ArrayInput([
                    '--year' => (string) $year,
                ]);
                $commandInput->setInteractive($input->isInteractive());

                $exitCode = $application->find($commandName)->run($commandInput, $output);
            } catch (Throwable $exception) {
                $failedSeries[] = $series;
                $io->error(sprintf('%s failed: %s', $series, $exception->getMessage()));

                continue;
            }

            if ($exitCode !== Command::SUCCESS) {
                $failedSeries[] = $series;
                $io->error(sprintf('%s failed with exit code %d.', $series, $exitCode));
            }
        }

        if ($failedSeries !== []) {
            implode(', ', $failedSeries)
                |> (static fn (string $series) => sprintf('Schedule scraping failed for: %s.', $series))
                |> $io->error(...);

            return Command::FAILURE;
        }

        $io->success(sprintf('Completed schedule scraping for all series for %d.', $year));

        return Command::SUCCESS;
    }
}
