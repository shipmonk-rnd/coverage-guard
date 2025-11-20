<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

enum CoverageOutputFormat: string
{

    case Clover = 'clover';
    case Cobertura = 'cobertura';

}
