<?php

declare(strict_types=1);

namespace App\ApiResource;

final readonly class WeekendOverviewSeries
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }
}
