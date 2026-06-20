<?php

declare(strict_types=1);

namespace App\Scraper;

use App\Dto\RacingSession;
use App\Dto\RacingSessionTiming;
use App\Service\CountryNameNormalizer;
use App\Service\CountryNameResolver;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Rinvex\Country\CountryLoaderException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final readonly class WsbkScheduleScraper
{
    private const string API_URL = 'https://api.pulselive.worldsbk.com/wsbk-events/v1';
    private const string CALENDAR_URL = 'https://www.worldsbk.com/en/calendar/event';
    private const string CATEGORY_CODE = 'SBK';
    private const string SERIES_NAME = 'WorldSBK';

    private CountryNameResolver $countryNameResolver;

    private CountryNameNormalizer $countryNameNormalizer;

    private LoggerInterface $logger;

    public function __construct(private ?Closure $fetcher = null, ?CountryNameResolver $countryNameResolver = null, ?CountryNameNormalizer $countryNameNormalizer = null, #[Autowire(service: 'monolog.logger.scraper')] ?LoggerInterface $scraperLogger = null)
    {
        $this->countryNameResolver = $countryNameResolver ?? new CountryNameResolver();
        $this->countryNameNormalizer = $countryNameNormalizer ?? new CountryNameNormalizer();
        $this->logger = $scraperLogger ?? new NullLogger();
    }

    /**
     * @return list<RacingSession>
     *
     * @throws CountryLoaderException|Throwable
     */
    public function scrape(int $year): array
    {
        $this->logger->info('Schedule scrape started.', ['series' => self::CATEGORY_CODE, 'year' => $year]);

        try {
            $rounds = $this->fetchData(sprintf('%s/seasons/%d/rounds', self::API_URL, $year), 'rounds');
            usort($rounds, static fn (array $a, array $b): int => ((int) ($a['attributes']['sequence_order'] ?? 0)) <=> ((int) ($b['attributes']['sequence_order'] ?? 0)));

            $sessions = [];

            foreach ($rounds as $round) {
                foreach ($this->sessionsFromRound($round, $year) as $session) {
                    $sessions[] = $session;
                }
            }

            usort($sessions, static fn (RacingSession $a, RacingSession $b): int => $a->startsAt <=> $b->startsAt);
            $this->logger->info('Schedule scrape completed.', ['series' => self::CATEGORY_CODE, 'year' => $year, 'session_count' => count($sessions)]);

            return $sessions;
        } catch (Throwable $exception) {
            $this->logger->error('Schedule scrape failed.', ['series' => self::CATEGORY_CODE, 'year' => $year, 'exception' => $exception]);

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $round
     *
     * @return list<RacingSession>
     *
     * @throws CountryLoaderException
     */
    private function sessionsFromRound(array $round, int $year): array
    {
        $attributes = $round['attributes'] ?? null;

        if (!is_array($attributes)) {
            return [];
        }

        $roundId = $this->stringValue($attributes['source_id'] ?? null);
        $roundNumber = $this->intValue($attributes['sequence_order'] ?? null);
        $eventName = $this->stringValue($attributes['description'] ?? null);
        $location = $this->stringValue($attributes['name'] ?? null);

        if ($roundId === null || $roundNumber === null || $eventName === null || $location === null) {
            return [];
        }

        $countryCode = $this->stringValue($attributes['country_iso'] ?? null) ?? $roundId;
        $countryName = $this->countryNameResolver->resolve($countryCode)
            ?? $this->countryNameNormalizer->normalize($eventName);

        $data = $roundId
                |> rawurlencode(...)
                |> (fn ($x) => sprintf('%s/seasons/%d/rounds/%s/sessions', self::API_URL, $year, $x))
                |> (fn ($x) => $this->fetchData($x, 'sessions'));
        $sessions = [];

        foreach ($data as $session) {
            $racingSession = $this->sessionFromData($session, $year, $roundId, $roundNumber, $eventName, $countryName, $location);

            if ($racingSession instanceof RacingSession) {
                $sessions[] = $racingSession;
            }
        }

        return $sessions;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function sessionFromData(array $session, int $year, string $roundId, int $roundNumber, string $eventName, string $countryName, string $location): ?RacingSession
    {
        if (($session['relationships']['category']['data']['id'] ?? null) !== self::CATEGORY_CODE) {
            return null;
        }

        $attributes = $session['attributes'] ?? null;

        if (!is_array($attributes)) {
            return null;
        }

        $sessionName = $this->stringValue($attributes['description'] ?? null) ?? $this->stringValue($attributes['brief_description'] ?? null);
        $startsAt = $this->dateTimeValue($attributes['start_date_utc'] ?? null);

        if ($sessionName === null || $startsAt === null) {
            return null;
        }

        $endsAt = $this->dateTimeValue($attributes['end_date_utc'] ?? null);
        $trackStart = $this->dateTimeValue($attributes['start_date_circuit'] ?? null);

        return new RacingSession(
            series: self::CATEGORY_CODE,
            seriesName: self::SERIES_NAME,
            round: $roundNumber,
            eventName: $eventName,
            countryName: $countryName,
            location: $location,
            sessionName: $sessionName,
            timing: new RacingSessionTiming(
                startsAt: $startsAt->setTimezone(new DateTimeZone('UTC')),
                endsAt: $endsAt?->setTimezone(new DateTimeZone('UTC')),
                trackTimezoneOffset: $this->trackTimezoneOffset($startsAt, $trackStart),
            ),
            sourceUrl: sprintf('%s/%d-%s', self::CALENDAR_URL, $year, rawurlencode($roundId)),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchData(string $url, string $resource): array
    {
        $payload = $this->json($this->fetch($url));
        $data = $payload['data'] ?? null;

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Could not decode WorldSBK %s data.', $resource));
        }

        return array_values(array_filter($data, static fn (mixed $item): bool => is_array($item)));
    }

    private function fetch(string $url): string
    {
        if ($this->fetcher instanceof Closure) {
            return (string) ($this->fetcher)($url);
        }

        $context = stream_context_create(['http' => ['header' => implode("\r\n", ['User-Agent: grid-time/0.1 (+https://worldsbk.com schedule scraper)', 'Accept: application/json']), 'timeout' => 20]]);

        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            $json = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($json === false) {
            $this->logger->error('Schedule source request failed.', ['source_url' => $url, 'error' => $error]);

            throw new RuntimeException(sprintf('Could not fetch "%s": %s', $url, $error ?? 'unknown error'));
        }

        $this->logger->debug('Schedule source request completed.', ['source_url' => $url]);

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $json): array
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not decode WorldSBK schedule data.', previous: $exception);
        }

        if (!is_array($data)) {
            throw new RuntimeException('Could not decode WorldSBK schedule data.');
        }

        return $data;
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

    private function trackTimezoneOffset(DateTimeImmutable $startsAt, ?DateTimeImmutable $trackStart): ?string
    {
        if ($trackStart === null) {
            return null;
        }

        $offsetSeconds = $trackStart->getTimestamp() - $startsAt->getTimestamp();

        if (abs($offsetSeconds) > 14 * 60 * 60) {
            return null;
        }

        $hours = intdiv(abs($offsetSeconds), 60 * 60);
        $minutes = intdiv(abs($offsetSeconds) % (60 * 60), 60);

        return sprintf('%s%02d:%02d', $offsetSeconds < 0 ? '-' : '+', $hours, $minutes);
    }
}
