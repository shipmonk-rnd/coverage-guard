<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class CliOption
{

    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    )
    {
    }

}
