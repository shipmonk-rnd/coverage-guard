<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use function array_filter;
use function count;
use function round;

/**
 * @api
 */
abstract class CodeBlock
{

    /**
     * @param non-empty-list<LineOfCode> $lines
     */
    public function __construct(
        private readonly array $lines,
    )
    {
    }

    /**
     * @return non-empty-list<LineOfCode>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * @deprecated use getCoverableLinesCount() instead
     */
    public function getExecutableLinesCount(): int
    {
        return $this->getCoverableLinesCount();
    }

    /**
     * Number of executable lines that are not excluded
     */
    public function getCoverableLinesCount(): int
    {
        return count($this->getCoverableLines());
    }

    /**
     * Calculates the coverage percentage of the code block.
     *
     * Excluded lines are not counted, block with nothing to cover is fully covered.
     *
     * @return int 0-100
     */
    public function getCoveragePercentage(): int
    {
        $totalCoverableLines = $this->getCoverableLinesCount();

        if ($totalCoverableLines === 0) {
            return 100;
        }

        $coveredLines = $this->getCoveredLinesCount();

        return (int) round(($coveredLines / $totalCoverableLines) * 100, 0);
    }

    /**
     * Calculates the number of covered executable lines in the code block.
     */
    public function getCoveredLinesCount(): int
    {
        $coveredLines = 0;
        foreach ($this->getCoverableLines() as $line) {
            if ($line->isCovered()) {
                $coveredLines++;
            }
        }
        return $coveredLines;
    }

    /**
     * Calculates the percentage of changed executable lines in the code block.
     *
     * @return int 0-100
     */
    public function getChangePercentage(): int
    {
        $totalCoverableLines = count($this->getCoverableLines());

        if ($totalCoverableLines === 0) {
            return 0;
        }

        $changedLines = $this->getChangedLinesCount();

        return (int) round(($changedLines / $totalCoverableLines) * 100, 0);
    }

    /**
     * Calculates the number of changed executable lines in the code block.
     */
    public function getChangedLinesCount(): int
    {
        $changedLines = 0;

        foreach ($this->getCoverableLines() as $line) {
            if ($line->isChanged()) {
                $changedLines++;
            }
        }

        return $changedLines;
    }

    public function getStartLineNumber(): int
    {
        return $this->lines[0]->getNumber();
    }

    /**
     * @return array<LineOfCode>
     */
    private function getCoverableLines(): array
    {
        return array_filter($this->lines, static function (LineOfCode $line): bool {
            return $line->isExecutable() && !$line->isExcluded();
        });
    }

}
