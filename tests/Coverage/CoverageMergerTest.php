<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Coverage;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function array_map;

final class CoverageMergerTest extends TestCase
{

    public function testMergeEmptyArray(): void
    {
        $result = (new CoverageMerger())->merge([]);

        self::assertSame([], $result);
    }

    public function testMergeSingleCoverageSet(): void
    {
        $fileCoverage = new FileCoverage(
            '/path/to/file.php',
            [
                new ExecutableLine(10, 5),
                new ExecutableLine(20, 0),
            ],
            100,
        );

        $result = (new CoverageMerger())->merge([[$fileCoverage]]);

        self::assertCount(1, $result);
        self::assertSame('/path/to/file.php', $result[0]->filePath);
        self::assertCount(2, $result[0]->executableLines);
        self::assertSame(10, $result[0]->executableLines[0]->lineNumber);
        self::assertSame(5, $result[0]->executableLines[0]->hits);
        self::assertSame(20, $result[0]->executableLines[1]->lineNumber);
        self::assertSame(0, $result[0]->executableLines[1]->hits);
        self::assertSame(100, $result[0]->expectedLinesCount);
    }

    public function testMergeMultipleCoverageSetsWithSameFile(): void
    {
        $coverage1 = new FileCoverage(
            '/path/to/file.php',
            [
                new ExecutableLine(10, 5),
                new ExecutableLine(20, 0),
            ],
        );

        $coverage2 = new FileCoverage(
            '/path/to/file.php',
            [
                new ExecutableLine(10, 3),
                new ExecutableLine(30, 2),
            ],
        );

        $result = (new CoverageMerger())->merge([[$coverage1], [$coverage2]]);

        self::assertCount(1, $result);
        self::assertSame('/path/to/file.php', $result[0]->filePath);
        self::assertCount(3, $result[0]->executableLines);

        // Line 10: 5 + 3 = 8 hits
        self::assertSame(10, $result[0]->executableLines[0]->lineNumber);
        self::assertSame(8, $result[0]->executableLines[0]->hits);

        // Line 20: 0 + 0 = 0 hits
        self::assertSame(20, $result[0]->executableLines[1]->lineNumber);
        self::assertSame(0, $result[0]->executableLines[1]->hits);

        // Line 30: 0 + 2 = 2 hits
        self::assertSame(30, $result[0]->executableLines[2]->lineNumber);
        self::assertSame(2, $result[0]->executableLines[2]->hits);
    }

    public function testMergeMultipleFilesFromDifferentSets(): void
    {
        $coverage1a = new FileCoverage('/path/to/file1.php', [new ExecutableLine(10, 5)]);
        $coverage1b = new FileCoverage('/path/to/file2.php', [new ExecutableLine(20, 3)]);

        $coverage2a = new FileCoverage('/path/to/file1.php', [new ExecutableLine(10, 2)]);
        $coverage2b = new FileCoverage('/path/to/file3.php', [new ExecutableLine(30, 1)]);

        $result = (new CoverageMerger())->merge([
            [$coverage1a, $coverage1b],
            [$coverage2a, $coverage2b],
        ]);

        self::assertCount(3, $result);

        // Should have file1, file2, file3
        $filePaths = array_map(static fn (FileCoverage $fc) => $fc->filePath, $result);
        self::assertContains('/path/to/file1.php', $filePaths);
        self::assertContains('/path/to/file2.php', $filePaths);
        self::assertContains('/path/to/file3.php', $filePaths);
    }

    public function testMergeChecksExpectedLinesCount(): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Inconsistent expected lines count');

        $coverage1 = new FileCoverage('/path/to/file.php', [new ExecutableLine(10, 5)], 100);
        $coverage2 = new FileCoverage('/path/to/file.php', [new ExecutableLine(20, 3)], 120);

        (new CoverageMerger())->merge([[$coverage1], [$coverage2]]);
    }

    public function testMergeWithNullExpectedLinesCount(): void
    {
        $coverage1 = new FileCoverage('/path/to/file.php', [new ExecutableLine(10, 5)], null);
        $coverage2 = new FileCoverage('/path/to/file.php', [new ExecutableLine(20, 3)], null);

        $result = (new CoverageMerger())->merge([[$coverage1], [$coverage2]]);

        self::assertCount(1, $result);
        self::assertNull($result[0]->expectedLinesCount);
    }

}
