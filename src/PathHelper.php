<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use function rtrim;
use function str_replace;
use const DIRECTORY_SEPARATOR;

final class PathHelper
{

    private readonly string $cwd;

    public function __construct(string $cwd)
    {
        $this->cwd = rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public function relativizePath(string $path): string
    {
        return str_replace($this->cwd, '', $path);
    }

}
