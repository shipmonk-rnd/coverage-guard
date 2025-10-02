<?php declare(strict_types = 1);

namespace Extractor;

use LogicException;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\XmlLoader;

final class CloverCoverageExtractorTest extends TestCase
{

    public function testExtractsCoverageFromCloverXml(): void
    {
        $extractor = new CloverCoverageExtractor(new XmlLoader());
        $coverage = $extractor->getCoverage(__DIR__ . '/../fixtures/clover.xml');

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
        $extractor = new CloverCoverageExtractor(new XmlLoader(), ['tests/fixtures/']);
        $coverage = $extractor->getCoverage(__DIR__ . '/../fixtures/clover.xml');

        self::assertArrayHasKey('Sample.php', $coverage);
        self::assertArrayNotHasKey('tests/fixtures/Sample.php', $coverage);
    }

    public function testThrowsExceptionForInvalidXml(): void
    {
        $extractor = new CloverCoverageExtractor(new XmlLoader());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Failed to parse XML file');

        $extractor->getCoverage(__DIR__ . '/../fixtures/Sample.php'); // not an XML file
    }

}
