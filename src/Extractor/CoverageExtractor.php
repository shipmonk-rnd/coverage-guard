<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Extractor;

use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;

interface CoverageExtractor
{

    /**
     * @return list<FileCoverage>
     *
     * @throws ErrorException
     */
    public function getCoverage(string $coverageFile): array;

}
