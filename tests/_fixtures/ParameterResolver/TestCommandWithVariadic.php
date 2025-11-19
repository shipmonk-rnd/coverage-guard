<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

final class TestCommandWithVariadic
{

    public function __invoke(
        #[TestCliArgument('files', 'Input files')]
        string ...$files,
    ): void
    {
    }

}
