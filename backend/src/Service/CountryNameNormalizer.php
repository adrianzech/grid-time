<?php

declare(strict_types=1);

namespace App\Service;

final readonly class CountryNameNormalizer
{
    public function normalize(string $countryName): string
    {
        $countryName = trim($countryName);

        return preg_match('/[a-z]/', $countryName) === 1
            ? $countryName
            : ucwords(strtolower($countryName));
    }
}
