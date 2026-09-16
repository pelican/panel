# Code Standards

Code style, static analysis, and tests are enforced by CI on every pull request; run them locally before pushing. The conventions at the bottom are checked in review.

## Code Style

We use [Pint](https://laravel.com/docs/pint) with the `laravel` preset plus a few overrides; see [pint.json](./pint.json).

Run it with `composer pint`.

## Static Analysis

[Larastan](https://github.com/larastan/larastan) (PHPStan) must pass without errors. The configuration lives in [phpstan.neon](./phpstan.neon) and includes a custom rule (`App\PHPStan\ForbiddenGlobalFunctionsRule`) that forbids the global `app()` and `resolve()` helpers; use dependency injection instead.

Run it with `composer phpstan`.

## Tests

Tests are written with [Pest](https://pestphp.com) and live in `tests/Unit`, `tests/Integration`, and `tests/Filament`. New features and bug fixes should come with tests. CI runs the suites against both SQLite and MySQL.

Run the whole suite with `vendor/bin/pest` (add `--parallel` to speed it up).

## API Contract

The HTTP API is frozen: from 1.0 onward, responses and accepted request inputs only change in backwards-compatible ways. Read [api-versioning.md](./api-versioning.md) before changing anything the API returns or accepts.

## Conventions

* Use backed enums in `app/Enums` instead of class constants.
* Panel UI is built with Filament: the three panels live in `app/Filament/Admin`, `app/Filament/Server`, and `app/Filament/App`, with shared components in `app/Filament/Components`.
* Translation strings go in `lang/en` only; other languages are managed through [Crowdin](https://crowdin.com/project/pelican-dev).
* Database changes are made through new migrations; do not edit migrations that have already shipped.
