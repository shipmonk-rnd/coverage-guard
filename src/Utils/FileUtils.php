<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use function array_map;
use function count;
use function file;
use function realpath;
use function rtrim;

final class FileUtils
{

    /**
     * Returns lines without EOL chars, normalizes EOF
     *
     * @return list<string>
     *
     * @throws ErrorException
     */
    public static function readFileLines(string $file): array
    {
        $lines = file($file);
        if ($lines === false) {
            throw new ErrorException("Failed to read file: {$file}");
        }

        if ($lines === []) {
            return [];
        }

        $lastLineIndex = count($lines) - 1;
        $lastLine = $lines[$lastLineIndex];

        if (rtrim($lastLine, "\n\r") !== $lastLine) {
            $lines[] = ''; // if last line ends with newline, add empty line to ensure expected number of lines is reached
        }

        return array_map(static fn (string $line): string => rtrim($line, "\n\r"), $lines);
    }

    /**
     * @throws ErrorException
     */
    public static function realpath(string $path): string
    {
        $realpath = realpath($path);
        if ($realpath === false) {
            throw new ErrorException("Could not realpath '$path'");
        }
        return $realpath;
    }

}
