<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli\Options;

use Attribute;
use ShipMonk\CoverageGuard\Cli\CliOption;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use function array_map;
use function implode;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class OutputFormatCliOption extends CliOption
{

    public function __construct()
    {
        $formats = implode('|', array_map(static fn (CoverageFormat $format): string => $format->value, CoverageFormat::cases()));
        parent::__construct('output-format', "Coverage format, use {$formats}");
    }

}
