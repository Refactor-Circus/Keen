# Drop the paratest conflict pin

Ticket: [Refactor-Circus/Keen#5](https://github.com/Refactor-Circus/Keen/issues/5)

## Goal

Let composer resolve Keen's dev dependencies with Pest 5.3 (which needs `brianium/paratest` 7.26.1+), so Cortex's job (Refactor-Circus/Cortex#13) can proceed.

## Acceptance criteria

1. `composer.json` has no `brianium/paratest` entry under `conflict`.
2. `composer.json` has no empty `conflict` section left behind, and stays valid JSON (`composer validate --strict` passes and `jq -e 'has("conflict")|not' composer.json` exits 0).
3. No other `require` / `require-dev` entry in `composer.json` changes (`pestphp/pest` stays `^5.3.1`; there is no direct `phpunit/phpunit` entry, it stays transitive). `git diff composer.json` shows only the removed `conflict` block.
4. `composer update pestphp/pest brianium/paratest --with-dependencies` resolves with `pestphp/pest` 5.3.x and `brianium/paratest` >= 7.26.1, checked with `composer show pestphp/pest brianium/paratest` and `composer why-not brianium/paratest 7.26.1` (no blockers).
5. `composer test` exits 0 with the same test count as before the change. It is run twice with `--parallel` (as the script does) and once without `--parallel`; any new flakiness blocks the change.
6. `composer.lock` stays untracked and gitignored (D-005, superseding D-003). `git status --porcelain` lists only `composer.json`; the commit contains `composer.json` alone. The targeted update in 4 runs against a local untracked lock, only to verify resolution.

## Design

Delete the `"conflict": { "brianium/paratest": ">=7.26.1" }` block at the end of `composer.json` (and the trailing comma before it). No code, test, config or docs change. This follows `architecture/dependencies`: no new dependency is added and none is made required of consumers; the change only removes a dev-resolution blocker.

## Out of scope

- Any change to the `pestphp/pest` constraint or any other `require` / `require-dev` entry.
- Any other dependency, code or docs change.
- Tracking `composer.lock` or changing `.gitignore`.

## Addendum 2026-10-28 (D-003)

`composer.lock` is updated and committed alongside `composer.json`, limited to `pestphp/pest`, `brianium/paratest` and their dependencies. Do not run a full `composer update`.

## Addendum 2026-10-28 (D-005)

D-005 supersedes D-003: `composer.lock` is gitignored and was never committed, so it stays untracked. Only `composer.json` is committed. The targeted `composer update pestphp/pest brianium/paratest --with-dependencies` runs against a local untracked lock just to check criterion 4.

## Risks and open questions

- Paratest 7.26.1+ may change parallel test behaviour; the DB-backed hash chain, append-only guard and `tests/Modes/RenamedTables` could show shared-state flakiness across workers. Criterion 5 (repeated parallel and serial runs) confirms it.
