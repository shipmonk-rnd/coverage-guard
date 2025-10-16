<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

final class CliOptions
{

    public function __construct(
        public readonly string $coverageFile,
        public readonly ?string $patchFile,
        public readonly ?string $configFile,
        public readonly bool $debug,
    )
    {
    }

}
