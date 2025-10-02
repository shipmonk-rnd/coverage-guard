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
vendor/bin/coverage-guard clover.xml # Without any configuration, reports longer methods with 0% line coverage
```

### Supported coverage formats
- PHPUnit clover format (`.xml`)
- PHPUnit serialized PHP format (`.cov`)

## Verifying only changed code

Example:
```sh
git diff master...HEAD > changes.patch
vendor/bin/coverage-guard clover.xml --patch changes.patch
```

## Contributing
- Check your code by `composer check`
- Autofix coding-style by `composer fix:cs`
- All functionality must be tested
