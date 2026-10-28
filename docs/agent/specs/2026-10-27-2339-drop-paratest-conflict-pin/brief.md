Risk: low

## What changed

- `composer.json`: removed the `conflict` block (`brianium/paratest: >=7.26.1`) and the trailing comma before it. One file, 1 line added and 4 removed. No code, test, config, docs or lock changes.

## Why

The conflict pin blocked composer from resolving Pest 5.3, which needs `brianium/paratest` 7.26.1+. That in turn blocked Cortex's job (Refactor-Circus/Cortex#13). Ticket: Refactor-Circus/Keen#5. Decisions that shaped it: D-002 (only `architecture/dependencies` applies) and D-005 (supersedes D-003): `composer.lock` is gitignored and stays untracked, so only `composer.json` is committed.

## Look at

- `composer.json:142`: the end of the file. Confirm that only the `conflict` block is gone, that the JSON is still valid, and that no `require` or `require-dev` entry changed.

## Tested

Round 2 gate: PASS.

- Criteria 1-3 (no `conflict` key, valid JSON, only that block removed): the branch diff, plus `composer validate --strict`.
- Criterion 4 (resolution): `composer show` gives pestphp/pest v5.3.4 and brianium/paratest v7.27.4, which is >= 7.26.1.
- Criterion 5 (tests): `composer test` passes (phpstan 0 errors, pint, pest 62 tests with 220 assertions, type coverage 100%). Serial pest also passes with 62 tests. No flakiness was seen.
- Criterion 6 (lock untracked): the branch diff contains only `composer.json`, and `composer.lock` is untracked.

## Risks

- Consumers or CI that run a fresh install may now resolve newer paratest versions. The gate saw no flakiness on 7.27.4, but the DB-backed hash chain tests and `tests/Modes/RenamedTables` run in parallel and are the most exposed to shared-state problems.
- `composer.lock` is untracked, so each environment resolves for itself.
- Nothing is left open (nits: 0).
