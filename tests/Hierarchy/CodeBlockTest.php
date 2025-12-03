<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PHPUnit\Framework\TestCase;

final class CodeBlockTest extends TestCase
{

    public function testGetLines(): void
    {
        $lines = [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: false, excluded: false, covered: false, changed: false, contents: 'comment'),
        ];

        $block = $this->createBlock(lines: $lines);

        self::assertSame($lines, $block->getLines());
    }

    public function testGetStartLineNumber(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 5, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 6, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertSame(5, $block->getStartLineNumber());
    }

    public function testGetExecutableLinesCount(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: false, excluded: false, covered: false, changed: false, contents: 'comment'),
            new LineOfCode(number: 3, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 4, executable: false, excluded: false, covered: false, changed: false, contents: 'whitespace'),
        ]);

        self::assertSame(2, $block->getExecutableLinesCount());
    }

    public function testGetCoveredLinesCount(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 3, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 4, executable: false, excluded: false, covered: false, changed: false, contents: 'comment'),
        ]);

        self::assertSame(2, $block->getCoveredLinesCount());
    }

    public function testGetCoveragePercentageFullyCovered(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
        ]);

        self::assertSame(100, $block->getCoveragePercentage());
    }

    public function testGetCoveragePercentagePartiallyCovered(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 3, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
            new LineOfCode(number: 4, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertSame(50, $block->getCoveragePercentage());
    }

    public function testGetCoveragePercentageNoExecutableLines(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: false, excluded: false, covered: false, changed: false, contents: 'comment'),
            new LineOfCode(number: 2, executable: false, excluded: false, covered: false, changed: false, contents: 'whitespace'),
        ]);

        self::assertSame(0, $block->getCoveragePercentage());
    }

    public function testGetCoveragePercentageFullyUncovered(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertSame(0, $block->getCoveragePercentage());
    }

    public function testGetChangedLinesCount(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
            new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
            new LineOfCode(number: 3, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
            new LineOfCode(number: 4, executable: false, excluded: false, covered: false, changed: true, contents: 'comment'),
        ]);

        self::assertSame(2, $block->getChangedLinesCount());
    }

    public function testGetChangePercentageFullyChanged(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
            new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
        ]);

        self::assertSame(100, $block->getChangePercentage());
    }

    public function testGetChangePercentagePartiallyChanged(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
            new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
        ]);

        self::assertSame(50, $block->getChangePercentage());
    }

    public function testGetChangePercentageNoExecutableLines(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: false, excluded: false, covered: false, changed: true, contents: 'comment'),
        ]);

        self::assertSame(0, $block->getChangePercentage());
    }

    public function testGetNode(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
        ]);

        $node = $block->getNode();
        self::assertSame('testMethod', $node->name->toString());
    }

    public function testGetMethodName(): void
    {
        $block = $this->createBlock(lines: [
            new LineOfCode(number: 1, executable: true, excluded: false, covered: true, changed: false, contents: 'code'),
        ]);

        self::assertSame('testMethod', $block->getMethodName());
    }

    /**
     * @param non-empty-list<LineOfCode> $lines
     */
    private function createBlock(
        array $lines,
    ): ClassMethodBlock
    {
        $node = new ClassMethod(
            name: new Identifier('testMethod'),
        );

        return new ClassMethodBlock(
            node: $node,
            lines: $lines,
        );
    }

}
