<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

use LogicException;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\LineOfCode;

final class EnforceCoverageForMethodsRuleTest extends TestCase
{

    public function testReturnsErrorWhenMethodHasNoRequiredCoverage(): void
    {
        $rule = new EnforceCoverageForMethodsRule(requiredCoveragePercentage: 1, minExecutableLines: 5);

        $block = $this->createBlock([
                new LineOfCode(number: 1, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 6, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
            ]);

        $error = $rule->inspect(codeBlock: $block, context: $this->createContext(className: self::class, methodName: 'createBlock'));

        self::assertNotNull($error);
        self::assertSame('Method <white>' . self::class . '::testMethod</white> has no coverage, expected at least 1%.', $error->getMessage());
    }

    public function testReturnsErrorWhenMethodHasInsufficientCoverage(): void
    {
        $rule = new EnforceCoverageForMethodsRule(requiredCoveragePercentage: 50, minExecutableLines: 5);

        $block = $this->createBlock([
                new LineOfCode(number: 1, executable: true, excluded: false, covered: true, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 6, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
            ]);

        $error = $rule->inspect(codeBlock: $block, context: $this->createContext(className: self::class, methodName: 'createBlock'));

        self::assertNotNull($error);
        self::assertSame('Method <white>' . self::class . '::testMethod</white> has only 17% coverage, expected at least 50%.', $error->getMessage());
    }

    public function testReturnsNullWhenMethodHasLessThanMinExecutableLinesDefault(): void
    {
        $rule = new EnforceCoverageForMethodsRule(minExecutableLines: 5);

        $block = $this->createBlock([
                new LineOfCode(number: 1, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
            ]);

        $error = $rule->inspect(codeBlock: $block, context: $this->createContext());

        self::assertNull($error);
    }

    public function testReturnsNullWhenMethodHasLessThanMinExecutableLines(): void
    {
        $rule = new EnforceCoverageForMethodsRule(minExecutableLines: 10);

        $block = $this->createBlock([
                new LineOfCode(number: 1, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
            ]);

        $error = $rule->inspect(codeBlock: $block, context: $this->createContext());

        self::assertNull($error);
    }

    public function testReturnsNullWhenMethodHasSufficientCoverage(): void
    {
        $rule = new EnforceCoverageForMethodsRule(requiredCoveragePercentage: 50, minExecutableLines: 5);

        $block = $this->createBlock([
                new LineOfCode(number: 1, executable: true, excluded: false, covered: true, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, excluded: false, covered: true, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, excluded: false, covered: true, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 6, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
            ]);

        $error = $rule->inspect(codeBlock: $block, context: $this->createContext());

        self::assertNull($error);
    }

    public function testReturnsNullWhenMethodIsNotChangedEnough(): void
    {
        $rule = new EnforceCoverageForMethodsRule(
            requiredCoveragePercentage: 50,
            minExecutableLines: 5,
            minMethodChangePercentage: 50,
        );

        $block = $this->createBlock([
                new LineOfCode(number: 1, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 3, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 4, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 5, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 6, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
            ]);

        $error = $rule->inspect(codeBlock: $block, context: $this->createContext(patchMode: true));

        self::assertNull($error);
    }

    public function testReturnsErrorWhenMethodIsChangedEnough(): void
    {
        $rule = new EnforceCoverageForMethodsRule(
            requiredCoveragePercentage: 50,
            minExecutableLines: 5,
            minMethodChangePercentage: 50,
        );

        $block = $this->createBlock([
                new LineOfCode(number: 1, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 2, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 3, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 4, executable: true, excluded: false, covered: false, changed: true, contents: 'code'),
                new LineOfCode(number: 5, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
                new LineOfCode(number: 6, executable: true, excluded: false, covered: false, changed: false, contents: 'code'),
            ]);

        $error = $rule->inspect(codeBlock: $block, context: $this->createContext(className: self::class, methodName: 'createBlock', patchMode: true));

        self::assertNotNull($error);
        self::assertSame('Method <white>' . self::class . '::testMethod</white> has no coverage, expected at least 50%.', $error->getMessage());
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

    private function createContext(
        ?string $className = null,
        ?string $methodName = null,
        string $filePath = '/path/to/file.php',
        bool $patchMode = false,
    ): InspectionContext
    {
        return new InspectionContext(
            className: $className,
            methodName: $methodName,
            filePath: $filePath,
            patchMode: $patchMode,
        );
    }

    /**
     * @param non-empty-list<LineOfCode> $lines
     */
    private function createBlock(
        array $lines,
        string $methodName = 'testMethod',
    ): ClassMethodBlock
    {
        $node = new ClassMethod(
            name: new Identifier($methodName),
        );

        return new ClassMethodBlock(
            node: $node,
            lines: $lines,
        );
    }

}
