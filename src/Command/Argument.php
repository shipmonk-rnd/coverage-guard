<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

final class Argument
{

    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly bool $variadic = false,
    )
    {
    }

}
