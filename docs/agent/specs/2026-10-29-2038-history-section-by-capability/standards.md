# Standards for History section by capability

| Standard | File | Source | Why it applies |
| --- | --- | --- | --- |
| architecture/dependencies | vendor/refactor-circus/standards/standards/architecture/dependencies.md | refactor-circus/standards@dev-main | Atrium stays a suggest; Atrium code only in src/Atrium behind class_exists; no host package named in code |
| architecture/configuration | vendor/refactor-circus/standards/standards/architecture/configuration.md | refactor-circus/standards@dev-main | New `keen.atrium.history` config block (screens, slot, exclude, tabs, nav) with banner, casts and defaults |
| architecture/service-providers | vendor/refactor-circus/standards/standards/architecture/service-providers.md | refactor-circus/standards@dev-main | Screen extensions registered from KeenServiceProvider::boot() behind the ui.enabled / Atrium guard |
| architecture/public-api | vendor/refactor-circus/standards/standards/architecture/public-api.md | refactor-circus/standards@dev-main | New public surface: `x-keen::audit-trail`/`audit.*`, section and tab keys, route name `atrium.keen.history.show`; final classes |
| architecture/naming | vendor/refactor-circus/standards/standards/architecture/naming.md | refactor-circus/standards@dev-main | HistoryUiController, HistoryExtensions naming |
| http/authorization | vendor/refactor-circus/standards/standards/http/authorization.md | refactor-circus/standards@dev-main | Host check first, then History::allows(); Keen screens' access closure via Keen's policies |
| http/routes | vendor/refactor-circus/standards/standards/http/routes.md | refactor-circus/standards@dev-main | New named dashboard route `atrium.keen.history.show` |
| data/data-objects | vendor/refactor-circus/standards/standards/data/data-objects.md | refactor-circus/standards@dev-main | History page builds ListAuditEntriesData::from() in an Atrium controller |
| testing/test-layers | vendor/refactor-circus/standards/standards/testing/test-layers.md | refactor-circus/standards@dev-main | Feature tests with fixture host screens; route names, not URLs |
| testing/arch-tests | vendor/refactor-circus/standards/standards/testing/arch-tests.md | refactor-circus/standards@dev-main | New arch rule: no RefactorCircus\Atrium use outside src/Atrium; existing rules must keep passing |
