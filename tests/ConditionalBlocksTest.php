<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Hierarchy\ArrowFunctionBlock;
use ShipMonk\CoverageGuard\Hierarchy\CaseBlock;
use ShipMonk\CoverageGuard\Hierarchy\CatchBlock;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\ClosureBlock;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Hierarchy\DoWhileBlock;
use ShipMonk\CoverageGuard\Hierarchy\ElseBlock;
use ShipMonk\CoverageGuard\Hierarchy\ElseIfBlock;
use ShipMonk\CoverageGuard\Hierarchy\FinallyBlock;
use ShipMonk\CoverageGuard\Hierarchy\ForBlock;
use ShipMonk\CoverageGuard\Hierarchy\ForeachBlock;
use ShipMonk\CoverageGuard\Hierarchy\FunctionBlock;
use ShipMonk\CoverageGuard\Hierarchy\IfBlock;
use ShipMonk\CoverageGuard\Hierarchy\MatchBlock;
use ShipMonk\CoverageGuard\Hierarchy\SwitchBlock;
use ShipMonk\CoverageGuard\Hierarchy\TryBlock;
use ShipMonk\CoverageGuard\Hierarchy\WhileBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use function array_map;
use function explode;
use function file_get_contents;
use function str_contains;
use function trim;

final class ConditionalBlocksTest extends TestCase
{

    public function testAllConditionalBlocksAreDetected(): void
    {
        $filePath = __DIR__ . '/_fixtures/ConditionalBlocks.php';
        $fileContents = file_get_contents($filePath);
        self::assertNotFalse($fileContents);

        // Create a simple coverage map (all lines covered for this test)
        $linesCoverage = [];
        $linesChanged = [];
        $linesContents = [];
        $lines = explode("\n", $fileContents);

        foreach ($lines as $lineNumber => $lineContent) {
            $actualLineNumber = $lineNumber + 1; // Line numbers start at 1
            $linesContents[$actualLineNumber] = $lineContent;
            // Mark non-empty lines as executable and covered for this test
            if (trim($lineContent) !== '' && !str_contains($lineContent, '<?php') && !str_contains($lineContent, 'namespace') && !str_contains($lineContent, 'class ')) {
                $linesCoverage[$actualLineNumber] = 1;
            }
        }

        // Create a rule that collects all blocks
        $collectingRule = new class implements CoverageRule {

            /**
             * @var list<CodeBlock>
             */
            private array $blocks = [];

            public function inspect(
                CodeBlock $codeBlock,
                bool $patchMode,
            ): ?CoverageError
            {
                $this->blocks[] = $codeBlock;
                return null;
            }

            /**
             * @return list<CodeBlock>
             */
            public function getBlocks(): array
            {
                return $this->blocks;
            }

        };

        // Parse and analyze the file
        $parser = (new ParserFactory())->createForHostVersion();
        $stmts = $parser->parse($fileContents);
        self::assertNotNull($stmts);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $analyser = new CodeBlockAnalyser(
            patchMode: false,
            filePath: $filePath,
            linesChanged: $linesChanged,
            linesCoverage: $linesCoverage,
            linesContents: $linesContents,
            rules: [$collectingRule],
        );
        $traverser->addVisitor($analyser);
        $traverser->traverse($stmts);

        // Verify we collected the expected block types
        $blockTypes = array_map(
            static fn (CodeBlock $block) => $block::class,
            $collectingRule->getBlocks(),
        );

        // We expect to find these block types in ConditionalBlocks.php
        self::assertContains(ForeachBlock::class, $blockTypes, 'ForeachBlock should be detected');
        self::assertContains(ForBlock::class, $blockTypes, 'ForBlock should be detected');
        self::assertContains(WhileBlock::class, $blockTypes, 'WhileBlock should be detected');
        self::assertContains(DoWhileBlock::class, $blockTypes, 'DoWhileBlock should be detected');
        self::assertContains(IfBlock::class, $blockTypes, 'IfBlock should be detected');
        self::assertContains(ElseIfBlock::class, $blockTypes, 'ElseIfBlock should be detected');
        self::assertContains(ElseBlock::class, $blockTypes, 'ElseBlock should be detected');
        self::assertContains(SwitchBlock::class, $blockTypes, 'SwitchBlock should be detected');
        self::assertContains(CaseBlock::class, $blockTypes, 'CaseBlock should be detected');
        self::assertContains(TryBlock::class, $blockTypes, 'TryBlock should be detected');
        self::assertContains(CatchBlock::class, $blockTypes, 'CatchBlock should be detected');
        self::assertContains(FinallyBlock::class, $blockTypes, 'FinallyBlock should be detected');
        self::assertContains(FunctionBlock::class, $blockTypes, 'FunctionBlock should be detected');
        self::assertContains(ClosureBlock::class, $blockTypes, 'ClosureBlock should be detected');
        self::assertContains(ArrowFunctionBlock::class, $blockTypes, 'ArrowFunctionBlock should be detected');
        self::assertContains(MatchBlock::class, $blockTypes, 'MatchBlock should be detected');

        // Verify FunctionBlock has the correct function name
        $functionBlock = null;
        foreach ($collectingRule->getBlocks() as $block) {
            if ($block instanceof FunctionBlock) {
                $functionBlock = $block;
                break;
            }
        }
        self::assertNotNull($functionBlock, 'FunctionBlock should be found');
        self::assertSame('standaloneFunction', $functionBlock->getFunctionName());

        // Verify parent relationships: find blocks inside methods
        $ifBlockInsideMethod = null;
        $foreachBlockInsideMethod = null;

        foreach ($collectingRule->getBlocks() as $block) {
            if ($block instanceof IfBlock && $block->getParent() instanceof ClassMethodBlock) {
                $ifBlockInsideMethod = $block;
            }

            if ($block instanceof ForeachBlock && $block->getParent() instanceof ClassMethodBlock) {
                $foreachBlockInsideMethod = $block;
            }
        }

        self::assertNotNull($ifBlockInsideMethod, 'IfBlock inside method should be found');
        self::assertInstanceOf(ClassMethodBlock::class, $ifBlockInsideMethod->getParent());
        self::assertSame('testIf', $ifBlockInsideMethod->getParent()->getMethodName());

        self::assertNotNull($foreachBlockInsideMethod, 'ForeachBlock inside method should be found');
        self::assertInstanceOf(ClassMethodBlock::class, $foreachBlockInsideMethod->getParent());
        self::assertSame('testForeach', $foreachBlockInsideMethod->getParent()->getMethodName());

        // Verify standalone function has no parent
        self::assertNull($functionBlock->getParent(), 'Standalone function should have no parent');
    }

}
