# Tasks: Drop the paratest conflict pin

## Group 1: Drop the conflict and verify resolution
depends on: none
files: composer.json
criteria: 1, 2, 3, 4, 5, 6

- [x] 1.1 Record the baseline: `composer test` test count (composer.lock is gitignored and stays untracked, D-005)
- [x] 1.2 Remove the `conflict` block (and the trailing comma before it) from `composer.json`; check `composer validate --strict` and `jq -e 'has("conflict")|not' composer.json`
- [x] 1.3 Run `composer update pestphp/pest brianium/paratest --with-dependencies` against the local untracked lock (never a full `composer update`); check `composer show pestphp/pest brianium/paratest` (pest 5.3.x, paratest >= 7.26.1) and `composer why-not brianium/paratest 7.26.1`
- [x] 1.4 Check `git diff composer.json` shows only the removed block and `git status --porcelain` lists only `composer.json`
- [x] 1.5 Run `composer test` twice with `--parallel` and once without; same test count as baseline, no flakiness
- [x] 1.6 Commit `composer.json` alone
