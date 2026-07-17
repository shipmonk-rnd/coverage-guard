<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Excluder;

use LogicException;

/**
 * @api
 */
final class ExclusionContext
{

    /**
     * @param array<int, string> $linesContents line => contents (without EOL), starting at 1
     */
    public function __construct(
        private readonly string $filePath,
        private readonly array $linesContents,
    )
    {
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Without trailing EOL
     */
    public function getLineContents(int $lineNumber): string
    {
        if (!isset($this->linesContents[$lineNumber])) {
            throw new LogicException("Line number #{$lineNumber} of file '{$this->filePath}' is expected to exist.");
        }

        return $this->linesContents[$lineNumber];
    }

}
