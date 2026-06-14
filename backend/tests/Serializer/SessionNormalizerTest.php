<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Entity\Event;
use App\Entity\Season;
use App\Entity\Series;
use App\Entity\Session;
use App\Serializer\SessionNormalizer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SessionNormalizerTest extends TestCase
{
    /**
     * @throws ExceptionInterface
     */
    public function testAddsTrackTimezoneOffsetToJsonLdSessionOutput(): void
    {
        $session = $this->createSession();
        $normalizer = new SessionNormalizer();
        $normalizer->setNormalizer(new class implements NormalizerInterface {
            public function normalize(mixed $data, ?string $format = null, array $context = []): array
            {
                return [
                    '@id' => '/api/sessions/1',
                    '@type' => 'Session',
                    'name' => 'Practice 1',
                ];
            }

            public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
            {
                return true;
            }

            /**
             * @return array<string, bool>
             */
            public function getSupportedTypes(?string $format): array
            {
                return ['*' => false];
            }
        });

        $normalized = $normalizer->normalize($session, 'jsonld');

        self::assertIsArray($normalized);
        self::assertSame('+11:00', $normalized['trackTimezoneOffset'] ?? null);
    }

    public function testSupportsOnlyJsonLdSessionsOnce(): void
    {
        $normalizer = new SessionNormalizer();

        self::assertTrue($normalizer->supportsNormalization($this->createSession(), 'jsonld'));
        self::assertFalse($normalizer->supportsNormalization($this->createSession(), 'json'));
        self::assertFalse($normalizer->supportsNormalization(new stdClass(), 'jsonld'));
        self::assertFalse($normalizer->supportsNormalization($this->createSession(), 'jsonld', [
            'grid_time_session_normalizer_already_called' => true,
        ]));
    }

    private function createSession(): Session
    {
        $series = new Series('F1', 'Formula 1');
        $season = new Season($series, 2026, 'Formula 1 2026');
        $event = new Event($season, 1, 'Australian Grand Prix', 'Australia', 'https://www.formula1.com/en/racing/2026/australia');

        return new Session(
            event: $event,
            name: 'Practice 1',
            startsAt: new DateTimeImmutable('2026-03-06T01:30:00+00:00'),
            endsAt: new DateTimeImmutable('2026-03-06T02:30:00+00:00'),
            sourceUrl: 'https://www.formula1.com/en/racing/2026/australia',
            trackTimezoneOffset: '+11:00',
        );
    }
}
