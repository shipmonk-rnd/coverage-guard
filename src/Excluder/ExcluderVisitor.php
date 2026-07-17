<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Excluder;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use function range;

final class ExcluderVisitor extends NodeVisitorAbstract
{

    /**
     * @var array<int, int>
     */
    private array $excludedLines = [];

    /**
     * @param list<ExecutableLineExcluder> $excluders
     */
    public function __construct(
        private readonly array $excluders,
    )
    {
    }

    public function enterNode(Node $node): ?int
    {
        foreach ($this->excluders as $excluder) {
            $excludedLineRange = $excluder->getExcludedLineRange($node);
            if ($excludedLineRange !== null) {
                foreach (range($excludedLineRange->getStart(), $excludedLineRange->getEnd()) as $excludedLine) {
                    $this->excludedLines[$excludedLine] = $excludedLine;
                }
            }
        }

        return null;
    }

    public function isLineExcluded(int $lineNumber): bool
    {
        return isset($this->excludedLines[$lineNumber]);
    }

}
