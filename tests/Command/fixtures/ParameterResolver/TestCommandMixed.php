<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

final class TestCommandMixed
{

    public function __invoke(
        #[CliArgument]
        string $input,

        #[CliOption]
        bool $verbose = false,

        #[CliOption]
        ?string $output = null,
    ): void
    {
    }

}
