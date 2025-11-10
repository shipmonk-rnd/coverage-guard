<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use ShipMonk\CoverageGuard\Coverage\FileCoverage;
use ShipMonk\CoverageGuard\Exception\ErrorException;

interface CoverageWriter
{

    /**
     * @param array<FileCoverage> $fileCoverages
     *
     * @throws ErrorException
     */
    public function write(
        array $fileCoverages,
        string $indent,
    ): string;

}
