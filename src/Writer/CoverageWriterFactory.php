<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use ShipMonk\CoverageGuard\Cli\CoverageFormat;

final class CoverageWriterFactory
{

    public static function create(CoverageFormat $format): CoverageWriter
    {
        return match ($format) {
            CoverageFormat::Clover => new CloverCoverageWriter(),
            CoverageFormat::Cobertura => new CoberturaCoverageWriter(),
        };
    }

}
