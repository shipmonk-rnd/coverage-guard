<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\XmlLoader;
use function array_combine;
use function array_map;

final class CoberturaCoverageExtractorTest extends TestCase
{

    public function testExtractsCoverageFromCoberturaXml(): void
    {
        $extractor = new CoberturaCoverageExtractor(new XmlLoader());
        $coverage = $extractor->getCoverage(__DIR__ . '/../_fixtures/Extractor/cobertura.xml');

        self::assertNotEmpty($coverage);
        self::assertSame('tests/_fixtures/Sample.php', $coverage[0]->filePath);
        $fileCoverage = $coverage[0];

        $lineNumberToHitCount = array_combine(
            array_map(static fn (ExecutableLine $line) => $line->lineNumber, $fileCoverage->executableLines),
            array_map(static fn (ExecutableLine $line) => $line->hits, $fileCoverage->executableLines),
        );

        self::assertSame([
            10 => 1,
            15 => 0,
        ], $lineNumberToHitCount);
    }

    public function testThrowsExceptionForInvalidXml(): void
    {
        $extractor = new CoberturaCoverageExtractor(new XmlLoader());

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Failed to parse XML file');

        $extractor->getCoverage(__DIR__ . '/../_fixtures/Sample.php'); // not an XML file
    }

}
