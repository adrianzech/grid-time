<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ClientIpAnonymizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientIpAnonymizerTest extends TestCase
{
    #[DataProvider('ipProvider')]
    public function testAnonymizesClientIpAddresses(?string $ip, ?string $expected): void
    {
        self::assertSame($expected, new ClientIpAnonymizer()->anonymize($ip));
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function ipProvider(): iterable
    {
        yield 'ipv4' => ['203.0.113.42', '203.0.113.0/24'];
        yield 'ipv6' => ['2001:db8:1234:5678:abcd:ef01:2345:6789', '2001:db8:1234:5678::/64'];
        yield 'missing' => [null, null];
        yield 'invalid' => ['not-an-ip', null];
    }
}
