<?php

declare(strict_types=1);

namespace App\Tests\Scraper;

use App\Scraper\Formula2ScheduleScraper;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Throwable;

final class ScheduleScraperLoggingTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function testLogsAFailedScrapeWithoutResponseData(): void
    {
        $logger = new InMemoryLogger();
        $scraper = new Formula2ScheduleScraper(
            fetcher: static fn (): never => throw new RuntimeException('Source unavailable.'),
            scraperLogger: $logger,
        );

        try {
            $scraper->scrape(2026);
            self::fail('Expected the scraper to rethrow the source error.');
        } catch (RuntimeException $exception) {
            self::assertSame('Source unavailable.', $exception->getMessage());
        }

        self::assertSame('info', $logger->records[0]['level']);
        self::assertSame('Schedule scrape started.', $logger->records[0]['message']);
        self::assertSame(['series' => 'F2', 'year' => 2026], $logger->records[0]['context']);
        self::assertSame('error', $logger->records[1]['level']);
        self::assertSame('Schedule scrape failed.', $logger->records[1]['message']);
        self::assertSame('F2', $logger->records[1]['context']['series']);
        self::assertArrayHasKey('exception', $logger->records[1]['context']);
    }
}

final class InMemoryLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string|Stringable, context: array<array-key, mixed>}>
     */
    public array $records = [];

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}
