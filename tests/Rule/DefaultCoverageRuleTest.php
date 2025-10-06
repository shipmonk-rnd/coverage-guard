<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\LineOfCode;

class DefaultCoverageRuleTest extends TestCase
{

    public function testReturnsErrorWhenMethodIsFullyUncoveredFullyChangedAndHasMoreThan5ExecutableLines(): void
    {
        $rule = new DefaultCoverageRule();

        $block = new ClassMethodBlock(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            lines: [
                new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 6, executable: true, covered: false, changed: true, contents: 'code'),
            ],
        );

        $error = $rule->inspect(codeBlock: $block, patchMode: true);

        self::assertNotNull($error);
        self::assertSame('Method <white>TestClass::testMethod</white> is fully changed and fully untested.', $error->getMessage());
    }

    public function testReturnsErrorWithDifferentMessageWhenNotInPatchMode(): void
    {
        $rule = new DefaultCoverageRule();

        $block = new ClassMethodBlock(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            lines: [
                new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 6, executable: true, covered: false, changed: true, contents: 'code'),
            ],
        );

        $error = $rule->inspect(codeBlock: $block, patchMode: false);

        self::assertNotNull($error);
        self::assertSame('Method <white>TestClass::testMethod</white> is fully untested.', $error->getMessage());
    }

    public function testReturnsNullWhenMethodHasExactly5ExecutableLines(): void
    {
        $rule = new DefaultCoverageRule();

        $block = new ClassMethodBlock(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            lines: [
                new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, covered: false, changed: true, contents: 'code'),
            ],
        );

        $error = $rule->inspect(codeBlock: $block, patchMode: true);

        self::assertNull($error);
    }

    public function testReturnsNullWhenMethodIsPartiallyCovered(): void
    {
        $rule = new DefaultCoverageRule();

        $block = new ClassMethodBlock(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            lines: [
                new LineOfCode(number: 1, executable: true, covered: true, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 6, executable: true, covered: false, changed: true, contents: 'code'),
            ],
        );

        $error = $rule->inspect(codeBlock: $block, patchMode: true);

        self::assertNull($error);
    }

    public function testReturnsNullWhenMethodIsNotFullyChanged(): void
    {
        $rule = new DefaultCoverageRule();

        $block = new ClassMethodBlock(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            lines: [
                new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 3, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 6, executable: true, covered: false, changed: true, contents: 'code'),
            ],
        );

        $error = $rule->inspect(codeBlock: $block, patchMode: true);

        self::assertNull($error);
    }

}
