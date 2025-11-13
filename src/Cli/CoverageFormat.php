<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

enum CoverageFormat: string
{

    case Clover = 'clover';
    case Cobertura = 'cobertura';

}
