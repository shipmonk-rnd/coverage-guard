<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

final class PatchFileDiff
{

    /**
     * @param string $targetPath raw target path as present in the '+++' header, e.g. 'b/src/Foo.php' or '/dev/null'
     * @param array<int, string> $addedLines added line number in the new file version => line content
     */
    public function __construct(
        public readonly string $targetPath,
        public readonly array $addedLines,
    )
    {
    }

}
