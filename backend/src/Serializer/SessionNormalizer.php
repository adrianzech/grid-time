<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Session;
use ArrayObject;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SessionNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'grid_time_session_normalizer_already_called';

    /**
     * @return array<string, mixed>|string|int|float|bool|ArrayObject<int|string, mixed>|null
     *
     * @throws ExceptionInterface
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);

        if (!is_array($normalized) || !$data instanceof Session) {
            return $normalized;
        }

        $normalized['trackTimezoneOffset'] = $data->getTrackTimezoneOffset();

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Session
            && $format === 'jsonld'
            && !($context[self::ALREADY_CALLED] ?? false);
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            Session::class => false,
        ];
    }
}
