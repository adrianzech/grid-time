<?php

declare(strict_types=1);

namespace App\Scraper;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;

final readonly class Formula3ScheduleDataExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extractNextData(string $html, string $url): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $node = $xpath->query('//script[@id="__NEXT_DATA__"]')->item(0);

        if (!$node instanceof DOMElement) {
            throw new RuntimeException(sprintf('Could not find Next.js data in "%s".', $url));
        }

        $data = json_decode($node->textContent, true);

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Could not decode Next.js data in "%s".', $url));
        }

        return $data;
    }

    public function extractYearFromSeasonName(?string $seasonName): ?int
    {
        if ($seasonName === null || preg_match('/\b(?<year>\d{4})\b/', $seasonName, $matches) !== 1) {
            return null;
        }

        return (int) $matches['year'];
    }

    public function stringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    public function dateTimeValue(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function extractTimezoneOffset(?string $value): ?string
    {
        if ($value === null || preg_match('/(?<offset>[+-]\d{2}:\d{2}|Z)$/', $value, $matches) !== 1) {
            return null;
        }

        return $matches['offset'] === 'Z' ? '+00:00' : $matches['offset'];
    }
}
