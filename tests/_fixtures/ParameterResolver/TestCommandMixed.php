<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use ShipMonk\CoverageGuard\Cli\Options\OutputFormatCliOption;
use ShipMonk\CoverageGuard\Cli\Options\VerboseCliOption;

final class TestCommandMixed
{

    public function __invoke(
        #[TestCliArgument('input')]
        string $input,

        #[VerboseCliOption]
        bool $verbose = false,

        #[OutputFormatCliOption]
        ?string $output = null,
    ): void
    {
    }

}
