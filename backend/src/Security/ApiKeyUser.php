<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ApiKeyUser implements UserInterface
{
    public function __construct(private string $identifier, private string $scope)
    {
    }

    public function getRoles(): array
    {
        return $this->scope === 'schedule:read' ? ['ROLE_SCHEDULE_READ'] : [];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }
}
