# Post-mortem: Drop the paratest conflict pin

Spec `2026-10-27-2339-drop-paratest-conflict-pin`, PR [#6](https://github.com/Refactor-Circus/Keen/pull/6) (merged 2026-10-28), ticket [#5](https://github.com/Refactor-Circus/Keen/issues/5). Post-mortem run `wf_d1b6b31a-a2d`.

## Metrics

| Rounds | Decisions | Deviations | Escalations | Findings (blocker / major / minor / nit) |
| --- | --- | --- | --- | --- |
| 2 | 5 | 3 | 1 | 1 / 0 / 0 / 0 |

Runs: `wf_3f7aba25-05c` (needs-decision, round 0), then `wf_be500958-cb1` (approved, round 2).

## Root causes

1. **Spec gap (composer.lock tracked).** Spec review pass 1 said composer.lock was tracked ("verified with git ls-files"). It is gitignored. D-003 (update and commit the lock) and the D-004 approval both rested on that claim. The build caught it, which cost one run, Q-001, and D-005 to supersede D-003. Evidence: spec-review.md Round 1 AC6, decisions.md D-003 and D-005, decision.md "Previous run".
2. **Tooling (stale generated files).** The round 1 gate failed on `ringside check` ("Generated files are stale or missing"). The gitignored standards index and summary had drifted behind the local Ringside dev-main. Main failed the same way. The fix was `ringside sync`, which left the rework commit 5b7e8d5 empty. Evidence: round-1/gate.md, build.md Rework round 1.
3. **Reviewer noise.** Round 1 marked criteria 2, 4, 5 and 6 as only partly proven, although build.md 1.2–1.4 shows the builder ran them. The only real blocker was the environmental one.
4. **Spec gap (commands in criterion 4).** `composer show a b` reads `b` as a version. `why-not paratest 7.26.1` still prints pest's own `^7.27.4` minimum. A bare targeted `composer update` cannot run without a lock. The builder logged three deviations and used `composer local:update`.
5. **Tooling (impact check).** The Cortex impact check failed `KeenInCiTest` because the test reads composer.lock and a fresh worktree has none. It was reported as a Break and needed a human to clear it.

## Human review delta

PR #6 had no human review comments. The only human correction came through RingMaster: D-005 kept composer.lock untracked. The process has these gaps:

- Spec review should verify repo-state claims with `git ls-files` / `git check-ignore`. It should check them again when a decision widens what gets committed.
- The gate should run `ringside sync`, or compare against main, before `ringside check`.
- Decisions should be logged when they are made. D-003 was reconstructed afterwards.
- The impact check should seed or ignore gitignored files such as composer.lock.

## Proposals awaiting your decision

Each one needs accept, edit or reject. This post-mortem ran unattended, so nothing has been adopted yet. The spec stays `merged` until they are decided. The full proposal text is in `post-mortem-proposals.md`.

- **`architecture/dependencies` (amend, global → queue).** Adds a "Lock files" section: never commit composer.lock, so a dependency change is a composer.json-only diff. Check tracked state with `git ls-files` / `git check-ignore` before claiming it. Tests read `composer.json` or `InstalledVersions`, never the lock. It also adds a `conflict` bullet: a conflict entry is a temporary pin, so record why it exists and remove it once upstream is fixed. Evidence: root causes 1 and 5, Cortex `tests/Feature/Compat/KeenInCiTest.php:89`, composer.lock gitignored in every suite package.
- **`tooling/composer-local` (add, project).** Targeted updates go through `composer local:update -- <pkgs> --with-dependencies`, never a bare `composer update <pkgs>`. Run `composer show` once per package. Only a `refactor-circus/keen` line from `why-not` counts as a blocker from Keen. Evidence: root cause 4, build.md Deviations.

## Rejected proposals

None yet.
