<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\CountryNameNormalizer;
use App\Service\CountryNameResolver;
use PHPUnit\Framework\TestCase;

final class CountryNameResolverTest extends TestCase
{
    public function testResolvesIsoCountryCodes(): void
    {
        $resolver = new CountryNameResolver();

        self::assertSame('Brazil', $resolver->resolve('BR'));
        self::assertSame('United Kingdom', $resolver->resolve('GBR'));
        self::assertSame('Czechia', $resolver->resolve('cze'));
        self::assertSame('Portugal', $resolver->resolve('POR'));
        self::assertSame('Netherlands', $resolver->resolve('NED'));
        self::assertNull($resolver->resolve('unknown'));
    }

    public function testNormalizesUppercaseCountryNames(): void
    {
        $normalizer = new CountryNameNormalizer();

        self::assertSame('Czechia', $normalizer->normalize('CZECHIA'));
        self::assertSame('United Kingdom', $normalizer->normalize('United Kingdom'));
    }
}
