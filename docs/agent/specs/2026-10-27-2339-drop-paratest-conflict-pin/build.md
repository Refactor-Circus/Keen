# Build

## Group 1

Worktree: `/Users/jay/Projects/packages/Keen--2026-10-27-2339-drop-paratest-conflict-pin`, branch `ring-master/2026-10-27-2339-drop-paratest-conflict-pin`, commit `4a19754` ("2026-10-27-2339-drop-paratest-conflict-pin: group 1 Drop the conflict and verify resolution").

### Files

- `composer.json`: removed `"conflict": { "brianium/paratest": ">=7.26.1" }` and the comma before it. The commit has this one file (1 insertion, 4 deletions).

Not committed, gitignored and untracked (D-005): `composer.local.json` was regenerated from the edited `composer.json` by `bin/composer-local.php`, and `composer.local.lock` was updated by the targeted update below. There is no `composer.lock` in the worktree.

### Checks

- 1.1 Baseline: `pest --list-tests` (lists tests, runs none) found **62 tests** before the change on PHPUnit 13.4.1 / paratest 7.26.0. Per file: Arch 14, Log 7, Mcp/OutputSchema 1, Performance 8, Policy 2, Recording 12, Styles 1, Surfaces 9, Tables 2, Modes/RenamedTables 6. This listing ran with the main checkout's vendor; its `tests/`, `src/` and `phpunit.xml.dist` match the worktree base `6262a9b` (`git diff --stat 6262a9b c1ae544 -- tests src phpunit.xml.dist` is empty).
- 1.2 Before the edit, `jq -e 'has("conflict")|not' composer.json` printed `false` and exited 1. After the edit it printed `true` and exited 0. `composer validate --strict` reports `./composer.json is valid`.
- 1.3 Targeted update: `composer local:update -- pestphp/pest brianium/paratest --with-dependencies`, which runs `composer update pestphp/pest brianium/paratest --with-dependencies` with `COMPOSER=composer.local.json` against the untracked `composer.local.lock`. This is not a full update. Lock changes:
  - brianium/paratest v7.26.0 => **v7.27.4**
  - pestphp/pest v5.3.1 => **v5.3.4**
  - phpunit/phpunit 13.4.1 => 13.4.5
  - phpstan/phpdoc-parser 2.3.6 => 2.3.9
  - refactor-circus/atrium, keystone and ringside only refreshed their path-repository reference to the sibling checkout's HEAD. The symlinks were unchanged ("Source already present").

  `composer show pestphp/pest` gives v5.3.4 and `composer show brianium/paratest` gives v7.27.4. Each was run on its own, because `composer show a b` reads `b` as a version constraint and errors out. `composer why-not brianium/paratest 7.26.1` names only `pestphp/pest v5.3.4 requires brianium/paratest (^7.27.4)`, which is pest's own minimum, not a blocker from Keen. `composer why-not brianium/paratest 7.27.4` confirms that version is installed. No `refactor-circus/keen` conflict remains. After the update, `pest --list-tests` on the worktree vendor (PHPUnit 13.4.5) still finds the same 62 tests.
- 1.4 `git diff composer.json` shows only the removed `conflict` block (`],` becomes `]`). `git status --porcelain` listed only ` M composer.json`.
- 1.5 Not run here. Per the job instructions the gate runs `composer test` after the build. It should run twice with `--parallel` and once without, and match the 62-test baseline.
- 1.6 Committed `composer.json` alone.

### Deviations

- Criterion 4's `composer show pestphp/pest brianium/paratest` was run as two single-package `composer show` calls, because Composer reads the second argument as a version.
- Criterion 4's `composer why-not brianium/paratest 7.26.1` still prints one line, but it comes from upstream pest 5.3.4 requiring `^7.27.4`, not from Keen. The resolved paratest is 7.27.4, which meets ">= 7.26.1".
- The targeted update ran through the existing `composer local:update` script, so it used the gitignored `composer.local.json` / `composer.local.lock`, keeping the sibling symlinks in `vendor/`. A bare `composer update <pkgs>` cannot do a partial update without a `composer.lock`, and running it would have replaced the symlinks with VCS installs.

## Rework round 1

Commit `5b7e8d5` ("2026-10-27-2339-drop-paratest-conflict-pin: rework round 1"). It is an empty commit that records the round. The branch diff against `main` is still `composer.json` alone.

### Blocker: `composer exec ringside check` failed

- Cause: the failure was not caused by this change. The same check also fails in the main checkout (`/Users/jay/Projects/packages/Keen`, which has the same "Generated files are stale or missing" output). The stale files are the generated standards files, which are gitignored. They had fallen behind the local Ringside checkout (dev-main) that `vendor/refactor-circus/ringside` points to.
- Fix: ran `composer exec ringside sync` in the worktree, which reported "synced 22 standards from 1 package(s) + local; 2 file(s) written". Both files it wrote are gitignored (`docs/agent/standards.index.yml`, `docs/agent/standards.summary.md`). Afterwards `git status --porcelain` was empty, so there were no generated files to commit, and criterion 6 still holds.
- `composer exec ringside check` now reports "ringside: check passed." in the worktree.
- The main checkout still fails `ringside check` until someone runs `vendor/bin/ringside sync` there. Doing so only rewrites gitignored files.

### Checks

- `composer test:unit`: passed, 62 tests and 220 assertions, the same as the 62-test baseline.
