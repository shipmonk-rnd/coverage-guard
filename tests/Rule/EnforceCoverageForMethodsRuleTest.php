<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

use LogicException;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\LineOfCode;

final class EnforceCoverageForMethodsRuleTest extends TestCase
{

    public function testReturnsErrorWhenMethodHasNoRequiredCoverage(): void
    {
        $rule = new EnforceCoverageForMethodsRule(requiredCoveragePercentage: 1, minExecutableLines: 5);

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
        self::assertSame('Method <white>TestClass::testMethod</white> has no coverage, expected at least 1%.', $error->getMessage());
    }

    public function testReturnsErrorWhenMethodHasInsufficientCoverage(): void
    {
        $rule = new EnforceCoverageForMethodsRule(requiredCoveragePercentage: 50, minExecutableLines: 5);

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

        $error = $rule->inspect(codeBlock: $block, patchMode: false);

        self::assertNotNull($error);
        self::assertSame('Method <white>TestClass::testMethod</white> has only 16% coverage, expected at least 50%.', $error->getMessage());
    }

    public function testReturnsNullWhenMethodHasLessThanMinExecutableLinesDefault(): void
    {
        $rule = new EnforceCoverageForMethodsRule(minExecutableLines: 5);

        $block = new ClassMethodBlock(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            lines: [
                new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, covered: false, changed: true, contents: 'code'),
            ],
        );

        $error = $rule->inspect(codeBlock: $block, patchMode: false);

        self::assertNull($error);
    }

    public function testReturnsNullWhenMethodHasLessThanMinExecutableLines(): void
    {
        $rule = new EnforceCoverageForMethodsRule(minExecutableLines: 10);

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

        $error = $rule->inspect(codeBlock: $block, patchMode: false);

        self::assertNull($error);
    }

    public function testReturnsNullWhenMethodHasSufficientCoverage(): void
    {
        $rule = new EnforceCoverageForMethodsRule(requiredCoveragePercentage: 50, minExecutableLines: 5);

        $block = new ClassMethodBlock(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            lines: [
                new LineOfCode(number: 1, executable: true, covered: true, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, covered: true, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, covered: true, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 6, executable: true, covered: false, changed: true, contents: 'code'),
            ],
        );

        $error = $rule->inspect(codeBlock: $block, patchMode: false);

        self::assertNull($error);
    }

    public function testReturnsNullWhenMethodIsNotChangedEnough(): void
    {
        $rule = new EnforceCoverageForMethodsRule(
            requiredCoveragePercentage: 50,
            minExecutableLines: 5,
            minMethodChangePercentage: 50,
        );

        $block = new ClassMethodBlock(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            lines: [
                new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 3, executable: true, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 4, executable: true, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 5, executable: true, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 6, executable: true, covered: false, changed: false, contents: 'code'),
            ],
        );

        $error = $rule->inspect(codeBlock: $block, patchMode: true);

        self::assertNull($error);
    }

    public function testReturnsErrorWhenMethodIsChangedEnough(): void
    {
        $rule = new EnforceCoverageForMethodsRule(
            requiredCoveragePercentage: 50,
            minExecutableLines: 5,
            minMethodChangePercentage: 50,
        );

        $block = new ClassMethodBlock(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            lines: [
                new LineOfCode(number: 1, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 6, executable: true, covered: false, changed: false, contents: 'code'),
            ],
        );

        $error = $rule->inspect(codeBlock: $block, patchMode: true);

        self::assertNotNull($error);
        self::assertSame('Method <white>TestClass::testMethod</white> has no coverage, expected at least 50%.', $error->getMessage());
    }

    public function testThrowsExceptionWhenRequiredCoveragePercentageIsTooLow(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Minimal required coverage percentage must be between 0 and 100');

        new EnforceCoverageForMethodsRule(requiredCoveragePercentage: -1);
    }

    public function testThrowsExceptionWhenRequiredCoveragePercentageIsTooHigh(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Minimal required coverage percentage must be between 0 and 100');

        new EnforceCoverageForMethodsRule(requiredCoveragePercentage: 101);
    }

    public function testThrowsExceptionWhenMinMethodChangePercentageIsTooLow(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Minimal required method change percentage must be between 0 and 100');

        new EnforceCoverageForMethodsRule(minMethodChangePercentage: -1);
    }

    public function testThrowsExceptionWhenMinMethodChangePercentageIsTooHigh(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Minimal required method change percentage must be between 0 and 100');

        new EnforceCoverageForMethodsRule(minMethodChangePercentage: 101);
    }

    public function testThrowsExceptionWhenMinExecutableLinesIsNegative(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Minimal required executable lines must be at least 0');

        new EnforceCoverageForMethodsRule(minExecutableLines: -1);
    }

}
