<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Cli;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Command\ConvertCommand;
use ShipMonk\CoverageGuard\CoverageProvider;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\Utils\ConfigResolver;
use function fopen;
use function rewind;
use function stream_get_contents;

final class CommandRunnerTest extends TestCase
{

    public function testRunConvertCommandHappyPath(): void
    {
        $registry = new CommandRegistry();
        $outputStream = $this->createMemoryStream();
        $printer = new Printer($outputStream, noColor: true);
        $configResolver = new ConfigResolver(__DIR__);

        $registry->register(new ConvertCommand(new CoverageProvider($printer), $configResolver, $outputStream));
        $runner = $this->createRunner($registry);

        $printerStream = $this->createMemoryStream();
        $printer = new Printer($printerStream, noColor: true);

        $inputFile = __DIR__ . '/../_fixtures/ConvertCommand/clover.xml';
        $argv = ['coverage-guard', 'convert', $inputFile, '--format', 'cobertura'];

        $exitCode = $runner->run($argv, $printer);

        self::assertSame(0, $exitCode);

        $output = $this->getStreamContents($outputStream);
        self::assertStringContainsString('<?xml version=', $output);
        self::assertStringContainsString('<coverage', $output);
    }

    public function testRunCommandWithHelp(): void
    {
        $registry = new CommandRegistry();
        $outputStream = $this->createMemoryStream();
        $printer = new Printer($outputStream, noColor: true);
        $configResolver = new ConfigResolver(__DIR__);

        $registry->register(new ConvertCommand(new CoverageProvider($printer), $configResolver, $outputStream));
        $runner = $this->createRunner($registry);

        $printerStream = $this->createMemoryStream();
        $printer = new Printer($printerStream, noColor: true);

        $argv = ['coverage-guard', 'convert', '--help'];

        $exitCode = $runner->run($argv, $printer);

        self::assertSame(1, $exitCode);

        $output = $this->getStreamContents($printerStream);
        self::assertStringContainsString('convert', $output);
        self::assertStringContainsString('Usage:', $output);
    }

    public function testRunWithNoArguments(): void
    {
        $registry = new CommandRegistry();
        $stream = $this->createMemoryStream();
        $printer = new Printer($stream, noColor: true);
        $configResolver = new ConfigResolver(__DIR__);
        $registry->register(new ConvertCommand(new CoverageProvider($printer), $configResolver));
        $runner = $this->createRunner($registry);

        $printerStream = $this->createMemoryStream();
        $printer = new Printer($printerStream, noColor: true);

        $argv = ['coverage-guard'];

        $exitCode = $runner->run($argv, $printer);

        self::assertSame(1, $exitCode);

        $output = $this->getStreamContents($printerStream);
        self::assertStringContainsString('Usage:', $output);
    }

    public function testRunWithUnknownCommand(): void
    {
        $registry = new CommandRegistry();
        $stream = $this->createMemoryStream();
        $printer = new Printer($stream, noColor: true);
        $configResolver = new ConfigResolver(__DIR__);
        $registry->register(new ConvertCommand(new CoverageProvider($printer), $configResolver));
        $runner = $this->createRunner($registry);

        $printerStream = $this->createMemoryStream();
        $printer = new Printer($printerStream, noColor: true);

        $argv = ['coverage-guard', 'unknown-command'];

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown command: unknown-command');

        $runner->run($argv, $printer);
    }

    /**
     * @return resource
     */
    private function createMemoryStream()
    {
        $stream = fopen('php://memory', 'w+');
        self::assertNotFalse($stream);

        return $stream;
    }

    private function createRunner(CommandRegistry $registry): CommandRunner
    {
        $parameterResolver = new ParameterResolver();

        return new CommandRunner(
            $registry,
            new CliParser(),
            $parameterResolver,
            new HelpRenderer($parameterResolver),
        );
    }

    /**
     * @param resource $stream
     */
    private function getStreamContents($stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        self::assertNotFalse($contents);

        return $contents;
    }

}
