<?php

declare(strict_types=1);

namespace App\Tests\Scraper;

use App\Scraper\MotoGpScheduleScraper;
use Closure;
use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MotoGpScheduleScraperTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testScrapesMotoGpMoto2AndMoto3SessionsFromSameSource(): void
    {
        $scraper = new MotoGpScheduleScraper($this->fetcher([
            'https://api.pulselive.motogp.com/motogp/v1/events?seasonYear=2026' => $this->json([
                $this->event(sequence: 2, name: 'Brazil', additionalName: 'BRAZIL', country: 'BR', circuitName: 'Goiânia', broadcasts: [
                    $this->broadcast('MGP', 'RAC', 'Grand Prix', '2026-03-22T15:00:00-0300', '2026-03-22T15:00:00-0300', 'SESSION'),
                ]),
                $this->event(sequence: 1, name: 'PT Grand Prix of Thailand', additionalName: 'THAILAND', country: 'TH', circuitName: 'Chang International Circuit', broadcasts: [
                    $this->broadcast('MGP', 'PRESS', 'Pre-Event Press Conference', '2026-02-26T18:00:00+0700', '2026-02-26T18:30:00+0700', 'MEDIA'),
                    $this->broadcast('MT3', 'FP1', 'Free Practice Nr. 1', '2026-02-27T09:00:00+0700', '2026-02-27T09:35:00+0700', 'SESSION'),
                    $this->broadcast('MT2', 'FP1', 'Free Practice Nr. 1', '2026-02-27T09:50:00+0700', '2026-02-27T10:30:00+0700', 'SESSION'),
                    $this->broadcast('MGP', 'FP1', 'Free Practice Nr. 1', '2026-02-27T10:45:00+0700', '2026-02-27T11:30:00+0700', 'SESSION'),
                    $this->broadcast('MGP', 'PR', 'Practice', '2026-02-27T15:00:00+0700', '2026-02-27T16:00:00+0700', 'SESSION'),
                ]),
                [
                    'sequence' => 3,
                    'kind' => 'TEST',
                    'name' => 'Buriram Test',
                    'country' => 'TH',
                    'circuit' => ['name' => 'Chang International Circuit'],
                    'broadcasts' => [
                        $this->broadcast('MGP', 'FP1', 'Session 1', '2026-02-21T08:00:00+0700', '2026-02-21T12:00:00+0700', 'SESSION'),
                    ],
                ],
            ]),
        ]));

        $motogpSessions = $scraper->scrape(2026, 'MGP', 'MotoGP');
        $moto2Sessions = $scraper->scrape(2026, 'MT2', 'Moto2');
        $moto3Sessions = $scraper->scrape(2026, 'MT3', 'Moto3');

        self::assertCount(3, $motogpSessions);
        self::assertSame('MGP', $motogpSessions[0]->series);
        self::assertSame('MotoGP', $motogpSessions[0]->seriesName);
        self::assertSame(1, $motogpSessions[0]->round);
        self::assertSame('Thailand', $motogpSessions[0]->eventName);
        self::assertSame('Chang International Circuit', $motogpSessions[0]->location);
        self::assertSame('Free Practice Nr. 1', $motogpSessions[0]->sessionName);
        self::assertSame('2026-02-27 03:45:00', $motogpSessions[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-02-27 04:30:00', $motogpSessions[0]->endsAt?->format('Y-m-d H:i:s'));
        self::assertSame('+07:00', $motogpSessions[0]->trackTimezoneOffset);
        self::assertSame('https://www.motogp.com/en/calendar/2026', $motogpSessions[0]->sourceUrl);

        self::assertSame('Grand Prix', $motogpSessions[2]->sessionName);
        self::assertSame('Brazil', $motogpSessions[2]->eventName);
        self::assertSame('2026-03-22 18:00:00', $motogpSessions[2]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('-03:00', $motogpSessions[2]->trackTimezoneOffset);

        self::assertCount(1, $moto2Sessions);
        self::assertSame('MT2', $moto2Sessions[0]->series);
        self::assertSame('Moto2', $moto2Sessions[0]->seriesName);
        self::assertSame('Free Practice Nr. 1', $moto2Sessions[0]->sessionName);
        self::assertSame('2026-02-27 02:50:00', $moto2Sessions[0]->startsAt->format('Y-m-d H:i:s'));

        self::assertCount(1, $moto3Sessions);
        self::assertSame('MT3', $moto3Sessions[0]->series);
        self::assertSame('Moto3', $moto3Sessions[0]->seriesName);
        self::assertSame('2026-02-27 02:00:00', $moto3Sessions[0]->startsAt->format('Y-m-d H:i:s'));
    }

    /**
     * @throws JsonException
     */
    public function testSkipsMalformedSessions(): void
    {
        $scraper = new MotoGpScheduleScraper($this->fetcher([
            'https://api.pulselive.motogp.com/motogp/v1/events?seasonYear=2026' => $this->json([
                $this->event(sequence: 1, name: 'Thailand', additionalName: 'THAILAND', country: 'TH', circuitName: 'Chang International Circuit', broadcasts: [
                    $this->broadcast('MGP', 'FP1', 'Free Practice Nr. 1', 'TBC', '2026-02-27T11:30:00+0700', 'SESSION'),
                    [
                        'category' => ['acronym' => 'MGP'],
                        'type' => 'SESSION',
                        'shortname' => 'FP2',
                        'date_start' => '2026-02-28T10:10:00+0700',
                    ],
                ]),
            ]),
        ]));

        $sessions = $scraper->scrape(2026, 'MGP', 'MotoGP');

        self::assertCount(1, $sessions);
        self::assertSame('FP2', $sessions[0]->sessionName);
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
     * @param list<array<string, mixed>> $broadcasts
     *
     * @return array<string, mixed>
     */
    private function event(int $sequence, string $name, string $additionalName, string $country, string $circuitName, array $broadcasts): array
    {
        return [
            'sequence' => $sequence,
            'kind' => 'GP',
            'name' => $name,
            'additional_name' => $additionalName,
            'country' => $country,
            'circuit' => ['name' => $circuitName],
            'broadcasts' => $broadcasts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function broadcast(string $categoryCode, string $shortName, string $name, string $startsAt, string $endsAt, string $type): array
    {
        return [
            'category' => ['acronym' => $categoryCode],
            'shortname' => $shortName,
            'name' => $name,
            'date_start' => $startsAt,
            'date_end' => $endsAt,
            'type' => $type,
        ];
    }

    /**
     * @param array<int, mixed> $data
     *
     * @throws JsonException
     */
    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
