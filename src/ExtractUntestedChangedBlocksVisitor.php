<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\LineOfCode;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\ReportedError;
use function array_combine;
use function assert;
use function count;
use function file;
use function range;

final class ExtractUntestedChangedBlocksVisitor extends NodeVisitorAbstract
{

    /**
     * @var array<int, string>
     */
    private readonly array $linesContent;

    private ?string $currentClass = null;

    /**
     * @var list<ReportedError>
     */
    private array $reportedErrors = [];

    /**
     * @param array<int, int> $linesChanged line => line
     * @param array<int, int> $linesCoverage executable_line => hits
     * @param list<CoverageRule> $rules
     */
    public function __construct(
        private readonly bool $patchMode,
        private readonly string $filePath,
        private readonly array $linesChanged,
        private readonly array $linesCoverage,
        private readonly array $rules,
    )
    {
        $lines = file($this->filePath);
        if ($lines === false) {
            throw new LogicException("Failed to read file: {$this->filePath}");
        }
        $this->linesContent = array_combine(range(1, count($lines)), $lines);
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
            if (!isset($this->linesContent[$lineNumber])) {
                throw new LogicException("Line number #{$lineNumber} of file '{$this->filePath}' was expected to exist.");
            }

            $executableLines[] = new LineOfCode(
                number: $lineNumber,
                executable: isset($this->linesCoverage[$lineNumber]),
                covered: isset($this->linesCoverage[$lineNumber]) && $this->linesCoverage[$lineNumber] > 0,
                changed: isset($this->linesChanged[$lineNumber]),
                contents: $this->linesContent[$lineNumber],
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
