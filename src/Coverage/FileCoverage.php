<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Coverage;

use function count;

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

    public function getCoveragePercentage(): float
    {
        $coveredLines = 0;
        foreach ($this->executableLines as $line) {
            if ($line->hits > 0) {
                $coveredLines++;
            }
        }
        $totalLines = count($this->executableLines);
        return $coveredLines / $totalLines * 100;
    }

}
