<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use LogicException;
use function array_filter;
use function count;

/**
 * @api
 */
abstract class CodeBlock
{

    /**
     * @param non-empty-list<LineOfCode> $lines
     */
    public function __construct(
        private string $filePath,
        private array $lines,
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
     * @param int $requiredPercentage 0-100
     */
    public function isCoveredAtLeastByPercent(int $requiredPercentage): bool
    {
        if ($requiredPercentage < 0 || $requiredPercentage > 100) {
            throw new LogicException('Minimal required percentage must be between 0 and 100');
        }

        $executableLines = $this->getExecutableLines();
        $totalLines = count($executableLines);

        if ($totalLines === 0) {
            return false;
        }

        $coveredLines = 0;

        foreach ($executableLines as $line) {
            if ($line->isCovered()) {
                $coveredLines++;
            }
        }

        $coveragePercentage = ($coveredLines / $totalLines) * 100;
        return $coveragePercentage >= $requiredPercentage;
    }

    public function getCoveragePercentage(): float
    {
        $executableLines = $this->getExecutableLines();
        $totalLines = count($executableLines);

        if ($totalLines === 0) {
            return 0;
        }

        $coveredLines = 0;

        foreach ($executableLines as $line) {
            if ($line->isCovered()) {
                $coveredLines++;
            }
        }

        return ($coveredLines / $totalLines) * 100;
    }

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
     * @param int $requiredPercentage 0-100
     */
    public function isChangedAtLeastByPercent(int $requiredPercentage): bool
    {
        if ($requiredPercentage < 0 || $requiredPercentage > 100) {
            throw new LogicException('Minimal required percentage must be between 0 and 100');
        }

        $executableLines = $this->getExecutableLines();
        $totalLines = count($executableLines);

        if ($totalLines === 0) {
            return false;
        }

        $changedLines = 0;

        foreach ($executableLines as $line) {
            if ($line->isChanged()) {
                $changedLines++;
            }
        }

        $changedPercentage = ($changedLines / $totalLines) * 100;
        return $changedPercentage >= $requiredPercentage;
    }

    /**
     * True if block has 100% coverage
     */
    public function isFullyCovered(): bool
    {
        foreach ($this->getExecutableLines() as $line) {
            if (!$line->isCovered()) {
                return false;
            }
        }
        return true;
    }

    /**
     * True if block is completely new or changed (in provided patch file)
     */
    public function isFullyChanged(): bool
    {
        foreach ($this->getExecutableLines() as $line) {
            if (!$line->isChanged()) {
                return false;
            }
        }
        return true;
    }

    /**
     * True if block has at least one executable line changed
     */
    public function isChanged(): bool
    {
        foreach ($this->getExecutableLines() as $line) {
            if ($line->isChanged()) {
                return true;
            }
        }
        return false;
    }

    /**
     * True if block is not tested at all
     */
    public function isFullyUncovered(): bool
    {
        foreach ($this->getExecutableLines() as $line) {
            if ($line->isCovered()) {
                return false;
            }
        }
        return true;
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

    public function getStartLineNumber(): int
    {
        return $this->lines[0]->getNumber();
    }

}
