<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use LogicException;

/**
 * @api
 */
final class LineOfCode
{

    public function __construct(
        private readonly int $number,
        private readonly bool $executable,
        private readonly bool $covered,
        private readonly bool $changed,
        private readonly string $contents,
    )
    {
        if (!$executable && $covered) {
            throw new LogicException("Invalid state for line #{$this->number} '{$contents}': marked as covered, but not executable.");
        }
    }

    /**
     * Starting at 1
     */
    public function getNumber(): int
    {
        return $this->number;
    }

    public function isExecutable(): bool
    {
        return $this->executable;
    }

    public function isCovered(): bool
    {
        return $this->covered;
    }

    public function isChanged(): bool
    {
        return $this->changed;
    }

    /**
     * Without trailing EOL
     */
    public function getContents(): string
    {
        return $this->contents;
    }

}
