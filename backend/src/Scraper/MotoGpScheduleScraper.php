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
use Rinvex\Country\CountryLoaderException;
use RuntimeException;
use Throwable;

final readonly class MotoGpScheduleScraper
{
    private const string BASE_URL = 'https://api.pulselive.motogp.com';
    private const string CALENDAR_URL = 'https://www.motogp.com/en/calendar';
    private const string EVENT_KIND_GP = 'GP';
    private const string BROADCAST_TYPE_SESSION = 'SESSION';

    private CountryNameResolver $countryNameResolver;

    private CountryNameNormalizer $countryNameNormalizer;

    public function __construct(private ?Closure $fetcher = null, ?CountryNameResolver $countryNameResolver = null, ?CountryNameNormalizer $countryNameNormalizer = null)
    {
        $this->countryNameResolver = $countryNameResolver ?? new CountryNameResolver();
        $this->countryNameNormalizer = $countryNameNormalizer ?? new CountryNameNormalizer();
    }

    /**
     * @return list<RacingSession>
     *
     * @throws CountryLoaderException
     */
    public function scrape(int $year, string $categoryCode, string $seriesName): array
    {
        $events = array_values(array_filter(
            $this->fetchEvents($year),
            static fn (array $event): bool => ($event['kind'] ?? null) === self::EVENT_KIND_GP,
        ));
        usort($events, static fn (array $a, array $b): int => ((int) ($a['sequence'] ?? 0)) <=> ((int) ($b['sequence'] ?? 0)));

        $sessions = [];

        foreach ($events as $event) {
            foreach ($this->sessionsFromEvent($event, $categoryCode, $seriesName, $year) as $session) {
                $sessions[] = $session;
            }
        }

        usort($sessions, static fn (RacingSession $a, RacingSession $b): int => $a->startsAt <=> $b->startsAt);

        return $sessions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchEvents(int $year): array
    {
        $data = $this->json($this->fetch(sprintf('%s/motogp/v1/events?seasonYear=%d', self::BASE_URL, $year)));

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Could not decode MotoGP events for %d.', $year));
        }

        return array_values(array_filter($data, static fn (mixed $event): bool => is_array($event)));
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return list<RacingSession>
     *
     * @throws CountryLoaderException
     */
    private function sessionsFromEvent(array $event, string $categoryCode, string $seriesName, int $year): array
    {
        $round = $this->intValue($event['sequence'] ?? null);
        $eventName = $this->displayName($event['additional_name'] ?? null) ?? $this->displayName($event['name'] ?? null);
        $countryCode = $this->stringValue($event['country'] ?? null);

        if ($eventName === null) {
            return [];
        }

        $countryName = $countryCode === null
            ? $this->countryNameNormalizer->normalize($eventName)
            : $this->countryNameResolver->resolve($countryCode) ?? $this->countryNameNormalizer->normalize($eventName);
        $location = $this->stringValue($event['circuit']['name'] ?? null)
            ?? $countryName;
        $broadcasts = $event['broadcasts'] ?? null;

        if ($round === null || !is_array($broadcasts)) {
            return [];
        }

        $sessions = [];

        foreach ($broadcasts as $broadcast) {
            $session = is_array($broadcast)
                ? $this->sessionFromBroadcast($broadcast, $categoryCode, $seriesName, $round, $eventName, $countryName, $location, $year)
                : null;

            if ($session instanceof RacingSession) {
                $sessions[] = $session;
            }
        }

        usort($sessions, static fn (RacingSession $a, RacingSession $b): int => $a->startsAt <=> $b->startsAt);

        return $sessions;
    }

    /**
     * @param array<string, mixed> $broadcast
     */
    private function sessionFromBroadcast(array $broadcast, string $categoryCode, string $seriesName, int $round, string $eventName, string $countryName, string $location, int $year): ?RacingSession
    {
        if (!$this->isSessionForCategory($broadcast, $categoryCode)) {
            return null;
        }

        $sessionName = $this->stringValue($broadcast['name'] ?? null) ?? $this->stringValue($broadcast['shortname'] ?? null);
        $sessionStartTime = $this->stringValue($broadcast['date_start'] ?? null);
        $startsAt = $this->dateTimeValue($sessionStartTime);

        if ($sessionName === null || $startsAt === null) {
            return null;
        }

        $endsAt = $this->dateTimeValue($broadcast['date_end'] ?? null);

        return new RacingSession(
            series: $categoryCode,
            seriesName: $seriesName,
            round: $round,
            eventName: $eventName,
            countryName: $countryName,
            location: $location,
            sessionName: $sessionName,
            timing: new RacingSessionTiming(startsAt: $startsAt->setTimezone(new DateTimeZone('UTC')), endsAt: $endsAt?->setTimezone(new DateTimeZone('UTC')), trackTimezoneOffset: $this->extractTimezoneOffset($sessionStartTime)),
            sourceUrl: sprintf('%s/%d', self::CALENDAR_URL, $year),
        );
    }

    /**
     * @param array<string, mixed> $broadcast
     */
    private function isSessionForCategory(array $broadcast, string $categoryCode): bool
    {
        return ($broadcast['type'] ?? null) === self::BROADCAST_TYPE_SESSION
            && ($broadcast['category']['acronym'] ?? null) === $categoryCode;
    }

    private function fetch(string $url): string
    {
        if ($this->fetcher instanceof Closure) {
            return (string) ($this->fetcher)($url);
        }

        $context = stream_context_create(['http' => ['header' => implode("\r\n", ['User-Agent: racecal/0.1 (+https://motogp.com schedule scraper)', 'Accept: application/json']), 'timeout' => 20]]);

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
            throw new RuntimeException(sprintf('Could not fetch "%s": %s', $url, $error ?? 'unknown error'));
        }

        return $json;
    }

    private function json(string $json): mixed
    {
        try {
            return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not decode MotoGP schedule data.', previous: $exception);
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function displayName(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        if ($value === null || preg_match('/[a-z]/', $value) === 1) {
            return $value;
        }

        if (in_array($value, ['UK', 'USA', 'UAE'], true)) {
            return $value;
        }

        return str_replace(
            [' Gp', ' Of '],
            [' GP', ' of '],
            ucwords(strtolower($value)),
        );
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

    private function extractTimezoneOffset(?string $value): ?string
    {
        if ($value === null || preg_match('/(?<offset>[+-]\d{2}:?\d{2}|Z)$/', $value, $matches) !== 1) {
            return null;
        }

        if ($matches['offset'] === 'Z') {
            return '+00:00';
        }

        return sprintf('%s:%s', substr($matches['offset'], 0, 3), substr($matches['offset'], -2));
    }
}
