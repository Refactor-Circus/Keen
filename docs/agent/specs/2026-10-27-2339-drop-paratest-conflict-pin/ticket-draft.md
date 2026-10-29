# Drop the paratest conflict pin

## Problem

`composer.json` has `"conflict": { "brianium/paratest": ">=7.26.1" }`. Pest 5.3 requires paratest 7.26.1 or newer, so composer cannot resolve Keen's dev dependencies with Pest 5.3. Cortex's job (Refactor-Circus/Cortex#13) waits on this.

## Desired outcome

The `brianium/paratest` entry is removed from the `conflict` section of `composer.json` (and the `conflict` section too if it ends up empty), and composer resolves with Pest 5.3.

## Acceptance hints

- `composer.json` no longer lists `brianium/paratest` under `conflict`.
- `composer update` resolves with Pest 5.3 / paratest 7.26.1+.
- `composer test` passes.
- The PR's CI lanes (P8.5 / L13.*) are green.

## Out of scope

- Any change to pest or phpunit constraints.
- Any other dependency, code or docs change.
