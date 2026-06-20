<?php

declare(strict_types=1);

namespace App\Service;

final readonly class ClientIpAnonymizer
{
    public function anonymize(?string $ip): ?string
    {
        $packedIp = $ip === null ? false : inet_pton($ip);

        if ($packedIp === false) {
            return null;
        }

        if (strlen($packedIp) === 4) {
            return sprintf('%d.%d.%d.0/24', ord($packedIp[0]), ord($packedIp[1]), ord($packedIp[2]));
        }

        $anonymizedIp = substr($packedIp, 0, 8) . str_repeat("\0", 8);

        return sprintf('%s/64', inet_ntop($anonymizedIp));
    }
}
