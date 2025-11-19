<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use ShipMonk\CoverageGuard\Cli\Options\OutputFormatCliOption;

final class TestCommandWithRequiredOption
{

    public function __invoke(
        #[OutputFormatCliOption]
        string $output,
    ): void
    {
    }

}
