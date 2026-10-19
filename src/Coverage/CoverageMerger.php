<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Coverage;

use ShipMonk\CoverageGuard\Exception\ErrorException;

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

                $previous = $fileExpectedLines[$filePath] ?? null;
                $current = $fileCoverage->expectedLinesCount;

                // only some formats (clover) carry lines count, so missing value is not an inconsistency
                if ($previous !== null && $current !== null && $previous !== $current) {
                    throw new ErrorException("Inconsistent expected lines count for file '{$filePath}': {$previous} vs. {$current}. Check incriminated file for 'coverage.project.file.metrics.loc' in given clover source files.");
                }
                $fileExpectedLines[$filePath] = $previous ?? $current;

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
