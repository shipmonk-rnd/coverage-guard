<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Cli\CoverageFormat;
use ShipMonk\CoverageGuard\Exception\ErrorException;

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

    public function testInvokeWithNonExistentFile(): void
    {
        $command = new ConvertCommand();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('File not found');

        ($command)(
            'nonexistent.xml',
            CoverageFormat::Clover,
        );
    }

}
