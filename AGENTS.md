# AGENTS.md

Agent notes for this repository. Keep changes consistent with existing code and tests.

## Repo snapshot
- Language: PHP (target 8.2+)
- Package type: library
- Autoload: PSR-4 `FuzzyFox\\` => `src/`
- Tests: Pest (phpunit.xml)
- Helpers: global functions in `src/helpers.php`

## Build, lint, test

### Install dependencies
```bash
composer install
```

### Run the full test suite
```bash
composer test
```

### Run a single test file
```bash
vendor/bin/pest tests/FluentValueTest.php
```

### Run a single test by name (recommended)
```bash
vendor/bin/pest --filter "can create from array"
```

### Run coverage
```bash
composer coverage
```

### Linting / formatting
```bash
composer lint
```

```bash
vendor/bin/phpstan analyse
```

```bash
vendor/bin/pest --type-coverage
```

```bash
composer format
```

```bash
vendor/bin/rector process
```

```bash
vendor/bin/pint
```

Notes:
- PHPStan config: `phpstan.neon`
- Rector config: `rector.php`
- Pint config: `pint.json`

## Code style guidelines

### General
- Follow existing formatting and naming patterns in `src/FluentValue.php` and `tests/FluentValueTest.php`.
- Indentation: 4 spaces, no tabs.
- Braces: next line for class/method declarations, same line for control structures.
- Keep line lengths reasonable; wrap long concatenations like existing tests.

### Namespaces and imports
- Use namespaces consistently (`FuzzyFox` for src). Tests are in the global namespace for Pest.
- Use `use` statements for class imports at the top of files.
- Keep import order stable with the surrounding file (no automatic resorting).

### Types
- Use typed properties and return types where practical.
- Use `mixed` for flexible inputs/outputs (as already used in the core class).
- Prefer explicit nullable types (e.g., `?self`) over loose `mixed` when intent is clear.
- No `declare(strict_types=1);` in current codebase; avoid adding unless requested.

### Naming conventions
- Classes: StudlyCaps (e.g., `FluentValue`).
- Methods: camelCase (e.g., `isNotEmpty`, `setPendingOverride`).
- Pest tests: `it('does something', ...)` or `test('does something', ...)` with sentence-style names.
- Variables: camelCase for local vars (`$pendingOverrides`, `$currentKey`).

### Collections and data access
- The library models fluent access; keep API surface consistent with existing helpers.
- When resolving values, use the `value()` helper for closure resolution with context.
- Use `wrap()` to return nested FluentValue instances for arrays. Objects are preserved and accessed via properties.

### Object handling
- Preserve objects; do not convert to arrays on access.
- When converting to arrays, call `toArray()` if the object defines it.
- Support public properties, magic `__get`/`__set`, and `ArrayAccess` when reading/writing objects.

### Error handling and edge cases
- Favor returning defaults over throwing, consistent with `get()` and `__get()` behavior.
- For missing keys, return wrapped default or `null` rather than raising errors.
- Keep `offsetUnset` behavior consistent with pending overrides clearing for single keys.

### Testing style
- Use Pest `it()` blocks for tests.
- Prefer `assertEquals` for value comparisons and `assertInstanceOf` for type checks.
- Use real-world-ish fixtures as seen in `tests/FluentValueTest.php`.
- Keep tests self-contained and deterministic; no IO or network access.

## Project structure
- `src/FluentValue.php`: core implementation
- `src/helpers.php`: global helpers `fluent()` and `value()`
- `tests/FluentValueTest.php`: Pest coverage
- `tests/Pest.php`: Pest bootstrap
- `docs/`: additional documentation and examples
- `docs/OBJECT_HANDLING.md`: object behavior and conversion rules
- `docs/DEVELOPMENT.md`: local workflows

## Conventions observed in this repo
- Uses closures extensively for lazy evaluation with parent context.
- Uses array and object access interchangeably.
- Uses dot notation keys for nested get/set/has.

## Cursor / Copilot rules
- No Cursor rules found (.cursor/rules/ or .cursorrules).
- No Copilot instructions found (.github/copilot-instructions.md).

## When adding new code
- Mirror the public API style already present (fluent, array access, dot notation).
- Keep public methods documented with concise docblocks where already used.
- Add tests alongside new behavior; follow the test naming pattern.

## When updating tests
- Keep test names descriptive and scoped to one behavior.
- Use the same data shapes as examples in README when possible.
- Update README examples if behavior changes.
