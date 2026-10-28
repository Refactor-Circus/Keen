# Spec review

## Round 1

- AC2: named the checks (`composer validate --strict`, `jq -e 'has("conflict")|not'`).
- AC3 and Out of scope: composer.json has no direct `phpunit/phpunit` entry; reworded to cover pest and other require entries, checked by `git diff composer.json`.
- AC4: named the targeted `composer update pestphp/pest brianium/paratest --with-dependencies` and the `composer show` / `why-not` checks, so unrelated packages are not bumped.
- AC5: made it concrete (same test count, two parallel runs, one serial run); dropped the undefined P8.5 / L13.* lane ids and expanded the risk text to name the DB-backed tests.
- AC6: composer.lock is tracked (verified with `git ls-files`); phrased as a `git status --porcelain` check, leaving the commit decision to the open question (unchanged).

## Pass 2: recheck D-003

- D-003 (update and commit composer.lock, limited to pest, paratest and their dependencies) landed in `spec.md`: AC6 now states the lock is committed with a limited diff, Out of scope carves out the targeted lock update, and an Addendum records the decision. No open questions remain.
