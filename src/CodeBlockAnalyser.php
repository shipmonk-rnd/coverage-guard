<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\LineOfCode;
use ShipMonk\CoverageGuard\Report\ReportedError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use function assert;
use function range;

final class CodeBlockAnalyser extends NodeVisitorAbstract
{

    private ?string $currentClass = null;

    /**
     * @var list<ReportedError>
     */
    private array $reportedErrors = [];

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

            $block = new ClassMethodBlock(
                $this->currentClass,
                $node->name->toString(),
                $this->filePath,
                $lines,
            );

            if ($this->patchMode && !$block->isChanged()) {
                return null; // unchanged methods not passed to rules in patch mode
            }

            foreach ($this->inspectCodeBlock($block) as $reportedError) {
                $this->reportedErrors[] = $reportedError;
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
    private function inspectCodeBlock(ClassMethodBlock $block): array
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
