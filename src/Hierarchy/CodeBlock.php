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
        private readonly string $filePath,
        private readonly array $lines,
    )
    {
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return non-empty-list<LineOfCode>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getExecutableLinesCount(): int
    {
        return count($this->getExecutableLines());
    }

    /**
     * Calculates the coverage percentage of the code block.
     *
     * @return int 0-100
     */
    public function getCoveragePercentage(): int
    {
        $totalExecutableLines = $this->getExecutableLinesCount();

        if ($totalExecutableLines === 0) {
            return 0;
        }

        $coveredLines = $this->getCoveredLinesCount();

        return (int) round(($coveredLines / $totalExecutableLines) * 100, 0);
    }

    /**
     * Calculates the number of covered executable lines in the code block.
     */
    public function getCoveredLinesCount(): int
    {
        $coveredLines = 0;
        foreach ($this->getExecutableLines() as $line) {
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
        $executableLines = $this->getExecutableLines();
        $totalExecutableLines = count($executableLines);

        if ($totalExecutableLines === 0) {
            return 0;
        }

        $changedLines = $this->getChangedLinesCount();

        return (int) round(($changedLines / $totalExecutableLines) * 100, 0);
    }

    /**
     * Calculates the number of changed executable lines in the code block.
     */
    public function getChangedLinesCount(): int
    {
        $executableLines = $this->getExecutableLines();
        $changedLines = 0;

        foreach ($executableLines as $line) {
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
    private function getExecutableLines(): array
    {
        return array_filter($this->lines, static function (LineOfCode $line): bool {
            return $line->isExecutable();
        });
    }

}
