<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Coverage\CoverageMerger;
use ShipMonk\CoverageGuard\Extractor\ExtractorFactory;
use function fclose;
use function file_get_contents;
use function fopen;
use function preg_replace;
use function rewind;
use function stream_get_contents;

final class MergeCommandTest extends TestCase
{

    public function testMergeCoberturaCoverageFiles(): void
    {
        $input1 = __DIR__ . '/../_fixtures/MergeCommand/cobertura-1.xml';
        $input2 = __DIR__ . '/../_fixtures/MergeCommand/cobertura-2.xml';
        $expectedFile = __DIR__ . '/../_fixtures/MergeCommand/cobertura-merged-expected.xml';

        $this->assertMergeProducesExpectedOutput(
            [$input1, $input2],
            $expectedFile,
            CoverageFormat::Cobertura,
        );
    }

    public function testMergeMultipleSourcesIntoSingleCommonSource(): void
    {
        $input1 = __DIR__ . '/../_fixtures/MergeCommand/cobertura-multiple-sources-1.xml';
        $input2 = __DIR__ . '/../_fixtures/MergeCommand/cobertura-multiple-sources-2.xml';
        $expectedFile = __DIR__ . '/../_fixtures/MergeCommand/cobertura-multiple-sources-expected.xml';

        $this->assertMergeProducesExpectedOutput(
            [$input1, $input2],
            $expectedFile,
            CoverageFormat::Cobertura,
        );
    }

    public function testMergeSkipsFilesWithEmptyPackages(): void
    {
        $emptyInput = __DIR__ . '/../_fixtures/MergeCommand/cobertura-empty-packages.xml';
        $validInput = __DIR__ . '/../_fixtures/MergeCommand/cobertura-empty-packages-with-data.xml';
        $expectedFile = __DIR__ . '/../_fixtures/MergeCommand/cobertura-empty-packages-expected.xml';

        $this->assertMergeProducesExpectedOutput(
            [$emptyInput, $validInput],
            $expectedFile,
            CoverageFormat::Cobertura,
        );
    }

    /**
     * @param list<string> $inputFiles
     */
    private function assertMergeProducesExpectedOutput(
        array $inputFiles,
        string $expectedFile,
        CoverageFormat $format,
    ): void
    {
        // Create a memory stream to capture output
        $outputStream = fopen('php://memory', 'w+');
        self::assertNotFalse($outputStream);

        try {
            $indent = '    ';

            $command = new MergeCommand(new ExtractorFactory(), new CoverageMerger(), $outputStream);
            $command($format, $indent, ...$inputFiles);

            rewind($outputStream);
            $actualContent = stream_get_contents($outputStream);
            $expectedContent = file_get_contents($expectedFile);

            self::assertNotFalse($actualContent);
            self::assertNotFalse($expectedContent);

            $actualContentWithDummyTimestamp = preg_replace('/timestamp=".*?"/', 'timestamp="dummy"', $actualContent);

            self::assertSame($expectedContent, $actualContentWithDummyTimestamp);
        } finally {
            fclose($outputStream);
        }
    }

}
