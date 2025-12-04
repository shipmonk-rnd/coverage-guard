<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Ast\FileTraverser;
use ShipMonk\CoverageGuard\Excluder\ExecutableLineExcluder;
use ShipMonk\CoverageGuard\Excluder\IgnoreThrowNewExceptionLineExcluder;
use ShipMonk\CoverageGuard\Excluder\NormalizeMultilineCallsLineExcluder;
use ShipMonk\CoverageGuard\Fixtures\MyLogicException;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\InspectionContext;
use function array_keys;
use function file;
use function sort;
use function str_contains;
use const FILE_IGNORE_NEW_LINES;

final class CodeBlockAnalyserTest extends TestCase
{

    public function testAnalysesSimpleClass(): void
    {
        $filePath = __DIR__ . '/_fixtures/CodeBlockAnalyser/SimpleClass.php';

        $rule = $this->createContextCapturingRule();
        $analyser = $this->createAnalyser($filePath, [$rule]);

        $this->traverseFile($filePath, $analyser);

        $capturedContexts = $rule->capturedContexts;

        self::assertCount(2, $capturedContexts);

        // First method: simpleMethod
        self::assertSame('SimpleClass', $capturedContexts[0]->getClassName());
        self::assertSame('simpleMethod', $capturedContexts[0]->getMethodName());
        self::assertSame($filePath, $capturedContexts[0]->getFilePath());
        self::assertFalse($capturedContexts[0]->isPatchMode());

        // Second method: anotherMethod
        self::assertSame('SimpleClass', $capturedContexts[1]->getClassName());
        self::assertSame('anotherMethod', $capturedContexts[1]->getMethodName());
        self::assertSame($filePath, $capturedContexts[1]->getFilePath());
        self::assertFalse($capturedContexts[1]->isPatchMode());
    }

    public function testAnalysesClassWithAnonymousClass(): void
    {
        $filePath = __DIR__ . '/_fixtures/CodeBlockAnalyser/ClassWithAnonymousClass.php';

        $rule = $this->createContextCapturingRule();
        $analyser = $this->createAnalyser($filePath, [$rule]);

        $this->traverseFile($filePath, $analyser);

        /** @var list<InspectionContext> $capturedContexts */
        $capturedContexts = $rule->capturedContexts;

        self::assertCount(3, $capturedContexts);

        $methodsByClass = [];
        foreach ($capturedContexts as $context) {
            $className = $context->getClassName();
            $methodName = $context->getMethodName();

            $classNameKey = $className ?? '';
            self::assertNotNull($methodName);

            $methodsByClass[$classNameKey][] = $methodName;

            self::assertSame($filePath, $context->getFilePath());
            self::assertFalse($context->isPatchMode());
        }

        self::assertArrayHasKey('ClassWithAnonymousClass', $methodsByClass);
        self::assertSame(['methodWithAnonymousClass', 'regularMethod'], $methodsByClass['ClassWithAnonymousClass']);

        self::assertArrayHasKey('', $methodsByClass);
        self::assertSame(['methodOfAnonymousClass'], $methodsByClass['']);

        self::assertCount(2, $methodsByClass);
    }

    public function testAnalysesTrait(): void
    {
        $filePath = __DIR__ . '/_fixtures/CodeBlockAnalyser/TraitWithMethod.php';

        $rule = $this->createContextCapturingRule();
        $analyser = $this->createAnalyser($filePath, [$rule]);

        $this->traverseFile($filePath, $analyser);

        $capturedContexts = $rule->capturedContexts;

        self::assertCount(1, $capturedContexts);

        // Trait method
        self::assertSame('TraitWithMethod', $capturedContexts[0]->getClassName());
        self::assertSame('traitMethod', $capturedContexts[0]->getMethodName());
        self::assertSame($filePath, $capturedContexts[0]->getFilePath());
        self::assertFalse($capturedContexts[0]->isPatchMode());
    }

    public function testPatchModeIsPropagatedToContext(): void
    {
        $filePath = __DIR__ . '/_fixtures/CodeBlockAnalyser/SimpleClass.php';

        $rule = $this->createContextCapturingRule();
        $analyser = $this->createAnalyser($filePath, [$rule], patchMode: true);

        $this->traverseFile($filePath, $analyser);

        $capturedContexts = $rule->capturedContexts;

        self::assertCount(2, $capturedContexts);

        foreach ($capturedContexts as $context) {
            self::assertTrue($context->isPatchMode(), 'Context should have patchMode = true');
        }
    }

    public function testSkipsUnchangedMethodsInPatchMode(): void
    {
        $filePath = __DIR__ . '/_fixtures/CodeBlockAnalyser/SimpleClass.php';
        $rule = $this->createContextCapturingRule();

        // In patch mode with no changed lines, methods should be skipped
        $analyser = $this->createAnalyser(
            filePath: $filePath,
            rules: [$rule],
            patchMode: true,
            linesChanged: [], // No changed lines
        );

        $this->traverseFile($filePath, $analyser);

        // No methods should be analyzed because none have changed lines
        self::assertCount(0, $rule->capturedContexts);
    }

