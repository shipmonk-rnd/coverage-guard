<?php declare(strict_types = 1);

namespace Extractor;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\XmlLoader;

final class CloverCoverageExtractorTest extends TestCase
{

    #[DataProvider('provideCoverageFiles')]
    public function testExtractsCoverageFromCloverXml(string $filePath): void
    {
        $extractor = new CloverCoverageExtractor(new XmlLoader());
        $coverage = $extractor->getCoverage($filePath);

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
        $extractor = new CloverCoverageExtractor(new XmlLoader());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Failed to parse XML file');

        $extractor->getCoverage(__DIR__ . '/../fixtures/Sample.php'); // not an XML file
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideCoverageFiles(): iterable
    {
        yield 'default' => [__DIR__ . '/../fixtures/clover.xml'];
        yield 'with package' => [__DIR__ . '/../fixtures/clover_with_package.xml'];
    }

}
