# Keen

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `jayi/keen`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.

## Role in the suite

- Keen is the audit log of the jayi suite. No other package depends on it: it listens to `JayI\Foundation\Contracts\ActionStartingEvent` / `ActionFinishedEvent` and binds jayi/foundation's `AuditTrail`, which every package and Atrium read history through.
- Package-specific knowledge (labels, snapshot extras, subject and scope pickers, context, redaction) comes from `JayI\Foundation\Audit\AuditHooks` or the `Auditable` event contract, never from Keen referencing another package.
- Code lives in `src/Domains/Audit` (actions, events, model, services, HTTP, MCP, commands, policy). Actions extend `JayI\Foundation\Actions\Action` with a starting and finished event each; requests extend Foundation's request bases.
- Atrium screens use only `x-atrium::*` components and Atrium's safelisted utilities; `tests/Feature/StylesTest.php` enforces it. Keen ships no stylesheet.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.
