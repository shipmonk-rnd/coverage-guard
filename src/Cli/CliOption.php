<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

abstract class CliOption
{

    public function __construct(
        public readonly string $name,
        public readonly string $description,
    )
    {
    }

}
