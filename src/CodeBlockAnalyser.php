<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use ShipMonk\CoverageGuard\Excluder\ExecutableLineExcluder;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\LineOfCode;
use ShipMonk\CoverageGuard\Report\ReportedError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\InspectionContext;
use function array_pop;
use function end;
use function range;

final class CodeBlockAnalyser extends NodeVisitorAbstract
{

    /**
     * Anonymous classes can be nested
     *
     * @var list<string|null>
     */
    private array $currentClassStack = [];

    /**
     * Anonymous classes can cause nested methods
     *
     * @var list<string|null>
     */
    private array $currentMethodStack = [];

    /**
     * @var list<ReportedError>
     */
    private array $reportedErrors = [];

    /**
     * @var array<int, int>
     */
    private array $excludedLines = [];

    /**
     * @param array<int, int> $linesChanged line => line
     * @param array<int, int> $linesCoverage executable_line => hits
     * @param array<int, string> $linesContents
     * @param list<CoverageRule> $rules
     * @param list<ExecutableLineExcluder> $excluders
     */
    public function __construct(
        private readonly bool $patchMode,
        private readonly string $filePath,
        private readonly array $linesChanged,
        private readonly array $linesCoverage,
        private readonly array $linesContents,
        private readonly array $rules,
        private readonly array $excluders,
    )
    {
    }

    public function enterNode(Node $node): ?int
    {
        foreach ($this->excluders as $excluder) {
            $excludedExecutableLineRange = $excluder->getExcludedLineRange($node);
            if ($excludedExecutableLineRange !== null) {
                foreach (range($excludedExecutableLineRange->getStart(), $excludedExecutableLineRange->getEnd()) as $excludedLine) {
                    $this->excludedLines[$excludedLine] = $excludedLine;
                }
            }
        }

        if ($node instanceof ClassLike) {
            $this->currentClassStack[] = $node->namespacedName?->toString();
        }

        if ($node instanceof ClassMethod) {
            $this->currentMethodStack[] = $node->name->name;
        }

        return null;
    }

    public function leaveNode(Node $node): mixed
    {
        if ($node instanceof ClassMethod && $node->stmts !== null) {
            $currentClass = end($this->currentClassStack) !== false ? end($this->currentClassStack) : null;
            $currentMethod = end($this->currentMethodStack) !== false ? end($this->currentMethodStack) : null;
            $startLine = $node->name->getStartLine();
            $endLine = $node->getEndLine();

            $lines = $this->getLines($startLine, $endLine);
            if ($lines === []) {
                $classStr = $currentClass ?? 'unknown';
                $methodStr = $currentMethod ?? 'unknown';
                throw new LogicException("Class method '{$classStr}::{$methodStr}' has no executable lines although it has some statements");
            }

            $block = new ClassMethodBlock(
                $node,
                $lines,
            );
            $context = new InspectionContext(
                className: $currentClass,
                methodName: $currentMethod,
                filePath: $this->filePath,
                patchMode: $this->patchMode,
            );

            if ($this->patchMode && $block->getChangedLinesCount() === 0) {
                return null; // unchanged methods not passed to rules in patch mode
            }

            foreach ($this->inspectCodeBlock($block, $context) as $reportedError) {
                $this->reportedErrors[] = $reportedError;
            }
        }

        if ($node instanceof ClassLike) {
            array_pop($this->currentClassStack);
        }

        if ($node instanceof ClassMethod) {
            array_pop($this->currentMethodStack);
        }

        return null;
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
                excluded: isset($this->excludedLines[$lineNumber]),
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
    private function inspectCodeBlock(
        ClassMethodBlock $block,
        InspectionContext $context,
    ): array
    {
        $reportedErrors = [];
        foreach ($this->rules as $rule) {
            $coverageError = $rule->inspect($block, $context);

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

}
