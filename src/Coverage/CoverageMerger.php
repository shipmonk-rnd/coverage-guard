<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Coverage;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use function array_key_exists;

final class CoverageMerger
{

    /**
     * @param list<array<FileCoverage>> $coverageSets
     * @return list<FileCoverage>
     *
     * @throws ErrorException
     */
    public function merge(array $coverageSets): array
    {
        /** @var array<string, array<int, int>> $fileLineHits File path => [line number => total hits] */
        $fileLineHits = [];

        /** @var array<string, int|null> $fileExpectedLines File path => expected lines count */
        $fileExpectedLines = [];

        foreach ($coverageSets as $coverageSet) {
            foreach ($coverageSet as $fileCoverage) {
                $filePath = $fileCoverage->filePath;

                if (array_key_exists($filePath, $fileExpectedLines) && $fileExpectedLines[$filePath] !== $fileCoverage->expectedLinesCount) {
                    $previous = $fileExpectedLines[$filePath] ?? 'none';
                    $current = $fileCoverage->expectedLinesCount ?? 'none';
                    throw new ErrorException("Inconsistent expected lines count for file '{$filePath}': {$previous} vs. {$current}. Check incriminated file for 'coverage.project.file.metrics.loc' in given clover source files.");
                }
                $fileExpectedLines[$filePath] = $fileCoverage->expectedLinesCount;

                foreach ($fileCoverage->executableLines as $line) {
                    $fileLineHits[$filePath][$line->lineNumber] = ($fileLineHits[$filePath][$line->lineNumber] ?? 0) + $line->hits;
                }
            }
        }

        $merged = [];
        foreach ($fileLineHits as $filePath => $lineHits) {
            $executableLines = [];
            foreach ($lineHits as $lineNumber => $hits) {
                $executableLines[] = new ExecutableLine($lineNumber, $hits);
            }

            $merged[] = new FileCoverage($filePath, $executableLines, $fileExpectedLines[$filePath] ?? null);
        }

        return $merged;
    }

}
