<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

final class InitializationResult
{

    public function __construct(
        public readonly CliOptions $cliOptions,
        public readonly Config $config,
    )
    {
    }

}
