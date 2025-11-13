<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use function array_keys;
use function array_values;
use function fwrite;
use function getenv;
use function in_array;
use function str_replace;
use function stream_isatty;
use const PHP_EOL;

final class Printer
{

    private const COLORS = [
        '<red>' => "\033[31m",
        '<green>' => "\033[32m",
        '<orange>' => "\033[33m",
        '<gray>' => "\033[37m",
        '<white>' => "\033[97m",
        '</red>' => "\033[0m",
        '</green>' => "\033[0m",
        '</orange>' => "\033[0m",
        '</gray>' => "\033[0m",
        '</white>' => "\033[0m",
    ];

    /**
     * @param resource $resource
     */
    public function __construct(
        private readonly mixed $resource,
        private readonly bool $noColor,
    )
    {
    }

    /**
     * @param resource $resource
     * @param list<string> $argv
     */
    public static function create(
        $resource,
        array $argv,
    ): self
    {
        $noColor = in_array('--no-color', $argv, true)
            || getenv('NO_COLOR') !== false
            || (!stream_isatty($resource) && !in_array('--color', $argv, true));

        return new self($resource, $noColor);
    }

    public function hasDisabledColors(): bool
    {
        return $this->noColor;
    }

    public function printLine(string $string): void
    {
        $this->print($string . PHP_EOL);
    }

    public function printWarning(
        string $string,
    ): void
    {
        $this->printLine('<orange>Warn:</orange> ' . $string);
    }

    public function printInfo(
        string $string,
    ): void
    {
        $this->printLine('<white>Info:</white> ' . $string);
    }

    public function print(string $string): void
    {
        $result = fwrite($this->resource, $this->colorize($string));

        if ($result === false) {
            throw new LogicException('Could not write to output stream.');
        }
    }

    private function colorize(string $string): string
    {
        return str_replace(
            array_keys(self::COLORS),
            $this->noColor ? '' : array_values(self::COLORS),
            $string,
        );
    }

}
