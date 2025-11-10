<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Coverage\CoverageMerger;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use function array_keys;
use function array_values;
use function fclose;
use function fopen;
use function preg_quote;
use function preg_replace;
use function realpath;
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
        ]);
    }

    /**
     * @param resource $stream
     * @param array<string, string> $replacements
     */
    private function assertStreamMatchesFile(
        mixed $stream,
        string $expectedFile,
        array $replacements = [],
    ): void
    {
        rewind($stream);
        $actual = stream_get_contents($stream);
        fclose($stream);
        self::assertIsString($actual);

        $actualReplaced = preg_replace(array_keys($replacements), array_values($replacements), $actual);

        self::assertNotNull($actualReplaced);
        self::assertStringEqualsFile($expectedFile, $actualReplaced, "File $expectedFile does not match");
    }

    /**
     * @return resource
     */
    private function createStream(): mixed
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        return $stream;
    }

    private function buildRegexForPath(string $path): string
    {
        $realPath = realpath($path);
        self::assertIsString($realPath);
        return '#' . preg_quote($realPath, '#') . '#';
    }

}
