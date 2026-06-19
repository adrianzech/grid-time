<?php

declare(strict_types=1);

namespace App\Scraper;

use App\Dto\RacingSession;
use App\Dto\RacingSessionTiming;
use App\Service\CountryNameNormalizer;
use Closure;
use DateTimeZone;
use RuntimeException;

final readonly class Formula3ScheduleScraper
{
    private const string BASE_URL = 'https://www.fiaformula3.com';
    private const string SERIES_CODE = 'F3';
    private const string SERIES_NAME = 'Formula 3';

    private Formula3ScheduleDataExtractor $extractor;

    private CountryNameNormalizer $countryNameNormalizer;

    public function __construct(private ?Closure $fetcher = null, ?Formula3ScheduleDataExtractor $extractor = null, ?CountryNameNormalizer $countryNameNormalizer = null)
    {
        $this->extractor = $extractor ?? new Formula3ScheduleDataExtractor();
        $this->countryNameNormalizer = $countryNameNormalizer ?? new CountryNameNormalizer();
    }

    /**
     * @return list<RacingSession>
     */
    public function scrape(int $year): array
    {
        $races = $this->discoverRaces($year);
        $sessions = [];

        foreach ($races as $race) {
            foreach ($this->scrapeRace($race) as $session) {
                $sessions[] = $session;
            }
        }

        usort($sessions, static fn (RacingSession $a, RacingSession $b): int => $a->startsAt <=> $b->startsAt);

        return $sessions;
    }

    /**
     * @return list<array{raceId: int, roundNumber: int, countryName: string, location: string}>
     */
    private function discoverRaces(int $year): array
    {
        $calendarData = $this->fetchCalendarData(self::BASE_URL . '/Calendar');
        $pageData = $this->extractor->extractYearFromSeasonName($this->extractor->stringValue($calendarData['pageData']['SeasonName'] ?? null)) === $year
            ? $calendarData['pageData']
            : $this->fetchCalendarPageDataForYear($calendarData, $year);

        $races = $pageData['Races'] ?? null;

        if (!is_array($races)) {
            return [];
        }

        $normalizedRaces = [];

        foreach ($races as $race) {
            if (!is_array($race)) {
                continue;
            }

            $raceId = $this->extractor->intValue($race['RaceId'] ?? null);
            $roundNumber = $this->extractor->intValue($race['RoundNumber'] ?? null);
            $countryName = $this->extractor->stringValue($race['CountryName'] ?? null);
            $location = $this->extractor->stringValue($race['CircuitShortName'] ?? null)
                ?? $this->extractor->stringValue($race['CircuitName'] ?? null)
                ?? $countryName;

            if ($raceId === null || $roundNumber === null || $countryName === null || $location === null) {
                continue;
            }

            $normalizedRaces[] = [
                'raceId' => $raceId,
                'roundNumber' => $roundNumber,
                'countryName' => $countryName,
                'location' => $location,
            ];
        }

        usort($normalizedRaces, static fn (array $a, array $b): int => $a['roundNumber'] <=> $b['roundNumber']);

        return $normalizedRaces;
    }

    /**
     * @param array<string, mixed> $calendarData
     *
     * @return array<string, mixed>
     */
    private function fetchCalendarPageDataForYear(array $calendarData, int $year): array
    {
        $seasonId = $this->findSeasonId($calendarData, $year);

        if ($seasonId === null) {
            throw new RuntimeException(sprintf('Could not find a Formula 3 season for %d.', $year));
        }

        return $this->fetchCalendarData(sprintf('%s/Calendar?seasonid=%d', self::BASE_URL, $seasonId))['pageData'];
    }

    /**
     * @return array{pageData: array<string, mixed>, seasonData: mixed}
     */
    private function fetchCalendarData(string $url): array
    {
        $nextData = $this->extractor->extractNextData($this->fetch($url), $url);
        $pageData = $nextData['props']['pageProps']['pageData'] ?? null;

        if (!is_array($pageData)) {
            throw new RuntimeException(sprintf('Could not find Formula 3 calendar data in "%s".', $url));
        }

        return [
            'pageData' => $pageData,
            'seasonData' => $nextData['props']['pageProps']['seasonData'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $calendarData
     */
    private function findSeasonId(array $calendarData, int $year): ?int
    {
        $seasonData = $calendarData['seasonData'] ?? null;

        if (!is_array($seasonData)) {
            return null;
        }

        foreach ($seasonData as $season) {
            if (!is_array($season)) {
                continue;
            }

            if ($this->extractor->extractYearFromSeasonName($this->extractor->stringValue($season['SeasonName'] ?? null)) !== $year) {
                continue;
            }

            return $this->extractor->intValue($season['SeasonId'] ?? null);
        }

        return null;
    }

    /**
     * @param array{raceId: int, roundNumber: int, countryName: string, location: string} $race
     *
     * @return list<RacingSession>
     */
    private function scrapeRace(array $race): array
    {
        $url = sprintf('%s/Results?raceid=%d', self::BASE_URL, $race['raceId']);
        $nextData = $this->extractor->extractNextData($this->fetch($url), $url);
        $pageData = $nextData['props']['pageProps']['pageData'] ?? null;

        if (!is_array($pageData)) {
            return [];
        }

        $eventName = $this->extractor->stringValue($pageData['CountryName'] ?? null) ?? $race['countryName'];
        $location = $this->extractor->stringValue($pageData['CircuitShortName'] ?? null)
            ?? $this->extractor->stringValue($pageData['CircuitInformation']['CircuitName'] ?? null)
            ?? $race['location'];
        $sessionResults = $pageData['SessionResults'] ?? null;

        if (!is_array($sessionResults)) {
            return [];
        }

        $sessions = [];

        foreach ($sessionResults as $session) {
            if (!is_array($session)) {
                continue;
            }

            $sessionName = $this->extractor->stringValue($session['SessionName'] ?? null);
            $sessionStartTime = $this->extractor->stringValue($session['SessionStartTime'] ?? null);
            $startsAt = $this->extractor->dateTimeValue($sessionStartTime);

            if ($sessionName === null || $startsAt === null) {
                continue;
            }

            $endsAt = $this->extractor->dateTimeValue($session['SessionEndTime'] ?? null);
            $trackTimezoneOffset = $this->extractor->extractTimezoneOffset($sessionStartTime);

            $sessions[] = new RacingSession(
                series: self::SERIES_CODE,
                seriesName: self::SERIES_NAME,
                round: $race['roundNumber'],
                eventName: $eventName,
                countryName: $this->countryNameNormalizer->normalize($eventName),
                location: $location,
                sessionName: $sessionName,
                timing: new RacingSessionTiming(startsAt: $startsAt->setTimezone(new DateTimeZone('UTC')), endsAt: $endsAt?->setTimezone(new DateTimeZone('UTC')), trackTimezoneOffset: $trackTimezoneOffset),
                sourceUrl: $url,
            );
        }

        usort($sessions, static fn (RacingSession $a, RacingSession $b): int => $a->startsAt <=> $b->startsAt);

        return $sessions;
    }

    private function fetch(string $url): string
    {
        if ($this->fetcher instanceof Closure) {
            return (string) ($this->fetcher)($url);
        }

        $context = stream_context_create(['http' => ['header' => implode("\r\n", ['User-Agent: racecal/0.1 (+https://fiaformula3.com schedule scraper)', 'Accept: text/html,application/xhtml+xml']), 'timeout' => 20]]);

        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            $html = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($html === false) {
            throw new RuntimeException(sprintf('Could not fetch "%s": %s', $url, $error ?? 'unknown error'));
        }

        return $html;
    }
}
