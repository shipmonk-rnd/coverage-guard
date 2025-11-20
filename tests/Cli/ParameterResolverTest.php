<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ShipMonk\CoverageGuard\Exception\ErrorException;

final class ParameterResolverTest extends TestCase
{

    public function testGetArgumentDefinitionsWithCliArgument(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithArguments::class, '__invoke');

        $definitions = $resolver->getArgumentDefinitions($method);

        self::assertCount(1, $definitions);
        self::assertSame('file', $definitions[0]->name);
        self::assertSame('Path to file', $definitions[0]->description);
        self::assertFalse($definitions[0]->variadic);
    }

    public function testGetArgumentDefinitionsWithVariadic(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithVariadic::class, '__invoke');

        $definitions = $resolver->getArgumentDefinitions($method);

        self::assertCount(1, $definitions);
        self::assertSame('files', $definitions[0]->name);
        self::assertTrue($definitions[0]->variadic);
    }

    public function testGetArgumentDefinitionsWithCustomName(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithCustomName::class, '__invoke');

        $definitions = $resolver->getArgumentDefinitions($method);

        self::assertCount(1, $definitions);
        self::assertSame('custom-file', $definitions[0]->name);
    }

    public function testGetArgumentDefinitionsWithCamelCaseConversion(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithCamelCase::class, '__invoke');

        $definitions = $resolver->getArgumentDefinitions($method);

        self::assertCount(1, $definitions);
        self::assertSame('input-file', $definitions[0]->name);
    }

    public function testGetOptionDefinitionsWithCliOption(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithOptions::class, '__invoke');

        $definitions = $resolver->getOptionDefinitions($method);

        self::assertCount(2, $definitions);
        self::assertSame('verbose', $definitions[0]->name);
        self::assertSame('Print more details', $definitions[0]->description);
        self::assertFalse($definitions[0]->acceptsValue);

        self::assertSame('config', $definitions[1]->name);
        self::assertTrue($definitions[1]->acceptsValue);
    }

    public function testResolveParametersWithStringArgument(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithArguments::class, '__invoke');

        $resolved = $resolver->resolveParameters($method, ['test.txt'], []);

        self::assertCount(1, $resolved);
        self::assertSame('test.txt', $resolved[0]);
    }

    public function testResolveParametersWithIntArgument(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithIntArgument::class, '__invoke');

        $resolved = $resolver->resolveParameters($method, ['42'], []);

        self::assertCount(1, $resolved);
        self::assertSame(42, $resolved[0]);
    }

    public function testResolveParametersWithInvalidIntThrowsException(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithIntArgument::class, '__invoke');

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Invalid value 'not-a-number' for count. Expected an integer.");

        $resolver->resolveParameters($method, ['not-a-number'], []);
    }

    public function testResolveParametersWithVariadic(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithVariadic::class, '__invoke');

        $resolved = $resolver->resolveParameters($method, ['file1.txt', 'file2.txt', 'file3.txt'], []);

        self::assertCount(3, $resolved);
        self::assertSame('file1.txt', $resolved[0]);
        self::assertSame('file2.txt', $resolved[1]);
        self::assertSame('file3.txt', $resolved[2]);
    }

    public function testResolveParametersWithBooleanOption(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithOptions::class, '__invoke');

        $resolved = $resolver->resolveParameters($method, [], ['verbose' => true]);

        self::assertCount(2, $resolved);
        self::assertTrue($resolved[0]);
        self::assertNull($resolved[1]);
    }

    public function testResolveParametersWithStringOption(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithOptions::class, '__invoke');

        $resolved = $resolver->resolveParameters($method, [], ['config' => 'config.php']);

        self::assertCount(2, $resolved);
        self::assertFalse($resolved[0]); // default value
        self::assertSame('config.php', $resolved[1]);
    }

    public function testResolveParametersWithNullableOption(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithOptions::class, '__invoke');

        $resolved = $resolver->resolveParameters($method, [], []);

        self::assertCount(2, $resolved);
        self::assertFalse($resolved[0]); // default value
        self::assertNull($resolved[1]); // nullable
    }

    public function testResolveParametersWithBooleanOptionGivenValueThrowsException(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithOptions::class, '__invoke');

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Option --verbose does not expect a value, but got 'some-value'");

        $resolver->resolveParameters($method, [], ['verbose' => 'some-value']);
    }

    public function testResolveParametersWithStringOptionMissingValueThrowsException(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithRequiredOption::class, '__invoke');

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Option --output-format requires a value');

        $resolver->resolveParameters($method, [], ['output-format' => true]);
    }

    public function testResolveParametersWithEnumArgument(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithEnum::class, '__invoke');

        $resolved = $resolver->resolveParameters($method, ['clover'], []);

        self::assertCount(1, $resolved);
        self::assertInstanceOf(CoverageOutputFormat::class, $resolved[0]);
        self::assertSame(CoverageOutputFormat::Clover, $resolved[0]);
    }

    public function testResolveParametersWithInvalidEnumValueThrowsException(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithEnum::class, '__invoke');

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Invalid value 'invalid' for format. Expected one of: clover, cobertura");

        $resolver->resolveParameters($method, ['invalid'], []);
    }

    public function testResolveParametersWithMixedArgumentsAndOptions(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandMixed::class, '__invoke');

        $resolved = $resolver->resolveParameters(
            $method,
            ['input.txt'],
            ['verbose' => true, 'output-format' => 'output.txt'],
        );

        self::assertCount(3, $resolved);
        self::assertSame('input.txt', $resolved[0]);
        self::assertTrue($resolved[1]);
        self::assertSame('output.txt', $resolved[2]);
    }

    public function testResolveParametersWithParameterWithoutAttributeThrowsException(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithoutAttribute::class, '__invoke');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Parameter input of __invoke is neither an argument nor an option');

        $resolver->resolveParameters($method, [], []);
    }

    public function testResolveParametersWithRequiredOptionNotProvidedThrowsException(): void
    {
        $resolver = new ParameterResolver();
        $method = new ReflectionMethod(TestCommandWithRequiredOption::class, '__invoke');

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Required option --output-format not provided');

        $resolver->resolveParameters($method, [], []);
    }

}
