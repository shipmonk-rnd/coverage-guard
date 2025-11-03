<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

final class TestCommandWithCustomName
{

    public function __invoke(
        #[CliArgument('custom-file')]
        string $inputFile,
    ): void
    {
    }

}
