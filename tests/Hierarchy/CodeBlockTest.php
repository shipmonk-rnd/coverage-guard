<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use LogicException;
use PHPUnit\Framework\TestCase;

final class CodeBlockTest extends TestCase
{

    public function testGetFilePath(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
        ]);

        self::assertSame('/path/to/file.php', $block->getFilePath());
    }

    public function testGetLines(): void
    {
        $lines = [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: false, covered: false, changed: false, contents: 'comment'),
        ];

        $block = $this->createBlock(filePath: '/path/to/file.php', lines: $lines);

        self::assertSame($lines, $block->getLines());
    }

    public function testGetStartLineNumber(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 5, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 6, executable: true, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertSame(5, $block->getStartLineNumber());
    }

    public function testGetExecutableLinesCount(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: false, covered: false, changed: false, contents: 'comment'),
            new LineOfCode(number: 3, executable: true, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 4, executable: false, covered: false, changed: false, contents: 'whitespace'),
        ]);

        self::assertSame(2, $block->getExecutableLinesCount());
    }

    public function testGetCoveredLinesCount(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 3, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 4, executable: false, covered: false, changed: false, contents: 'comment'),
        ]);

        self::assertSame(2, $block->getCoveredLinesCount());
    }

    public function testGetCoveragePercentageFullyCovered(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: true, changed: false, contents: 'code'),
        ]);

        self::assertSame(100.0, $block->getCoveragePercentage());
    }

    public function testGetCoveragePercentagePartiallyCovered(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 3, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 4, executable: true, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertSame(50.0, $block->getCoveragePercentage());
    }

    public function testGetCoveragePercentageNoExecutableLines(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: false, covered: false, changed: false, contents: 'comment'),
            new LineOfCode(number: 2, executable: false, covered: false, changed: false, contents: 'whitespace'),
        ]);

        self::assertSame(0.0, $block->getCoveragePercentage());
    }

    public function testGetCoveragePercentageFullyUncovered(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertSame(0.0, $block->getCoveragePercentage());
    }

    public function testIsFullyCoveredWhenAllExecutableLinesAreCovered(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: false, covered: false, changed: false, contents: 'comment'),
            new LineOfCode(number: 3, executable: true, covered: true, changed: false, contents: 'code'),
        ]);

        self::assertTrue($block->isFullyCovered());
    }

    public function testIsFullyCoveredWhenSomeLinesAreNotCovered(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertFalse($block->isFullyCovered());
    }

    public function testIsFullyCoveredWhenNoExecutableLines(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: false, covered: false, changed: false, contents: 'comment'),
        ]);

        self::assertTrue($block->isFullyCovered());
    }

    public function testIsFullyUncoveredWhenNoLinesAreCovered(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: false, covered: false, changed: false, contents: 'comment'),
            new LineOfCode(number: 3, executable: true, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertTrue($block->isFullyUncovered());
    }

    public function testIsFullyUncoveredWhenSomeLinesAreCovered(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertFalse($block->isFullyUncovered());
    }

    public function testIsFullyUncoveredWhenNoExecutableLines(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: false, covered: false, changed: false, contents: 'comment'),
        ]);

        self::assertTrue($block->isFullyUncovered());
    }

    public function testIsCoveredAtLeastByPercentWith100Percent(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: true, changed: false, contents: 'code'),
        ]);

        self::assertTrue($block->isCoveredAtLeastByPercent(100));
    }

    public function testIsCoveredAtLeastByPercentWith50Percent(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertTrue($block->isCoveredAtLeastByPercent(50));
        self::assertFalse($block->isCoveredAtLeastByPercent(51));
    }

    public function testIsCoveredAtLeastByPercentWithNoExecutableLines(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: false, covered: false, changed: false, contents: 'comment'),
        ]);

        self::assertFalse($block->isCoveredAtLeastByPercent(0));
    }

    public function testIsCoveredAtLeastByPercentThrowsExceptionForInvalidPercentage(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Minimal required percentage must be between 0 and 100');

        $block->isCoveredAtLeastByPercent(101);
    }

    public function testIsCoveredAtLeastByPercentThrowsExceptionForNegativePercentage(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Minimal required percentage must be between 0 and 100');

        $block->isCoveredAtLeastByPercent(-1);
    }

    public function testIsFullyChangedWhenAllExecutableLinesAreChanged(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
            new LineOfCode(number: 2, executable: false, covered: false, changed: false, contents: 'comment'),
            new LineOfCode(number: 3, executable: true, covered: false, changed: true, contents: 'code'),
        ]);

        self::assertTrue($block->isFullyChanged());
    }

    public function testIsFullyChangedWhenSomeLinesAreNotChanged(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertFalse($block->isFullyChanged());
    }

    public function testIsFullyChangedWhenNoExecutableLines(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: false, covered: false, changed: false, contents: 'comment'),
        ]);

        self::assertTrue($block->isFullyChanged());
    }

    public function testIsChangedWhenAtLeastOneExecutableLineIsChanged(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: true, contents: 'code'),
        ]);

        self::assertTrue($block->isChanged());
    }

    public function testIsChangedWhenNoLinesAreChanged(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertFalse($block->isChanged());
    }

    public function testIsChangedWhenOnlyNonExecutableLinesAreChanged(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: false, covered: false, changed: true, contents: 'comment'),
        ]);

        self::assertFalse($block->isChanged());
    }

    public function testIsChangedAtLeastByPercentWith100Percent(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: true, contents: 'code'),
        ]);

        self::assertTrue($block->isChangedAtLeastByPercent(100));
    }

    public function testIsChangedAtLeastByPercentWith50Percent(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
            new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertTrue($block->isChangedAtLeastByPercent(50));
        self::assertFalse($block->isChangedAtLeastByPercent(51));
    }

    public function testIsChangedAtLeastByPercentThrowsExceptionForInvalidPercentage(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Minimal required percentage must be between 0 and 100');

        $block->isChangedAtLeastByPercent(101);
    }

    public function testIsChangedAtLeastByPercentThrowsExceptionForNegativePercentage(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Minimal required percentage must be between 0 and 100');

        $block->isChangedAtLeastByPercent(-1);
    }

    public function testMethodReflectionCanBeRetrieved(): void
    {
        $block = $this->createBlock(filePath: '/path/to/file.php', lines: [
            new LineOfCode(number: 1, executable: true, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: false, covered: false, changed: false, contents: 'comment'),
        ]);

        self::assertSame('createBlock', $block->getMethodReflection()->getShortName());
    }

    public function testMethodReflectionThrowsExceptionForNonExistentMethod(): void
    {
        $block = new ClassMethodBlock(
            className: self::class,
            methodName: 'nonExistentMethod',
            filePath: '/path/to/file.php',
            lines: [
                new LineOfCode(number: 1, executable: true, covered: false, changed: false, contents: 'code'),
            ],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Could not get reflection for method');

        $block->getMethodReflection();
    }

    /**
     * @param non-empty-list<LineOfCode> $lines
     */
    private function createBlock(
        string $filePath,
        array $lines,
    ): ClassMethodBlock
    {
        return new ClassMethodBlock(
            className: self::class,
            methodName: 'createBlock',
            filePath: $filePath,
            lines: $lines,
        );
    }

}
