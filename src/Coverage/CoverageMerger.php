<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Coverage;

use function ksort;
use function max;

final class CoverageMerger
{

    /**
     * Merge multiple coverage data sets into one
     * For each file, line hits are summed across all inputs
     *
     * @param list<list<FileCoverage>> $coverageSets
     * @return list<FileCoverage>
     */
    public static function merge(array $coverageSets): array
    {
        /** @var array<string, array<int, int>> $fileLineHits File path => [line number => total hits] */
        $fileLineHits = [];

        /** @var array<string, int|null> $fileExpectedLines File path => expected lines count */
        $fileExpectedLines = [];

        foreach ($coverageSets as $coverageSet) {
            foreach ($coverageSet as $fileCoverage) {
                $filePath = $fileCoverage->filePath;

                if (!isset($fileLineHits[$filePath])) {
                    $fileLineHits[$filePath] = [];
                }

                // Track expected lines count (use max if multiple values exist)
                if ($fileCoverage->expectedLinesCount !== null) {
                    $fileExpectedLines[$filePath] = isset($fileExpectedLines[$filePath])
                        ? max($fileExpectedLines[$filePath], $fileCoverage->expectedLinesCount)
                        : $fileCoverage->expectedLinesCount;
                }

                // Sum hits for each line
                foreach ($fileCoverage->executableLines as $line) {
                    $fileLineHits[$filePath][$line->lineNumber] = ($fileLineHits[$filePath][$line->lineNumber] ?? 0) + $line->hits;
                }
            }
        }

        // Build merged FileCoverage objects
        $merged = [];
        foreach ($fileLineHits as $filePath => $lineHits) {
            ksort($lineHits); // Sort by line number

            $executableLines = [];
            foreach ($lineHits as $lineNumber => $hits) {
                $executableLines[] = new ExecutableLine($lineNumber, $hits);
            }

            $expectedLinesCount = $fileExpectedLines[$filePath] ?? null;
            $merged[] = new FileCoverage($filePath, $executableLines, $expectedLinesCount);
        }

        return $merged;
    }

}
