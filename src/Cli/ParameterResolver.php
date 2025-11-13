<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use BackedEnum;
use LogicException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function array_map;
use function array_shift;
use function enum_exists;
use function implode;
use function is_a;
use function is_bool;
use function is_numeric;
use function preg_replace;
use function strtolower;

final class ParameterResolver
{

    /**
     * @return list<ArgumentDefinition>
     */
    public function getArgumentDefinitions(ReflectionMethod $method): array
    {
        $definitions = [];

        foreach ($method->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes(CliArgument::class);

            if ($attributes === []) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $name = $attribute->name ?? $this->parameterNameToCliName($parameter->getName());
            $description = $attribute->description ?? '';

            $definitions[] = new ArgumentDefinition(
                $name,
                $description,
                $parameter->isVariadic(),
            );
        }

        return $definitions;
    }

    /**
     * @return list<OptionDefinition>
     */
    public function getOptionDefinitions(ReflectionMethod $method): array
    {
        $definitions = [];

        foreach ($method->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes(CliOption::class);

            if ($attributes === []) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $name = $attribute->name ?? $this->parameterNameToCliName($parameter->getName());
            $description = $attribute->description ?? '';
            $acceptsValue = !$this->isBooleanParameter($parameter);
            $isRequired = !$parameter->allowsNull() && !$parameter->isDefaultValueAvailable();

            $definitions[] = new OptionDefinition(
                $name,
                $description,
                $acceptsValue,
                $isRequired,
            );
        }

        return $definitions;
    }

    /**
     * Resolve method parameters from parsed CLI arguments and options
     *
     * @param list<string> $arguments
     * @param array<string, string|bool> $options
     * @return list<string|int|BackedEnum|bool|null>
     *
     * @throws ErrorException
     */
    public function resolveParameters(
        ReflectionMethod $method,
        array $arguments,
        array $options,
    ): array
    {
        $resolved = [];

        foreach ($method->getParameters() as $parameter) {
            $argumentAttrs = $parameter->getAttributes(CliArgument::class);
            $optionAttrs = $parameter->getAttributes(CliOption::class);

            if ($argumentAttrs !== []) {
                if ($parameter->isVariadic()) { // only last parameter can be variadic
                    $resolved = [
                        ...$resolved,
                        ...$arguments,
                    ];
                } else {
                    $value = array_shift($arguments);
                    if ($value !== null) {
                        $resolved[] = $this->coerceType($value, $parameter);
                    }
                }
            } elseif ($optionAttrs !== []) {
                $resolved[] = $this->resolveOption($parameter, $options);
            } else {
                throw new LogicException("Parameter {$parameter->getName()} of {$method->getName()} is neither an argument nor an option");
            }
        }

        return $resolved;
    }

    /**
     * Resolve a single option parameter
     *
     * @param array<string, string|bool> $options
     *
     * @throws ErrorException
     */
    private function resolveOption(
        ReflectionParameter $parameter,
        array $options,
    ): string|int|BackedEnum|bool|null
    {
        $attributes = $parameter->getAttributes(CliOption::class);
        if ($attributes === []) {
            throw new LogicException("Parameter {$parameter->getName()} does not have CliOption attribute");
        }

        $attribute = $attributes[0]->newInstance();
        $optionName = $attribute->name ?? $this->parameterNameToCliName($parameter->getName());

        if (!isset($options[$optionName])) {
            if ($parameter->allowsNull()) {
                return null;
            }

            if ($parameter->isDefaultValueAvailable()) {
                /** @var string|int|BackedEnum|bool|null $default */
                $default = $parameter->getDefaultValue(); // @phpstan-ignore missingType.checkedException (cannot throw, checked above)
                return $default;
            }

            throw new ErrorException("Required option --{$optionName} not provided");
        }

        $value = $options[$optionName];

        if ($this->isBooleanParameter($parameter)) {
            if (!is_bool($value)) {
                throw new ErrorException("Option --{$optionName} does not expect a value, but got '{$value}'");
            }

            return $value === true;
        }

        if (is_bool($value)) {
            throw new ErrorException("Option --{$optionName} requires a value");
        }

        return $this->coerceType($value, $parameter);
    }

    /**
     * Coerce a string value to the parameter's type
     *
     * @throws ErrorException
     */
    private function coerceType(
        string $value,
        ReflectionParameter $parameter,
    ): string|int|BackedEnum
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType) {
            throw new LogicException("Parameter {$parameter->getName()} is missing native type");
        }

        $typeName = $type->getName();

        if (enum_exists($typeName) && is_a($typeName, BackedEnum::class, true)) {
            foreach ($typeName::cases() as $case) {
                if ($case->value === $value) {
                    return $case;
                }
            }

            $validValues = array_map(static fn (BackedEnum $case) => $case->value, $typeName::cases());
            throw new ErrorException("Invalid value '{$value}' for {$parameter->getName()}. Expected one of: " . implode(', ', $validValues));
        }

        if ($typeName === 'string') {
            return $value;
        }

        if ($typeName === 'int') {
            if (!is_numeric($value)) {
                throw new ErrorException("Invalid value '{$value}' for {$parameter->getName()}. Expected an integer.");
            }
            return (int) $value;
        }

        throw new LogicException("Unsupported type {$typeName} in parameter {$parameter->getName()}");
    }

    private function isBooleanParameter(ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        return $type->getName() === 'bool';
    }

    /**
     * camelCase -> kebab-case
     */
    private function parameterNameToCliName(string $name): string
    {
        $kebab = preg_replace('/([a-z])([A-Z])/', '$1-$2', $name);
        return strtolower($kebab ?? $name);
    }

}
