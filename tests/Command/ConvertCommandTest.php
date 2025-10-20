<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function fopen;

final class ConvertCommandTest extends TestCase
{

    public function testGetName(): void
    {
        $command = new ConvertCommand();
        self::assertSame('convert', $command->getName());
    }

    public function testGetDescription(): void
    {
        $command = new ConvertCommand();
        self::assertStringContainsString('Convert', $command->getDescription());
    }

    public function testGetArguments(): void
    {
        $command = new ConvertCommand();
        $arguments = $command->getArguments();

        self::assertCount(1, $arguments);
        self::assertSame('input-file', $arguments[0]->name);
    }

    public function testGetOptions(): void
    {
        $command = new ConvertCommand();
        $options = $command->getOptions();

        self::assertCount(1, $options);
        self::assertSame('format', $options[0]->name);
        self::assertTrue($options[0]->requiresValue);
    }

    public function testExecuteRequiresFormatOption(): void
    {
        $command = new ConvertCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $inputFile = __DIR__ . '/../fixtures/clover.xml';

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('--format is required');

        $command->execute(
            [$inputFile],
            [],
            $printer,
        );
    }

    public function testExecuteWithInvalidFormat(): void
    {
        $command = new ConvertCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $inputFile = __DIR__ . '/../fixtures/clover.xml';

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Invalid value \'invalid\' for option --format');

        $command->execute(
            [$inputFile],
            ['format' => 'invalid'],
            $printer,
        );
    }

    public function testExecuteWithNonExistentFile(): void
    {
        $command = new ConvertCommand();
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('File not found');

        $command->execute(
            ['nonexistent.xml'],
            ['format' => 'clover'],
            $printer,
        );
    }

    /**
     * Note: Actual conversion execution is tested in BinTest to avoid stdout pollution
     * We only test validation and error cases here
     */

}
