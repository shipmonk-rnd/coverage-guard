<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Coverage;

use function array_reduce;
use function count;
use function round;

final class FileCoverage
{

    /**
     * @param list<ExecutableLine> $executableLines
     */
    public function __construct(
        public readonly string $filePath,
        public readonly array $executableLines,
        public readonly ?int $expectedLinesCount = null,
    )
    {
    }

    public function getCoveragePercentage(): int
    {
        $coveredLines = array_reduce($this->executableLines, static function (int $carry, ExecutableLine $line): int {
            return $line->hits > 0 ? $carry + 1 : $carry;
        }, 0);
        $totalLines = count($this->executableLines);
        return (int) round($coveredLines / $totalLines * 100, 0);
    }

}
