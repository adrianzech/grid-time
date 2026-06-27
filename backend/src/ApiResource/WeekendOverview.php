<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\WeekendOverviewProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/weekend-overview',
            provider: WeekendOverviewProvider::class,
        ),
    ],
)]
final readonly class WeekendOverview
{
    /**
     * @param list<WeekendOverviewSession> $sessions
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public WeekendOverviewSeries $series,
        public WeekendOverviewEvent $event,
        public array $sessions,
    ) {
    }
}
