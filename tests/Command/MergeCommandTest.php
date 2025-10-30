<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\TestCase;
use ShipMonk\CoverageGuard\Exception\ErrorException;

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

    public function testInvokeRequiresAtLeastTwoFiles(): void
    {
        $command = new MergeCommand();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('At least 2 files are required to merge');

        ($command)(null, 'single-file.xml');
    }

    /**
     * Note: Actual merge execution is tested in BinTest to avoid stdout pollution
     * We only test validation and error cases here
     */
    public function testInvokeWithInvalidFile(): void
    {
        $command = new MergeCommand();

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('File not found');

        ($command)(null, 'nonexistent1.xml', 'nonexistent2.xml');
    }

}
