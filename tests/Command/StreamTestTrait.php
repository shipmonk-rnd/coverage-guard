<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use PHPUnit\Framework\Assert;
use function array_keys;
use function array_values;
use function fclose;
use function fopen;
use function preg_quote;
use function preg_replace;
use function realpath;
use function rewind;
use function stream_get_contents;

trait StreamTestTrait
{

    /**
     * @param resource $stream
     * @param array<string, string> $replacements
     */
    private function assertStreamMatchesFile(
        mixed $stream,
        string $expectedFile,
        array $replacements = [],
    ): void
    {
        rewind($stream);
        $actual = stream_get_contents($stream);
        fclose($stream);
        Assert::assertIsString($actual);

        $actualReplaced = preg_replace(array_keys($replacements), array_values($replacements), $actual);

        Assert::assertNotNull($actualReplaced);
        Assert::assertStringEqualsFile($expectedFile, $actualReplaced, "File $expectedFile does not match");
    }

    /**
     * @return resource
     */
    private function createStream(): mixed
    {
        $stream = fopen('php://memory', 'w+');
        Assert::assertIsResource($stream);
        return $stream;
    }

    private function buildRegexForPath(string $path): string
    {
        $realPath = realpath($path);
        Assert::assertIsString($realPath);
        return '#' . preg_quote($realPath, '#') . '#';
    }

}
