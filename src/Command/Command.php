<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;

interface Command
{

    public function getName(): string;

    public function getDescription(): string;

    /**
     * @return list<Argument>
     */
    public function getArguments(): array;

    /**
     * @return list<Option>
     */
    public function getOptions(): array;

    /**
     * @param list<string> $arguments
     * @param array<string, string|bool> $options
     * @return int Exit code
     *
     * @throws ErrorException
     */
    public function execute(
        array $arguments,
        array $options,
        Printer $printer,
    ): int;

}
