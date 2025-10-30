<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

final class TestCommandWithEnum
{

    public function __invoke(
        #[CliArgument]
        CoverageFormat $format,
    ): void
    {
    }

}
