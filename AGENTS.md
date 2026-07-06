# AGENTS.md

Guidance for coding agents working on `ckrack/messenger-logging-bundle` — a
Symfony bundle that assigns a stable UUIDv7 to each Messenger message and emits
structured lifecycle logs (queued, received, handled, failed, retried).

## Project Facts

- PHP `^8.2`, Symfony `^7.4 || ^8.0` (see `composer.json` for the source of
  truth; do not widen or narrow constraints without an explicit reason).
- Namespace is `C10k\MessengerLoggingBundle`, but all user-facing naming
  (config root, service tags, package name) uses the `ckrack` prefix:
  `ckrack_messenger_logging`. Keep that split intact.
- No runtime dependency on Monolog — only `psr/log`. Monolog is dev-only for
  tests.

## Commands

Local tooling runs through Docker Compose via Make:

```bash
make check    # composer validate --strict + cs + phpstan + phpunit — run before committing
make test     # phpunit --testdox
make static   # phpstan (level 10, phpstan.dist.neon)
make cs       # php-cs-fixer check
make fix      # php-cs-fixer fix
```

- If Docker is unavailable, the direct equivalents are
  `vendor/bin/phpunit`, `vendor/bin/phpstan analyse -c phpstan.dist.neon
  --no-progress`, and `vendor/bin/php-cs-fixer`.
- php-cs-fixer needs `--allow-unsupported-php-version=yes` (flag, not the
  deprecated `PHP_CS_FIXER_IGNORE_ENV` env var). Prefer config/flags over env
  vars in general.
- Pre-commit hooks run composer-validate and php-cs-fixer; PHPStan and PHPUnit
  run on pre-push. Don't bypass hooks.
- After any change, state explicitly which checks you ran and their results.

## CI

`.github/workflows/ci.yml` runs a matrix of PHP 8.2–8.5 × composer
`lowest`/`stable` deps. PHPStan has failed before only on the lowest-deps leg —
when touching dependencies or type-level code, consider
`composer update --prefer-lowest --prefer-stable` locally. GitHub Action
versions are pinned to commit SHAs; keep new pins in that style.

## Commits, PRs, Releases

- Conventional Commits are enforced (commitsar, `strict: true`) and PR titles
  must be semantic (`feat:`, `fix:`, `docs:`, `refactor:`, `chore:`, `test:`,
  `ci:`, `build:`).
- Releases are cut by release-please from commit messages: `feat:` bumps
  minor, `fix:` bumps patch. Choose the type deliberately.
- One commit per logical change set, not per file. Leave the working tree
  clean after committing.

## Code Style

PSR-12 via php-cs-fixer, plus conventions the fixer does not enforce — match
the existing code:

- `declare(strict_types=1);` in every file.
- Classes are `final`; constructor property promotion with `readonly`.
- Nullable types are written `Type|null`, never `?Type`.
- Global functions are imported with `use function ...;`.
- Event subscribers stay thin and uniform: back-fill the UUID, then log once
  with context from `MessengerLogContextBuilder`. Put shared logic in the
  builder, not in subscribers.
- PHPStan runs at level 10 over `src/` and `tests/`. Add `ignoreErrors`
  entries only with a comment explaining why, following the existing pattern
  in `phpstan.dist.neon`.

## Design Principles

- **Privacy-first stamp logging.** Never reflect stamp getters generically —
  stamps can carry sensitive data or large payloads (e.g.
  `HandledStamp::getResult()`). Stamp data enters logs only through an
  explicit `StampNormalizerInterface` implementation, discovered via the
  `ckrack_messenger_logging.stamp_normalizer` tag or the config map
  (`RegisterStampNormalizersPass`). Unknown stamps are listed by class name
  with an empty context.
- **One subscriber per lifecycle event**, each with a configurable PSR-3 log
  level. Worker-level events (`WorkerStartedEvent` etc.) are intentionally not
  logged — don't add them.
- The bundle must be a no-op when disabled and must not alter default logger
  behavior unless `log_channel` is configured.

## Testing

- PHPUnit 11 with attributes (`#[CoversClass]`), no annotations.
- Assert on logged output via Monolog's test handler
  (`tests/Fixtures/MonologTestLoggerTrait.php`), not logger mocks.
- DI behavior (normalizer discovery, config overrides) is tested with a
  compiled container in `tests/DependencyInjection/`.
- New lifecycle or normalizer behavior needs both a unit test and, where DI is
  involved, a compiled-container test.

## Documentation

The README is the spec — update it in the same commit as any behavior change
(events, config keys, log context fields). Style preferences:

- Lifecycle flows are shown as Mermaid sequence diagrams.
- Markdown wraps at 80 characters (`.editorconfig`).
