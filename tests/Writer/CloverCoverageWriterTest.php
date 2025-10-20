<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Coverage\ExecutableLine;
use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Extractor\CloverCoverageExtractor;
use ShipMonk\CoverageGuard\XmlLoader;
use function fclose;
use function fopen;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

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

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $writer->write([$fileCoverage], $stream);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($output);
        self::assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $output);
        self::assertStringContainsString('<coverage', $output);
        self::assertStringContainsString('<project', $output);
        self::assertStringContainsString('<file name="/path/to/Sample.php">', $output);
        self::assertStringContainsString('<line num="10" type="stmt" count="5"/>', $output);
        self::assertStringContainsString('<line num="20" type="stmt" count="0"/>', $output);
        self::assertStringContainsString('<line num="30" type="stmt" count="3"/>', $output);
        self::assertStringContainsString('<metrics loc="50"/>', $output);
    }

    public function testWriteCanBeReadBackByExtractor(): void
    {
        $writer = new CloverCoverageWriter();
        $extractor = new CloverCoverageExtractor(new XmlLoader());

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
        $tempFile = tempnam(sys_get_temp_dir(), 'clover-test-');
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
        $writer = new CloverCoverageWriter();

        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $writer->write([], $stream);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($output);
        self::assertStringContainsString('<coverage', $output);
        self::assertStringContainsString('<project', $output);
        self::assertStringContainsString('</coverage>', $output);
    }

}
