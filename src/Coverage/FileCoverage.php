<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Coverage;

final class FileCoverage
{

    /**
     * @param list<ExecutableLine> $executableLines
     */
    public function __construct(
        public readonly string $filePath,
        public readonly array $executableLines,
        public readonly ?int $expectedLinesCount = null,
    )
    {
    }

}
