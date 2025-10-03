<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use function array_keys;
use function array_values;
use function fwrite;
use function str_replace;
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
     * @var resource
     */
    private $resource;

    private bool $noColor;

    /**
     * @param resource $resource
     */
    public function __construct(
        $resource,
        bool $noColor,
    )
    {
        $this->resource = $resource;
        $this->noColor = $noColor;
    }

    public function hasDisabledColors(): bool
    {
        return $this->noColor;
    }

    public function printLine(string $string): void
    {
        $this->print($string . PHP_EOL);
    }

    public function printWarning(string $string): void
    {
        $this->printLine('');
        $this->printLine('<orange>Warning:</orange> ' . $string);
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
