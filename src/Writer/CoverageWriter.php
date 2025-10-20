<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use ShipMonk\CoverageGuard\Coverage\FileCoverage;

interface CoverageWriter
{

    /**
     * @param list<FileCoverage> $fileCoverages
     * @param resource $output
     */
    public function write(
        array $fileCoverages,
        $output,
    ): void;

}
