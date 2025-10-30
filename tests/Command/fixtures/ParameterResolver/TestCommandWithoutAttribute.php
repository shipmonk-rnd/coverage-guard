<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

final class TestCommandWithoutAttribute
{

    public function __invoke(
        string $input,
    ): void
    {
    }

}
