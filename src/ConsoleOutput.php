<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use function fwrite;
use const STDERR;
use const STDOUT;

final class ConsoleOutput
{

    public function writeLine(string $message): void
    {
        fwrite(STDOUT, $message . "\n");
    }

    public function writeErrorLine(string $message): void
    {
        fwrite(STDERR, $message . "\n");
    }

}
