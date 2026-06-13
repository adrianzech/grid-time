<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [new Get(), new GetCollection()],
    normalizationContext: ['groups' => ['series:read']],
)]
#[ApiFilter(SearchFilter::class, properties: ['code' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['name'])]
#[ORM\Entity]
#[ORM\Table(name: 'series')]
#[ORM\UniqueConstraint(name: 'uniq_series_code', columns: ['code'])]
class Series
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['series:read', 'season:read'])]
    private int $id;

    public function __construct(
        #[ORM\Column(length: 32)]
        #[Groups(['series:read', 'season:read'])]
        private string $code,
        #[ORM\Column(length: 128)]
        #[Groups(['series:read', 'season:read'])]
        private string $name,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getCode(): string
    {
        return $this->code;
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
