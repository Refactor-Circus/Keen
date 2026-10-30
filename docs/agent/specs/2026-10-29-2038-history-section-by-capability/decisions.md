# Decisions

Earlier D-ids are recorded in shape.md.

## D-020: Suite: proving the package runs without Atrium, and refusing an Atrium too old for the screen registry
- Choice: Prove it both ways: a WithoutAtrium suite inside `composer test`, and a CI lane with Atrium uninstalled. Every package that renders Atrium screens adds a composer `conflict` on refactor-circus/atrium versions older than the screen-extensions registry (Atrium PR #16).
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: user, cross-spec decision (2026-10-29)

## D-021: Suite: malformed integration config entries (screen maps, slots) at boot
- Choice: Throw InvalidArgumentException in local and testing. In production, report once, skip the entry and keep booting (same rule as Atrium D-022).
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: user, cross-spec decision (2026-10-29)

## D-022: What should `keen.atrium.history.exclude` default to?
- Choice: ['keen.*', 'atrium.*']
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-023: How should History nav items be grouped?
- Choice: Package label plus a `groups` config map, with defaults for showroom and pennantplus
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-024: Should the nav label be 'History' or 'Audit log'?
- Choice: History
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-025: Do Keen's own screens declare the `history` slot that shape.md's D-013 addendum lists for audited detail screens?
- Choice: No: the standard slots only, with `aside` on keen.entry
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-026: Which screens should the History section exclude by default? Atrium registers as an auditing package, so the wildcard puts History on its dashboard and settings screens.
- Choice: (b) default to ['keen.*', 'atrium.*']
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-027: Confirm the route name `atrium.keen.history.show` rather than keeping `atrium.history.show`. decisions.md does not exist, so this is not yet recorded as final.
- Choice: atrium.keen.history.show
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-028: How should invalid `keen.atrium.history` config behave at boot?
- Choice: Log a warning, skip the bad entry and keep booting
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-029: Should composer.json add a `conflict` constraint on Atrium releases before the screen registry, to protect consumers who upgrade Keen alone? This changes the dependency set.
- Choice: Add `conflict` for refactor-circus/atrium below the PR #16 release
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-030: When a tab registered by Keen is hidden by its authorize check and the request carries ?tab=keen.activity, should the response be 403 or a fallback to the default tab?
- Choice: Match whatever Atrium's registry does for hidden tabs
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-031: How should the test reset be proven (criterion 16)?
- Choice: Single-test direct call to the reset hook, asserting a custom registration is gone and keen.history persists
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)

## D-032: How should the 'no markup change when nothing is registered' guarantee be enforced given that criterion 17 permits editing existing tests?
- Choice: Add a dedicated snapshot-free structural assertion test (no screen-tabs, no section testids, same column count)
- Rationale: The user accepted the recommendation.
- Date: 2026-10-29
- Raised by: spec review; the user accepted all recommendations (2026-10-29)
