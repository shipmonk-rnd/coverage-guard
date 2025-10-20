<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

enum CoverageFormat: string
{

    case Clover = 'clover';
    case Cobertura = 'cobertura';

}
