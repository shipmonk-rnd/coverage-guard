<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class TestCliArgument extends CliArgument
{

    public function __construct(
        string $name,
        string $description = null,
    )
    {
        parent::__construct($name, $description ?? '-');
    }

}
