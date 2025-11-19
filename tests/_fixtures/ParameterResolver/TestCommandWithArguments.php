<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

final class TestCommandWithArguments
{

    public function __invoke(
        #[TestCliArgument('file', 'Path to file')]
        string $file,
    ): void
    {
    }

}
