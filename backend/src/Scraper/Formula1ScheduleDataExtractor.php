<?php

declare(strict_types=1);

namespace App\Scraper;

use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Exception;
use RuntimeException;

final readonly class Formula1ScheduleDataExtractor
{
    public function eventName(string $html): ?string
    {
        $node = $this->createXPath($html)->query('//h1')->item(0);

        if (!$node instanceof DOMNode) {
            return null;
        }

        return $this->normalizeText($node->textContent);
    }

    public function locationFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $slug = basename((string) $path);

        return ucwords(str_replace('-', ' ', $slug));
    }

    /**
     * @return list<array{description: string, startTime: string, endTime?: string, gmtOffset: string}>
     */
    public function meetingSessions(string $html): array
    {
        $json = $this->extractJsonArrayAfterKey($html) ?? $this->extractJsonArrayAfterKey(stripcslashes($html));

        if ($json === null) {
            return [];
        }

        $sessions = json_decode($json, true);

        if (!is_array($sessions)) {
            return [];
        }

        return array_values(array_filter($sessions, static fn (mixed $session): bool => is_array($session) && isset($session['description'], $session['startTime'], $session['gmtOffset']) && is_string($session['description']) && is_string($session['startTime']) && is_string($session['gmtOffset']) && (!isset($session['endTime']) || is_string($session['endTime']))));
    }

    /**
     * @param array{description: string, startTime: string, endTime?: string, gmtOffset: string} $session
     *
     * @return array{DateTimeImmutable, DateTimeImmutable|null}
     */
    public function sessionTimes(array $session, string $url): array
    {
        try {
            $sessionTimezone = new DateTimeZone($session['gmtOffset']);
            $startsAt = new DateTimeImmutable($session['startTime'], $sessionTimezone);
            $endsAt = isset($session['endTime']) ? new DateTimeImmutable($session['endTime'], $sessionTimezone) : null;
        } catch (Exception $exception) {
            throw new RuntimeException(sprintf('Could not parse session time for "%s".', $url), 0, $exception);
        }

        return [$startsAt, $endsAt];
    }

    private function createXPath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function normalizeText(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)));
    }

    private function extractJsonArrayAfterKey(string $html): ?string
    {
        $keyPosition = strpos($html, '"meetingSessions":');

        if ($keyPosition === false) {
            return null;
        }

        $arrayStart = strpos($html, '[', $keyPosition + strlen('"meetingSessions":'));

        if ($arrayStart === false) {
            return null;
        }

        return $this->extractJsonArrayAt($html, $arrayStart);
    }

    private function extractJsonArrayAt(string $html, int $start): ?string
    {
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($html);

        for ($position = $start; $position < $length; ++$position) {
            $character = $html[$position];

            if ($inString) {
                $escaped = $this->advanceJsonStringState($character, $escaped, $inString);

                continue;
            }

            if ($character === '"') {
                $inString = true;

                continue;
            }

            if ($character === '[') {
                ++$depth;

                continue;
            }

            if ($character !== ']') {
                continue;
            }

            --$depth;

            if ($depth === 0) {
                return substr($html, $start, $position - $start + 1);
            }
        }

        return null;
    }

    private function advanceJsonStringState(string $character, bool $escaped, bool &$inString): bool
    {
        if ($escaped) {
            return false;
        }

        if ($character === '\\') {
            return true;
        }

        if ($character === '"') {
            $inString = false;
        }

        return false;
    }
}
