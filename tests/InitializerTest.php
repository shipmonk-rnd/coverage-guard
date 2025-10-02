<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use PHPUnit\Framework\TestCase;

class InitializerTest extends TestCase
{

    public function testInitializeWithCoverageFileOnly(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $result = $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile]);

        self::assertSame($coverageFile, $result->coverageFile);
        self::assertNull($result->patchFile);
    }

    public function testInitializeFailsWhenNoArgumentsProvided(): void
    {
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Usage: vendor/bin/coverage-guard <clover-coverage.xml> [--patch <changes.patch>] [--config <config.php>]');

        $initializer->initialize(__DIR__, ['coverage-guard']);
    }

    public function testInitializeFailsWhenCoverageFileDoesNotExist(): void
    {
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Coverage file not found: non-existent.xml');

        $initializer->initialize(__DIR__, ['coverage-guard', 'non-existent.xml']);
    }

    public function testInitializeFailsWhenCustomConfigFileDoesNotExist(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Provided config file not found: 'non-existent-config.php'");

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config', 'non-existent-config.php']);
    }

    public function testInitializeFailsWhenCustomConfigFileDoesNotExistWithEqualsSyntax(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Provided config file not found: 'non-existent-config.php'");

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config=non-existent-config.php']);
    }

    public function testInitializeFailsWhenPatchFileDoesNotExistWithEqualsSyntax(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Patch file not found: non-existent.patch');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--patch=non-existent.patch']);
    }

    public function testInitializeFailsWithUnknownLongOption(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown option: --unknown');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--unknown']);
    }

    public function testInitializeFailsWithUnknownLongOptionWithEquals(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown option: --unknown=value');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--unknown=value']);
    }

    public function testInitializeFailsWithUnknownShortOption(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown option: -u');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '-u']);
    }

    public function testInitializeFailsWithUnknownArgument(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown argument: extra-arg');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, 'extra-arg']);
    }

    public function testInitializeFailsWithMultipleUnknownArguments(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unknown argument: arg1');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, 'arg1', 'arg2']);
    }

    public function testInitializeFailsWhenConfigOptionHasNoValue(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Option --config requires a value');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config']);
    }

    public function testInitializeFailsWhenPatchOptionHasNoValue(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Option --patch requires a value');

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--patch']);
    }

    public function testLoadConfigSucceedsWithValidConfig(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $configFile = __DIR__ . '/fixtures/valid-config.php';
        $initializer = new Initializer();

        $result = $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config', $configFile]);

        self::assertSame(__DIR__ . '/fixtures/', $result->config->getGitRoot());
    }

    public function testLoadConfigFailsWhenConfigFileReturnsNonConfigInstance(): void
    {
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $configFile = __DIR__ . '/fixtures/invalid-config.php';
        $initializer = new Initializer();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Config file '$configFile' must return an instance of " . Config::class);

        $initializer->initialize(__DIR__, ['coverage-guard', $coverageFile, '--config', $configFile]);
    }

}
