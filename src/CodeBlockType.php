<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

enum CodeBlockType
{

    case ClassMethod;
    case Foreach;
    case For;
    case While;
    case If;
    case ElseIf;
    case Else;
    case Catch;
    case Finally;

}
