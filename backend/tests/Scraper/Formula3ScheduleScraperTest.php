<?php

declare(strict_types=1);

namespace App\Tests\Scraper;

use App\Scraper\Formula3ScheduleScraper;
use Closure;
use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Formula3ScheduleScraperTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testScrapesFormula3SessionsFromNextData(): void
    {
        $scraper = new Formula3ScheduleScraper($this->fetcher([
            'https://www.fiaformula3.com/Calendar' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'SeasonName' => 'Formula 3 2026',
                            'Races' => [
                                [
                                    'RaceId' => 1073,
                                    'RoundNumber' => 4,
                                    'CountryName' => 'Austria',
                                    'CircuitShortName' => 'Spielberg',
                                ],
                            ],
                        ],
                        'seasonData' => [],
                    ],
                ],
            ]),
            'https://www.fiaformula3.com/Results?raceid=1073' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'CountryName' => 'Austria',
                            'CircuitShortName' => 'Spielberg',
                            'SessionResults' => [
                                [
                                    'SessionName' => 'Feature Race',
                                    'SessionStartTime' => '2026-06-28T08:30:00+02:00',
                                    'SessionEndTime' => '2026-06-28T09:15:00+02:00',
                                ],
                                [
                                    'SessionName' => 'Practice',
                                    'SessionStartTime' => '2026-06-26T08:35:00+02:00',
                                    'SessionEndTime' => '2026-06-26T09:20:00+02:00',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]));

        $sessions = $scraper->scrape(2026);

        self::assertCount(2, $sessions);
        self::assertSame('F3', $sessions[0]->series);
        self::assertSame('Formula 3', $sessions[0]->seriesName);
        self::assertSame(4, $sessions[0]->round);
        self::assertSame('Austria', $sessions[0]->eventName);
        self::assertSame('Spielberg', $sessions[0]->location);
        self::assertSame('Practice', $sessions[0]->sessionName);
        self::assertSame('2026-06-26 06:35:00', $sessions[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-26 07:20:00', $sessions[0]->endsAt?->format('Y-m-d H:i:s'));
        self::assertSame('https://www.fiaformula3.com/Results?raceid=1073', $sessions[0]->sourceUrl);
        self::assertSame('+02:00', $sessions[0]->trackTimezoneOffset);
    }

    /**
     * @throws JsonException
     */
    public function testFetchesRequestedSeasonBySeasonId(): void
    {
        $scraper = new Formula3ScheduleScraper($this->fetcher([
            'https://www.fiaformula3.com/Calendar' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'SeasonName' => 'Formula 3 2026',
                            'Races' => [],
                        ],
                        'seasonData' => [
                            [
                                'SeasonId' => 183,
                                'SeasonName' => 'Formula 3 2025',
                            ],
                        ],
                    ],
                ],
            ]),
            'https://www.fiaformula3.com/Calendar?seasonid=183' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'SeasonName' => 'Formula 3 2025',
                            'Races' => [
                                [
                                    'RaceId' => 1051,
                                    'RoundNumber' => 6,
                                    'CountryName' => 'Austria',
                                    'CircuitName' => 'Red Bull Ring',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            'https://www.fiaformula3.com/Results?raceid=1051' => $this->nextDataHtml([
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
                                    'SessionStartTime' => '2025-06-28T09:30:00+02:00',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]));

        $sessions = $scraper->scrape(2025);

        self::assertCount(1, $sessions);
        self::assertSame(6, $sessions[0]->round);
        self::assertSame('Red Bull Ring', $sessions[0]->location);
        self::assertSame('Sprint Race', $sessions[0]->sessionName);
        self::assertSame('2025-06-28 07:30:00', $sessions[0]->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('+02:00', $sessions[0]->trackTimezoneOffset);
    }

    /**
     * @throws JsonException
     */
    public function testSkipsMalformedSessions(): void
    {
        $scraper = new Formula3ScheduleScraper($this->fetcher([
            'https://www.fiaformula3.com/Calendar' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'SeasonName' => 'Formula 3 2026',
                            'Races' => [
                                [
                                    'RaceId' => 1073,
                                    'RoundNumber' => 4,
                                    'CountryName' => 'Austria',
                                    'CircuitShortName' => 'Spielberg',
                                ],
                            ],
                        ],
                        'seasonData' => [],
                    ],
                ],
            ]),
            'https://www.fiaformula3.com/Results?raceid=1073' => $this->nextDataHtml([
                'props' => [
                    'pageProps' => [
                        'pageData' => [
                            'SessionResults' => [
                                [
                                    'SessionName' => 'Practice',
                                    'SessionStartTime' => 'TBC',
                                ],
                                [
                                    'SessionStartTime' => '2026-06-26T08:35:00+02:00',
                                ],
                                [
                                    'SessionName' => 'Qualifying Session',
                                    'SessionStartTime' => '2026-06-26T14:00:00+02:00',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]));

        $sessions = $scraper->scrape(2026);

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
