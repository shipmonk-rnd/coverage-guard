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

## Example usage

```sh
# Run tests, collect coverage, generate report:
XDEBUG_MODE=coverage vendor/bin/phpunit tests --coverage-filter src --coverage-clover clover.xml

# Verify coverage:
vendor/bin/coverage-guard check clover.xml
```


In real application, you will probably use `phpunit.xml` to [configure PHPUnit coverage](https://docs.phpunit.de/en/10.5/code-coverage.html#including-files):

```xml
<coverage processUncoveredFiles="true">
    <include>
        <directory>src</directory>
    </include>
    <report>
        <clover outputFile="clover.xml"/>
    </report>
</coverage>
```

To collect coverage, you can pick traditional [XDebug](https://xdebug.org/docs/install) or performant [PCOV](https://github.com/krakjoe/pcov/blob/develop/INSTALL.md) extension.


## Enforce coverage for new code only

```sh
git diff master...HEAD > changes.patch
vendor/bin/coverage-guard check clover.xml --patch changes.patch
```

- When patch is provided, this tool will only analyse changed files and methods and won't report violations from elsewhere.
- This allows you to gradually enforce code coverage for new code only.


## Configuration

Create a `coverage-guard.php` file in your project root to customize behavior and set up your `CoverageRules`.
The config file must return an instance of `ShipMonk\CoverageGuard\Config`:

```php
<?php

use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Rule\EnforceCoverageForMethodsRule;

$config = new Config();

// Your rules what must be covered
$config->addRule(new EnforceCoverageForMethodsRule(
    requiredCoveragePercentage: 50,
    minMethodChangePercentage: 50, // when --patch is provided, check only methods changed by more than 50%
    minExecutableLines: 5, // only check methods with at least 5 executable lines
));

// Replace prefix of absolute paths in coverage files
// Handy if you want to reuse clover.xml generated in CI
$config->addCoveragePathMapping('/absolute/ci/prefix', __DIR__);

// As filepaths in git patches are relative to the project root, you can specify the root directory here
// It gets autodetected if cwd is beside /.git/ or if git binary is available
$config->setGitRoot(__DIR__);

// Make CLI file paths clickable to your IDE
// Available placeholders: {file}, {relFile}, {line}
$config->setEditorUrl('phpstorm://open?file={file}&line={line}');

return $config;
```

### Advanced usage:

- For custom enforcement logic, implement `CoverageRule` and pass it to `Config::addRule()` method:
  - Inspire by prepared [`EnforceCoverageForMethodsRule`](src/Rule/EnforceCoverageForMethodsRule.php) or our own [coverage config](./coverage-guard.php).


### What can you enforce:
The `CodeBlock` class passed to `CoverageRule` is aware **which line is executable, changed and covered**.
Also, you can use **reflection** to pinpoint your rules.
This allows you to setup huge variety of rules, examples:

- All **newly created methods** must have some coverage
- When a method is **changed by more than 50%,** it must have at least 50% coverage
- All methods in your codebase **longer than 10 executable lines** must have some coverage
- All **`Controller` methods** must have at least 50% coverage
- Every method must be tested unless custom **`#[NoCoverageAllowed]` attribute** is used
- ...

## Global CLI options

- `--verbose` show detailed processing information
- `--help` show generic help (or command help when combined with command name)
- `--no-color` to disable colors (`NO_COLOR` env is also supported)
- `--color` to force colors even when output is not a TTY


Run `vendor/bin/coverage-guard <command> --help` for command-specific options.


## Supported PHPUnit coverage formats

| Format | Filesize        | Rating     | Notes                                                                                                                                                    |
|--------|-----------------|----------|----------------------------------------------------------------------------------------------------------------------------------------------------------|
| **clover** (`.xml`) | (baseline)      | 🟢 Best  | Usable in [PHPStorm coverage visualization](https://www.jetbrains.com/help/phpstorm/viewing-code-coverage-results.html). Allows better integrity checks. |
| **cobertura** (`.xml`) | 1.7x bigger     | 🟡 OK    | Usable in [GitLab coverage visualization](https://docs.gitlab.com/ci/testing/code_coverage/#coverage-visualization)                                      |
| **php** (`.cov`) | 8x - 40x bigger | 🔴 Avoid | May produce warnings on old PHPUnit when xdebug is not active. Good coverage causes HUGE filesizes easily reaching over 100 MB.                          |


## Commands

### `check`

- Main command to enforce code coverage rules on your codebase as described above.

```sh
vendor/bin/coverage-guard check clover.xml
```

Options:
- `--verbose` – show detailed processing information
- `--patch` – path to git diff, to check coverage only for changed files & methods
- `--config` – path to custom PHP config

### `merge` & `convert`

- Merging multiple coverage files into a single file is useful when running tests in parallel CI jobs.
- Please note those commands **do not maintain all data from original XMLs**
  - It only produces minimal XML files while maintaining usability by PHPStorm, GitLab and Coverage Guard
- Input formats: `clover`, `cobertura`, `php` (autodetected)
- Output formats: `clover`, `cobertura`

```sh
vendor/bin/coverage-guard merge coverage/*.xml --format clover > merged-clover.xml
vendor/bin/coverage-guard convert cobertura.xml --format clover > clover.xml
```

Options:
- `--format` – output format (`clover` or `cobertura`)
- `--indent` – output XML indentation (defaults to 4 spaces); for tabs use `--indent=$'\t'`
- `--config` – path to custom PHP config

### `patch-coverage`

- Calculate coverage percentage for lines changed in a patch file.
- Handy for [GitLab coverage pattern](https://docs.gitlab.com/ci/testing/code_coverage/#configure-coverage-reporting): `coverage: '/Coverage:\s+(\d+\.\d+%)/'`
  - You will see coverage of changed lines in your MR detail

```sh
git diff master...HEAD > changes.patch
vendor/bin/coverage-guard patch-coverage clover.xml --patch changes.patch
```

Options:
- `--patch` – path to diff file (required)
- `--config` – path to custom PHP config

Output example:
```
Patch Coverage Statistics:

  Changed executable lines: 45
  Covered lines:            38
  Uncovered lines:          7
  Coverage:                 84.44%
```

## Optional dependencies
- Libraries:
  - `phpunit/php-code-coverage` for loading coverage cov files
  - `sebastian/diff` for processing diff/patch files
- PHP extensions:
  - `ext-libxml` and `ext-simplexml` for loading coverage XML files
  - `ext-dom` for `check` and `merge` commands
  - `ext-tokenizer` to see syntax highlighted code blocks

## Contributing
- Check your code by `composer check`
- Autofix coding-style by `composer fix:cs`
- All functionality must be tested
