<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

/**
 * Represents a standalone function (not a class method)
 *
 * @api
 */
final class FunctionBlock extends CodeBlock
{

    private readonly string $functionName;

    /**
     * @param non-empty-list<LineOfCode> $lines
     */
    public function __construct(
        string $functionName,
        string $filePath,
        array $lines,
        ?CodeBlock $parent = null,
    )
    {
        parent::__construct($filePath, $lines, $parent);
        $this->functionName = $functionName;
    }

    public function getFunctionName(): string
    {
        return $this->functionName;
    }

}
