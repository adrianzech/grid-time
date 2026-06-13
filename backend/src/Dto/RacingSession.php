<?php

declare(strict_types=1);

namespace App\Dto;

use DateTimeImmutable;

final readonly class RacingSession
{
    public function __construct(
        public string $series,
        public string $seriesName,
        public int $round,
        public string $eventName,
        public string $location,
        public string $sessionName,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public string $sourceUrl,
    ) {
    }
}
