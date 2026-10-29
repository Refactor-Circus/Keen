VERDICT: CHANGES

## Gate results (worktree Keen--2026-10-27-2339-drop-paratest-conflict-pin)

- `composer test`: PASS (phpstan 0 errors, pint passed, pest 62/62, 220 assertions).
- `composer lint:check`: PASS.
- `composer exec ringside check`: FAIL (exit 1). Output: "ringside: 1 problem(s) - Generated files are stale or missing. Run: vendor/bin/ringside sync". BLOCKER.

## Changed files

- composer.json (diff removes only the `conflict` block and the trailing comma).

## Acceptance criteria

1. Proven: the diff removes `brianium/paratest` from `conflict` in composer.json.
2. Proven by diff: the `conflict` key is gone entirely and the JSON is well-formed (the composer commands ran). `composer validate --strict` and `jq` were not run separately.
3. Proven: `git diff main...HEAD` shows only the conflict block removed.
4. Proven: installed `pestphp/pest` is v5.3.4 and `brianium/paratest` is v7.27.4 (>= 7.26.1). `composer why-not` was not run.
5. Partly proven: `composer test` passes with 62 tests. The second parallel run and the non-parallel run were not repeated.
6. Partly proven: only composer.json changed in the branch. The `git status` and `.gitignore` state of composer.lock was not checked.

## Blocker

`ringside check` reports stale or missing generated files. Run `ringside sync` in the worktree and commit any resulting generated files, or confirm the failure is already present on main.
