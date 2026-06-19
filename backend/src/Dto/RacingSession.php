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
        public string $countryName,
        public string $location,
        public string $sessionName,
        RacingSessionTiming $timing,
        public string $sourceUrl,
    ) {
        $this->startsAt = $timing->startsAt;
        $this->endsAt = $timing->endsAt;
        $this->trackTimezoneOffset = $timing->trackTimezoneOffset;
    }

    public DateTimeImmutable $startsAt;

    public ?DateTimeImmutable $endsAt;

    public ?string $trackTimezoneOffset;
}
