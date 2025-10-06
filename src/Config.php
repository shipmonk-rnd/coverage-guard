<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard;

use LogicException;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use function file_exists;
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
     * @var array<string, string>
     */
    private array $coveragePathMapping = [];

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

    public function addCoveragePathMapping(
        string $originalPathInCoverageFile,
        string $existingPathToUseInstead,
    ): self
    {
        if (!file_exists($existingPathToUseInstead)) {
            throw new LogicException("Provided new path '$existingPathToUseInstead' does not exist");
        }
        if (!is_dir($existingPathToUseInstead)) {
            throw new LogicException("Provided new path '$existingPathToUseInstead' is not a directory");
        }

        $this->coveragePathMapping[$originalPathInCoverageFile] = $existingPathToUseInstead;
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
     * @return array<string, string>
     */
    public function getCoveragePathMapping(): array
    {
        return $this->coveragePathMapping;
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
