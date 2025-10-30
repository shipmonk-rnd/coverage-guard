<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

final class TestCommandWithRequiredOption
{

    public function __invoke(
        #[CliOption]
        string $output,
    ): void
    {
    }

}
