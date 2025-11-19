<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use ShipMonk\CoverageGuard\Cli\Options\ConfigCliOption;
use ShipMonk\CoverageGuard\Cli\Options\VerboseCliOption;

final class TestCommandWithOptions
{

    public function __invoke(
        #[VerboseCliOption]
        bool $verbose = false,

        #[ConfigCliOption]
        ?string $config = null,
    ): void
    {
    }

}
