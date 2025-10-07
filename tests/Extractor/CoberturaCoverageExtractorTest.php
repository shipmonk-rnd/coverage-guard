<?php declare(strict_types = 1);

namespace Extractor;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\CoberturaCoverageExtractor;
use ShipMonk\CoverageGuard\XmlLoader;

final class CoberturaCoverageExtractorTest extends TestCase
{

    public function testExtractsCoverageFromCoberturaXml(): void
    {
        $extractor = new CoberturaCoverageExtractor(new XmlLoader());
        $coverage = $extractor->getCoverage(__DIR__ . '/../fixtures/cobertura.xml');

        self::assertArrayHasKey('tests/fixtures/Sample.php', $coverage);
        $fileCoverage = $coverage['tests/fixtures/Sample.php'];

        self::assertSame([
            8 => 1,
            10 => 1,
            13 => 0,
            15 => 0,
        ], $fileCoverage);
    }

    public function testThrowsExceptionForInvalidXml(): void
    {
        $extractor = new CoberturaCoverageExtractor(new XmlLoader());

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Failed to parse XML file');

        $extractor->getCoverage(__DIR__ . '/../fixtures/Sample.php'); // not an XML file
    }

}
