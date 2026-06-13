<?php

declare(strict_types=1);

namespace App\Command;

use App\Importer\SchedulePersister;
use App\Scraper\Formula1ScheduleScraper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:scrape:f1',
    description: 'Scrapes the Formula 1 calendar and prints session start times.',
)]
final class ScrapeFormula1ScheduleCommand extends Command
{
    public function __construct(
        private readonly Formula1ScheduleScraper $scraper,
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
        $io = new SymfonyStyle($input, $output);
        $year = filter_var($input->getOption('year'), FILTER_VALIDATE_INT);

        if ($year === false) {
            $io->error('The --year option must be an integer.');

            return Command::INVALID;
        }

        try {
            $sessions = $this->scraper->scrape($year);
            $persistedSessions = $this->schedulePersister->persist($year, $sessions);
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($sessions === []) {
            $io->warning(sprintf('No F1 sessions found for %d.', $year));

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

        $io->title(sprintf('Formula 1 %d session schedule', $year));
        $table->render();
        $io->success(sprintf('Persisted %d sessions to the database.', $persistedSessions));
        $io->text(sprintf('Source: https://www.formula1.com/en/racing/%d', $year));

        return Command::SUCCESS;
    }
}
