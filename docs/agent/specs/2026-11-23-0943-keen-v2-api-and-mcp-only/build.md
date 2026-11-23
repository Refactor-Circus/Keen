# Build: Keen v2: API and MCP only

## Group 1

Audited packages endpoint and MCP tool (criteria 1 to 6).

### Files

- `src/Domains/Audit/Actions/ListAuditedPackagesAction.php` (new): `handle(?Authenticatable $viewer = null): array` (`list<Package>`). It keeps the `PackageRegistry` packages that are not `keen`, where `audits()` is true and `History::allows($package, $viewer, [])` is true. It sorts them by label, then key, with `strcmp`. It has no transaction and writes nothing.
- `src/Domains/Audit/Events/AuditedPackagesListingActionEvent.php` (new, no payload) and `AuditedPackagesListedActionEvent.php` (new, `list<string> $keys`): `ActionStartingEvent`/`ActionFinishedEvent` plus `ReadActionEvent`.
- `src/Domains/Audit/Http/Resources/AuditedPackageResource.php` (new): `final`, `@mixin Package`, `{key, label}`, and `schema()` with both fields required.
- `src/Domains/Audit/Http/Requests/IndexAuditedPackageRequest.php`, `src/Domains/Audit/Http/Controllers/AuditedPackageController.php` (new); `src/Domains/Audit/routes.php` adds `GET packages` as `packages.index`.
- `src/Domains/Audit/Mcp/Requests/ListAuditedPackagesMcpRequest.php`, `src/Domains/Audit/Mcp/Tools/ListAuditedPackagesTool.php` (new): empty input schema, and `outputSchema()` is `AuditedPackageResource::collectionSchema($schema)`. `src/Mcp/KeenServer.php` adds the tool to `TOOLS`.
- `tests/Pest.php`: `parityGaps()` exempts `ListAuditedPackagesAction` from the `ui` surface only.
- `tests/Feature/Mcp/OutputSchemaTest.php`: the count of structured tools goes from 3 to 4 (see Deviations).

### Tests

- `tests/Feature/AuditedPackagesActionsTest.php` checks that the action:
  - returns `Package`s sorted by label, then key
  - never lists `keen`
  - leaves out a package with `{key}.audit.enabled` false
  - leaves out a package whose `authorizeHistory()` refuses the viewer, keeps the rest, and still lists that package for a viewer the rule allows
  - dispatches the Listing event, then the Listed event (both are `ReadActionEvent`s, and Listed carries the keys)
  - records no audit entry
- `tests/Feature/Http/AuditedPackagesTest.php`, by route name: `keen.packages.index` answers 200 with exactly `{"data": [{key, label}, ...]}`, and 401 for a guest when `keen.authorization` is on.
- `tests/Feature/Mcp/ListAuditedPackagesToolTest.php`: `mcpTool()` returns the same items as structured content and passes the output schema check, and the tool is in `KeenServer::TOOLS`.
- `composer analyse` passes. Per the job instructions, the Pest suite is left to the gate.

### Deviations

- `ShopServiceProvider` is unchanged. Tests that need extra packages (for sorting, refusal and turned-off auditing) register them in `PackageRegistry` inside the test. This follows the task's "only if needed".
- `tests/Feature/Mcp/OutputSchemaTest.php` is not in the group's file list, and task 1.5 asks for it to pass unchanged. It asserts exactly 3 structured tools, though, and the new tool (which criterion 5 requires) makes 4, so the count is now 4. Nothing else in that test changed. `SurfacesTest` and `PolicyTest` are untouched.
- While Atrium is still installed (until Group 2), signed-in viewers also see `atrium`, because its package authorizes them. So the refusal case checks with `toContain`/`not->toContain` rather than an exact list. The exact-list cases run as a guest, which never sees `atrium`, so they hold both before and after Group 2.
