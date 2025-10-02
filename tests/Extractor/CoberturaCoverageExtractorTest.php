<?php declare(strict_types = 1);

namespace Extractor;

use LogicException;
use PHPUnit\Framework\TestCase;
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

    public function testStripsPathsFromFilePaths(): void
    {
        $extractor = new CoberturaCoverageExtractor(new XmlLoader(), ['tests/fixtures/']);
        $coverage = $extractor->getCoverage(__DIR__ . '/../fixtures/cobertura.xml');

        self::assertArrayHasKey('Sample.php', $coverage);
        self::assertArrayNotHasKey('tests/fixtures/Sample.php', $coverage);
    }

    public function testThrowsExceptionForInvalidXml(): void
    {
        $extractor = new CoberturaCoverageExtractor(new XmlLoader());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Failed to parse XML file');

        $extractor->getCoverage(__DIR__ . '/../fixtures/Sample.php'); // not an XML file
    }

}
