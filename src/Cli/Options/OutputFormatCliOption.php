<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli\Options;

use Attribute;
use ShipMonk\CoverageGuard\Cli\CliOption;
use ShipMonk\CoverageGuard\Cli\CoverageOutputFormat;
use function array_map;
use function implode;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class OutputFormatCliOption extends CliOption
{

    public function __construct()
    {
        $formats = implode('|', array_map(static fn (CoverageOutputFormat $format): string => $format->value, CoverageOutputFormat::cases()));
        parent::__construct('output-format', "Coverage format, use {$formats}");
    }

}
