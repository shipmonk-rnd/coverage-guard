<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use function is_dir;
use function realpath;
use const DIRECTORY_SEPARATOR;

/**
 * @api
 */
final class Config
{

    private ?string $gitRoot = null;

    /**
     * @var list<string>
     */
    private array $stripPaths = [];

    /**
     * @var list<CoverageRule>
     */
    private array $rules = [];

    public function __construct()
    {
    }

    public function setGitRoot(string $gitRoot): self
    {
        if (!is_dir($gitRoot)) {
            throw new LogicException("Provided git root '$gitRoot' is not a directory");
        }

        $this->gitRoot = $this->realpath($gitRoot) . DIRECTORY_SEPARATOR;
        return $this;
    }

    public function addStripPath(string $stripPath): self
    {
        if (!is_dir($stripPath)) {
            throw new LogicException("Provided strip path '$stripPath' is not a directory");
        }

        $this->stripPaths[] = $this->realpath($stripPath) . DIRECTORY_SEPARATOR;
        return $this;
    }

    public function addRule(CoverageRule $rule): self
    {
        $this->rules[] = $rule;
        return $this;
    }

    public function getGitRoot(): ?string
    {
        return $this->gitRoot;
    }

    /**
     * @return list<string>
     */
    public function getStripPaths(): array
    {
        return $this->stripPaths;
    }

    /**
     * @return list<CoverageRule>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    private function realpath(string $path): string
    {
        $realpath = realpath($path);
        if ($realpath === false) {
            throw new LogicException("Could not realpath '$path'");
        }
        return $realpath;
    }

}
