<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use function array_slice;
use function explode;
use function file_get_contents;
use function implode;

final class CodeBlock
{

    public function __construct(
        public readonly CodeBlockType $type,
        public readonly string $path,
        public readonly int $startLine,
        public readonly int $endLine,
    )
    {
    }

    public function getBlockCode(): string
    {
        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new LogicException("Failed to read file: {$this->path}");
        }

        $lines = explode("\n", $contents);
        $block = array_slice($lines, $this->startLine - 1, $this->endLine - $this->startLine + 1);

        return implode("\n", $block);
    }

}
