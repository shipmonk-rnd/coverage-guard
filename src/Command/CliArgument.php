<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class CliArgument
{

    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    )
    {
    }

}
