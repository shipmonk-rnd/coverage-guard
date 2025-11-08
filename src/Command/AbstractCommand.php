<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use function realpath;

abstract class AbstractCommand implements Command
{

    /**
     * Get realpath or null if file doesn't exist
     */
    protected function tryRealpath(string $path): ?string
    {
        $realpath = realpath($path);
        return $realpath === false ? null : $realpath;
    }

}
