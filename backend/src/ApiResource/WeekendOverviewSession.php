<?php

declare(strict_types=1);

namespace App\ApiResource;

final readonly class WeekendOverviewSession
{
    public function __construct(
        public string $id,
        public int $databaseId,
        public string $event,
        public string $name,
        public string $startsAt,
        public ?string $endsAt,
        public string $sourceUrl,
        public ?string $trackTimezoneOffset,
    ) {
    }
}
