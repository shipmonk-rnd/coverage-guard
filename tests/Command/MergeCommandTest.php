<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Coverage\CoverageMerger;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use function preg_quote;
use const DIRECTORY_SEPARATOR;

final class MergeCommandTest extends TestCase
{

    use CommandTestTrait;

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
        $commandStream = $this->createStream();
        $printerStream = $this->createStream();

        $fixturesDir = __DIR__ . '/../_fixtures/MergeCommand';

        $indent = '    ';
        $printer = new Printer($printerStream, noColor: true);
        $configResolver = new ConfigResolver(__DIR__);
        $configPath = $fixturesDir . '/config.php';

        $command = new MergeCommand(new CoverageProvider($printer), new CoverageMerger(), $configResolver, $commandStream);
        $command($format, $indent, $configPath, ...$inputFiles);

        $this->assertStreamMatchesFile($commandStream, $expectedFile, [
            '/timestamp=".*?"/' => 'timestamp="dummy"',
            $this->buildRegexForPath($fixturesDir) => '/new/absolute',
            '#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '#' => '/',
        ]);
    }

}
