<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use ShipMonk\CoverageGuard\Exception\ErrorException;

interface CoverageExtractor
{

    /**
     * @return array<string, array<int, int>> file_path => [executable_line => hits]
     *
     * @throws ErrorException
     */
    public function getCoverage(string $coverageFile): array;

}
