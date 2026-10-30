# Spec review

## Round 1

Revised `spec.md` against the round 1 findings. Everything the listed open questions depend on was left unsettled: Q1 to Q4 stay in "Risks and open questions", and the config default (`['keen.*']`) is unchanged but marked "pending Q1". Where a finding asked for a decision, the spec now holds regardless of the outcome and the test or text says so.

### Changed

- **Criterion 2 (guard).** Added `method_exists(Atrium::class, 'screens')` to the guard so an Atrium from before the registry boots cleanly. Added a test with the registry capability reported missing. Why: an upgrade of Keen alone would otherwise fatal on every request.
- **Criterion 4.** Added tests for the entry time link (link when the route exists, plain text when it doesn't). Why: the "while that route exists" branch was unobservable.
- **Criterion 5.** Added a test that every `__('keen::keen.*')` key used in the new views exists in `lang/en/keen.php`, and named the new keys. Why: missing keys render as the key string and pass.
- **Criterion 7.** Named the invalid inputs (`subject_id` without `subject_type`, `limit` above the maximum), the fixture size (5 entries, `limit=2`) and added a `?source=other` check.
- **Criterion 8.** Nav authorization is resolved lazily; added a query-count test with several audited packages.
- **Criterion 9.** Stated that section keys and screen keys are separate namespaces (and the section never attaches to `keen.history` under `keen.*`). Specified cast/drop behaviour for malformed config shapes. Added the invalid-slot unit test and a boot-time test. The throw-versus-skip outcome is deliberately left to the open question on invalid config at boot.
- **Criterion 10.** Tests set `exclude` explicitly (so they hold whichever way Q1 goes), plus one test that reads the shipped default and an `atrium.dashboard` fixture. Replaced "spying on AuditTrail" with a counting fake of `AuditTrail` bound in the container, asserting zero `list` calls on refused screens with `History::allows` stubbed. Added query-count bounds for audited and refused screens. Added a refusal when a detail record is not an Eloquent model.
- **Criterion 11.** Added the non-model test and a note about morph classes and unmapped models (to be documented).
- **Criterion 12.** Scope isolation is now stated to rest on host screen authorization (`History::allows` takes only package-wide input), with a cross-organization test. The hidden-tab test asserts concrete absences; its 403-or-fallback status follows Atrium and is left to the open question. Replaced the `?tab=activity` default-tab test with a check that Keen registers no `activity` tab, leaving the fallback to the Roster spec. The missing-screen case now asserts 200, no exception, no "Activity" text, no testid.
- **Criterion 13.** Listed the standard slots per screen. The `history` slot is neither declared nor asserted, because that is an open question. Tests now assert on `probe-{slot}` testids emitted by stub views, name the undeclared-slot fixtures, count row probes against the row count, and include an explicit "nothing registered" markup test (no `screen-tabs`, no `section-` testid, fixed header cell count).
- **Criterion 14.** Added list-screen (null record) and unauthenticated cases.
- **Criterion 15.** Added the `?source=other` test.
- **Criterion 18.** Cut the docs test to a file check for the two removed names in README and the skill. The finding about checking more strings conflicts with the finding about dropping the brittle grep; the first was chosen. Documentation of new names is a review item.
- **Out of scope.** Added the four missing boundaries (no aliases or shims, no change to Atrium's own screens beyond `exclude`, no API or MCP changes, no new permission or policy).
- **Risks.** Pointed the wildcard-cost risk at the new query-count tests, and added a not-shadowed route test to the merge-order note.

### Left unchanged on purpose

- Q1 to Q4 and the remaining open questions. In particular: the default for `exclude`, the Composer `conflict` constraint, throw-versus-skip at boot, 403-versus-fallback, and the `history` slot.
- Criterion 16 (test reset proof) and criterion 17 (forced test changes). Both are open questions. The new "nothing registered" markup test in criterion 13 is added but criterion 17's allowance is untouched.
