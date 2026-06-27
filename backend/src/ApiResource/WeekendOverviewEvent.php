<?php

declare(strict_types=1);

namespace App\ApiResource;

final readonly class WeekendOverviewEvent
{
    public function __construct(
        public string $id,
        public int $databaseId,
        public int $roundNumber,
        public string $name,
        public string $countryName,
        public string $location,
        public string $sourceUrl,
    ) {
    }
}
