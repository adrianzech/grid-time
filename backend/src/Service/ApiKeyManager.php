<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ApiKeyManager
{
    public function __construct(private EntityManagerInterface $entityManager, private string $apiKeyPepper)
    {
    }

    /** @return array{apiKey: ApiKey, token: string} */
    public function create(string $label, int $requestsPerMinute = 120, bool $internal = false): array
    {
        $identifier = bin2hex(random_bytes(8));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $apiKey = new ApiKey($identifier, $label, $this->hash($secret), requestsPerMinute: $requestsPerMinute, internal: $internal);
        $this->entityManager->persist($apiKey);
        $this->entityManager->flush();

        return ['apiKey' => $apiKey, 'token' => sprintf('gt_live_%s_%s', $identifier, $secret)];
    }

    public function findValid(string $token): ?ApiKey
    {
        if (preg_match('/^gt_live_([a-f0-9]{16})_([A-Za-z0-9_-]{43})$/', $token, $matches) !== 1) {
            return null;
        }
        $apiKey = $this->entityManager->getRepository(ApiKey::class)->findOneBy(['identifier' => $matches[1]]);
        if (!$apiKey instanceof ApiKey || $apiKey->isRevoked() || !$apiKey->matchesSecret($this->hash($matches[2]))) {
            return null;
        }
        $apiKey->markUsed();
        $this->entityManager->flush();

        return $apiKey;
    }

    private function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, $this->apiKeyPepper);
    }
}
