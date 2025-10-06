<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\TestCase;
use function escapeshellarg;
use function exec;
use function implode;

class BinTest extends TestCase
{

    public function testBinDetectsUntestedChangedMethod(): void
    {
        $binPath = __DIR__ . '/../bin/coverage-guard';
        $coverageFile = __DIR__ . '/fixtures/clover.xml';
        $patchFile = __DIR__ . '/fixtures/sample.patch';
        $configFile = __DIR__ . '/fixtures/config-for-bintest.php';

        $command = implode(' ', [
            'php',
            escapeshellarg($binPath),
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
        $coverageFile = __DIR__ . '/fixtures/clover_with_package.xml';

        $command = implode(' ', [
            'php',
            escapeshellarg($binPath),
            escapeshellarg($coverageFile),
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
        self::assertStringContainsString('Error', $outputString, 'Expected error message in output');
    }

}
