<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Writer;

use ShipMonk\CoverageGuard\Cli\CoverageOutputFormat;

final class CoverageWriterFactory
{

    public static function create(CoverageOutputFormat $format): CoverageWriter
    {
        return match ($format) {
            CoverageOutputFormat::Clover => new CloverCoverageWriter(),
            CoverageOutputFormat::Cobertura => new CoberturaCoverageWriter(),
        };
    }

}
