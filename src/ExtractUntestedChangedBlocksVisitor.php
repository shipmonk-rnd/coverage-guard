<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use function array_filter;
use function count;
use function range;

final class ExtractUntestedChangedBlocksVisitor extends NodeVisitorAbstract
{

    /**
     * @var list<CodeBlock>
     */
    private array $untestedBlocks = [];

    /**
     * @param array<int, int>|null $linesChanged line => line, null means scan all blocks
     * @param array<int, int> $linesCoverage executable_line => hits
     */
    public function __construct(
        private string $filePath,
        private ?array $linesChanged,
        private array $linesCoverage,
    )
    {
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof ClassMethod && $node->stmts !== null) {
            $startLine = $node->name->getStartLine();
            $endLine = $node->getEndLine();

            $executableLines = $this->getExecutableLines($startLine, $endLine);
            if ($executableLines === []) {
                return null;
            }

            if ($this->linesChanged === null) {
                $changedLines = $executableLines; // when patch is not provided, scan all lines
            } else {
                $changedLines = array_filter($executableLines, function (int $line): bool {
                    return isset($this->linesChanged[$line]);
                });
            }

            if ($changedLines === []) {
                return null;
            }

            $testedLines = array_filter($executableLines, function (int $line): bool {
                return isset($this->linesCoverage[$line]) && $this->linesCoverage[$line] > 0;
            });

            if ($changedLines === $executableLines && count($testedLines) === 0) {
                $this->untestedBlocks[] = new CodeBlock(
                    CodeBlockType::ClassMethod,
                    $this->filePath,
                    $startLine,
                    $endLine,
                );
                return self::DONT_TRAVERSE_CHILDREN; // do not report sub-blocks
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function getExecutableLines(
        int $startLine,
        int $endLine,
    ): array
    {
        $executableLines = [];
        foreach (range($startLine, $endLine) as $line) {
            $isExecutableLine = isset($this->linesCoverage[$line]);

            if (!$isExecutableLine) {
                continue;
            }

            $executableLines[] = $line;
        }
        return $executableLines;
    }

    /**
     * @return list<CodeBlock>
     */
    public function getUntestedBlocks(): array
    {
        return $this->untestedBlocks;
    }

}
