# PHP Code Coverage Guard

**Enforce code coverage** in your CI with ease! Not by percentage, but target **core functionality**.

- 🎮 **Game-changer:** Innovative approach to code coverage enforcement!
- 💾 **Legacy-friendly:** Allows you to start enforcing for new code only!
- ⚙️ **Extensible:** You specify what must be covered!
- 🕸️ **Lightweight:** Only depends on `nikic/php-parser`
- 🍰 **Easy-to-use:** No config needed for first try

This tool helps ensure that certain code blocks are covered by tests, typically core methods in Facades, Controllers, and other key areas of your application.

## Installation

```sh
composer require --dev shipmonk/coverage-guard
```

## Simple usage

```sh
vendor/bin/phpunit --coverage-clover clover.xml # Run tests with coverage
vendor/bin/coverage-guard clover.xml
```

### Supported coverage formats
- PHPUnit clover format (`.xml`, recommended)
- PHPUnit corbertura format (`.xml`)
- PHPUnit serialized PHP format (`.cov`)

## Verifying only changed code

```sh
git diff master...HEAD > changes.patch
vendor/bin/coverage-guard clover.xml --patch changes.patch # Without config, reports only fully new methods with 0% line coverage
```

- When patch is provided, this tool will only analyse changed files and methods and won't report violations from elsewhere.
- This allows you to gradually enforce code coverage for new code only.

## Configuration

Create a `coverage-guard.php` file in your project root to customize behavior and set up your `CoverageRules`.
The config file must return an instance of `ShipMonk\CoverageGuard\Config`:

```php
<?php

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\CoverageError;

$config = new Config();

// Your main specification of what must be covered
$config->addRule(new class implements CoverageRule {

    public function inspect(
        CodeBlock $codeBlock,
        bool $patchMode, // true when --patch was provided (thus only changed files and methods are analyzed)
    ): ?CoverageError
    {
        if (!$codeBlock instanceof ClassMethodBlock) {
            return null; // let's analyse only class methods
        }

        if (
            $codeBlock->isChangedAtLeastByPercent(50) // important for patch mode, otherwise all lines are considered changed
            && !$codeBlock->isCoveredAtLeastByPercent(50)
        ) {
            $shortClassName = $codeBlock->getMethodReflection() // you can rule based on reflection
                ->getDeclaringClass()
                ->getShortName();

            $methodRef = "{$shortClassName}::{$codeBlock->getMethodName()}";
            $coverage = (int) $codeBlock->getCoveragePercentage();
            $infix = $patchMode ? ' was significantly changed, but' : '';

            $error = "Method <white>$methodRef</white>$infix has only $coverage %% coverage.";

            return CoverageError::message($error);
        }

        return null;
    }

});

// Strip prefix from absolute paths in coverage files
// Handy if you want to reuse clover.xml from CI
$config->addStripPath('/absolute/ci/prefix');

// As filepaths in git patches are relative to the project root, you can specify the root directory here
// It gets autodetected if cwd is beside /.git/ or if git binary is available
$config->setGitRoot(__DIR__);

return $config;
```

You can also use a custom config file by `--config config.php`.

## Cli options

- `--patch <branch-diff.patch>` verify only changed code
- `--config <path/to/coverage-guard.php>` specify a custom config filepath

Even `--option=value` syntax is supported.

## Contributing
- Check your code by `composer check`
- Autofix coding-style by `composer fix:cs`
- All functionality must be tested
