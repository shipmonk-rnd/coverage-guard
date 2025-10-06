<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use PHPUnit\Framework\TestCase;
use const DIRECTORY_SEPARATOR;

final class ConfigTest extends TestCase
{

    public function testPaths(): void
    {
        $config = new Config();
        $config->setGitRoot(__DIR__);
        $config->addStripPath(__DIR__);

        self::assertSame(__DIR__ . DIRECTORY_SEPARATOR, $config->getGitRoot());
        self::assertSame([__DIR__ . DIRECTORY_SEPARATOR], $config->getStripPaths());
    }

}
