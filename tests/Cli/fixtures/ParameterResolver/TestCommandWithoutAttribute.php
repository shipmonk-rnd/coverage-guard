<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

final class TestCommandWithoutAttribute
{

    public function __invoke(
        string $input,
    ): void
    {
    }

}
