<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Hierarchy;

use LogicException;
use ReflectionException;
use ReflectionMethod;

/**
 * Represents a non-empty block of code for a specific method in a class/trait/enum
 *
 * @api
 */
final class ClassMethodBlock extends CodeBlock
{

    private readonly string $className;

    private readonly string $methodName;

    public function __construct(
        string $className,
        string $methodName,
        string $filePath,
        array $lines,
    )
    {
        parent::__construct($filePath, $lines);
        $this->methodName = $methodName;
        $this->className = $className;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getMethodReflection(): ReflectionMethod
    {
        try {
            return new ReflectionMethod($this->className, $this->methodName);
        } catch (ReflectionException $e) {
            throw new LogicException("Could not get reflection for method {$this->className}::{$this->methodName}", 0, $e);
        }
    }

}
