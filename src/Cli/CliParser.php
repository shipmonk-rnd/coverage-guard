<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use function array_key_exists;
use function count;
use function explode;
use function implode;
use function str_contains;
use function str_starts_with;
use function substr;

final class CliParser
{

    /**
     * Parse CLI arguments into arguments and options
     *
     * @param list<string> $args Raw CLI arguments (without script name and command name)
     * @param list<ArgumentDefinition> $argumentDefinitions
     * @param list<OptionDefinition> $optionDefinitions
     * @return array{arguments: list<string>, options: array<string, string|bool>}
     *
     * @throws ErrorException
     */
    public function parse(
        array $args,
        array $argumentDefinitions,
        array $optionDefinitions,
    ): array
    {
        $optionMap = $this->buildOptionMap($optionDefinitions);
        $all = $this->parseOptionsAndArguments($args);

        $this->validateOptions($all['options'], $optionMap);
        $this->validateArguments($all['arguments'], $argumentDefinitions);

        $options = $this->buildOptionsArray($all['options'], $optionMap);

        return [
            'arguments' => $all['arguments'],
            'options' => $options,
        ];
    }

    /**
     * Build option map for quick lookup
     *
     * @param list<OptionDefinition> $optionDefinitions
     * @return array<string, OptionDefinition>
     */
    private function buildOptionMap(array $optionDefinitions): array
    {
        $optionMap = [];
        foreach ($optionDefinitions as $opt) {
            $optionMap[$opt->name] = $opt;
        }
        return $optionMap;
    }

    /**
     * Parse all arguments into positional arguments and options
     *
     * @param list<string> $args
     * @return array{arguments: list<string>, options: array<string, string|null>}
     *
     * @throws ErrorException
     */
    private function parseOptionsAndArguments(array $args): array
    {
        $arguments = [];
        $options = [];

        $i = 0;
        $count = count($args);
        while ($i < $count) {
            if (!array_key_exists($i, $args)) {
                break; // Should not happen, but helps type analysis
            }

            $arg = $args[$i];

            if (str_starts_with($arg, '--')) {
                [$optionName, $optionValue] = $this->extractOptionNameAndValue($arg, $args, $i);
                $options[$optionName] = $optionValue;
            } else {
                $arguments[] = $arg;
            }

            $i++;
        }

        return [
            'arguments' => $arguments,
            'options' => $options,
        ];
    }

    /**
     * Extract option name and value from argument
     *
     * @param string $arg Current argument
     * @param list<string> $args All arguments
     * @param int $i Current index (will be modified if value is in next argument)
     * @return array{string, string|null}
     *
     * @throws ErrorException
     */
    private function extractOptionNameAndValue(
        string $arg,
        array $args,
        int &$i,
    ): array
    {
        $optionName = substr($arg, 2);
        $optionValue = null;

        // Check for --option=value syntax
        if (str_contains($optionName, '=')) {
            $parts = explode('=', $optionName, 2);
            if (count($parts) !== 2) {
                throw new ErrorException("Invalid option format: --{$optionName}");
            }
            [$optionName, $optionValue] = $parts;
        } elseif (isset($args[$i + 1])) {
            $nextArg = $args[$i + 1];
            if (!str_starts_with($nextArg, '--')) {
                // Value is in next argument
                $i++;
                $optionValue = $nextArg;
            }
        }

        return [$optionName, $optionValue];
    }

    /**
     * Build final options array from parsed values
     *
     * @param array<string, string|null> $parsedOptions
     * @param array<string, OptionDefinition> $optionMap
     * @return array<string, string|bool>
     */
    private function buildOptionsArray(
        array $parsedOptions,
        array $optionMap,
    ): array
    {
        $options = [];

        foreach ($parsedOptions as $optionName => $optionValue) {
            if (!isset($optionMap[$optionName])) {
                continue; // Should not happen as we validate options first
            }

            $optionDef = $optionMap[$optionName];

            if ($optionDef->requiresValue) {
                $options[$optionName] = (string) $optionValue;
            } else {
                $options[$optionName] = true;
            }
        }

        return $options;
    }

    /**
     * @param array<string, string|null> $parsedOptions Map of option names to values (null if no value provided)
     * @param array<string, OptionDefinition> $optionMap Map of option names to definitions
     *
     * @throws ErrorException
     */
    private function validateOptions(
        array $parsedOptions,
        array $optionMap,
    ): void
    {
        foreach ($parsedOptions as $optionName => $optionValue) {
            if (!isset($optionMap[$optionName])) {
                throw new ErrorException("Unknown option: --{$optionName}");
            }

            $optionDef = $optionMap[$optionName];

            if ($optionDef->requiresValue && $optionValue === null) {
                throw new ErrorException("Option --{$optionName} requires a value");
            }
        }
    }

    /**
     * @param list<string> $arguments
     * @param list<ArgumentDefinition> $argumentDefinitions
     *
     * @throws ErrorException
     */
    private function validateArguments(
        array $arguments,
        array $argumentDefinitions,
    ): void
    {
        $hasVariadic = false;
        foreach ($argumentDefinitions as $arg) {
            if ($arg->variadic) {
                $hasVariadic = true;
                break;
            }
        }

        $requiredCount = count($argumentDefinitions);
        $providedCount = count($arguments);

        if ($providedCount < $requiredCount) {
            // Find which argument is missing
            $missingArgs = [];
            foreach ($argumentDefinitions as $index => $arg) {
                if (!isset($arguments[$index])) {
                    $missingArgs[] = "<{$arg->name}>";
                }
            }

            $missingList = implode(', ', $missingArgs);
            $plural = count($missingArgs) > 1 ? 's' : '';
            throw new ErrorException("Missing required argument$plural: {$missingList}");
        }

        if (!$hasVariadic && $providedCount > $requiredCount) {
            throw new ErrorException("Too many arguments. Expected at most {$requiredCount}, got {$providedCount}");
        }
    }

}
