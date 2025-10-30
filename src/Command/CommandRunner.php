<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use LogicException;
use ReflectionException;
use ReflectionMethod;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function array_filter;
use function array_shift;
use function array_values;
use function in_array;
use function is_int;
use function str_starts_with;

final class CommandRunner
{

    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly CliParser $cliParser,
        private readonly ParameterResolver $parameterResolver,
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

        $command = $this->registry->getCommand($commandName);
        $invokeMethod = $this->getInvokeMethod($command);

        $argumentDefinitions = $this->parameterResolver->getArgumentDefinitions($invokeMethod);
        $optionDefinitions = $this->parameterResolver->getOptionDefinitions($invokeMethod);

        $parsed = $this->cliParser->parse(
            $argv,
            $argumentDefinitions,
            $optionDefinitions,
        );

        $parameters = $this->parameterResolver->resolveParameters(
            $invokeMethod,
            $parsed['arguments'],
            $parsed['options'],
        );

        try {
            $exitCode = $invokeMethod->invokeArgs($command, $parameters);
        } catch (ReflectionException $e) {
            throw new LogicException('Could not invoke command: ' . $e->getMessage(), 0, $e);
        }

        if (!is_int($exitCode)) {
            throw new LogicException('Command must return an integer exit code');
        }
        return $exitCode;
    }

    private function getInvokeMethod(Command $command): ReflectionMethod
    {
        try {
            return new ReflectionMethod($command, '__invoke');
        } catch (ReflectionException $e) {
            throw new LogicException('Could not get reflection for __invoke() method', 0, $e);
        }
    }

}
