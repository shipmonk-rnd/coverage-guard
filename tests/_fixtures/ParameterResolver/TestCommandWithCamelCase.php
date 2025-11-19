<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

final class TestCommandWithCamelCase
{

    public function __invoke(
        #[TestCliArgument('input-file')]
        string $inputFile,
    ): void
    {
    }

}
