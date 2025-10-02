# PHP Code Coverage Guard

**Enforce code coverage** in your CI with ease! Not by percentage, but target **core functionality**.

This tool helps ensure that certain code blocks are covered by tests, typically core methods in Facades, Controllers, and other key areas of your application.

- 🎮 **Game-changer:** Innovative approach to code coverage enforcement!
- 💾 **Legacy-friendly:** Allows you to start enforcing for new code only!
- ⚙️ **Extensible:** You specify what must be covered!
- 🕸️ **Lightweight:** Only depends on `nikic/php-parser`
- 🍰 **Easy-to-use:** No config needed for first try

## Installation

```sh
composer require --dev shipmonk/coverage-guard
```

## Simple usage

```sh
vendor/bin/phpunit --coverage-clover clover.xml # Run tests with coverage
vendor/bin/coverage-guard clover.xml # Without config, reports longer methods with 0% line coverage
```

### Supported coverage formats
- PHPUnit clover format (`.xml`)
- PHPUnit serialized PHP format (`.cov`)

## Verifying only changed code

Example:
```sh
git diff master...HEAD > changes.patch
vendor/bin/coverage-guard clover.xml --patch changes.patch # Without config, reports only fully new methods with 0% line coverage
```

The config file must return an instance of `ShipMonk\CoverageGuard\Config`. See [Configuration](#configuration) for more details.

## Configuration

Create a `coverage-guard.php` file in your project root to customize behavior:

```php
<?php

use ShipMonk\CoverageGuard\Config;

$config = new Config();

// Strip prefix from absolute paths in coverage files
// Handy if you want to reuse clover.xml from CI
$config->addStripPath('/absolute/ci/prefix');

// As git patch files are relative to the project root, you can specify the root directory here
// It gets autodetected if cwd is beside /.git/ and if git binary is available
$config->setGitRoot(__DIR__);

return $config;
```

You can also use a custom config file by passing `--config config.php`.

## Cli options

- `--patch <file>` verify only changed code
- `--config <file>` specify a custom config file

Even `--option=value` syntax is supported.

## Contributing
- Check your code by `composer check`
- Autofix coding-style by `composer fix:cs`
- All functionality must be tested
