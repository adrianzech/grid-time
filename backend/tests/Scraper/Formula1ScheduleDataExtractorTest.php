<?php

declare(strict_types=1);

namespace App\Tests\Scraper;

use App\Scraper\Formula1ScheduleDataExtractor;
use PHPUnit\Framework\TestCase;

final class Formula1ScheduleDataExtractorTest extends TestCase
{
    public function testExtractsCountryAndCircuitNamesFromRacePageData(): void
    {
        $extractor = new Formula1ScheduleDataExtractor();
        $html = <<<'HTML'
            <script>
                self.__next_f.push([1,"{\"meetingCountryName\":\"Austria\",\"circuitOfficialName\":\"Red Bull Ring\",\"circuitShortName\":\"Spielberg\"}"]);
            </script>
            HTML;

        self::assertSame('Austria', $extractor->countryName($html, 'https://www.formula1.com/en/racing/2026/austria'));
        self::assertSame('Red Bull Ring', $extractor->circuitName($html));
    }

    public function testFallsBackToUrlLocationWhenRacePageDataIsMissing(): void
    {
        $extractor = new Formula1ScheduleDataExtractor();

        self::assertSame('Great Britain', $extractor->countryName('', 'https://www.formula1.com/en/racing/2026/great-britain'));
        self::assertNull($extractor->circuitName(''));
    }
}
