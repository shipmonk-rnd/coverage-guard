<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
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
use ShipMonk\CoverageGuard\Hierarchy\MatchBlock;
use ShipMonk\CoverageGuard\Hierarchy\SwitchBlock;
use ShipMonk\CoverageGuard\Hierarchy\TryBlock;
use ShipMonk\CoverageGuard\Hierarchy\WhileBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\InspectionContext;
use function array_keys;
use function array_map;
use function count;
use function file;
use const FILE_IGNORE_NEW_LINES;

final class ConditionalBlocksTest extends TestCase
{

    public function testAllConditionalBlocksAreDetected(): void
    {
        $filePath = __DIR__ . '/_fixtures/ConditionalBlocks.php';
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

        $blockTypes = array_map(
            static fn (CodeBlock $block) => $block::class,
            $collectingRule->blocks,
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

        $functionBlock = null;
        $ifBlockInsideMethod = null;
        $ifBlockInsideElse = null;
        $elseIfBlock = null;
        $foreachBlockInsideMethod = null;
        $caseBlock = null;
        $tryBlock = null;
        $catchBlock = null;
        $finallyBlock = null;

        foreach ($collectingRule->blocks as $block) {
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

        self::assertNotNull($catchBlock, 'CatchBlock should be found');
        self::assertInstanceOf(TryBlock::class, $catchBlock->getParent());

        self::assertNotNull($finallyBlock, 'FinallyBlock should be found');
        self::assertInstanceOf(TryBlock::class, $finallyBlock->getParent());

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
