<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Command;

use ShipMonk\CoverageGuard\Exception\ErrorException;
use ShipMonk\CoverageGuard\Printer;
use function file_exists;
use function file_put_contents;
use const DIRECTORY_SEPARATOR;

final class InitCommand implements Command
{

    private const DEFAULT_CONFIG_FILENAME = 'coverage-guard.php';

    private const DEFAULT_CONFIG_CONTENT = <<<'PHP'
<?php

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Rule\EnforceCoverageForMethodsRule;

$config = new Config();
$config->addRule(new EnforceCoverageForMethodsRule(
    requiredCoveragePercentage: 50,
    minExecutableLines: 5,
));

return $config;

PHP;

    public function __construct(
        private readonly string $cwd,
        private readonly Printer $stdOutPrinter,
    )
    {
    }

    /**
     * @throws ErrorException
     */
    public function __invoke(): int
    {
        $configPath = $this->cwd . DIRECTORY_SEPARATOR . self::DEFAULT_CONFIG_FILENAME;

        if (file_exists($configPath)) {
            throw new ErrorException("Config file already exists at: {$configPath}");
        }

        $result = file_put_contents($configPath, self::DEFAULT_CONFIG_CONTENT);

        if ($result === false) {
            throw new ErrorException("Failed to write config file to: {$configPath}");
        }

        $this->stdOutPrinter->printLine("✅ Config file created at: <green>{$configPath}</green>. Adjust it to your needs.");

        return 0;
    }

    public function getName(): string
    {
        return 'init';
    }

    public function getDescription(): string
    {
        return 'Generate default configuration file';
    }

}
