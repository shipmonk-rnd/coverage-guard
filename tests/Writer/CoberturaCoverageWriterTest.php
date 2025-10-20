<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Extractor\CoberturaCoverageExtractor;
use ShipMonk\CoverageGuard\XmlLoader;
use function fclose;
use function fopen;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class CoberturaCoverageWriterTest extends TestCase
{

    public function testWriteGeneratesValidCoberturaXml(): void
    {
        $writer = new CoberturaCoverageWriter();

        $fileCoverage = new FileCoverage(
            '/path/to/Sample.php',
            [
                new ExecutableLine(10, 5),
                new ExecutableLine(20, 0),
                new ExecutableLine(30, 3),
            ],
        );

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $writer->write([$fileCoverage], $stream);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($output);
        self::assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $output);
        self::assertStringContainsString('<!DOCTYPE coverage SYSTEM "http://cobertura.sourceforge.net/xml/coverage-04.dtd">', $output);
        self::assertStringContainsString('<coverage', $output);
        self::assertStringContainsString('line-rate=', $output);
        self::assertStringContainsString('lines-covered="2"', $output); // 2 out of 3 lines covered
        self::assertStringContainsString('lines-valid="3"', $output);
        self::assertStringContainsString('<sources>', $output);
        self::assertStringContainsString('<source>/path/to</source>', $output);
        self::assertStringContainsString('<packages>', $output);
        self::assertStringContainsString('<line number="10" hits="5"/>', $output);
        self::assertStringContainsString('<line number="20" hits="0"/>', $output);
        self::assertStringContainsString('<line number="30" hits="3"/>', $output);
    }

    public function testWriteCanBeReadBackByExtractor(): void
    {
        $writer = new CoberturaCoverageWriter();
        $extractor = new CoberturaCoverageExtractor(new XmlLoader());

        $originalCoverage = [
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

        // Write to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'cobertura-test-');
        self::assertIsString($tempFile);

        try {
            $stream = fopen($tempFile, 'w');
            self::assertIsResource($stream);

            $writer->write($originalCoverage, $stream);
            fclose($stream);

            // Read it back
            $readCoverage = $extractor->getCoverage($tempFile);

            self::assertCount(2, $readCoverage);

            // Verify File1
            self::assertSame('/path/to/File1.php', $readCoverage[0]->filePath);
            self::assertCount(2, $readCoverage[0]->executableLines);
            self::assertSame(10, $readCoverage[0]->executableLines[0]->lineNumber);
            self::assertSame(5, $readCoverage[0]->executableLines[0]->hits);
            self::assertSame(20, $readCoverage[0]->executableLines[1]->lineNumber);
            self::assertSame(0, $readCoverage[0]->executableLines[1]->hits);

            // Verify File2
            self::assertSame('/path/to/File2.php', $readCoverage[1]->filePath);
            self::assertCount(1, $readCoverage[1]->executableLines);
            self::assertSame(15, $readCoverage[1]->executableLines[0]->lineNumber);
            self::assertSame(3, $readCoverage[1]->executableLines[0]->hits);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testWriteWithNoFiles(): void
    {
        $writer = new CoberturaCoverageWriter();

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $writer->write([], $stream);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($output);
        self::assertStringContainsString('<coverage', $output);
        self::assertStringContainsString('lines-covered="0"', $output);
        self::assertStringContainsString('lines-valid="0"', $output);
    }

    public function testWriteCalculatesCorrectLineRate(): void
    {
        $writer = new CoberturaCoverageWriter();

        // 3 out of 5 lines covered = 60%
        $fileCoverage = new FileCoverage(
            '/path/to/Sample.php',
            [
                new ExecutableLine(10, 1),
                new ExecutableLine(20, 0),
                new ExecutableLine(30, 1),
                new ExecutableLine(40, 0),
                new ExecutableLine(50, 1),
            ],
        );

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $writer->write([$fileCoverage], $stream);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($output);
        self::assertStringContainsString('line-rate="0.60000000000000"', $output);
        self::assertStringContainsString('lines-covered="3"', $output);
        self::assertStringContainsString('lines-valid="5"', $output);
    }

}
