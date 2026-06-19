<?php

declare(strict_types=1);

namespace App\Tests\Scraper;

use App\Scraper\WsbkScheduleScraper;
use Closure;
use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WsbkScheduleScraperTest extends TestCase
{
    private const string API_URL = 'https://api.pulselive.worldsbk.com/wsbk-events/v1';

    /**
     * @throws JsonException
     */
    public function testScrapesOnlyWorldSbkSessionsInChronologicalOrder(): void
    {
        $scraper = new WsbkScheduleScraper($this->fetcher([
            self::API_URL . '/seasons/2026/rounds' => $this->json([
                'data' => [
                    $this->round('POR', 2, 'Pirelli Portuguese Round', 'Autódromo Internacional do Algarve'),
                    $this->round('AUS', 1, 'Australian Round', 'Phillip Island Grand Prix Circuit'),
                ],
            ]),
            self::API_URL . '/seasons/2026/rounds/AUS/sessions' => $this->json([
                'data' => [
                    $this->session('SSP', 'Race 1', '2026-02-21T03:30:00+00:00', '2026-02-21T03:48:00+00:00', '2026-02-21T14:30:00+00:00'),
                    $this->session('SBK', 'Race 1', '2026-02-21T05:00:00+00:00', '2026-02-21T05:22:00+00:00', '2026-02-21T16:00:00+00:00'),
                    $this->session('SBK', 'Free Practice 1st Session', '2026-02-20T00:20:00+00:00', '2026-02-20T01:05:00+00:00', '2026-02-20T11:20:00+00:00'),
                ],
            ]),
            self::API_URL . '/seasons/2026/rounds/POR/sessions' => $this->json([
                'data' => [
                    $this->session('SBK', 'Race 2', '2026-03-29T14:00:00+00:00', '2026-03-29T14:22:00+00:00', '2026-03-29T15:00:00+00:00'),
                ],
            ]),
        ]));

        $sessions = $scraper->scrape(2026);

        self::assertCount(3, $sessions);
        self::assertSame('SBK', $sessions[0]->series);
        self::assertSame('WorldSBK', $sessions[0]->seriesName);
        self::assertSame(1, $sessions[0]->round);
        self::assertSame('Australian Round', $sessions[0]->eventName);
        self::assertSame('Australia', $sessions[0]->countryName);
        self::assertSame('Phillip Island Grand Prix Circuit', $sessions[0]->location);
        self::assertSame('Free Practice 1st Session', $sessions[0]->sessionName);
        self::assertSame('2026-02-20 00:20:00', $sessions[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-02-20 01:05:00', $sessions[0]->endsAt?->format('Y-m-d H:i:s'));
        self::assertSame('+11:00', $sessions[0]->trackTimezoneOffset);
        self::assertSame('https://www.worldsbk.com/en/calendar/event/2026-AUS', $sessions[0]->sourceUrl);

        self::assertSame('Race 1', $sessions[1]->sessionName);
        self::assertSame('Race 2', $sessions[2]->sessionName);
        self::assertSame(2, $sessions[2]->round);
        self::assertSame('Portugal', $sessions[2]->countryName);
        self::assertSame('+01:00', $sessions[2]->trackTimezoneOffset);
    }

    /**
     * @throws JsonException
     */
    public function testSkipsMalformedSessionsAndRejectsInvalidPayloads(): void
    {
        $scraper = new WsbkScheduleScraper($this->fetcher([
            self::API_URL . '/seasons/2026/rounds' => $this->json([
                'data' => [$this->round('AUS', 1, 'Australian Round', 'Phillip Island Grand Prix Circuit')],
            ]),
            self::API_URL . '/seasons/2026/rounds/AUS/sessions' => $this->json([
                'data' => [
                    $this->session('SBK', 'Race 1', 'invalid', '2026-02-21T05:22:00+00:00', '2026-02-21T16:00:00+00:00'),
                    $this->session('SBK', '', '2026-02-21T05:00:00+00:00', '2026-02-21T05:22:00+00:00', '2026-02-21T16:00:00+00:00'),
                    $this->session('SBK', 'Race 2', '2026-02-22T05:00:00+00:00', '2026-02-22T05:22:00+00:00', 'invalid'),
                ],
            ]),
        ]));

        $sessions = $scraper->scrape(2026);

        self::assertCount(1, $sessions);
        self::assertSame('Race 2', $sessions[0]->sessionName);
        self::assertNull($sessions[0]->trackTimezoneOffset);

        $invalidScraper = new WsbkScheduleScraper($this->fetcher([
            self::API_URL . '/seasons/2026/rounds' => '{}',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Could not decode WorldSBK rounds data.');
        $invalidScraper->scrape(2026);
    }

    /**
     * @return array<string, mixed>
     */
    private function round(string $sourceId, int $sequenceOrder, string $description, string $name): array
    {
        return [
            'attributes' => [
                'source_id' => $sourceId,
                'sequence_order' => $sequenceOrder,
                'description' => $description,
                'name' => $name,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function session(string $category, string $description, string $startsAt, string $endsAt, string $trackStart): array
    {
        return [
            'attributes' => [
                'description' => $description,
                'start_date_utc' => $startsAt,
                'end_date_utc' => $endsAt,
                'start_date_circuit' => $trackStart,
            ],
            'relationships' => [
                'category' => [
                    'data' => ['id' => $category],
                ],
            ],
        ];
    }

    /**
     * @param array<string, string> $responses
     */
    private function fetcher(array $responses): Closure
    {
        return static function (string $url) use ($responses): string {
            if (!isset($responses[$url])) {
                throw new RuntimeException(sprintf('Unexpected URL "%s".', $url));
            }

            return $responses[$url];
        };
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws JsonException
     */
    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
