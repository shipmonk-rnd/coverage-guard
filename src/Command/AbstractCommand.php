<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Writer\CloverCoverageWriter;
use ShipMonk\CoverageGuard\Writer\CoberturaCoverageWriter;
use ShipMonk\CoverageGuard\Writer\CoverageWriter;
use function realpath;

abstract class AbstractCommand implements Command
{

    /**
     * Create coverage writer for the specified format
     */
    protected function createWriter(CoverageFormat $format): CoverageWriter
    {
        return match ($format) {
            CoverageFormat::Clover => new CloverCoverageWriter(),
            CoverageFormat::Cobertura => new CoberturaCoverageWriter(),
        };
    }

    /**
     * Get realpath or null if file doesn't exist
     */
    protected function tryRealpath(string $path): ?string
    {
        $realpath = realpath($path);
        return $realpath === false ? null : $realpath;
    }

}
