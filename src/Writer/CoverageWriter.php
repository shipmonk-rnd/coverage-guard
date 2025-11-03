<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use ShipMonk\CoverageGuard\Coverage\FileCoverage;

interface CoverageWriter
{

    /**
     * @param list<FileCoverage> $fileCoverages
     */
    public function write(
        array $fileCoverages,
        string $indent,
    ): string;

}
