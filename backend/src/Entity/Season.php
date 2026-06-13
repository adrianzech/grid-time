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
    normalizationContext: ['groups' => ['season:read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['series.code' => 'exact', 'year' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['year'])]
#[ORM\Entity]
#[ORM\Table(name: 'season')]
#[ORM\UniqueConstraint(name: 'uniq_season_series_year', columns: ['series_id', 'year'])]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['season:read', 'event:read'])]
    private int $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Series::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        #[ApiProperty(readableLink: false)]
        #[Groups(['season:read'])]
        private Series $series,
        #[ORM\Column]
        #[Groups(['season:read', 'event:read'])]
        private int $year,
        #[ORM\Column(length: 128)]
        #[Groups(['season:read', 'event:read'])]
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
