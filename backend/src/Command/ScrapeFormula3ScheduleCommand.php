<?php

declare(strict_types=1);

namespace App\Command;

use App\Importer\SchedulePersister;
use App\Scraper\Formula3ScheduleScraper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:scrape:f3',
    description: 'Scrapes the Formula 3 calendar and prints session start times.',
)]
final class ScrapeFormula3ScheduleCommand extends Command
{
    public function __construct(
        private readonly Formula3ScheduleScraper $scraper,
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
            $io->warning(sprintf('No F3 sessions found for %d.', $year));

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

        $io->title(sprintf('Formula 3 %d session schedule', $year));
        $table->render();
        $io->success(sprintf('Persisted %d sessions to the database.', $persistedSessions));
        $io->text('Source: https://www.fiaformula3.com/Calendar');

        return Command::SUCCESS;
    }
}
