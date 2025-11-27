<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeVisitorAbstract;
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
use ShipMonk\CoverageGuard\Hierarchy\LineOfCode;
use ShipMonk\CoverageGuard\Hierarchy\MatchBlock;
use ShipMonk\CoverageGuard\Hierarchy\SwitchBlock;
use ShipMonk\CoverageGuard\Hierarchy\TryBlock;
use ShipMonk\CoverageGuard\Hierarchy\WhileBlock;
use ShipMonk\CoverageGuard\Report\ReportedError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use function assert;
use function end;
use function range;
use function spl_object_id;

final class CodeBlockAnalyser extends NodeVisitorAbstract
{

    private ?string $currentClass = null;

    /**
     * @var list<ReportedError>
     */
    private array $reportedErrors = [];

    /**
     * @var array<int, CodeBlock> Stack of parent blocks (node id => block)
     */
    private array $parentStack = [];

    /**
     * @param array<int, int> $linesChanged line => line
     * @param array<int, int> $linesCoverage executable_line => hits
     * @param array<int, string> $linesContents
     * @param list<CoverageRule> $rules
     */
    public function __construct(
        private readonly bool $patchMode,
        private readonly string $filePath,
        private readonly array $linesChanged,
        private readonly array $linesCoverage,
        private readonly array $linesContents,
        private readonly array $rules,
    )
    {
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof ClassLike && $node->name !== null) {
            assert($node->namespacedName !== null); // using NameResolver
            $this->currentClass = $node->namespacedName->toString();
        }

        if ($node instanceof ClassMethod && $node->stmts !== null) {
            if ($this->currentClass === null) {
                throw new LogicException('Found class method without a class, should never happen');
            }

            $startLine = $node->name->getStartLine();
            $endLine = $node->getEndLine();

            $lines = $this->getLines($startLine, $endLine);
            if ($lines === []) {
                return null;
            }

            $parent = $this->getCurrentParent();
            $block = new ClassMethodBlock(
                $this->currentClass,
                $node->name->toString(),
                $this->filePath,
                $lines,
                $parent,
            );

            $this->trackBlock($node, $block);
            $this->processBlock($block);
            return null;
        }

        if ($node instanceof Foreach_ && $node->stmts !== []) {
            $block = $this->createBlock($node, ForeachBlock::class);
            if ($block !== null) {
                $this->processBlock($block);
            }
            return null;
        }

        if ($node instanceof For_ && $node->stmts !== []) {
            $block = $this->createBlock($node, ForBlock::class);
            if ($block !== null) {
                $this->processBlock($block);
            }
            return null;
        }

        if ($node instanceof While_ && $node->stmts !== []) {
            $block = $this->createBlock($node, WhileBlock::class);
            if ($block !== null) {
                $this->processBlock($block);
            }
            return null;
        }

        if ($node instanceof If_ && $node->stmts !== []) {
            $block = $this->createBlock($node, IfBlock::class);
            if ($block !== null) {
                $this->processBlock($block);
            }

            // Process elseif blocks
            foreach ($node->elseifs as $elseif) {
                if ($elseif->stmts !== []) {
                    $elseifBlock = $this->createChildBlock($elseif, ElseIfBlock::class);
                    if ($elseifBlock !== null) {
                        $this->processBlock($elseifBlock);
                    }
                }
            }

            // Process else block
            if ($node->else !== null && $node->else->stmts !== []) {
                $elseBlock = $this->createChildBlock($node->else, ElseBlock::class);
                if ($elseBlock !== null) {
                    $this->processBlock($elseBlock);
                }
            }

            return null;
        }

        if ($node instanceof Do_ && $node->stmts !== []) {
            $block = $this->createBlock($node, DoWhileBlock::class);
            if ($block !== null) {
                $this->processBlock($block);
            }
            return null;
        }

        if ($node instanceof Switch_ && $node->cases !== []) {
            $block = $this->createBlock($node, SwitchBlock::class);
            if ($block !== null) {
                $this->processBlock($block);
            }

            // Process individual case blocks
            foreach ($node->cases as $case) {
                if ($case->stmts !== []) {
                    $caseBlock = $this->createChildBlock($case, CaseBlock::class);
                    if ($caseBlock !== null) {
                        $this->processBlock($caseBlock);
                    }
                }
            }

            return null;
        }

        if ($node instanceof TryCatch && $node->stmts !== []) {
            $block = $this->createBlock($node, TryBlock::class);
            if ($block !== null) {
                $this->processBlock($block);
            }

            // Process catch blocks
            foreach ($node->catches as $catch) {
                if ($catch->stmts !== []) {
                    $catchBlock = $this->createChildBlock($catch, CatchBlock::class);
                    if ($catchBlock !== null) {
                        $this->processBlock($catchBlock);
                    }
                }
            }

            // Process finally block
            if ($node->finally !== null && $node->finally->stmts !== []) {
                $finallyBlock = $this->createChildBlock($node->finally, FinallyBlock::class);
                if ($finallyBlock !== null) {
                    $this->processBlock($finallyBlock);
                }
            }

            return null;
        }

