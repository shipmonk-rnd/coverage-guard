<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\MatchArm;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Finally_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Ast\FileTraverser;
use ShipMonk\CoverageGuard\Excluder\ExcluderVisitor;
use ShipMonk\CoverageGuard\Excluder\ExclusionContext;
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
use ShipMonk\CoverageGuard\Hierarchy\MatchArmBlock;
use ShipMonk\CoverageGuard\Hierarchy\MatchBlock;
use ShipMonk\CoverageGuard\Hierarchy\PropertyHookBlock;
use ShipMonk\CoverageGuard\Hierarchy\SwitchBlock;
use ShipMonk\CoverageGuard\Hierarchy\TryBlock;
use ShipMonk\CoverageGuard\Hierarchy\WhileBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\InspectionContext;
use function array_keys;
use function array_map;
use function class_exists;
use function count;
use function file;
use const FILE_IGNORE_NEW_LINES;

final class ConditionalBlocksTest extends TestCase
{

    public function testAllConditionalBlocksAreDetected(): void
    {
        $blocks = $this->collectBlocks(__DIR__ . '/_fixtures/ConditionalBlocks.php');

        $blockTypes = array_map(
            static fn (CodeBlock $block) => $block::class,
            $blocks,
        );

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
        self::assertContains(MatchArmBlock::class, $blockTypes, 'MatchArmBlock should be detected');

        $functionBlock = null;
        $ifBlockInsideMethod = null;
        $ifBlockInsideElse = null;
        $elseIfBlock = null;
        $foreachBlockInsideMethod = null;
        $caseBlock = null;
        $matchArmBlock = null;
        $tryBlock = null;
        $catchBlock = null;
        $finallyBlock = null;

        foreach ($blocks as $block) {
            if ($block instanceof FunctionBlock) {
                $functionBlock ??= $block;
            }

            if ($block instanceof ElseIfBlock) {
                $elseIfBlock ??= $block;
            }

            if ($block instanceof TryBlock) {
                $tryBlock ??= $block;
            }

            if ($block instanceof IfBlock && $block->getParent() instanceof ClassMethodBlock) {
                $ifBlockInsideMethod = $block;
            }

            if ($block instanceof IfBlock && $block->getParent() instanceof ElseBlock) {
                $ifBlockInsideElse = $block;
            }

            if ($block instanceof ForeachBlock && $block->getParent() instanceof ClassMethodBlock) {
                $foreachBlockInsideMethod = $block;
            }

            if ($block instanceof CaseBlock) {
                $caseBlock ??= $block;
            }

            if ($block instanceof MatchArmBlock) {
                $matchArmBlock ??= $block;
            }

            if ($block instanceof CatchBlock) {
                $catchBlock ??= $block;
            }

            if ($block instanceof FinallyBlock) {
                $finallyBlock ??= $block;
            }
        }

        self::assertNotNull($functionBlock, 'FunctionBlock should be found');
        self::assertSame('standaloneFunction', $functionBlock->getFunctionName());
        self::assertSame('standaloneFunction', $functionBlock->getNode()->name->toString());
        self::assertNull($functionBlock->getParent(), 'Standalone function should have no parent');

        self::assertNotNull($ifBlockInsideMethod, 'IfBlock inside method should be found');
        self::assertInstanceOf(ClassMethodBlock::class, $ifBlockInsideMethod->getParent());
        self::assertSame('testIf', $ifBlockInsideMethod->getParent()->getMethodName());

        self::assertNotNull($foreachBlockInsideMethod, 'ForeachBlock inside method should be found');
        self::assertInstanceOf(ClassMethodBlock::class, $foreachBlockInsideMethod->getParent());
        self::assertSame('testForeach', $foreachBlockInsideMethod->getParent()->getMethodName());

        // parent chain mimics the AST: if inside else -> else -> if -> method
        self::assertNotNull($ifBlockInsideElse, 'IfBlock inside else should be found');
        $elseBlock = $ifBlockInsideElse->getParent();
        self::assertInstanceOf(ElseBlock::class, $elseBlock);
        self::assertInstanceOf(IfBlock::class, $elseBlock->getParent());
        self::assertInstanceOf(ClassMethodBlock::class, $elseBlock->getParent()->getParent());
        self::assertSame('testIf', $elseBlock->getParent()->getParent()->getMethodName());

        self::assertNotNull($caseBlock, 'CaseBlock should be found');
        self::assertInstanceOf(SwitchBlock::class, $caseBlock->getParent());

        self::assertNotNull($matchArmBlock, 'MatchArmBlock should be found');
        self::assertInstanceOf(MatchBlock::class, $matchArmBlock->getParent());

        self::assertNotNull($catchBlock, 'CatchBlock should be found');
        self::assertInstanceOf(TryBlock::class, $catchBlock->getParent());

        self::assertNotNull($finallyBlock, 'FinallyBlock should be found');
        self::assertInstanceOf(TryBlock::class, $finallyBlock->getParent());

        // every block exposes the AST node it was created from
        $expectedNodeTypes = [
            ArrowFunctionBlock::class => ArrowFunction::class,
            CaseBlock::class => Case_::class,
            CatchBlock::class => Catch_::class,
            ClassMethodBlock::class => ClassMethod::class,
            ClosureBlock::class => Closure::class,
            DoWhileBlock::class => Do_::class,
            ElseBlock::class => Else_::class,
            ElseIfBlock::class => ElseIf_::class,
            FinallyBlock::class => Finally_::class,
            ForBlock::class => For_::class,
            ForeachBlock::class => Foreach_::class,
            FunctionBlock::class => Function_::class,
            IfBlock::class => If_::class,
            MatchArmBlock::class => MatchArm::class,
            MatchBlock::class => Match_::class,
            SwitchBlock::class => Switch_::class,
            TryBlock::class => TryCatch::class,
            WhileBlock::class => While_::class,
        ];

        foreach ($blocks as $block) {
            self::assertArrayHasKey($block::class, $expectedNodeTypes);
            self::assertInstanceOf($expectedNodeTypes[$block::class], $block->getNode());
        }

        // sibling branch blocks never share lines
        self::assertNotNull($elseIfBlock);
        self::assertNotNull($tryBlock);
        self::assertLessThan($this->getFirstLine($elseIfBlock), $this->getLastLine($ifBlockInsideMethod), 'IfBlock must end before its elseif starts');
        self::assertLessThan($this->getFirstLine($elseBlock), $this->getLastLine($elseIfBlock), 'ElseIfBlock must end before its else starts');
        self::assertLessThan($this->getFirstLine($catchBlock), $this->getLastLine($tryBlock), 'TryBlock must end before its catch starts');
        self::assertLessThan($this->getFirstLine($finallyBlock), $this->getLastLine($catchBlock), 'CatchBlock must end before its finally starts');
    }

