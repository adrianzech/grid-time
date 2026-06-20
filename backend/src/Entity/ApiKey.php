<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'api_key')]
#[ORM\UniqueConstraint(name: 'uniq_api_key_identifier', columns: ['identifier'])]
class ApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    public function __construct(
        #[ORM\Column(length: 64)]
        private readonly string $identifier,
        #[ORM\Column(length: 128)]
        private readonly string $label,
        #[ORM\Column(length: 64)]
        private readonly string $secretHash,
        #[ORM\Column(length: 64)]
        private readonly string $scope = 'schedule:read',
        #[ORM\Column]
        private readonly int $requestsPerMinute = 120,
        #[ORM\Column]
        private readonly bool $internal = false,
        #[ORM\Column]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
        #[ORM\Column(nullable: true)]
        private ?DateTimeImmutable $revokedAt = null,
        #[ORM\Column(nullable: true)]
        private ?DateTimeImmutable $lastUsedAt = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getRequestsPerMinute(): int
    {
        return $this->requestsPerMinute;
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function revoke(): void
    {
        $this->revokedAt = new DateTimeImmutable();
    }

    public function markUsed(): void
    {
        $this->lastUsedAt = new DateTimeImmutable();
    }

    public function matchesSecret(string $hash): bool
    {
        return hash_equals($this->secretHash, $hash);
    }
}
