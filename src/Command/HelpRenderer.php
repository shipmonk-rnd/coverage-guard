<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Printer;
use function implode;
use function str_pad;
use function str_repeat;

final class HelpRenderer
{

    private const INDENT = '  ';
    private const OPTION_PADDING = 25;

    public function renderCommandHelp(
        Command $command,
        Printer $printer,
    ): void
    {
        $printer->printLine("<white>{$command->getDescription()}</white>");
        $printer->printLine('');
        $printer->printLine('<white>Usage:</white>');

        $usageParts = ['coverage-guard', $command->getName()];

        foreach ($command->getArguments() as $arg) {
            $argStr = $arg->variadic ? "{$arg->name}..." : $arg->name;
            $usageParts[] = "<{$argStr}>";
        }

        if ($command->getOptions() !== []) {
            $usageParts[] = '[options]';
        }

        $printer->printLine(self::INDENT . implode(' ', $usageParts));
        $printer->printLine('');

        // Arguments
        if ($command->getArguments() !== []) {
            $printer->printLine('<white>Arguments:</white>');
            foreach ($command->getArguments() as $arg) {
                $argName = $arg->variadic ? "{$arg->name}..." : $arg->name;
                $printer->printLine(self::INDENT . "<green>{$argName}</green>");
                $printer->printLine(str_repeat(self::INDENT, 2) . $arg->description);
            }
            $printer->printLine('');
        }

        // Options
        if ($command->getOptions() !== []) {
            $printer->printLine('<white>Options:</white>');
            foreach ($command->getOptions() as $opt) {
                $optStr = $opt->requiresValue ? "--{$opt->name} <value>" : "--{$opt->name}";
                $optStrPadded = str_pad($optStr, self::OPTION_PADDING);
                $printer->printLine(self::INDENT . "<green>{$optStrPadded}</green>{$opt->description}");
            }
        }
    }

    public function renderGeneralHelp(
        CommandRegistry $registry,
        Printer $printer,
    ): void
    {
        $printer->printLine('<white>PHP Code Coverage Guard</white>');
        $printer->printLine('');
        $printer->printLine('<white>Usage:</white>');
        $printer->printLine(self::INDENT . 'coverage-guard <command> [arguments] [options]');
        $printer->printLine('');
        $printer->printLine('<white>Available commands:</white>');

        foreach ($registry->getAllCommands() as $command) {
            $namePadded = str_pad($command->getName(), 20);
            $printer->printLine(self::INDENT . "<green>{$namePadded}</green>{$command->getDescription()}");
        }

        $printer->printLine('');
        $printer->printLine('<white>Global options:</white>');
        $printer->printLine(self::INDENT . '<green>--help             </green>Show help message');
        $printer->printLine(self::INDENT . '<green>--verbose          </green>Show detailed processing information');
        $printer->printLine(self::INDENT . '<green>--no-color         </green>Disable colored output');
        $printer->printLine(self::INDENT . '<green>--color            </green>Force colored output');
        $printer->printLine('');
        $printer->printLine('Run "coverage-guard <command> --help" for more information on a specific command.');
    }

}
