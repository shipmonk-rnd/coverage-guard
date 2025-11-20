<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

enum CoverageInputFormat: string
{

    case Clover = 'clover';
    case Cobertura = 'cobertura';
    case Php = 'php';

}
