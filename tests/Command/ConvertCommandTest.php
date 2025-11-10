<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use function array_keys;
use function array_values;
use function dirname;
use function fclose;
use function fopen;
use function preg_quote;
use function preg_replace;
use function realpath;
use function rewind;
use function stream_get_contents;

final class ConvertCommandTest extends TestCase
{

    public function testConvertCloverToCobertura(): void
    {
        $inputFile = __DIR__ . '/../_fixtures/ConvertCommand/clover.xml';
        $expectedFile = __DIR__ . '/../_fixtures/ConvertCommand/clover-to-cobertura-expected.xml';

        $this->assertConvertProducesExpectedOutput(
            $inputFile,
            $expectedFile,
            CoverageFormat::Cobertura,
            '    ',
        );
    }

    public function testConvertCoberturaToClover(): void
    {
        $inputFile = __DIR__ . '/../_fixtures/ConvertCommand/cobertura.xml';
        $expectedFile = __DIR__ . '/../_fixtures/ConvertCommand/cobertura-to-clover-expected.xml';

        $this->assertConvertProducesExpectedOutput(
            $inputFile,
            $expectedFile,
            CoverageFormat::Clover,
            '    ',
        );
    }

    public function testConvertWithCustomIndent(): void
    {
        $inputFile = __DIR__ . '/../_fixtures/ConvertCommand/clover.xml';
        $expectedFile = __DIR__ . '/../_fixtures/ConvertCommand/clover-to-cobertura-tab-indent-expected.xml';

        $this->assertConvertProducesExpectedOutput(
            $inputFile,
            $expectedFile,
            CoverageFormat::Cobertura,
            '        ',
        );
    }

    private function assertConvertProducesExpectedOutput(
        string $inputFile,
        string $expectedFile,
        CoverageFormat $format,
        string $indent,
    ): void
    {
        $commandStream = $this->createStream();
        $printerStream = $this->createStream();

        $fixturesDir = __DIR__ . '/../_fixtures/ConvertCommand';

        $printer = new Printer($printerStream, noColor: true);
        $configResolver = new ConfigResolver(__DIR__);
        $configPath = $fixturesDir . '/config.php';

        $command = new ConvertCommand(new CoverageProvider($printer), $configResolver, $commandStream);
        $command($inputFile, $format, $configPath, $indent);

        $this->assertStreamMatchesFile($commandStream, $expectedFile, [
            '/timestamp=".*?"/' => 'timestamp="dummy"',
            '/generated=".*?"/' => 'generated="dummy"',
            $this->buildRegexForPath(dirname($fixturesDir)) => 'tests/_fixtures',
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
