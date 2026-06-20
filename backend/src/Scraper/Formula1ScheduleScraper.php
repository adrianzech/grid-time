<?php

declare(strict_types=1);

namespace App\Scraper;

use App\Dto\RacingSession;
use App\Dto\RacingSessionTiming;
use App\Service\CountryNameNormalizer;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final class Formula1ScheduleScraper
{
    private const string BASE_URL = 'https://www.formula1.com';
    private const string SERIES_CODE = 'F1';
    private const string SERIES_NAME = 'Formula 1';

    private Formula1ScheduleDataExtractor $extractor;

    private CountryNameNormalizer $countryNameNormalizer;

    private LoggerInterface $logger;

    public function __construct(?Formula1ScheduleDataExtractor $extractor = null, ?CountryNameNormalizer $countryNameNormalizer = null, #[Autowire(service: 'monolog.logger.scraper')] ?LoggerInterface $scraperLogger = null)
    {
        $this->extractor = $extractor ?? new Formula1ScheduleDataExtractor();
        $this->countryNameNormalizer = $countryNameNormalizer ?? new CountryNameNormalizer();
        $this->logger = $scraperLogger ?? new NullLogger();
    }

    /**
     * @return list<RacingSession>
     *
     * @throws Throwable
     */
    public function scrape(int $year): array
    {
        $this->logger->info('Schedule scrape started.', ['series' => self::SERIES_CODE, 'year' => $year]);

        try {
            $raceUrls = $this->discoverRaceUrls($year);
            $raceSchedules = [];

            foreach ($raceUrls as $url) {
                $raceSessions = $this->scrapeRace($url);

                if ($raceSessions === []) {
                    continue;
                }

                usort($raceSessions, static fn (RacingSession $a, RacingSession $b): int => $a->startsAt <=> $b->startsAt);

                $raceSchedules[] = $raceSessions;
            }

            $sessions = $this->numberRaceSchedules($raceSchedules);
            $this->logger->info('Schedule scrape completed.', ['series' => self::SERIES_CODE, 'year' => $year, 'session_count' => count($sessions)]);

            return $sessions;
        } catch (Throwable $exception) {
            $this->logger->error('Schedule scrape failed.', ['series' => self::SERIES_CODE, 'year' => $year, 'exception' => $exception]);

            throw $exception;
        }
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
            $this->logger->error('Schedule source request failed.', ['series' => self::SERIES_CODE, 'source_url' => $url, 'error' => $error]);

            throw new RuntimeException(sprintf('Could not fetch "%s": %s', $url, $error ?? 'unknown error'));
        }

        $this->logger->debug('Schedule source request completed.', ['series' => self::SERIES_CODE, 'source_url' => $url]);

        return $html;
    }

    private function isRaceSlug(string $slug): bool
    {
        return !str_starts_with($slug, 'pre-season-testing-');
    }

    /**
     * @return list<RacingSession>
     */
    private function scrapeRace(string $url): array
    {
        $html = $this->fetch($url);
        $eventName = $this->extractor->countryName($html, $url);
        $location = $this->extractor->circuitName($html) ?? $this->extractor->locationFromUrl($url);
        $sessions = [];

        foreach ($this->extractor->meetingSessions($html) as $session) {
            [$startsAt, $endsAt] = $this->extractor->sessionTimes($session, $url);

            $sessions[] = new RacingSession(series: self::SERIES_CODE, seriesName: self::SERIES_NAME, round: 0, eventName: $eventName, countryName: $this->countryNameNormalizer->normalize($eventName), location: $location, sessionName: $session['description'], timing: new RacingSessionTiming(startsAt: $startsAt->setTimezone(new DateTimeZone('UTC')), endsAt: $endsAt?->setTimezone(new DateTimeZone('UTC')), trackTimezoneOffset: $session['gmtOffset']), sourceUrl: $url);
        }

        return $sessions;
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
                $sessions[] = new RacingSession(series: $session->series, seriesName: $session->seriesName, round: $index + 1, eventName: $session->eventName, countryName: $session->countryName, location: $session->location, sessionName: $session->sessionName, timing: new RacingSessionTiming(startsAt: $session->startsAt, endsAt: $session->endsAt, trackTimezoneOffset: $session->trackTimezoneOffset), sourceUrl: $session->sourceUrl);
            }
        }

        usort($sessions, static fn (RacingSession $a, RacingSession $b): int => $a->startsAt <=> $b->startsAt);

        return $sessions;
    }
}
