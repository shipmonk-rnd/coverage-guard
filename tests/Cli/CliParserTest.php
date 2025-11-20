<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;

final class CliParserTest extends TestCase
{

    public function testParseEmptyArgs(): void
    {
        $parser = new CliParser();

        $result = $parser->parse([], [], []);

        self::assertSame([], $result['arguments']);
        self::assertSame([], $result['options']);
    }

    public function testParsePositionalArguments(): void
    {
        $parser = new CliParser();

        $arguments = [
            new ArgumentDefinition('file', 'Input file', variadic: false),
        ];

        $result = $parser->parse(['file.txt'], $arguments, []);

        self::assertSame(['file.txt'], $result['arguments']);
        self::assertSame([], $result['options']);
    }

    public function testParseVariadicArguments(): void
    {
        $parser = new CliParser();

        $arguments = [
            new ArgumentDefinition('files', 'Input files', variadic: true),
        ];

        $result = $parser->parse(['file1.txt', 'file2.txt', 'file3.txt'], $arguments, []);

        self::assertSame(['file1.txt', 'file2.txt', 'file3.txt'], $result['arguments']);
    }

    public function testParseBooleanOption(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
        ];

        $result = $parser->parse(['--verbose'], [], $options);

        self::assertSame([], $result['arguments']);
        self::assertArrayHasKey('verbose', $result['options']);
        self::assertTrue($result['options']['verbose']);
    }

    public function testParseOptionWithValue(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('config', 'Config file', acceptsValue: true, isRequired: false),
        ];

        $result = $parser->parse(['--config', 'config.php'], [], $options);

        self::assertSame([], $result['arguments']);
        self::assertArrayHasKey('config', $result['options']);
        self::assertSame('config.php', $result['options']['config']);
    }

    public function testParseOptionWithEqualsSign(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('config', 'Config file', acceptsValue: true, isRequired: false),
        ];

        $result = $parser->parse(['--config=config.php'], [], $options);

        self::assertSame([], $result['arguments']);
        self::assertArrayHasKey('config', $result['options']);
        self::assertSame('config.php', $result['options']['config']);
    }

    public function testParseMixedPositionalAndOptions(): void
    {
        $parser = new CliParser();

        $arguments = [
            new ArgumentDefinition('file', 'Input file', variadic: false),
        ];

        $options = [
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
            new OptionDefinition('config', 'Config file', acceptsValue: true, isRequired: false),
        ];

        $result = $parser->parse(['input.txt', '--verbose', '--config', 'config.php'], $arguments, $options);

        self::assertSame(['input.txt'], $result['arguments']);
        self::assertArrayHasKey('verbose', $result['options']);
        self::assertTrue($result['options']['verbose']);
        self::assertArrayHasKey('config', $result['options']);
        self::assertSame('config.php', $result['options']['config']);
    }

    public function testParseUnknownOptionThrowsException(): void
    {
        $parser = new CliParser();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --unknown, see --help');

        $parser->parse(['--unknown'], [], []);
    }

    public function testParseOptionRequiringValueWithoutValueThrowsException(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('config', 'Config file', acceptsValue: true, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Option --config requires a value');

        $parser->parse(['--config'], [], $options);
    }

    public function testParseTooFewPositionalArgumentsThrowsException(): void
    {
        $parser = new CliParser();

        $arguments = [
            new ArgumentDefinition('file1', 'First file', variadic: false),
            new ArgumentDefinition('file2', 'Second file', variadic: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Missing required argument: <file2>');

        $parser->parse(['only-one.txt'], $arguments, []);
    }

    public function testParseTooManyPositionalArgumentsThrowsException(): void
    {
        $parser = new CliParser();

        $arguments = [
            new ArgumentDefinition('file', 'Input file', variadic: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Too many arguments');

        $parser->parse(['file1.txt', 'file2.txt'], $arguments, []);
    }

    public function testParseBooleanOptionBeforePositionalArgument(): void
    {
        $parser = new CliParser();

        $arguments = [
            new ArgumentDefinition('file', 'Input file', variadic: false),
        ];

        $options = [
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
        ];

        // Test with option before argument: --verbose file.xml
        $result = $parser->parse(['--verbose', 'file.xml'], $arguments, $options);

        self::assertSame(['file.xml'], $result['arguments']);
        self::assertArrayHasKey('verbose', $result['options']);
        self::assertTrue($result['options']['verbose']);
    }

    public function testParseBooleanOptionAfterPositionalArgument(): void
    {
        $parser = new CliParser();

        $arguments = [
            new ArgumentDefinition('file', 'Input file', variadic: false),
        ];

        $options = [
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
        ];

        // Test with option after argument: file.xml --verbose
        $result = $parser->parse(['file.xml', '--verbose'], $arguments, $options);

        self::assertSame(['file.xml'], $result['arguments']);
        self::assertArrayHasKey('verbose', $result['options']);
        self::assertTrue($result['options']['verbose']);
    }

    public function testParseMultipleBooleanOptionsAndPositionalArgument(): void
    {
        $parser = new CliParser();

        $arguments = [
            new ArgumentDefinition('file', 'Input file', variadic: false),
        ];

        $options = [
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
            new OptionDefinition('debug', 'Debug mode', acceptsValue: false, isRequired: false),
        ];

        // Test with options in various positions
        $result = $parser->parse(['--verbose', 'file.xml', '--debug'], $arguments, $options);

        self::assertSame(['file.xml'], $result['arguments']);
        self::assertArrayHasKey('verbose', $result['options']);
        self::assertTrue($result['options']['verbose']);
        self::assertArrayHasKey('debug', $result['options']);
        self::assertTrue($result['options']['debug']);
    }

    public function testParseBooleanOptionWithValueThrowsException(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Option --verbose does not accept a value');

        $parser->parse(['--verbose=something'], [], $options);
    }

    public function testParseDuplicateBooleanOptionThrowsException(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Option --verbose cannot be specified multiple times');

        $parser->parse(['--verbose', '--verbose'], [], $options);
    }

    public function testParseDuplicateOptionWithValueThrowsException(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('config', 'Config file', acceptsValue: true, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Option --config cannot be specified multiple times');

        $parser->parse(['--config', 'first.php', '--config', 'second.php'], [], $options);
    }

    public function testParseDuplicateOptionWithEqualsSignThrowsException(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('config', 'Config file', acceptsValue: true, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Option --config cannot be specified multiple times');

        $parser->parse(['--config=first.php', '--config=second.php'], [], $options);
    }

    public function testParseMissingRequiredOptionThrowsException(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('format', 'Output format', acceptsValue: true, isRequired: true),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Missing required option: --format');

        $parser->parse([], [], $options);
    }

    public function testParseUnknownOptionWithTypoSuggestsSimilarOption(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('config', 'Config file', acceptsValue: true, isRequired: false),
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --confg. Did you mean: --config?');

        $parser->parse(['--confg', 'file.php'], [], $options);
    }

    public function testParseUnknownOptionWithSubstringSuggestsMatchingOption(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('output-format', 'Output format', acceptsValue: true, isRequired: false),
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --output. Did you mean: --output-format?');

        $parser->parse(['--output', 'clover'], [], $options);
    }

    public function testParseUnknownOptionWithSubstringInMiddleSuggestsMatchingOption(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('output-format', 'Output format', acceptsValue: true, isRequired: false),
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --format. Did you mean: --output-format?');

        $parser->parse(['--format', 'clover'], [], $options);
    }

    public function testParseUnknownOptionWithNoSimilarOptionPointsToHelp(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('config', 'Config file', acceptsValue: true, isRequired: false),
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --totally-invalid-option, see --help');

        $parser->parse(['--totally-invalid-option'], [], $options);
    }

    public function testParseUnknownOptionPrefersShortestSubstringMatch(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('config', 'Config file', acceptsValue: true, isRequired: false),
            new OptionDefinition('config-path', 'Config path', acceptsValue: true, isRequired: false),
            new OptionDefinition('config-path-override', 'Config path override', acceptsValue: true, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --conf. Did you mean: --config?');

        $parser->parse(['--conf', 'file.php'], [], $options);
    }

    public function testParseShortOptionThrowsException(): void
    {
        $parser = new CliParser();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: -x, short options are not supported');

        $parser->parse(['-x'], [], []);
    }

    public function testParseShortOptionWithValueThrowsException(): void
    {
        $parser = new CliParser();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: -o, short options are not supported');

        $parser->parse(['-o', 'value'], [], []);
    }

    public function testParseShortOptionEvenWhenLongOptionExistsThrowsException(): void
    {
        $parser = new CliParser();

        $options = [
            new OptionDefinition('verbose', 'Verbose output', acceptsValue: false, isRequired: false),
        ];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: -v, short options are not supported');

        $parser->parse(['-v'], [], $options);
    }

    public function testParseSingleDashAsArgumentIsAllowed(): void
    {
        $parser = new CliParser();

        $arguments = [
            new ArgumentDefinition('file', 'Input file', variadic: false),
        ];

        // Single dash "-" is commonly used to mean stdin/stdout in Unix tools
        $result = $parser->parse(['-'], $arguments, []);

        self::assertSame(['-'], $result['arguments']);
    }

}
