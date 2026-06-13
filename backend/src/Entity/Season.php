<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'season')]
#[ORM\UniqueConstraint(name: 'uniq_season_series_year', columns: ['series_id', 'year'])]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Series::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Series $series,
        #[ORM\Column]
        private int $year,
        #[ORM\Column(length: 128)]
        private string $name,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getSeries(): Series
    {
        return $this->series;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
    }
}
