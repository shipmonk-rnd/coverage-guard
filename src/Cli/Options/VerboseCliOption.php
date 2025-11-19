<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli\Options;

use Attribute;
use ShipMonk\CoverageGuard\Cli\CliOption;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class VerboseCliOption extends CliOption
{

    public function __construct()
    {
        parent::__construct('verbose', 'Print more details');
    }

}
