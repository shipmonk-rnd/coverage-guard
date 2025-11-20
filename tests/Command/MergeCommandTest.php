<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Cli\CoverageOutputFormat;
use ShipMonk\CoverageGuard\Coverage\CoverageFormatDetector;
use ShipMonk\CoverageGuard\Coverage\CoverageMerger;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\StreamTestTrait;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use function preg_quote;
use const DIRECTORY_SEPARATOR;

final class MergeCommandTest extends TestCase
{

    use StreamTestTrait;

    public function testMergeCoberturaCoverageFiles(): void
    {
        $input1 = __DIR__ . '/../_fixtures/MergeCommand/cobertura-1.xml';
        $input2 = __DIR__ . '/../_fixtures/MergeCommand/cobertura-2.xml';
        $expectedFile = __DIR__ . '/../_fixtures/MergeCommand/cobertura-merged-expected.xml';

        $this->assertMergeProducesExpectedOutput(
            [$input1, $input2],
            $expectedFile,
            CoverageOutputFormat::Cobertura,
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
            CoverageOutputFormat::Cobertura,
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
            CoverageOutputFormat::Cobertura,
        );
    }

    public function testAutodetectCoberturaFormat(): void
    {
        $input1 = __DIR__ . '/../_fixtures/MergeCommand/cobertura-1.xml';
        $input2 = __DIR__ . '/../_fixtures/MergeCommand/cobertura-2.xml';
        $expectedFile = __DIR__ . '/../_fixtures/MergeCommand/cobertura-merged-expected.xml';

        // When format is null, it should autodetect from input files
        $this->assertMergeProducesExpectedOutput(
            [$input1, $input2],
            $expectedFile,
            format: null,
        );
    }

    /**
     * @param list<string> $inputFiles
     */
    private function assertMergeProducesExpectedOutput(
        array $inputFiles,
        string $expectedFile,
        ?CoverageOutputFormat $format,
    ): void
    {
        $outStream = $this->createStream();
        $errStream = $this->createStream();

        $fixturesDir = __DIR__ . '/../_fixtures/MergeCommand';

        $indent = '    ';
        $outPrinter = new Printer($outStream, noColor: true);
        $errPrinter = new Printer($errStream, noColor: true);
        $configResolver = new ConfigResolver(__DIR__);
        $configPath = $fixturesDir . '/config.php';

        $command = new MergeCommand(new CoverageProvider(new CoverageFormatDetector(), $errPrinter), new CoverageMerger(), new CoverageFormatDetector(), $configResolver, $outPrinter);
        $command($format, $indent, $configPath, ...$inputFiles);

        $this->assertStreamMatchesFile($outStream, $expectedFile, [
            '/timestamp=".*?"/' => 'timestamp="dummy"',
            $this->buildRegexForPath($fixturesDir) => '/new/absolute',
            '#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '#' => '/',
        ]);
    }

}
