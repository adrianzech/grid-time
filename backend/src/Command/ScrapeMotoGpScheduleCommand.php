<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\RacingSession;
use App\Importer\SchedulePersister;
use App\Scraper\MotoGpScheduleScraper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:scrape:motogp',
    description: 'Scrapes the MotoGP calendar and prints session start times.',
)]
final class ScrapeMotoGpScheduleCommand extends Command
{
    public function __construct(
        private readonly MotoGpScheduleScraper $scraper,
        private readonly SchedulePersister $schedulePersister,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('year', null, InputOption::VALUE_REQUIRED, 'Season year to scrape.', '2026')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->executeScrape(
            input: $input,
            output: $output,
            seriesCode: 'MGP',
            seriesName: 'MotoGP',
            scrape: fn (int $year): array => $this->scraper->scrape($year, 'MGP', 'MotoGP'),
        );
    }

    /**
     * @param callable(int): list<RacingSession> $scrape
     */
    private function executeScrape(InputInterface $input, OutputInterface $output, string $seriesCode, string $seriesName, callable $scrape): int
    {
        $io = new SymfonyStyle($input, $output);
        $year = filter_var($input->getOption('year'), FILTER_VALIDATE_INT);

        if ($year === false) {
            $io->error('The --year option must be an integer.');

            return Command::INVALID;
        }

        try {
            $sessions = $scrape($year);
            $persistedSessions = $this->schedulePersister->persist($year, $sessions);
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($sessions === []) {
            $io->warning(sprintf('No %s sessions found for %d.', $seriesCode, $year));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Series', 'Round', 'Event', 'Session', 'Date', 'Start', 'End', 'TZ']);

        foreach ($sessions as $session) {
            $table->addRow([
                $session->series,
                (string) $session->round,
                $session->location,
                $session->sessionName,
                $session->startsAt->format('Y-m-d'),
                $session->startsAt->format('H:i'),
                $session->endsAt?->format('H:i') ?? '',
                'UTC',
            ]);
        }

        $io->title(sprintf('%s %d session schedule', $seriesName, $year));
        $table->render();
        $io->success(sprintf('Persisted %d sessions to the database.', $persistedSessions));
        $io->text(sprintf('Source: https://www.motogp.com/en/calendar/%d', $year));

        return Command::SUCCESS;
    }
}
