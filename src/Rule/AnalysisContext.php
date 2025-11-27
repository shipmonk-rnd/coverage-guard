<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * @api
 */
final class AnalysisContext
{

    public function __construct(
        private readonly ?string $className,
        private readonly ?string $methodName,
        private readonly string $filePath,
        private readonly bool $patchMode,
    )
    {
    }

    /**
     * FQN of current class/trait/enum
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getMethodName(): ?string
    {
        return $this->methodName;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * True when --patch option is used (and only changed code blocks are analyzed)
     */
    public function isPatchMode(): bool
    {
        return $this->patchMode;
    }

    /**
     * @return ReflectionClass<object>|null
     */
    public function getClassReflection(): ?ReflectionClass
    {
        if ($this->className === null) {
            return null;
        }
        try {
            return new ReflectionClass($this->className); // @phpstan-ignore argument.type (className should be FQN)
        } catch (ReflectionException $e) {
            throw new LogicException("Could not get reflection for class {$this->className}", 0, $e);
        }
    }

    public function getMethodReflection(): ?ReflectionMethod
    {
        if ($this->methodName === null || $this->className === null) {
            return null;
        }

        try {
            return new ReflectionMethod($this->className, $this->methodName);
        } catch (ReflectionException $e) {
            throw new LogicException("Could not get reflection for method {$this->className}::{$this->methodName}", 0, $e);
        }
    }

}
