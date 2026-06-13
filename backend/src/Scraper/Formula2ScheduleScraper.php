<?php

declare(strict_types=1);

namespace App\Scraper;

use App\Dto\RacingSession;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;

final readonly class Formula2ScheduleScraper
{
    private const string BASE_URL = 'https://www.fiaformula2.com';
    private const string SERIES_CODE = 'F2';
    private const string SERIES_NAME = 'Formula 2';

    public function __construct(private ?Closure $fetcher = null)
    {
    }

    /**
     * @return list<RacingSession>
     */
    public function scrape(int $year, DateTimeZone $timezone): array
    {
        $races = $this->discoverRaces($year);
        $sessions = [];

        foreach ($races as $race) {
            foreach ($this->scrapeRace($race, $timezone) as $session) {
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
        $pageData = $this->extractYearFromSeasonName($this->stringValue($calendarData['pageData']['SeasonName'] ?? null)) === $year
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

            $raceId = $this->intValue($race['RaceId'] ?? null);
            $roundNumber = $this->intValue($race['RoundNumber'] ?? null);
            $countryName = $this->stringValue($race['CountryName'] ?? null);
            $location = $this->stringValue($race['CircuitShortName'] ?? null)
                ?? $this->stringValue($race['CircuitName'] ?? null)
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
            throw new RuntimeException(sprintf('Could not find a Formula 2 season for %d.', $year));
        }

        return $this->fetchCalendarData(sprintf('%s/Calendar?seasonid=%d', self::BASE_URL, $seasonId))['pageData'];
    }

    /**
     * @return array{pageData: array<string, mixed>, seasonData: mixed}
     */
    private function fetchCalendarData(string $url): array
    {
        $nextData = $this->extractNextData($this->fetch($url), $url);
        $pageData = $nextData['props']['pageProps']['pageData'] ?? null;

        if (!is_array($pageData)) {
            throw new RuntimeException(sprintf('Could not find Formula 2 calendar data in "%s".', $url));
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

            if ($this->extractYearFromSeasonName($this->stringValue($season['SeasonName'] ?? null)) !== $year) {
                continue;
            }

            return $this->intValue($season['SeasonId'] ?? null);
        }

        return null;
    }

    /**
     * @param array{raceId: int, roundNumber: int, countryName: string, location: string} $race
     *
     * @return list<RacingSession>
     */
    private function scrapeRace(array $race, DateTimeZone $timezone): array
    {
        $url = sprintf('%s/Results?raceid=%d', self::BASE_URL, $race['raceId']);
        $nextData = $this->extractNextData($this->fetch($url), $url);
        $pageData = $nextData['props']['pageProps']['pageData'] ?? null;

        if (!is_array($pageData)) {
            return [];
        }

        $eventName = $this->stringValue($pageData['CountryName'] ?? null) ?? $race['countryName'];
        $location = $this->stringValue($pageData['CircuitShortName'] ?? null)
            ?? $this->stringValue($pageData['CircuitInformation']['CircuitName'] ?? null)
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

            $sessionName = $this->stringValue($session['SessionName'] ?? null);
            $startsAt = $this->dateTimeValue($session['SessionStartTime'] ?? null);

            if ($sessionName === null || $startsAt === null) {
                continue;
            }

            $endsAt = $this->dateTimeValue($session['SessionEndTime'] ?? null);

            $sessions[] = new RacingSession(
                series: self::SERIES_CODE,
                seriesName: self::SERIES_NAME,
                round: $race['roundNumber'],
                eventName: $eventName,
                location: $location,
                sessionName: $sessionName,
                startsAt: $startsAt->setTimezone($timezone),
                endsAt: $endsAt?->setTimezone($timezone),
                sourceUrl: $url,
            );
        }

        usort($sessions, static fn (RacingSession $a, RacingSession $b): int => $a->startsAt <=> $b->startsAt);

        return $sessions;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractNextData(string $html, string $url): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $node = $xpath->query('//script[@id="__NEXT_DATA__"]')->item(0);

        if (!$node instanceof DOMElement) {
            throw new RuntimeException(sprintf('Could not find Next.js data in "%s".', $url));
        }

        $data = json_decode($node->textContent, true);

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Could not decode Next.js data in "%s".', $url));
        }

        return $data;
    }

    private function fetch(string $url): string
    {
        if ($this->fetcher instanceof Closure) {
            return (string) ($this->fetcher)($url);
        }

        $context = stream_context_create(['http' => ['header' => implode("\r\n", ['User-Agent: racecal/0.1 (+https://fiaformula2.com schedule scraper)', 'Accept: text/html,application/xhtml+xml']), 'timeout' => 20]]);

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

    private function extractYearFromSeasonName(?string $seasonName): ?int
    {
        if ($seasonName === null || preg_match('/\b(?<year>\d{4})\b/', $seasonName, $matches) !== 1) {
            return null;
        }

        return (int) $matches['year'];
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    private function dateTimeValue(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
