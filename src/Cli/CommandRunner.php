<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use LogicException;
use ReflectionException;
use ReflectionMethod;
use ShipMonk\CoverageGuard\Command\Command;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function array_filter;
use function array_keys;
use function array_shift;
use function array_values;
use function in_array;
use function is_int;

final class CommandRunner
{

    public const GLOBAL_OPTIONS = [
        'help' => 'Show help message',
        'color' => 'Force colored output',
        'no-color' => 'Disable colored output',
    ];

    public function __construct(
        private readonly Printer $printer,
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
    ): int
    {
        array_shift($argv); // remove script name
        $commandName = array_shift($argv);

        if ($commandName === null || $commandName === '--help') {
            $this->helpRenderer->renderGeneralHelp($this->registry, $this->printer);
            return 1;
        }

        $command = $this->registry->getCommand($commandName);

        // command-specific help
        if (in_array('--help', $argv, true)) {
            $this->helpRenderer->renderCommandHelp($command, $this->printer);
            return 1;
        }

        $commandArgs = $this->removeGlobalCliOptions($argv);

        $invokeMethod = $this->getInvokeMethod($command);
        $argumentDefinitions = $this->parameterResolver->getArgumentDefinitions($invokeMethod);
        $optionDefinitions = $this->parameterResolver->getOptionDefinitions($invokeMethod);

        $globalOptionNames = array_keys(self::GLOBAL_OPTIONS);

        $parsed = $this->cliParser->parse(
            $commandArgs,
            $argumentDefinitions,
            $optionDefinitions,
            $globalOptionNames,
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

    /**
     * @param list<string> $argv
     * @return list<string>
     */
    private function removeGlobalCliOptions(array $argv): array
    {
        $globalOptionNames = array_keys(self::GLOBAL_OPTIONS);

        return array_values(array_filter($argv, static function (string $arg) use ($globalOptionNames): bool {
            foreach ($globalOptionNames as $name) {
                if ($arg === "--{$name}") {
                    return false;
                }
            }
            return true;
        }));
    }

}
