<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use function file_get_contents;
use function preg_replace;

final class CloverCoverageWriterTest extends TestCase
{

    public function testWriteGeneratesValidCloverXml(): void
    {
        $writer = new CloverCoverageWriter();

        $fileCoverage = new FileCoverage(
            '/path/to/Sample.php',
            [
                new ExecutableLine(10, 5),
                new ExecutableLine(20, 0),
                new ExecutableLine(30, 3),
            ],
            50,
        );

        $output = $writer->write([$fileCoverage], '    ');

        $expectedContent = file_get_contents(__DIR__ . '/../_fixtures/Writer/CloverCoverageWriter/valid-clover-expected.xml');

        self::assertSame($expectedContent, $this->normalizeOutput($output));
    }

    public function testWriteWithMultipleFiles(): void
    {
        $writer = new CloverCoverageWriter();

        $coverageData = [
            new FileCoverage(
                '/path/to/File1.php',
                [
                    new ExecutableLine(10, 5),
                    new ExecutableLine(20, 0),
                ],
            ),
            new FileCoverage(
                '/path/to/File2.php',
                [
                    new ExecutableLine(15, 3),
                ],
            ),
        ];

        $output = $writer->write($coverageData, '    ');

        $expectedContent = file_get_contents(__DIR__ . '/../_fixtures/Writer/CloverCoverageWriter/multiple-files-expected.xml');

        self::assertSame($expectedContent, $this->normalizeOutput($output));
    }

    public function testWriteWithNoFiles(): void
    {
        $writer = new CloverCoverageWriter();

        $output = $writer->write([], '    ');

        $expectedContent = file_get_contents(__DIR__ . '/../_fixtures/Writer/CloverCoverageWriter/no-files-expected.xml');

        self::assertSame($expectedContent, $this->normalizeOutput($output));
    }

    private function normalizeOutput(string $output): string
    {
        $normalized = preg_replace(
            ['/timestamp="[0-9]+"/', '/generated="[0-9]+"/'],
            ['timestamp="1234567890"', 'generated="1234567890"'],
            $output,
        );
        self::assertNotNull($normalized);
        return $normalized;
    }

}
