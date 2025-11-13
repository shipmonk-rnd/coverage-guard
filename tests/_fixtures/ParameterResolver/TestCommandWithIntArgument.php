<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

final class TestCommandWithIntArgument
{

    public function __invoke(
        #[CliArgument]
        int $count,
    ): void
    {
    }

}
