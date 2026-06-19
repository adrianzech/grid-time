<?php

declare(strict_types=1);

namespace App\Importer;

use App\Dto\RacingSession;
use App\Entity\Event;
use App\Entity\Season;
use App\Entity\Series;
use App\Entity\Session;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SchedulePersister
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param list<RacingSession> $sessions
     *
     * @throws DateMalformedStringException
     */
    public function persist(int $year, array $sessions): int
    {
        if ($sessions === []) {
            return 0;
        }

        $series = $this->findOrCreateSeries($sessions[0]->series, $sessions[0]->seriesName);
        $season = $this->findOrCreateSeason($series, $year);
        $persistedSessions = 0;
        $eventsByRound = [];
        $sessionsByEventAndName = [];

        foreach ($sessions as $session) {
            $event = $this->findOrCreateEvent($season, $session, $eventsByRound);
            $this->findOrCreateSession($event, $session, $sessionsByEventAndName);
            ++$persistedSessions;
        }

        $season->markScheduleUpdated(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $this->entityManager->flush();

        return $persistedSessions;
    }

    private function findOrCreateSeries(string $code, string $name): Series
    {
        $series = $this->entityManager->getRepository(Series::class)->findOneBy(['code' => $code]);

        if ($series instanceof Series) {
            $series->updateName($name);

            return $series;
        }

        $series = new Series($code, $name);
        $this->entityManager->persist($series);

        return $series;
    }

    private function findOrCreateSeason(Series $series, int $year): Season
    {
        $season = $this->entityManager->getRepository(Season::class)->findOneBy([
            'series' => $series,
            'year' => $year,
        ]);

        $name = sprintf('%s %d', $series->getName(), $year);

        if ($season instanceof Season) {
            $season->updateName($name);

            return $season;
        }

        $season = new Season($series, $year, $name);
        $this->entityManager->persist($season);

        return $season;
    }

    /**
     * @param array<int, Event> $eventsByRound
     */
    private function findOrCreateEvent(Season $season, RacingSession $session, array &$eventsByRound): Event
    {
        if (isset($eventsByRound[$session->round])) {
            $event = $eventsByRound[$session->round];
            $event->updateFromSchedule($session->eventName, $session->countryName, $session->location, $session->sourceUrl);

            return $event;
        }

        $event = $this->entityManager->getRepository(Event::class)->findOneBy([
            'season' => $season,
            'roundNumber' => $session->round,
        ]);

        if ($event instanceof Event) {
            $event->updateFromSchedule($session->eventName, $session->countryName, $session->location, $session->sourceUrl);
            $eventsByRound[$session->round] = $event;

            return $event;
        }

        $event = new Event(
            season: $season,
            roundNumber: $session->round,
            name: $session->eventName,
            countryName: $session->countryName,
            location: $session->location,
            sourceUrl: $session->sourceUrl,
        );
        $this->entityManager->persist($event);
        $eventsByRound[$session->round] = $event;

        return $event;
    }

    /**
     * @param array<string, Session> $sessionsByEventAndName
     */
    private function findOrCreateSession(Event $event, RacingSession $racingSession, array &$sessionsByEventAndName): void
    {
        $cacheKey = sprintf('%d:%s', $event->getRoundNumber(), $racingSession->sessionName);

        if (isset($sessionsByEventAndName[$cacheKey])) {
            $session = $sessionsByEventAndName[$cacheKey];
            $session->updateFromSchedule(
                $this->toUtc($racingSession->startsAt),
                $racingSession->endsAt === null ? null : $this->toUtc($racingSession->endsAt),
                $racingSession->sourceUrl,
                $racingSession->trackTimezoneOffset,
            );

            return;
        }

        $session = $this->entityManager->getRepository(Session::class)->findOneBy([
            'event' => $event,
            'name' => $racingSession->sessionName,
        ]);

        $startsAt = $this->toUtc($racingSession->startsAt);
        $endsAt = $racingSession->endsAt === null ? null : $this->toUtc($racingSession->endsAt);

        if ($session instanceof Session) {
            $session->updateFromSchedule($startsAt, $endsAt, $racingSession->sourceUrl, $racingSession->trackTimezoneOffset);
            $sessionsByEventAndName[$cacheKey] = $session;

            return;
        }

        $session = new Session(
            event: $event,
            name: $racingSession->sessionName,
            startsAt: $startsAt,
            endsAt: $endsAt,
            sourceUrl: $racingSession->sourceUrl,
            trackTimezoneOffset: $racingSession->trackTimezoneOffset,
        );
        $this->entityManager->persist($session);
        $sessionsByEventAndName[$cacheKey] = $session;
    }

    private function toUtc(DateTimeImmutable $dateTime): DateTimeImmutable
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'));
    }
}
