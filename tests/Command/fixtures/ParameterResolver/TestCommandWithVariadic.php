<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

final class TestCommandWithVariadic
{

    public function __invoke(
        #[CliArgument('files', 'Input files')]
        string ...$files,
    ): void
    {
    }

}