        if ($node instanceof Function_ && $node->stmts !== []) {
            $startLine = $node->name->getStartLine();
            $endLine = $node->getEndLine();

            $lines = $this->getLines($startLine, $endLine);
            if ($lines === []) {
                return null;
            }

            $parent = $this->getCurrentParent();
            $block = new FunctionBlock(
                $node->name->toString(),
                $this->filePath,
                $lines,
                $parent,
            );

            $this->trackBlock($node, $block);
            $this->processBlock($block);
            return null;
        }

        if ($node instanceof Closure && $node->stmts !== []) {
            $block = $this->createBlock($node, ClosureBlock::class);
            if ($block !== null) {
                $this->processBlock($block);
            }
            return null;
        }

        if ($node instanceof ArrowFunction) {
            $block = $this->createBlock($node, ArrowFunctionBlock::class);
            if ($block !== null) {
                $this->processBlock($block);
            }
            return null;
        }

        if ($node instanceof Match_) {
            $block = $this->createBlock($node, MatchBlock::class);
            if ($block !== null) {
                $this->processBlock($block);
            }
            return null;
        }

        return null;
    }

    public function leaveNode(Node $node): mixed
    {
        if ($node instanceof ClassLike && $node->name !== null) {
            $this->currentClass = null;
        }

        // Pop from parent stack when leaving nodes that create blocks
        if (
            $node instanceof ClassMethod ||
            $node instanceof Function_ ||
            $node instanceof Foreach_ ||
            $node instanceof For_ ||
            $node instanceof While_ ||
            $node instanceof Do_ ||
            $node instanceof If_ ||
            $node instanceof Switch_ ||
            $node instanceof TryCatch ||
            $node instanceof Closure ||
            $node instanceof ArrowFunction ||
            $node instanceof Match_
        ) {
            unset($this->parentStack[spl_object_id($node)]);
        }

        return null;
    }

    private function getCurrentParent(): ?CodeBlock
    {
        if ($this->parentStack === []) {
            return null;
        }

        return end($this->parentStack);
    }

    private function trackBlock(
        Node $node,
        CodeBlock $block,
    ): void
    {
        $this->parentStack[spl_object_id($node)] = $block;
    }

    /**
     * @return list<LineOfCode>
     */
    private function getLines(
        int $startLine,
        int $endLine,
    ): array
    {
        $executableLines = [];
        foreach (range($startLine, $endLine) as $lineNumber) {
            if (!isset($this->linesContents[$lineNumber])) {
                throw new LogicException("Line number #{$lineNumber} of file '{$this->filePath}' is expected to exist.");
            }

            $executableLines[] = new LineOfCode(
                number: $lineNumber,
                executable: isset($this->linesCoverage[$lineNumber]),
                covered: isset($this->linesCoverage[$lineNumber]) && $this->linesCoverage[$lineNumber] > 0,
                changed: isset($this->linesChanged[$lineNumber]),
                contents: $this->linesContents[$lineNumber],
            );
        }
        return $executableLines;
    }

    /**
     * @param class-string<T> $blockClass
     * @return T|null
     *
     * @template T of CodeBlock
     */
    private function createBlock(
        Node $node,
        string $blockClass,
    ): ?CodeBlock
    {
        $startLine = $node->getStartLine();
        $endLine = $node->getEndLine();

        $lines = $this->getLines($startLine, $endLine);
        if ($lines === []) {
            return null;
        }

        $parent = $this->getCurrentParent();
        $block = new $blockClass($this->filePath, $lines, $parent);
        $this->trackBlock($node, $block);
        return $block;
    }

    /**
     * Creates a block without tracking it as a parent (for child blocks like ElseIf, Else, Case, Catch, Finally)
     *
     * @param class-string<T> $blockClass
     * @return T|null
     *
     * @template T of CodeBlock
     */
    private function createChildBlock(
        Node $node,
        string $blockClass,
    ): ?CodeBlock
    {
        $startLine = $node->getStartLine();
        $endLine = $node->getEndLine();

        $lines = $this->getLines($startLine, $endLine);
        if ($lines === []) {
            return null;
        }

        $parent = $this->getCurrentParent();
        return new $blockClass($this->filePath, $lines, $parent);
    }

    private function processBlock(CodeBlock $block): void
    {
        if ($this->patchMode && $block->getChangedLinesCount() === 0) {
            return; // unchanged blocks not passed to rules in patch mode
        }

        foreach ($this->inspectCodeBlock($block) as $reportedError) {
            $this->reportedErrors[] = $reportedError;
        }
    }

    /**
     * @return list<ReportedError>
     */
    private function inspectCodeBlock(CodeBlock $block): array
    {
        $reportedErrors = [];
        foreach ($this->rules as $rule) {
            $coverageError = $rule->inspect($block, $this->patchMode);

            if ($coverageError !== null) {
                $reportedErrors[] = new ReportedError($block, $coverageError);
            }
        }

        return $reportedErrors;
    }

    /**
     * @return list<ReportedError>
     */
    public function getReportedErrors(): array
    {
        return $this->reportedErrors;
    }

}
