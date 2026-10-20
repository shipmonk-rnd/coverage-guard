<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
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
        $relativePath = str_starts_with($path, $this->cwd)
            ? substr($path, strlen($this->cwd))
            : $path;

        return str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    }

}
