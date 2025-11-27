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
use ShipMonk\CoverageGuard\Excluder\ExcluderVisitor;
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
use ShipMonk\CoverageGuard\Rule\InspectionContext;
use function assert;
use function end;
use function range;
use function spl_object_id;

final class CodeBlockAnalyser extends NodeVisitorAbstract
{

    private ?string $currentClass = null;

    private ?string $currentMethod = null;

    private bool $inAnonymousClass = false;

    /**
     * @var list<ReportedError>
     */
    private array $reportedErrors = [];

    /**
     * @var array<int, CodeBlock> stack of enclosing blocks, indexed by spl_object_id of their node
     */
    private array $parentStack = [];

    private InspectionContext $context;

    /**
     * @param array<int, int> $linesChanged line => line
     * @param array<int, int> $linesCoverage executable_line => hits
     * @param array<int, string> $linesContents
     * @param list<CoverageRule> $rules
     * @param ExcluderVisitor $excluderVisitor must be traversed before this visitor
     */
    public function __construct(
        private readonly bool $patchMode,
        private readonly string $filePath,
        private readonly array $linesChanged,
        private readonly array $linesCoverage,
        private readonly array $linesContents,
        private readonly array $rules,
        private readonly ExcluderVisitor $excluderVisitor,
    )
    {
        $this->updateContext();
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof ClassLike) {
            if ($node->name === null) {
                $this->inAnonymousClass = true;
            } else {
                assert($node->namespacedName !== null); // using NameResolver
                $this->currentClass = $node->namespacedName->toString();
                $this->updateContext();
            }
        }

        if ($node instanceof ClassMethod && $node->stmts !== null) {
            if ($this->inAnonymousClass) {
                return null; // ClassMethodBlock is emitted only for real methods
            }
            if ($this->currentClass === null) {
                throw new LogicException('Found class method without a class, should never happen');
            }

            $lines = $this->getLines($node->name->getStartLine(), $node->getEndLine());
            if ($lines === []) {
                return null;
            }

            $block = new ClassMethodBlock($node, $lines, $this->getCurrentParent());

            $this->currentMethod = $node->name->toString();
            $this->updateContext();

            $this->trackBlock($node, $block);
            $this->processBlock($block);

            return null;
        }

        if ($node instanceof Function_ && $node->stmts !== []) {
            $lines = $this->getLines($node->name->getStartLine(), $node->getEndLine());
            if ($lines === []) {
                return null;
            }

            $block = new FunctionBlock($node, $lines, $this->getCurrentParent());

            $this->trackBlock($node, $block);
            $this->processBlock($block);

            return null;
        }

        if ($node instanceof Foreach_ && $node->stmts !== []) {
            $this->processNestedBlock($node, ForeachBlock::class);
            return null;
        }

        if ($node instanceof For_ && $node->stmts !== []) {
            $this->processNestedBlock($node, ForBlock::class);
            return null;
        }

        if ($node instanceof While_ && $node->stmts !== []) {
            $this->processNestedBlock($node, WhileBlock::class);
            return null;
        }

        if ($node instanceof Do_ && $node->stmts !== []) {
            $this->processNestedBlock($node, DoWhileBlock::class);
            return null;
        }

        if ($node instanceof If_ && $node->stmts !== []) {
            $this->processNestedBlock($node, IfBlock::class);

            foreach ($node->elseifs as $elseif) {
                if ($elseif->stmts !== []) {
                    $this->processChildBlock($elseif, ElseIfBlock::class);
                }
            }

            if ($node->else !== null && $node->else->stmts !== []) {
                $this->processChildBlock($node->else, ElseBlock::class);
            }

            return null;
        }

        if ($node instanceof Switch_ && $node->cases !== []) {
            $this->processNestedBlock($node, SwitchBlock::class);

            foreach ($node->cases as $case) {
                if ($case->stmts !== []) {
                    $this->processChildBlock($case, CaseBlock::class);
                }
            }

            return null;
        }

        if ($node instanceof TryCatch && $node->stmts !== []) {
            $this->processNestedBlock($node, TryBlock::class);

            foreach ($node->catches as $catch) {
                if ($catch->stmts !== []) {
                    $this->processChildBlock($catch, CatchBlock::class);
                }
            }

            if ($node->finally !== null && $node->finally->stmts !== []) {
                $this->processChildBlock($node->finally, FinallyBlock::class);
            }

            return null;
        }

        if ($node instanceof Closure && $node->stmts !== []) {
            $this->processNestedBlock($node, ClosureBlock::class);
            return null;
        }

        if ($node instanceof ArrowFunction) {
            $this->processNestedBlock($node, ArrowFunctionBlock::class);
            return null;
        }

        if ($node instanceof Match_) {
            $this->processNestedBlock($node, MatchBlock::class);
            return null;
        }

        return null;
    }

    public function leaveNode(Node $node): mixed
    {
        if ($node instanceof ClassLike) {
            if ($node->name !== null) {
                $this->currentClass = null;
                $this->updateContext();
            } else {
                $this->inAnonymousClass = false;
            }
        }

        if ($node instanceof ClassMethod && !$this->inAnonymousClass) {
            $this->currentMethod = null;
            $this->updateContext();
        }

        unset($this->parentStack[spl_object_id($node)]);

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
     * Creates a block that becomes parent of blocks nested inside it
     *
     * @param class-string<CodeBlock> $blockClass
     */
    private function processNestedBlock(
        Node $node,
        string $blockClass,
    ): void
    {
        $block = $this->createBlock($node, $blockClass);
        if ($block === null) {
            return;
        }

        $this->trackBlock($node, $block);
        $this->processBlock($block);
    }

    /**
     * Creates a block that never becomes a parent (elseif, else, case, catch, finally),
     * blocks nested inside it get its enclosing block as parent
     *
     * @param class-string<CodeBlock> $blockClass
     */
    private function processChildBlock(
        Node $node,
        string $blockClass,
    ): void
    {
        $block = $this->createBlock($node, $blockClass);
        if ($block === null) {
            return;
        }

        $this->processBlock($block);
    }

    /**
     * @param class-string<CodeBlock> $blockClass
     */
    private function createBlock(
        Node $node,
        string $blockClass,
    ): ?CodeBlock
    {
        $lines = $this->getLines($node->getStartLine(), $node->getEndLine());
        if ($lines === []) {
            return null;
        }

        return new $blockClass($lines, $this->getCurrentParent());
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
                excluded: $this->excluderVisitor->isLineExcluded($lineNumber),
                covered: isset($this->linesCoverage[$lineNumber]) && $this->linesCoverage[$lineNumber] > 0,
                changed: isset($this->linesChanged[$lineNumber]),
                contents: $this->linesContents[$lineNumber],
            );
        }
        return $executableLines;
    }

    /**
     * @return list<ReportedError>
     */
    private function inspectCodeBlock(CodeBlock $block): array
    {
        $reportedErrors = [];
        foreach ($this->rules as $rule) {
            $coverageError = $rule->inspect($block, $this->context);

            if ($coverageError !== null) {
                $reportedErrors[] = new ReportedError($this->filePath, $block, $coverageError);
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

    private function updateContext(): void
    {
        $this->context = new InspectionContext(
            className: $this->currentClass,
            methodName: $this->currentMethod,
            filePath: $this->filePath,
            patchMode: $this->patchMode,
        );
    }

}
