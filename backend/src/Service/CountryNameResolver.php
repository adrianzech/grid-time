<?php

declare(strict_types=1);

namespace App\Service;

use Rinvex\Country\CountryLoader;
use Rinvex\Country\CountryLoaderException;

final readonly class CountryNameResolver
{
    private CountryLoader $countryLoader;

    public function __construct(?CountryLoader $countryLoader = null)
    {
        $this->countryLoader = $countryLoader ?? new CountryLoader();
    }

    /**
     * @throws CountryLoaderException
     */
    public function resolve(string $code): ?string
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return null;
        }

        $countryName = locale_get_display_region(sprintf('und_%s', $code), 'en');

        if ($countryName !== '' && $countryName !== $code) {
            return $countryName;
        }

        foreach ($this->countryLoader->where('extra.ioc', $code) as $country) {
            if (!is_array($country)) {
                continue;
            }

            $name = $country['name'] ?? null;
            $commonName = is_array($name) ? $name['common'] ?? null : null;

            return is_string($commonName) ? $commonName : null;
        }

        return null;
    }
}
