<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

final class TestCommandWithCamelCase
{

    public function __invoke(
        #[CliArgument]
        string $inputFile,
    ): void
    {
    }

}
