<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use ShipMonk\CoverageGuard\StreamTestTrait;
use function fclose;
use function file_exists;
use function file_get_contents;
use function rename;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use const DIRECTORY_SEPARATOR;

final class InitCommandTest extends TestCase
{

    use StreamTestTrait;

    public function testInvokeCreatesConfigFile(): void
    {
        $tempDir = sys_get_temp_dir();
        $stream = $this->createStream();
        $command = $this->createCommand($tempDir, $stream);

        $configPath = $tempDir . DIRECTORY_SEPARATOR . 'coverage-guard.php';

        // Ensure file doesn't exist before test
        if (file_exists($configPath)) {
            unlink($configPath);
        }

        $exitCode = $command();

        self::assertSame(0, $exitCode);
        self::assertFileExists($configPath);

        $content = file_get_contents($configPath);
        self::assertIsString($content);
        self::assertStringContainsString('use ShipMonk\CoverageGuard\Config;', $content);
        self::assertStringContainsString('use ShipMonk\CoverageGuard\Rule\EnforceCoverageForMethodsRule;', $content);
        self::assertStringContainsString('$config = new Config();', $content);
        self::assertStringContainsString('$config->addRule(new EnforceCoverageForMethodsRule(', $content);
        self::assertStringContainsString('return $config;', $content);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($output);
        self::assertStringContainsString('Config file created', $output);
        self::assertStringContainsString($configPath, $output);
    }

    public function testInvokeFailsWhenConfigFileAlreadyExists(): void
    {
        $tempDir = sys_get_temp_dir();
        $stream = $this->createStream();
        $command = $this->createCommand($tempDir, $stream);

        $configPath = $tempDir . '/coverage-guard.php';

        // Create a dummy file
        $tempFile = tempnam($tempDir, 'coverage-guard');
        self::assertIsString($tempFile);
        rename($tempFile, $configPath);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Config file already exists');

        $command();
    }

    /**
     * @param resource $stream
     */
    private function createCommand(
        string $cwd,
        mixed $stream,
    ): InitCommand
    {
        $printer = new Printer($stream, noColor: true);
        return new InitCommand($cwd, $printer);
    }

}
