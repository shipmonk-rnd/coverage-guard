<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

final class ArgumentDefinition
{

    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly bool $variadic,
    )
    {
    }

}
