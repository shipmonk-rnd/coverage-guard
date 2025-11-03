<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\TestCase;
use function escapeshellarg;
use function exec;
use function implode;

final class BinTest extends TestCase
{

    public function testBinDetectsUntestedChangedMethod(): void
    {
        $binPath = __DIR__ . '/../bin/coverage-guard';
        $coverageFile = __DIR__ . '/_fixtures/clover.xml';
        $patchFile = __DIR__ . '/_fixtures/sample.patch';
        $configFile = __DIR__ . '/_fixtures/config-for-bintest.php';

        $command = implode(' ', [
            'php',
            escapeshellarg($binPath),
            'check',
            escapeshellarg($coverageFile),
            '--patch',
            escapeshellarg($patchFile),
            '--config',
            escapeshellarg($configFile),
            '2>&1',
        ]);

        $output = [];
        $exitCode = null;
        exec($command, $output, $exitCode);

        self::assertSame(1, $exitCode, 'Expected exit code 1 when errors are found');
        self::assertNotEmpty($output, 'Expected output from the binary');

        $outputString = implode("\n", $output);
        self::assertStringContainsString('Sample.php', $outputString, 'Expected output to mention Sample.php');
        self::assertStringContainsString('We need 100% coverage!', $outputString, 'Expected custom error message from config');
    }

    public function testBinSucceedsWithNoCoverageIssues(): void
    {
        $binPath = __DIR__ . '/../bin/coverage-guard';
        $coverageFile = __DIR__ . '/_fixtures/clover_with_package.xml';
        $configFile = __DIR__ . '/_fixtures/config-for-bintest-no-rule.php';

        $command = implode(' ', [
            'php',
            escapeshellarg($binPath),
            'check',
            escapeshellarg($coverageFile),
            '--config',
            escapeshellarg($configFile),
            '2>&1',
        ]);

        $output = [];
        $exitCode = null;
        exec($command, $output, $exitCode);

        self::assertSame(0, $exitCode, 'Expected exit code 0 when no errors are found');
    }

    public function testBinShowsErrorOnInvalidArguments(): void
    {
        $binPath = __DIR__ . '/../bin/coverage-guard';

        $command = implode(' ', [
            'php',
            escapeshellarg($binPath),
            '2>&1',
        ]);

        $output = [];
        $exitCode = null;
        exec($command, $output, $exitCode);

        self::assertSame(1, $exitCode, 'Expected exit code 1 on invalid arguments');

        $outputString = implode("\n", $output);
        self::assertStringContainsString('Available commands:', $outputString, 'Expected help message in output');
    }

    public function testBinWithNoColorFlag(): void
    {
        $binPath = __DIR__ . '/../bin/coverage-guard';
        $coverageFile = __DIR__ . '/_fixtures/clover.xml';
        $patchFile = __DIR__ . '/_fixtures/sample.patch';
        $configFile = __DIR__ . '/_fixtures/config-for-bintest.php';

        $command = implode(' ', [
            'php',
            escapeshellarg($binPath),
            'check',
            escapeshellarg($coverageFile),
            '--patch',
            escapeshellarg($patchFile),
            '--config',
            escapeshellarg($configFile),
            '--no-color',
        ]);

        $output = [];
        $exitCode = null;
        exec($command, $output, $exitCode);

        $outputString = implode("\n", $output);
        self::assertStringNotContainsString("\033[", $outputString, 'Expected no ANSI color codes with --no-color');
        self::assertStringContainsString('Sample.php', $outputString, 'Expected output to mention Sample.php');
    }

    public function testBinWithColorFlag(): void
    {
        $binPath = __DIR__ . '/../bin/coverage-guard';
        $coverageFile = __DIR__ . '/_fixtures/clover.xml';
        $patchFile = __DIR__ . '/_fixtures/sample.patch';
        $configFile = __DIR__ . '/_fixtures/config-for-bintest.php';

        $command = implode(' ', [
            'php',
            escapeshellarg($binPath),
            'check',
            escapeshellarg($coverageFile),
            '--patch',
            escapeshellarg($patchFile),
            '--config',
            escapeshellarg($configFile),
            '--color',
            '2>&1',
        ]);

        $output = [];
        $exitCode = null;
        exec($command, $output, $exitCode);

        $outputString = implode("\n", $output);
        self::assertStringContainsString("\033[", $outputString, 'Expected ANSI color codes with --color');
        self::assertStringContainsString('Sample.php', $outputString, 'Expected output to mention Sample.php');
    }

    public function testBinDefaultBehaviorWithoutTty(): void
    {
        $binPath = __DIR__ . '/../bin/coverage-guard';
        $coverageFile = __DIR__ . '/_fixtures/clover.xml';
        $patchFile = __DIR__ . '/_fixtures/sample.patch';
        $configFile = __DIR__ . '/_fixtures/config-for-bintest.php';

        $command = implode(' ', [
            'php',
            escapeshellarg($binPath),
            'check',
            escapeshellarg($coverageFile),
            '--patch',
            escapeshellarg($patchFile),
            '--config',
            escapeshellarg($configFile),
            '2>&1',
        ]);

        $output = [];
        $exitCode = null;
        exec($command, $output, $exitCode);

        $outputString = implode("\n", $output);
        // When running via exec(), STDOUT is not a TTY, so colors should be disabled by default
        self::assertStringNotContainsString("\033[", $outputString, 'Expected no ANSI color codes when STDOUT is not a TTY');
    }

}
