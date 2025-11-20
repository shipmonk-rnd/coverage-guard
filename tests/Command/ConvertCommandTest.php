<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Cli\CoverageOutputFormat;
use ShipMonk\CoverageGuard\Coverage\CoverageFormatDetector;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\StreamTestTrait;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use function dirname;
use function preg_quote;
use const DIRECTORY_SEPARATOR;

final class ConvertCommandTest extends TestCase
{

    use StreamTestTrait;

    public function testConvertCloverToCobertura(): void
    {
        $inputFile = __DIR__ . '/../_fixtures/ConvertCommand/clover.xml';
        $expectedFile = __DIR__ . '/../_fixtures/ConvertCommand/clover-to-cobertura-expected.xml';

        $this->assertConvertProducesExpectedOutput(
            $inputFile,
            $expectedFile,
            CoverageOutputFormat::Cobertura,
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
            CoverageOutputFormat::Clover,
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
            CoverageOutputFormat::Cobertura,
            '        ',
        );
    }

    private function assertConvertProducesExpectedOutput(
        string $inputFile,
        string $expectedFile,
        CoverageOutputFormat $format,
        string $indent,
    ): void
    {
        $outStream = $this->createStream();
        $errStream = $this->createStream();

        $fixturesDir = __DIR__ . '/../_fixtures/ConvertCommand';

        $outPrinter = new Printer($outStream, noColor: true);
        $errPrinter = new Printer($errStream, noColor: true);
        $configResolver = new ConfigResolver(__DIR__);
        $configPath = null;

        $command = new ConvertCommand(new CoverageProvider(new CoverageFormatDetector(), $errPrinter), $configResolver, $outPrinter);
        $command($inputFile, $format, $configPath, $indent);

        $this->assertStreamMatchesFile($outStream, $expectedFile, [
            '/timestamp=".*?"/' => 'timestamp="dummy"',
            '/generated=".*?"/' => 'generated="dummy"',
            $this->buildRegexForPath(dirname($fixturesDir)) => 'tests/_fixtures',
            '#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '#' => '/',
        ]);
    }

}
