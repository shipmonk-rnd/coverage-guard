<?php declare(strict_types = 1);

namespace Extractor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\XmlLoader;
use function array_combine;
use function array_map;

final class CloverCoverageExtractorTest extends TestCase
{

    #[DataProvider('provideCoverageFiles')]
    public function testExtractsCoverageFromCloverXml(string $filePath): void
    {
        $extractor = new CloverCoverageExtractor(new XmlLoader());
        $coverage = $extractor->getCoverage($filePath);

        self::assertNotEmpty($coverage);
        self::assertSame('tests/fixtures/Sample.php', $coverage[0]->filePath);
        $fileCoverage = $coverage[0];

        $lineNumberToHitCount = array_combine(
            array_map(static fn (ExecutableLine $line) => $line->lineNumber, $fileCoverage->executableLines),
            array_map(static fn (ExecutableLine $line) => $line->hits, $fileCoverage->executableLines),
        );

        self::assertSame([
            8 => 1,
            10 => 1,
            13 => 0,
            15 => 0,
        ], $lineNumberToHitCount);
    }

    public function testThrowsExceptionForInvalidXml(): void
    {
        $extractor = new CloverCoverageExtractor(new XmlLoader());

        $this->expectException(ErrorException::class);
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
