<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Ast\FileTraverser;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\InspectionContext;
use function file;
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

        $capturedContexts = $rule->capturedContexts;

        self::assertCount(2, $capturedContexts);

        $methodsByClass = [];
        foreach ($capturedContexts as $context) {
            $className = $context->getClassName();
            $methodName = $context->getMethodName();

            self::assertNotNull($className);
            self::assertNotNull($methodName);

            $methodsByClass[$className][] = $methodName;

            self::assertSame($filePath, $context->getFilePath());
            self::assertFalse($context->isPatchMode());
        }

        self::assertCount(1, $methodsByClass, 'Methods of anonymous class should not emitted');
        self::assertArrayHasKey('ClassWithAnonymousClass', $methodsByClass);
        self::assertSame(['methodWithAnonymousClass', 'regularMethod'], $methodsByClass['ClassWithAnonymousClass']);
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
        $analyser = new CodeBlockAnalyser(
            patchMode: true,
            filePath: $filePath,
            linesChanged: [], // No changed lines
            linesCoverage: [9 => 1, 13 => 1, 14 => 1, 17 => 1], // Some coverage
            linesContents: $this->getFileLines($filePath),
            rules: [$rule],
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
        $analyser = new CodeBlockAnalyser(
            patchMode: true,
            filePath: $filePath,
            linesChanged: [9 => 9], // Line 9 is in simpleMethod
            linesCoverage: [9 => 1, 13 => 1, 14 => 1, 17 => 1],
            linesContents: $this->getFileLines($filePath),
            rules: [$rule],
        );

        $this->traverseFile($filePath, $analyser);

        $capturedContexts = $rule->capturedContexts;

        // Only the first method should be analyzed
        self::assertCount(1, $capturedContexts);
        self::assertSame('simpleMethod', $capturedContexts[0]->getMethodName());
    }

    /**
     * @param list<CoverageRule> $rules
     */
    private function createAnalyser(
        string $filePath,
        array $rules,
        bool $patchMode = false,
    ): CodeBlockAnalyser
    {
        return new CodeBlockAnalyser(
            patchMode: $patchMode,
            filePath: $filePath,
            linesChanged: $patchMode ? [9 => 9, 13 => 13, 14 => 14] : [],
            linesCoverage: [9 => 1, 13 => 1, 14 => 1, 17 => 1],
            linesContents: $this->getFileLines($filePath),
            rules: $rules,
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

}
