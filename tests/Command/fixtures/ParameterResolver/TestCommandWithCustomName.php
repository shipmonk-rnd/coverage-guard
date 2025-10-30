<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

final class TestCommandWithCustomName
{

    public function __invoke(
        #[CliArgument('custom-file')]
        string $inputFile,
    ): void
    {
    }

}
