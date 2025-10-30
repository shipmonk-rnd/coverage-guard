<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

final class TestCommandWithArguments
{

    public function __invoke(
        #[CliArgument('file', 'Path to file')]
        string $file,
    ): void
    {
    }

}
