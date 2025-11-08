<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Extractor\ExtractorFactory;
use function fclose;
use function file_get_contents;
use function fopen;
use function preg_replace;
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

    public function testInvokeWithNonExistentFile(): void
    {
        $command = new ConvertCommand(new ExtractorFactory());

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('File not found');

        ($command)(
            'nonexistent.xml',
            CoverageFormat::Clover,
        );
    }

    private function assertConvertProducesExpectedOutput(
        string $inputFile,
        string $expectedFile,
        CoverageFormat $format,
        string $indent,
    ): void
    {
        // Create a memory stream to capture output
        $outputStream = fopen('php://memory', 'w+');
        self::assertNotFalse($outputStream);

        try {
            $command = new ConvertCommand(new ExtractorFactory(), $outputStream);
            $command($inputFile, $format, $indent);

            rewind($outputStream);
            $actualContent = stream_get_contents($outputStream);
            $expectedContent = file_get_contents($expectedFile);

            self::assertNotFalse($actualContent);
            self::assertNotFalse($expectedContent);

            $actualContentNormalized = preg_replace(
                ['/timestamp="[0-9]+"/', '/generated="[0-9]+"/'],
                ['timestamp="dummy"', 'generated="dummy"'],
                $actualContent,
            );

            self::assertSame($expectedContent, $actualContentNormalized);
        } finally {
            fclose($outputStream);
        }
    }

}
