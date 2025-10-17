<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use function fopen;
use function str_replace;
use const DIRECTORY_SEPARATOR;

final class InitializerTest extends TestCase
{

    private function createTestPrinter(): Printer
    {
        $resource = fopen('php://memory', 'w');

        if ($resource === false) {
            throw new RuntimeException('Failed to open php://memory for writing');
        }

        return new Printer($resource, true);
    }

    private function createInitializer(): Initializer
    {
        return new Initializer($this->createTestPrinter());
    }

    public function testInitializeWithCoverageFileOnly(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $result = $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile]);

        self::assertSame($coverageFile, $result->cliOptions->coverageFile);
        self::assertNull($result->cliOptions->patchFile);
    }

    public function testInitializeFailsWhenNoArgumentsProvided(): void
    {
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageMatches('/Missing coverage file argument\./');

        $initializer->initialize(__DIR__, ['coverage-guard']);
    }

    public function testInitializeFailsWhenCoverageFileDoesNotExist(): void
    {
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Coverage file not found: non-existent.xml');

        $initializer->initialize(__DIR__, ['coverage-guard', 'non-existent.xml']);
    }

    public function testInitializeFailsWhenCustomConfigFileDoesNotExist(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Provided config file not found: 'non-existent-config.php'");

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config', 'non-existent-config.php']);
    }

    public function testInitializeFailsWhenCustomConfigFileDoesNotExistWithEqualsSyntax(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Provided config file not found: 'non-existent-config.php'");

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config=non-existent-config.php']);
    }

    public function testInitializeFailsWhenPatchFileDoesNotExistWithEqualsSyntax(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Patch file not found: non-existent.patch');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--patch=non-existent.patch']);
    }

    public function testInitializeFailsWithUnknownLongOption(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --unknown');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--unknown']);
    }

    public function testInitializeFailsWithUnknownLongOptionWithEquals(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: --unknown=value');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--unknown=value']);
    }

    public function testInitializeFailsWithUnknownShortOption(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown option: -u');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '-u']);
    }

    public function testInitializeFailsWithUnknownArgument(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown argument: extra-arg');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, 'extra-arg']);
    }

    public function testInitializeFailsWithMultipleUnknownArguments(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Unknown argument: arg1');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, 'arg1', 'arg2']);
    }

    public function testInitializeFailsWhenConfigOptionHasNoValue(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Option --config requires a value');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config']);
    }

    public function testInitializeFailsWhenPatchOptionHasNoValue(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Option --patch requires a value');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--patch']);
    }

    public function testLoadConfigSucceedsWithValidConfig(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $configFile = __DIR__ . '/fixtures/valid-config.php';
        $initializer = $this->createInitializer();

        $result = $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config', $configFile]);

        self::assertSame(str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/fixtures/'), $result->config->getGitRoot());
    }

    public function testLoadConfigFailsWhenConfigFileReturnsNonConfigInstance(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $configFile = __DIR__ . '/fixtures/invalid-config.php';
        $initializer = $this->createInitializer();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage("Config file '$configFile' must return an instance of " . Config::class);

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config', $configFile]);
    }

    public function testInitializeWithVerboseFlag(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $result = $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--verbose']);

        self::assertTrue($result->cliOptions->verbose, 'Verbose flag should be true when --verbose is provided');
    }

    public function testInitializeWithoutVerboseFlag(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $result = $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile]);

        self::assertFalse($result->cliOptions->verbose, 'Verbose flag should be false when --verbose is not provided');
    }

    public function testInitializeWithVerboseFlagAndOtherOptions(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $configFile = __DIR__ . '/fixtures/valid-config.php';
        $initializer = $this->createInitializer();

        $result = $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config', $configFile, '--verbose']);

        self::assertTrue($result->cliOptions->verbose, 'Verbose flag should be true when --verbose is provided with other options');
        self::assertSame(str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/fixtures/'), $result->config->getGitRoot());
    }

    public function testInitializeWithColorsFlag(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = $this->createInitializer();

        $result = $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--no-color', '--color']);

        self::assertSame($coverageFile, $result->cliOptions->coverageFile);
    }

}
