<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

final class TestCommandWithRequiredOption
{

    public function __invoke(
        #[CliOption]
        string $output,
    ): void
    {
    }

}
