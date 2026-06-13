<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [new Get(), new GetCollection()],
    normalizationContext: ['groups' => ['event:read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['season.series.code' => 'exact', 'season.year' => 'exact', 'roundNumber' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['roundNumber'])]
#[ORM\Entity]
#[ORM\Table(name: 'race_event')]
#[ORM\UniqueConstraint(name: 'uniq_event_season_round', columns: ['season_id', 'round_number'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['event:read', 'session:read'])]
    private int $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Season::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        #[ApiProperty(readableLink: false)]
        #[Groups(['event:read'])]
        private Season $season,
        #[ORM\Column]
        #[Groups(['event:read', 'session:read'])]
        private int $roundNumber,
        #[ORM\Column(length: 128)]
        #[Groups(['event:read', 'session:read'])]
        private string $name,
        #[ORM\Column(length: 128)]
        #[Groups(['event:read', 'session:read'])]
        private string $location,
        #[ORM\Column(length: 512)]
        #[Groups(['event:read', 'session:read'])]
        private string $sourceUrl,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getSeason(): Season
    {
        return $this->season;
    }

    public function getRoundNumber(): int
    {
        return $this->roundNumber;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function updateFromSchedule(string $name, string $location, string $sourceUrl): void
    {
        $this->name = $name;
        $this->location = $location;
        $this->sourceUrl = $sourceUrl;
    }
}
