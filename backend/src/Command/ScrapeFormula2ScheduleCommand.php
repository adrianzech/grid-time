<?php

declare(strict_types=1);

namespace App\Command;

use App\Importer\SchedulePersister;
use App\Scraper\Formula2ScheduleScraper;
use DateTimeZone;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:scrape:f2',
    description: 'Scrapes the Formula 2 calendar and prints session start times.',
)]
final class ScrapeFormula2ScheduleCommand extends Command
{
    public function __construct(
        private readonly Formula2ScheduleScraper $scraper,
        private readonly SchedulePersister $schedulePersister,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('year', null, InputOption::VALUE_REQUIRED, 'Season year to scrape.', '2026')
            ->addOption('timezone', null, InputOption::VALUE_REQUIRED, 'Timezone for parsed session times.', 'Europe/Vienna')
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
            $timezone = new DateTimeZone((string) $input->getOption('timezone'));
            $sessions = $this->scraper->scrape($year, new DateTimeZone('UTC'));
            $persistedSessions = $this->schedulePersister->persist($year, $sessions);
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if ($sessions === []) {
            $io->warning(sprintf('No F2 sessions found for %d.', $year));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Series', 'Round', 'Event', 'Session', 'Date', 'Start', 'End', 'TZ']);

        foreach ($sessions as $session) {
            $startsAt = $session->startsAt->setTimezone($timezone);
            $endsAt = $session->endsAt?->setTimezone($timezone);

            $table->addRow([
                $session->series,
                (string) $session->round,
                $session->location,
                $session->sessionName,
                $startsAt->format('Y-m-d'),
                $startsAt->format('H:i'),
                $endsAt?->format('H:i') ?? '',
                $timezone->getName(),
            ]);
        }

        $io->title(sprintf('Formula 2 %d session schedule', $year));
        $table->render();
        $io->success(sprintf('Persisted %d sessions to the database.', $persistedSessions));
        $io->text('Source: https://www.fiaformula2.com/Calendar');

        return Command::SUCCESS;
    }
}
