<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

class InitializationResult
{

    public function __construct(
        public readonly string $coverageFile,
        public readonly ?string $patchFile,
        public readonly Config $config,
    )
    {
    }

}
