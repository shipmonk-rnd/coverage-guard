<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function array_map;
use function fclose;
use function fopen;
use function rewind;
use function stream_get_contents;

final class CheckCommandTest extends TestCase
{

    public function testGetName(): void
    {
        $command = new CheckCommand();
        self::assertSame('check', $command->getName());
    }

    public function testGetDescription(): void
    {
        $command = new CheckCommand();
        self::assertStringContainsString('coverage', $command->getDescription());
    }

    public function testGetArguments(): void
    {
        $command = new CheckCommand();
        $arguments = $command->getArguments();

        self::assertCount(1, $arguments);
        self::assertSame('coverage-file', $arguments[0]->name);
    }

    public function testGetOptions(): void
    {
        $command = new CheckCommand();
        $options = $command->getOptions();

        self::assertCount(2, $options);

        $optionNames = array_map(static fn (Option $opt) => $opt->name, $options);
        self::assertContains('patch', $optionNames);
        self::assertContains('config', $optionNames);
    }

    public function testExecuteWithNonExistentCoverageFile(): void
    {
        $command = new CheckCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Coverage file not found');

        $command->execute(
            ['nonexistent.xml'],
            [],
            $printer,
        );
    }

    public function testExecuteWithValidCoverageFile(): void
    {
        $command = new CheckCommand();

        $outputStream = fopen('php://memory', 'w+');
        self::assertIsResource($outputStream);

        $printer = new Printer($outputStream, noColor: true);

        $coverageFile = __DIR__ . '/../fixtures/clover_with_package.xml';
        $configFile = __DIR__ . '/../fixtures/config-for-bintest-no-rule.php';

        $exitCode = $command->execute(
            [$coverageFile],
            ['config' => $configFile],
            $printer,
        );

        self::assertSame(0, $exitCode);
    }

    public function testExecuteWithPatchOption(): void
    {
        $command = new CheckCommand();

        $outputStream = fopen('php://memory', 'w+');
        self::assertIsResource($outputStream);

        $printer = new Printer($outputStream, noColor: true);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';
        $patchFile = __DIR__ . '/../fixtures/sample.patch';
        $configFile = __DIR__ . '/../fixtures/config-for-bintest.php';

        $exitCode = $command->execute(
            [$coverageFile],
            ['patch' => $patchFile, 'config' => $configFile],
            $printer,
        );

        self::assertSame(1, $exitCode); // Should find violations

        rewind($outputStream);
        $output = stream_get_contents($outputStream);
        fclose($outputStream);

        self::assertIsString($output);
        self::assertStringContainsString('Sample.php', $output);
    }

    public function testExecuteWithInvalidPatchExtension(): void
    {
        $command = new CheckCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';
        $patchFile = __DIR__ . '/../fixtures/config-for-bintest.php'; // Valid file but wrong extension

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('expecting .patch or .diff extension');

        $command->execute(
            [$coverageFile],
            ['patch' => $patchFile],
            $printer,
        );
    }

    public function testExecuteWithInvalidConfigExtension(): void
    {
        $command = new CheckCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $coverageFile = __DIR__ . '/../fixtures/clover.xml';
        $configFile = __DIR__ . '/../fixtures/sample.patch'; // Valid file but wrong extension

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('must have php extension');

        $command->execute(
            [$coverageFile],
            ['config' => $configFile],
            $printer,
        );
    }

}
