# Standards for Keen v2: API and MCP only

| Standard | File | Source | Why it applies |
| --- | --- | --- | --- |
| http/routes | vendor/refactor-circus/standards/standards/http/routes.md | refactor-circus/standards@dev-main | New `keen.packages.index` route in `Domains/Audit/routes.php`, inside the configured group |
| http/request-persist | vendor/refactor-circus/standards/standards/http/request-persist.md | refactor-circus/standards@dev-main | `IndexAuditedPackageRequest` owns the work; the controller only returns `persist()` |
| http/resources | vendor/refactor-circus/standards/standards/http/resources.md | refactor-circus/standards@dev-main | `AuditedPackageResource` is a final, model-less resource in `Http/Resources`, shared by HTTP and MCP |
| http/authorization | vendor/refactor-circus/standards/standards/http/authorization.md | refactor-circus/standards@dev-main | Guest 401 and per-package history checks on the new endpoint |
| actions/actions-events | vendor/refactor-circus/standards/standards/actions/actions-events.md | refactor-circus/standards@dev-main | New `ListAuditedPackagesAction` with its event pair (Keen's read actions keep events, as `ReadActionEvent`s) |
| data/data-objects | vendor/refactor-circus/standards/standards/data/data-objects.md | refactor-circus/standards@dev-main | The new action takes no input array; existing Data rules feed the OpenAPI parameters |
| architecture/naming | vendor/refactor-circus/standards/standards/architecture/naming.md | refactor-circus/standards@dev-main | Names of the new action, events, request, controller, resource and tool |
| architecture/domain-modules | vendor/refactor-circus/standards/standards/architecture/domain-modules.md | refactor-circus/standards@dev-main | New code goes in `src/Domains/Audit`; `src/Atrium` is deleted |
| architecture/dependencies | vendor/refactor-circus/standards/standards/architecture/dependencies.md | refactor-circus/standards@dev-main | No Atrium reference left; Keystone is the only suite require; Scramble is dev-only |
| architecture/configuration | vendor/refactor-circus/standards/standards/architecture/configuration.md | refactor-circus/standards@dev-main | `keen.ui` and `keen.atrium.*` are removed; the remaining banners stay accurate |
| architecture/service-providers | vendor/refactor-circus/standards/standards/architecture/service-providers.md | refactor-circus/standards@dev-main | The provider drops view and translation loading, the publish tags and the plugin registration |
| architecture/public-api | vendor/refactor-circus/standards/standards/architecture/public-api.md | refactor-circus/standards@dev-main | Removing the UI, components and config is a breaking change (v2.0.0) |
| testing/test-layers | vendor/refactor-circus/standards/standards/testing/test-layers.md | refactor-circus/standards@dev-main | Behavior tested at the action; HTTP and MCP test only their wiring, by route name |
| testing/arch-tests | vendor/refactor-circus/standards/standards/testing/arch-tests.md | refactor-circus/standards@dev-main | Arch rules: no `RefactorCircus\Atrium` or `Dedoc\Scramble` in `src`; parity over HTTP and MCP |
