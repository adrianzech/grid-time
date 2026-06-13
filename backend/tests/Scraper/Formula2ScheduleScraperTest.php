<?php

declare(strict_types=1);

namespace App\Tests\Scraper;

use App\Scraper\Formula2ScheduleScraper;
use Closure;
use DateTimeZone;
use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Formula2ScheduleScraperTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testScrapesFormula2SessionsFromNextData(): void
    {
        $scraper = new Formula2ScheduleScraper($this->fetcher([
            'https://www.fiaformula2.com/Calendar' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'SeasonName' => 'Formula 2 2026',
                            'Races' => [
                                [
                                    'RaceId' => 1096,
                                    'RoundNumber' => 6,
                                    'CountryName' => 'Austria',
                                    'CircuitShortName' => 'Spielberg',
                                ],
                            ],
                        ],
                        'seasonData' => [],
                    ],
                ],
            ]),
            'https://www.fiaformula2.com/Results?raceid=1096' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'CountryName' => 'Austria',
                            'CircuitShortName' => 'Spielberg',
                            'SessionResults' => [
                                [
                                    'SessionName' => 'Feature Race',
                                    'SessionStartTime' => '2026-06-28T10:00:00+02:00',
                                    'SessionEndTime' => '2026-06-28T11:05:00+02:00',
                                ],
                                [
                                    'SessionName' => 'Free Practice',
                                    'SessionStartTime' => '2026-06-26T11:55:00+02:00',
                                    'SessionEndTime' => '2026-06-26T12:40:00+02:00',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]));

        $sessions = $scraper->scrape(2026, new DateTimeZone('UTC'));

        self::assertCount(2, $sessions);
        self::assertSame('F2', $sessions[0]->series);
        self::assertSame('Formula 2', $sessions[0]->seriesName);
        self::assertSame(6, $sessions[0]->round);
        self::assertSame('Austria', $sessions[0]->eventName);
        self::assertSame('Spielberg', $sessions[0]->location);
        self::assertSame('Free Practice', $sessions[0]->sessionName);
        self::assertSame('2026-06-26 09:55:00', $sessions[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-26 10:40:00', $sessions[0]->endsAt?->format('Y-m-d H:i:s'));
        self::assertSame('https://www.fiaformula2.com/Results?raceid=1096', $sessions[0]->sourceUrl);
    }

    /**
     * @throws JsonException
     */
    public function testFetchesRequestedSeasonBySeasonId(): void
    {
        $scraper = new Formula2ScheduleScraper($this->fetcher([
            'https://www.fiaformula2.com/Calendar' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'SeasonName' => 'Formula 2 2026',
                            'Races' => [],
                        ],
                        'seasonData' => [
                            [
                                'SeasonId' => 182,
                                'SeasonName' => 'Formula 2 2025',
                            ],
                        ],
                    ],
                ],
            ]),
            'https://www.fiaformula2.com/Calendar?seasonid=182' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'SeasonName' => 'Formula 2 2025',
                            'Races' => [
                                [
                                    'RaceId' => 1084,
                                    'RoundNumber' => 7,
                                    'CountryName' => 'Austria',
                                    'CircuitName' => 'Red Bull Ring',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            'https://www.fiaformula2.com/Results?raceid=1084' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'CountryName' => 'Austria',
                            'CircuitInformation' => [
                                'CircuitName' => 'Red Bull Ring',
                            ],
                            'SessionResults' => [
                                [
                                    'SessionName' => 'Sprint Race',
                                    'SessionStartTime' => '2025-06-28T14:15:00+02:00',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]));

        $sessions = $scraper->scrape(2025, new DateTimeZone('UTC'));

        self::assertCount(1, $sessions);
        self::assertSame(7, $sessions[0]->round);
        self::assertSame('Red Bull Ring', $sessions[0]->location);
        self::assertSame('Sprint Race', $sessions[0]->sessionName);
        self::assertSame('2025-06-28 12:15:00', $sessions[0]->startsAt->format('Y-m-d H:i:s'));
    }

    /**
     * @throws JsonException
     */
    public function testSkipsMalformedSessions(): void
    {
        $scraper = new Formula2ScheduleScraper($this->fetcher([
            'https://www.fiaformula2.com/Calendar' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'SeasonName' => 'Formula 2 2026',
                            'Races' => [
                                [
                                    'RaceId' => 1096,
                                    'RoundNumber' => 6,
                                    'CountryName' => 'Austria',
                                    'CircuitShortName' => 'Spielberg',
                                ],
                            ],
                        ],
                        'seasonData' => [],
                    ],
                ],
            ]),
            'https://www.fiaformula2.com/Results?raceid=1096' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'SessionResults' => [
                                [
                                    'SessionName' => 'Free Practice',
                                    'SessionStartTime' => 'TBC',
                                ],
                                [
                                    'SessionStartTime' => '2026-06-26T11:55:00+02:00',
                                ],
                                [
                                    'SessionName' => 'Qualifying Session',
                                    'SessionStartTime' => '2026-06-26T15:55:00+02:00',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]));

        $sessions = $scraper->scrape(2026, new DateTimeZone('UTC'));

        self::assertCount(1, $sessions);
        self::assertSame('Qualifying Session', $sessions[0]->sessionName);
    }

    /**
     * @param array<string, string> $responses
     */
    private function fetcher(array $responses): Closure
    {
        return static function (string $url) use ($responses): string {
            if (!isset($responses[$url])) {
                throw new RuntimeException(sprintf('Unexpected URL "%s".', $url));
            }

            return $responses[$url];
        };
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws JsonException
     */
    private function nextDataHtml(array $data): string
    {
        return sprintf(
            '<!doctype html><html lang="en"><body><script id="__NEXT_DATA__" type="application/json">%s</script></body></html>',
            json_encode($data, JSON_THROW_ON_ERROR),
        );
    }
}
