# Development Guide

## Requirements
- PHP 8.2+
- Composer

## Install
```bash
composer install
```

## Common Commands

### Tests
```bash
composer test
```

Run a single test:
```bash
vendor/bin/pest --filter "can create from array"
```

### Linting and Type Coverage
```bash
composer lint
```

### Formatting
```bash
composer format
```

Formatting runs Rector before Pint, so refactors happen before formatting.

### Coverage
```bash
composer coverage
```

## Notes
- Pest is configured in `tests/Pest.php`.
- PHPStan config lives in `phpstan.neon`.
- Rector config lives in `rector.php`.
- Pint config lives in `pint.json`.
