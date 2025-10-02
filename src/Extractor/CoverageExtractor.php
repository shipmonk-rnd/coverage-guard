<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

interface CoverageExtractor
{

    /**
     * @return array<string, array<int, int>> file_path => [executable_line => hits]
     */
    public function getCoverage(string $coverageFile): array;

}
