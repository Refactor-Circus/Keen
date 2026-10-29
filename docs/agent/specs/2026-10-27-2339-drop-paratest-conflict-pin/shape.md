# Drop the paratest conflict pin — Shaping Notes

## Scope

Remove `brianium/paratest` from the `conflict` section of `composer.json` (and the section itself, now that it is the only entry), so composer resolves Keen's dev dependencies with Pest 5.3. Ticket: Refactor-Circus/Keen#5; Cortex#13 waits on it.

Out of scope: pest or phpunit constraints, any other dependency, code or docs change.

## Decisions

- D-001: ticket created as drafted.
- D-002: standards for this change are `architecture/dependencies` only.

## Context

- `composer.json` line 146-148: `"conflict": { "brianium/paratest": ">=7.26.1" }`; `pestphp/pest` is already `^5.3.1`, which needs paratest 7.26.1+.
- No product docs involved; no UI surface, so no visuals.

## Standards

- `architecture/dependencies`: the change edits the package's dependency constraints.