    private function getFirstLine(CodeBlock $block): int
    {
        return $block->getLines()[0]->getNumber();
    }

    private function getLastLine(CodeBlock $block): int
    {
        $lines = $block->getLines();
        return $lines[count($lines) - 1]->getNumber();
    }

    public function testPropertyHookBlocksAreDetected(): void
    {
        if (!class_exists(PropertyHook::class)) {
            self::markTestSkipped('Installed nikic/php-parser does not support property hooks');
        }

        $blocks = $this->collectBlocks(__DIR__ . '/_fixtures/PropertyHooks.php');

        $hookBlocks = [];
        $ifBlockInsideHook = null;

        foreach ($blocks as $block) {
            if ($block instanceof PropertyHookBlock) {
                $hookBlocks[] = $block;
            }

            if ($block instanceof IfBlock && $block->getParent() instanceof PropertyHookBlock) {
                $ifBlockInsideHook = $block;
            }
        }

        self::assertCount(3, $hookBlocks);
        self::assertSame(['get', 'get', 'set'], array_map(
            static fn (PropertyHookBlock $block) => $block->getHookName(),
            $hookBlocks,
        ));

        foreach ($hookBlocks as $hookBlock) {
            self::assertSame($hookBlock->getHookName(), $hookBlock->getNode()->name->toString());
            self::assertNull($hookBlock->getParent(), 'Property hook of a real class should have no parent block');
        }

        self::assertNotNull($ifBlockInsideHook, 'IfBlock nested in property hook should be found');
    }

    /**
     * @return list<CodeBlock>
     */
    private function collectBlocks(string $filePath): array
    {
        $linesContents = $this->getFileLines($filePath);

        $linesCoverage = [];
        foreach (array_keys($linesContents) as $lineNumber) {
            $linesCoverage[$lineNumber] = 1;
        }

        $collectingRule = new class implements CoverageRule {

            /**
             * @var list<CodeBlock>
             */
            public array $blocks = []; // @phpstan-ignore shipmonk.publicPropertyNotReadonly (ease testing)

            public function inspect(
                CodeBlock $codeBlock,
                InspectionContext $context,
            ): ?CoverageError
            {
                $this->blocks[] = $codeBlock;
                return null;
            }

        };

        $excluderVisitor = new ExcluderVisitor([], new ExclusionContext($filePath, $linesContents));
        $analyser = new CodeBlockAnalyser(
            patchMode: false,
            filePath: $filePath,
            linesChanged: [],
            linesCoverage: $linesCoverage,
            linesContents: $linesContents,
            rules: [$collectingRule],
            excluderVisitor: $excluderVisitor,
        );

        $traverser = new FileTraverser((new ParserFactory())->createForNewestSupportedVersion());
        $traverser->traverse($filePath, $linesContents, $excluderVisitor, $analyser);

        return $collectingRule->blocks;
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

}
