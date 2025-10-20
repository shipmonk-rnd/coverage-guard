<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function array_filter;
use function array_shift;
use function array_values;
use function in_array;
use function str_starts_with;

final class CommandRunner
{

    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly CliParser $cliParser,
        private readonly HelpRenderer $helpRenderer,
    )
    {
    }

    /**
     * @param list<string> $argv Raw CLI arguments including script name
     * @return int Exit code
     *
     * @throws ErrorException
     */
    public function run(
        array $argv,
        Printer $printer,
    ): int
    {
        // Remove script name
        array_shift($argv);

        // Show general help if: no arguments, or only --help without a command
        if ($argv === [] || $argv === ['--help']) {
            $this->helpRenderer->renderGeneralHelp($this->registry, $printer);
            return 1;
        }

        // Remove --no-color and --color from argv as they're already processed
        $argv = array_values(array_filter($argv, static fn (string $arg) => $arg !== '--no-color' && $arg !== '--color'));

        // Extract command name
        $commandName = array_shift($argv);

        if ($commandName === null || str_starts_with($commandName, '--')) {
            throw new ErrorException('No command specified. Use --help to see available commands.');
        }

        // Check for command-specific help
        if (in_array('--help', $argv, true)) {
            $command = $this->registry->getCommand($commandName);
            $this->helpRenderer->renderCommandHelp($command, $printer);
            return 1;
        }

        // Get the command
        $command = $this->registry->getCommand($commandName);

        // Parse command arguments and options
        $parsed = $this->cliParser->parse(
            $argv,
            $command->getArguments(),
            $command->getOptions(),
        );

        // Execute the command
        return $command->execute($parsed['arguments'], $parsed['options'], $printer);
    }

}