    public function testAnalyzesOnlyChangedMethodsInPatchMode(): void
    {
        $filePath = __DIR__ . '/_fixtures/CodeBlockAnalyser/SimpleClass.php';
        $rule = $this->createContextCapturingRule();

        // Mark only lines from the first method as changed
        $analyser = $this->createAnalyser(
            filePath: $filePath,
            rules: [$rule],
            patchMode: true,
            linesChanged: [9 => 9], // Line 9 is in simpleMethod
        );

        $this->traverseFile($filePath, $analyser);

        $capturedContexts = $rule->capturedContexts;

        // Only the first method should be analyzed
        self::assertCount(1, $capturedContexts);
        self::assertSame('simpleMethod', $capturedContexts[0]->getMethodName());
    }

    public function testIgnoreThrowNewExceptionLineExcluder(): void
    {
        $filePath = __DIR__ . '/_fixtures/CodeBlockAnalyser/ClassWithThrowStatements.php';
        $excluder = new IgnoreThrowNewExceptionLineExcluder([MyLogicException::class]);

        $this->assertExcludedLinesMatchFixtureComments($filePath, [$excluder]);
    }

    public function testNormalizeMultilineCallsLineExcluder(): void
    {
        $filePath = __DIR__ . '/_fixtures/CodeBlockAnalyser/ClassWithMultilineCalls.php';
        $excluder = new NormalizeMultilineCallsLineExcluder();

        $this->assertExcludedLinesMatchFixtureComments($filePath, [$excluder]);
    }

    /**
     * @param list<CoverageRule> $rules
     * @param list<ExecutableLineExcluder> $excluders
     * @param array<int, int>|null $linesCoverage
     * @param array<int, int>|null $linesChanged
     */
    private function createAnalyser(
        string $filePath,
        array $rules = [],
        bool $patchMode = false,
        array $excluders = [],
        ?array $linesCoverage = null,
        ?array $linesChanged = null,
    ): CodeBlockAnalyser
    {
        return new CodeBlockAnalyser(
            patchMode: $patchMode,
            filePath: $filePath,
            linesChanged: $linesChanged ?? ($patchMode ? [9 => 9, 13 => 13, 14 => 14] : []),
            linesCoverage: $linesCoverage ?? [9 => 1, 13 => 1, 14 => 1, 17 => 1],
            linesContents: $this->getFileLines($filePath),
            rules: $rules,
            excluders: $excluders,
        );
    }

    /**
     * @return array<int, string>
     */
    private function getFileLines(string $filePath): array
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new LogicException("Failed to read file: {$filePath}");
        }

        $result = [];
        foreach ($lines as $index => $line) {
            $result[$index + 1] = $line; // Line numbers start at 1
        }

        return $result;
    }

    private function traverseFile(
        string $filePath,
        CodeBlockAnalyser $analyser,
    ): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $traverser = new FileTraverser($parser);
        $traverser->traverse($filePath, $this->getFileLines($filePath), $analyser);
    }

    /**
     * @return CoverageRule&object{capturedContexts: list<InspectionContext>}
     */
    private function createContextCapturingRule(): CoverageRule
    {
        return new class implements CoverageRule {

            /**
             * @var list<InspectionContext>
             */
            public array $capturedContexts = []; // @phpstan-ignore shipmonk.publicPropertyNotReadonly

            public function inspect(
                CodeBlock $codeBlock,
                InspectionContext $context,
            ): ?CoverageError
            {
                if ($codeBlock instanceof ClassMethodBlock) {
                    $this->capturedContexts[] = $context;
                }
                return null;
            }

        };
    }

    /**
     * @return CoverageRule&object{capturedBlocks: list<ClassMethodBlock>}
     */
    private function createLineCapturingRule(): CoverageRule
    {
        return new class implements CoverageRule {

            /**
             * @var list<ClassMethodBlock>
             */
            public array $capturedBlocks = []; // @phpstan-ignore shipmonk.publicPropertyNotReadonly

            public function inspect(
                CodeBlock $codeBlock,
                InspectionContext $context,
            ): ?CoverageError
            {
                if ($codeBlock instanceof ClassMethodBlock) {
                    $this->capturedBlocks[] = $codeBlock;
                }
                return null;
            }

        };
    }

    /**
     * Helper method to test excluders by comparing excluded lines with "// excluded" comments in fixture
     *
     * @param list<ExecutableLineExcluder> $excluders
     */
    private function assertExcludedLinesMatchFixtureComments(
        string $filePath,
        array $excluders,
    ): void
    {
        $rule = $this->createLineCapturingRule();
        $linesContents = $this->getFileLines($filePath);

        // Find all lines with "// excluded" comment
        $expectedExcludedLines = [];
        foreach ($linesContents as $lineNumber => $lineContent) {
            if (str_contains($lineContent, '// excluded')) {
                $expectedExcludedLines[] = $lineNumber;
            }
        }

        $linesCoverage = [];
        foreach (array_keys($linesContents) as $lineNumber) {
            $linesCoverage[$lineNumber] = 1;
        }

        $analyser = $this->createAnalyser(
            filePath: $filePath,
            rules: [$rule],
            excluders: $excluders,
            linesCoverage: $linesCoverage,
        );

        $this->traverseFile($filePath, $analyser);

        // Collect all excluded lines from all blocks
        $actualExcludedLines = [];
        foreach ($rule->capturedBlocks as $block) {
            foreach ($block->getLines() as $line) {
                if ($line->isExcluded()) {
                    $actualExcludedLines[] = $line->getNumber();
                }
            }
        }

        sort($expectedExcludedLines);
        sort($actualExcludedLines);

        self::assertSame(
            $expectedExcludedLines,
            $actualExcludedLines,
            'Excluded lines should match "// excluded" comments in fixture file',
        );
    }

}
