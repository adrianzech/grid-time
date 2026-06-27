<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\WeekendOverview;
use App\ApiResource\WeekendOverviewEvent;
use App\ApiResource\WeekendOverviewSeries;
use App\ApiResource\WeekendOverviewSession;
use App\Entity\Session;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<WeekendOverview>
 */
final readonly class WeekendOverviewProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<WeekendOverview>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $query = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
        $year = $this->integerFilter($query);
        $windowStart = $this->dateFilter($query, 'windowStart');
        $windowEnd = $this->dateFilter($query, 'windowEnd');

        if ($year === null || $windowStart === null || $windowEnd === null) {
            throw new BadRequestHttpException('year, windowStart and windowEnd are required.');
        }

        if ($windowEnd <= $windowStart) {
            throw new BadRequestHttpException('windowEnd must be after windowStart.');
        }

        /** @var list<Session> $sessions */
        $sessions = $this->entityManager->createQueryBuilder()
            ->select('session', 'event', 'season', 'series')
            ->from(Session::class, 'session')
            ->innerJoin('session.event', 'event')
            ->innerJoin('event.season', 'season')
            ->innerJoin('season.series', 'series')
            ->andWhere('season.year = :year')
            ->andWhere('session.startsAt <= :windowEnd')
            ->andWhere('COALESCE(session.endsAt, session.startsAt) >= :windowStart')
            ->orderBy('session.startsAt', 'ASC')
            ->addOrderBy('series.code', 'ASC')
            ->setParameter('year', $year)
            ->setParameter('windowStart', $windowStart)
            ->setParameter('windowEnd', $windowEnd)
            ->getQuery()
            ->getResult();

        $itemsByEvent = [];

        foreach ($sessions as $session) {
            $event = $session->getEvent();
            $season = $event->getSeason();
            $series = $season->getSeries();
            $eventId = $this->eventIri($event->getId() ?? 0);

            $itemsByEvent[$eventId] ??= [
                'series' => new WeekendOverviewSeries($series->getCode(), $series->getName()),
                'event' => new WeekendOverviewEvent(
                    id: $eventId,
                    databaseId: $event->getId() ?? 0,
                    roundNumber: $event->getRoundNumber(),
                    name: $event->getName(),
                    countryName: $event->getCountryName(),
                    location: $event->getLocation(),
                    sourceUrl: $event->getSourceUrl(),
                ),
                'sessions' => [],
            ];

            $itemsByEvent[$eventId]['sessions'][] = new WeekendOverviewSession(
                id: $this->sessionIri($session->getId() ?? 0),
                databaseId: $session->getId() ?? 0,
                event: $eventId,
                name: $session->getName(),
                startsAt: $session->getStartsAt()->format(DATE_ATOM),
                endsAt: $session->getEndsAt()?->format(DATE_ATOM),
                sourceUrl: $session->getSourceUrl(),
                trackTimezoneOffset: $session->getTrackTimezoneOffset(),
            );
        }

        return array_values(array_map(
            static fn (array $item): WeekendOverview => new WeekendOverview(
                id: sprintf('%s-%s', $item['series']->code, $item['event']->databaseId),
                series: $item['series'],
                event: $item['event'],
                sessions: $item['sessions'],
            ),
            $itemsByEvent,
        ));
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function integerFilter(array $filters): ?int
    {
        $value = $filters['year'] ?? null;

        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function dateFilter(array $filters, string $name): ?DateTimeImmutable
    {
        $value = $filters[$name] ?? null;

        if (!is_string($value)) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private function eventIri(int $id): string
    {
        return sprintf('/api/events/%d', $id);
    }

    private function sessionIri(int $id): string
    {
        return sprintf('/api/sessions/%d', $id);
    }
}
