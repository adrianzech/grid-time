<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'race_event')]
#[ORM\UniqueConstraint(name: 'uniq_event_season_round', columns: ['season_id', 'round_number'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Season::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Season $season,
        #[ORM\Column]
        private int $roundNumber,
        #[ORM\Column(length: 128)]
        private string $name,
        #[ORM\Column(length: 128)]
        private string $location,
        #[ORM\Column(length: 512)]
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
