<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'series')]
#[ORM\UniqueConstraint(name: 'uniq_series_code', columns: ['code'])]
class Series
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    public function __construct(
        #[ORM\Column(length: 32)]
        private string $code,
        #[ORM\Column(length: 128)]
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
