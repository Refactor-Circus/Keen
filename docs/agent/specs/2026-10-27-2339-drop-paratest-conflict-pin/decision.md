# Decision: Drop the paratest conflict pin

Run `wf_be500958-cb1` (2026-10-28): **approved** at stage `decide`, 2 rounds (1 red gate round).

## Reasons

- Gate green; only nits left (0 nits, 0 findings left).
- Round 1 blocker (`ringside check` stale generated files) was environmental; fixed by `ringside sync`, which only rewrote gitignored files.

## Nits

None.

## Breaks

- **Cortex**: `composer test:unit` fails against the job (exit 2): 656 tests, 655 passed, 1 error. `KeenInCiTest::it_keeps_refactor_circus_keen_in_require_dev_with_a_lock_entry` cannot read `composer.lock` in the impact worktree (`Failed to open stream: No such file or directory`, tests/Feature/Compat/KeenInCiTest.php:89). Likely an impact-check artifact (fresh worktree has no gitignored lock), not a behaviour change from this job; proposed as a follow-up for the user to accept or decline.

## Previous run

Run `wf_3f7aba25-05c` (2026-10-28): needs-decision at stage `build` (Q-001, composer.lock tracking), answered as D-005.
