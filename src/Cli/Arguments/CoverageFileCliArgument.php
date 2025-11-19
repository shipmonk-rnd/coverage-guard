<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli\Arguments;

use Attribute;
use ShipMonk\CoverageGuard\Cli\CliArgument;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class CoverageFileCliArgument extends CliArgument
{

    public function __construct()
    {
        parent::__construct('coverage-file', 'Path to PHPUnit coverage file (.xml or .cov)');
    }

}
