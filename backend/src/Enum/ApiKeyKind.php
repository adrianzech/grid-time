<?php

declare(strict_types=1);

namespace App\Enum;

enum ApiKeyKind
{
    case FirstParty;
    case ThirdParty;

    public function isInternal(): bool
    {
        return $this === self::FirstParty;
    }
}
