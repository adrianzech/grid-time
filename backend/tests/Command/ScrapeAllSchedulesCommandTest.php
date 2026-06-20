<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ScrapeAllSchedulesCommand;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class ScrapeAllSchedulesCommandTest extends TestCase
{
    public function testRunsEveryScraperCommandForTheSelectedYear(): void
    {
        $executedCommands = [];
        $application = $this->createApplication($executedCommands);
        $tester = new CommandTester($application->find('app:scrape:all'));

        $exitCode = $tester->execute(['--year' => '2027']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame([
            'app:scrape:f1:2027',
            'app:scrape:f2:2027',
            'app:scrape:f3:2027',
            'app:scrape:motogp:2027',
            'app:scrape:moto2:2027',
            'app:scrape:moto3:2027',
            'app:scrape:wsbk:2027',
        ], $executedCommands);
    }

    public function testContinuesAfterAScraperFailureAndReturnsFailure(): void
    {
        $executedCommands = [];
        $application = $this->createApplication($executedCommands, ['app:scrape:f3' => Command::FAILURE]);
        $tester = new CommandTester($application->find('app:scrape:all'));

        $exitCode = $tester->execute(['--year' => '2026']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertSame([
            'app:scrape:f1:2026',
            'app:scrape:f2:2026',
            'app:scrape:f3:2026',
            'app:scrape:motogp:2026',
            'app:scrape:moto2:2026',
            'app:scrape:moto3:2026',
            'app:scrape:wsbk:2026',
        ], $executedCommands);
    }

    #[DataProvider('invalidYearProvider')]
    public function testRejectsAnInvalidYear(string $year): void
    {
        $executedCommands = [];
        $application = $this->createApplication($executedCommands);
        $tester = new CommandTester($application->find('app:scrape:all'));

        $exitCode = $tester->execute(['--year' => $year]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertSame([], $executedCommands);
        self::assertStringContainsString('The --year option must be an integer.', $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidYearProvider(): iterable
    {
        yield 'text' => ['next-year'];
        yield 'decimal' => ['2026.5'];
    }

    /**
     * @param list<string>       $executedCommands
     * @param array<string, int> $exitCodes
     */
    private function createApplication(array &$executedCommands, array $exitCodes = []): Application
    {
        $application = new Application();

        foreach ([
            'app:scrape:f1',
            'app:scrape:f2',
            'app:scrape:f3',
            'app:scrape:motogp',
            'app:scrape:moto2',
            'app:scrape:moto3',
            'app:scrape:wsbk',
        ] as $name) {
            $application->addCommands([new class($name, function (string $command) use (&$executedCommands): void {
                $executedCommands[] = $command;
            },
                $exitCodes[$name] ?? Command::SUCCESS,
            ) extends Command {
                public function __construct(private readonly string $commandName, private readonly Closure $recordExecution, private readonly int $exitCode)
                {
                    parent::__construct($commandName);
                }

                protected function configure(): void
                {
                    $this->addOption('year');
                }

                protected function execute(InputInterface $input, OutputInterface $output): int
                {
                    ($this->recordExecution)(sprintf('%s:%s', $this->commandName, $input->getOption('year')));

                    return $this->exitCode;
                }
            }]);
        }

        $application->addCommands([new ScrapeAllSchedulesCommand()]);

        return $application;
    }
}
