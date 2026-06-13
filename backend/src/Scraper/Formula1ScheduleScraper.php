<?php

declare(strict_types=1);

namespace App\Scraper;

use App\Dto\RacingSession;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Exception;
use RuntimeException;

final class Formula1ScheduleScraper
{
    private const string BASE_URL = 'https://www.formula1.com';
    private const string SERIES_CODE = 'F1';
    private const string SERIES_NAME = 'Formula 1';

    /**
     * @return list<RacingSession>
     */
    public function scrape(int $year, DateTimeZone $timezone): array
    {
        $raceUrls = $this->discoverRaceUrls($year);
        $raceSchedules = [];

        foreach ($raceUrls as $url) {
            $raceSessions = $this->scrapeRace($url, $timezone);

            if ($raceSessions === []) {
                continue;
            }

            usort($raceSessions, static fn (RacingSession $a, RacingSession $b): int => $a->startsAt <=> $b->startsAt);

            $raceSchedules[] = $raceSessions;
        }

        return $this->numberRaceSchedules($raceSchedules);
    }

    /**
     * @return array<int, string>
     */
    private function discoverRaceUrls(int $year): array
    {
        $url = sprintf('%s/en/racing/%d', self::BASE_URL, $year);
        $html = $this->fetch($url);
        $links = [];

        if (!preg_match_all('~href="(?<href>/en/racing/' . $year . '/(?<slug>[^"/?#]+))"~', $html, $matches)) {
            return [];
        }

        foreach ($matches['href'] as $index => $href) {
            $slug = $matches['slug'][$index];

            if (!$this->isRaceSlug($slug) || isset($links[$slug])) {
                continue;
            }

            $links[$slug] = self::BASE_URL . $href;
        }

        return array_combine(range(1, count($links)), array_values($links)) ?: [];
    }

    private function fetch(string $url): string
    {
        $context = stream_context_create(['http' => ['header' => implode("\r\n", ['User-Agent: racecal/0.1 (+https://formula1.com schedule scraper)', 'Accept: text/html,application/xhtml+xml']), 'timeout' => 20]]);

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

    private function isRaceSlug(string $slug): bool
    {
        return !str_starts_with($slug, 'pre-season-testing-');
    }

    /**
     * @return list<RacingSession>
     */
    private function scrapeRace(string $url, DateTimeZone $timezone): array
    {
        $html = $this->fetch($url);
        $xpath = $this->createXPath($html);
        $eventName = $this->firstText($xpath) ?? 'Unknown Grand Prix';
        $location = $this->extractLocationFromUrl($url);
        $sessions = [];

        foreach ($this->extractMeetingSessions($html) as $session) {
            [$startsAt, $endsAt] = $this->createSessionTimes($session, $url);

            $sessions[] = new RacingSession(series: self::SERIES_CODE, seriesName: self::SERIES_NAME, round: 0, eventName: $eventName, location: $location, sessionName: $session['description'], startsAt: $startsAt->setTimezone($timezone), endsAt: $endsAt?->setTimezone($timezone), sourceUrl: $url);
        }

        return $sessions;
    }

    /**
     * @param array{description: string, startTime: string, endTime?: string, gmtOffset: string} $session
     *
     * @return array{DateTimeImmutable, DateTimeImmutable|null}
     */
    private function createSessionTimes(array $session, string $url): array
    {
        try {
            $sessionTimezone = new DateTimeZone($session['gmtOffset']);
            $startsAt = new DateTimeImmutable($session['startTime'], $sessionTimezone);
            $endsAt = isset($session['endTime']) ? new DateTimeImmutable($session['endTime'], $sessionTimezone) : null;
        } catch (Exception $exception) {
            throw new RuntimeException(sprintf('Could not parse session time for "%s".', $url), 0, $exception);
        }

        return [$startsAt, $endsAt];
    }

    private function createXPath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function firstText(DOMXPath $xpath): ?string
    {
        $node = $xpath->query('//h1')->item(0);

        if (!$node instanceof DOMNode) {
            return null;
        }

        return $this->normalizeText($node->textContent);
    }

    private function normalizeText(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)));
    }

    private function extractLocationFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $slug = basename((string) $path);

        return ucwords(str_replace('-', ' ', $slug));
    }

    /**
     * @return list<array{description: string, startTime: string, endTime?: string, gmtOffset: string}>
     */
    private function extractMeetingSessions(string $html): array
    {
        $json = $this->extractJsonArrayAfterKey($html) ?? $this->extractJsonArrayAfterKey(stripcslashes($html));

        if ($json === null) {
            return [];
        }

        $sessions = json_decode($json, true);

        if (!is_array($sessions)) {
            return [];
        }

        return array_values(array_filter($sessions, static fn (mixed $session): bool => is_array($session) && isset($session['description'], $session['startTime'], $session['gmtOffset']) && is_string($session['description']) && is_string($session['startTime']) && is_string($session['gmtOffset']) && (!isset($session['endTime']) || is_string($session['endTime']))));
    }

    private function extractJsonArrayAfterKey(string $html): ?string
    {
        $keyPosition = strpos($html, '"meetingSessions":');

        if ($keyPosition === false) {
            return null;
        }

        $arrayStart = strpos($html, '[', $keyPosition + strlen('"meetingSessions":'));

        if ($arrayStart === false) {
            return null;
        }

        return $this->extractJsonArrayAt($html, $arrayStart);
    }

    private function extractJsonArrayAt(string $html, int $start): ?string
    {
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($html);

        for ($position = $start; $position < $length; ++$position) {
            $character = $html[$position];

            if ($inString) {
                $escaped = $this->advanceJsonStringState($character, $escaped, $inString);

                continue;
            }

            if ($character === '"') {
                $inString = true;

                continue;
            }

            if ($character === '[') {
                ++$depth;

                continue;
            }

            if ($character !== ']') {
                continue;
            }

            --$depth;

            if ($depth === 0) {
                return substr($html, $start, $position - $start + 1);
            }
        }

        return null;
    }

    private function advanceJsonStringState(string $character, bool $escaped, bool &$inString): bool
    {
        if ($escaped) {
            return false;
        }

        if ($character === '\\') {
            return true;
        }

        if ($character === '"') {
            $inString = false;
        }

        return false;
    }

    /**
     * @param list<list<RacingSession>> $raceSchedules
     *
     * @return list<RacingSession>
     */
    private function numberRaceSchedules(array $raceSchedules): array
    {
        usort($raceSchedules, static fn (array $a, array $b): int => $a[0]->startsAt <=> $b[0]->startsAt);

        $sessions = [];

        foreach ($raceSchedules as $index => $raceSessions) {
            foreach ($raceSessions as $session) {
                $sessions[] = new RacingSession(series: $session->series, seriesName: $session->seriesName, round: $index + 1, eventName: $session->eventName, location: $session->location, sessionName: $session->sessionName, startsAt: $session->startsAt, endsAt: $session->endsAt, sourceUrl: $session->sourceUrl);
            }
        }

        usort($sessions, static fn (RacingSession $a, RacingSession $b): int => $a->startsAt <=> $b->startsAt);

        return $sessions;
    }
}
