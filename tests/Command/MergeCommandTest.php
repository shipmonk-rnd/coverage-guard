<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function fopen;

final class MergeCommandTest extends TestCase
{

    public function testGetName(): void
    {
        $command = new MergeCommand();
        self::assertSame('merge', $command->getName());
    }

    public function testGetDescription(): void
    {
        $command = new MergeCommand();
        self::assertStringContainsString('Merge', $command->getDescription());
    }

    public function testGetArguments(): void
    {
        $command = new MergeCommand();
        $arguments = $command->getArguments();

        self::assertCount(1, $arguments);
        self::assertSame('files', $arguments[0]->name);
        self::assertTrue($arguments[0]->variadic);
    }

    public function testGetOptions(): void
    {
        $command = new MergeCommand();
        $options = $command->getOptions();

        self::assertCount(1, $options);
        self::assertSame('format', $options[0]->name);
        self::assertTrue($options[0]->requiresValue);
    }

    public function testExecuteRequiresAtLeastTwoFiles(): void
    {
        $command = new MergeCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('At least 2 files are required to merge');

        $command->execute(
            ['single-file.xml'],
            [],
            $printer,
        );
    }

    /**
     * Note: Actual merge execution is tested in BinTest to avoid stdout pollution
     * We only test validation and error cases here
     */
    public function testExecuteWithInvalidFile(): void
    {
        $command = new MergeCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('File not found');

        $command->execute(
            ['nonexistent1.xml', 'nonexistent2.xml'],
            [],
            $printer,
        );
    }

    /** Removed testExecuteWithFormatOption to avoid stdout pollution */
    public function testExecuteWithInvalidFormat(): void
    {
        $command = new MergeCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $file1 = __DIR__ . '/../fixtures/clover.xml';
        $file2 = __DIR__ . '/../fixtures/clover_with_package.xml';

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Invalid value \'invalid\' for option --format');

        $command->execute(
            [$file1, $file2],
            ['format' => 'invalid'],
            $printer,
        );
    }

}
