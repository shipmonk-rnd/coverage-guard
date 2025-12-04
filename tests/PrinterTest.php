<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use function fopen;
use function rewind;
use function stream_get_contents;
use function stream_wrapper_register;
use function trigger_error;
use const E_USER_WARNING;

final class PrinterTest extends TestCase
{

    public function testPrintLine(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $printer = new Printer($stream, noColor: true);

        $printer->printLine('Hello World');

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertSame("Hello World\n", $output);
    }

    public function testCreateWithNoColorArgument(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $printer = Printer::create($stream, ['--no-color']);

        self::assertTrue($printer->hasDisabledColors());
    }

    #[RunInSeparateProcess]
    public function testBrokenPipeHandling(): void
    {
        // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
        $wrapper = new class {

            public static bool $called = false; // @phpstan-ignore shipmonk.publicPropertyNotReadonly

            /**
             * @var resource
             */
            public mixed $context; // @phpstan-ignore property.uninitialized, shipmonk.publicPropertyNotReadonly

            public function stream_open(): bool
            {
                return true;
            }

            public function stream_write(): void
            {
                trigger_error('fwrite(): errno=32 Broken pipe', E_USER_WARNING);
                self::$called = true;
            }

        };

        stream_wrapper_register('broken', $wrapper::class);

        $handle = fopen('broken://2', 'w');
        self::assertIsResource($handle);

        $printer = new Printer($handle, true);
        $printer->printLine('Hello World');

        self::assertTrue($wrapper::$called);
    }

}
