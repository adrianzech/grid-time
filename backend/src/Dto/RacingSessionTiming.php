<?php

declare(strict_types=1);

namespace App\Dto;

use DateTimeImmutable;

final readonly class RacingSessionTiming
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public ?string $trackTimezoneOffset,
    ) {
    }
}
