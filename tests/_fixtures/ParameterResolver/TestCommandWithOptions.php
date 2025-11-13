<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

final class TestCommandWithOptions
{

    public function __invoke(
        #[CliOption(description: 'Enable verbose output')]
        bool $verbose = false,

        #[CliOption]
        ?string $config = null,
    ): void
    {
    }

}
