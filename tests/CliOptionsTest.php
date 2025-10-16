<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\TestCase;

final class CliOptionsTest extends TestCase
{

    public function testCliOptionsWithAllParameters(): void
    {
        $cliOptions = new CliOptions(
            'coverage.xml',
            'changes.patch',
            'config.php',
            true,
        );

        self::assertSame('coverage.xml', $cliOptions->coverageFile);
        self::assertSame('changes.patch', $cliOptions->patchFile);
        self::assertSame('config.php', $cliOptions->configFile);
        self::assertTrue($cliOptions->verbose);
    }

    public function testCliOptionsWithNullPatchFile(): void
    {
        $cliOptions = new CliOptions(
            'coverage.xml',
            null,
            'config.php',
            false,
        );

        self::assertSame('coverage.xml', $cliOptions->coverageFile);
        self::assertNull($cliOptions->patchFile);
        self::assertSame('config.php', $cliOptions->configFile);
        self::assertFalse($cliOptions->verbose);
    }

    public function testCliOptionsWithVerboseEnabled(): void
    {
        $cliOptions = new CliOptions(
            'coverage.xml',
            null,
            null,
            true,
        );

        self::assertTrue($cliOptions->verbose);
    }

    public function testCliOptionsWithVerboseDisabled(): void
    {
        $cliOptions = new CliOptions(
            'coverage.xml',
            null,
            null,
            false,
        );

        self::assertFalse($cliOptions->verbose);
    }

}
