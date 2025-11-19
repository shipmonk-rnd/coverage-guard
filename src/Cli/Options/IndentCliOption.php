<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli\Options;

use Attribute;
use ShipMonk\CoverageGuard\Cli\CliOption;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class IndentCliOption extends CliOption
{

    public function __construct()
    {
        parent::__construct('indent', 'XML indent to use');
    }

}
