<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\ApiKeyManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly ApiKeyManager $apiKeyManager, private readonly CacheItemPoolInterface $cacheApp)
    {
    }

    public function supports(Request $request): bool
    {
        return preg_match('#^/api/(series|seasons|events|sessions)(?:/|\.|$)#', $request->getPathInfo()) === 1;
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->headers->get('X-API-Key');
        $apiKey = is_string($token) ? $this->apiKeyManager->findValid($token) : null;
        if ($apiKey === null) {
            throw new AuthenticationException('Invalid API key.');
        }

        if (!$apiKey->isInternal()) {
            $limiter = new RateLimiterFactory([
                'id' => 'api_key_' . $apiKey->getRequestsPerMinute(),
                'policy' => 'fixed_window',
                'limit' => $apiKey->getRequestsPerMinute(),
                'interval' => '1 minute',
            ], new CacheStorage($this->cacheApp));
            $limit = $limiter->create($apiKey->getIdentifier())->consume();
            if (!$limit->isAccepted()) {
                throw new AuthenticationException('API rate limit exceeded.');
            }
        }

        return new SelfValidatingPassport(new UserBadge($apiKey->getIdentifier(), fn (): ApiKeyUser => new ApiKeyUser($apiKey->getIdentifier(), $apiKey->getScope())));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $status = $exception->getMessage() === 'API rate limit exceeded.' ? Response::HTTP_TOO_MANY_REQUESTS : Response::HTTP_UNAUTHORIZED;

        return new JsonResponse(['message' => $exception->getMessage()], $status);
    }
}
