<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ApiKey;
use App\Enum\ApiKeyKind;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Random\RandomException;

final readonly class ApiKeyManager
{
    public function __construct(private EntityManagerInterface $entityManager, private string $apiKeyPepper, private LoggerInterface $securityLogger)
    {
    }

    /** @return array{apiKey: ApiKey, token: string}
     * @throws RandomException
     */
    public function create(string $label, int $requestsPerMinute = 120, ApiKeyKind $kind = ApiKeyKind::ThirdParty): array
    {
        $identifier = bin2hex(random_bytes(8));
        $secret = 32
                |> random_bytes(...)
                |> base64_encode(...)
                |> (fn ($x) => strtr($x, '+/', '-_'))
                |> (fn ($x) => rtrim($x, '='));
        $apiKey = new ApiKey($identifier, $label, $this->hash($secret), requestsPerMinute: $requestsPerMinute, kind: $kind);
        $this->entityManager->persist($apiKey);
        $this->entityManager->flush();
        $this->securityLogger->info('API key created.', [
            'api_key_identifier' => $identifier,
            'api_key_kind' => $kind->name,
            'requests_per_minute' => $requestsPerMinute,
        ]);

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

    public function revoke(string $identifier): bool
    {
        $apiKey = $this->entityManager->getRepository(ApiKey::class)->findOneBy(['identifier' => $identifier]);

        if (!$apiKey instanceof ApiKey) {
            $this->securityLogger->warning('API key revocation requested for an unknown key.', ['api_key_identifier' => $identifier]);

            return false;
        }

        $apiKey->revoke();
        $this->entityManager->flush();
        $this->securityLogger->info('API key revoked.', ['api_key_identifier' => $identifier]);

        return true;
    }

    private function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, $this->apiKeyPepper);
    }
}
