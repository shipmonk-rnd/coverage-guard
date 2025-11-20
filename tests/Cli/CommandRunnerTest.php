<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Cli\Options\VerboseCliOption;
use ShipMonk\CoverageGuard\Command\Command;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\StreamTestTrait;

final class CommandRunnerTest extends TestCase
{

    use StreamTestTrait;

    public function testRunConvertCommandHappyPath(): void
    {
        $runner = $this->createRunner();

        $argv = ['coverage-guard', 'test', 'arg', '--verbose=value'];

        $exitCode = $runner->run($argv);

        self::assertSame(1234, $exitCode);
    }

    public function testRunCommandWithHelp(): void
    {
        $stream = $this->createStream();
        $runner = $this->createRunner($stream);

        $argv = ['coverage-guard', 'test', '--help'];

        $exitCode = $runner->run($argv);

        self::assertSame(1, $exitCode);

        $expectedFile = __DIR__ . '/../_fixtures/CommandRunnerTest/command-help-expected.txt';
        $this->assertStreamMatchesFile($stream, $expectedFile);
    }

    public function testRunWithNoArguments(): void
    {
        $stream = $this->createStream();
        $runner = $this->createRunner($stream);

        $argv = ['coverage-guard'];

        $exitCode = $runner->run($argv);

        self::assertSame(1, $exitCode);

        $expectedFile = __DIR__ . '/../_fixtures/CommandRunnerTest/no-arguments-expected.txt';
        $this->assertStreamMatchesFile($stream, $expectedFile);
    }

    public function testRunWithUnknownCommand(): void
    {
        $runner = $this->createRunner();

        $argv = ['coverage-guard', 'unknown-command'];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown command: unknown-command');

        $runner->run($argv);
    }

    public function testRunWithTypoInGlobalOptionSuggestsCorrectOption(): void
    {
        $runner = $this->createRunner();

        $argv = ['coverage-guard', 'test', 'arg', '--helpp'];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --helpp. Did you mean: --help?');

        $runner->run($argv);
    }

    public function testRunWithTypoInColorOptionSuggestsCorrectOption(): void
    {
        $runner = $this->createRunner();

        $argv = ['coverage-guard', 'test', 'arg', '--colours'];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --colours. Did you mean: --color?');

        $runner->run($argv);
    }

    public function testRunWithTypoInNoColorOptionSuggestsCorrectOption(): void
    {
        $runner = $this->createRunner();

        $argv = ['coverage-guard', 'test', 'arg', '--no-colours'];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --no-colours. Did you mean: --no-color?');

        $runner->run($argv);
    }

    /**
     * @param resource|null $outputStream
     */
    private function createRunner(mixed $outputStream = null): CommandRunner
    {
        $registry = new CommandRegistry();
        $registry->register(new class implements Command {

            public function __invoke(
                #[TestCliArgument(name: 'argument', description: 'Argument description')]
                string $argument,

                #[VerboseCliOption]
                string $option,
            ): int
            {
                return 1234;
            }

            public function getName(): string
            {
                return 'test';
            }

            public function getDescription(): string
            {
                return 'Test command';
            }

        });

        $stream = $outputStream ?? $this->createStream();
        $parameterResolver = new ParameterResolver();

        return new CommandRunner(
            new Printer($stream, noColor: true),
            $registry,
            new CliParser(),
            $parameterResolver,
            new HelpRenderer($parameterResolver),
        );
    }

}
