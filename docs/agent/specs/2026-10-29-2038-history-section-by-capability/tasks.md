# Tasks: History section by capability

## Group 1: Keen audit components and strings
package: Keen
depends on: none
files: resources/views/components/audit-trail.blade.php, resources/views/components/audit/entries.blade.php, resources/views/components/audit/changes.blade.php, resources/views/ui/index.blade.php, resources/views/ui/show.blade.php, lang/en/keen.php, tests/Feature/Ui/AuditComponentsTest.php, tests/Feature/Ui/KeenStringsTest.php, tests/Feature/SurfacesTest.php
criteria: 3, 4, 5, 17

- [x] 1.1 Write tests: port Atrium's AuditComponentsTest to tests/Feature/Ui/AuditComponentsTest.php rendering x-keen:: (criterion 3 cases, criterion 4 view-all href and entry-time link with and without the atrium.keen.entries.show route) and add tests/Feature/Ui/KeenStringsTest.php (no atrium::atrium. keys in Keen views; every keen::keen.* key used exists in lang/en/keen.php).
- [x] 1.2 Port audit-trail, audit/entries and audit/changes from Atrium into resources/views/components with the same props and markup, linking view-all to atrium.keen.history.show and the entry time to atrium.keen.entries.show only when that route exists, and using keen::keen.* strings only.
- [x] 1.3 Add history, activity, history_for, empty, when, app_badge, view_all, newest, older, no_changes, field, before and after to lang/en/keen.php.
- [x] 1.4 Switch ui/index and ui/show to x-keen::audit.entries and x-keen::audit.changes, and change SurfacesTest "shows each package's history in its own atrium screens" to render x-keen::audit-trail (criterion 17, noted for the PR list).
- [x] 1.5 Run vendor/bin/pest; all green, including tests/Feature/StylesTest.php over resources/views.

## Group 2: Composer conflict and the no-Atrium proof
package: Keen
depends on: none
files: composer.json, composer.local.json, phpunit.without-atrium.xml, tests/WithoutAtrium/bootstrap.php, tests/WithoutAtrium/WithoutAtriumTestCase.php, tests/WithoutAtrium/WithoutAtriumTest.php, .github/workflows/tests.yml, tests/Feature/ComposerTest.php
criteria: 1, 20

- [x] 2.1 Write tests: tests/Feature/ComposerTest.php (require has only refactor-circus/keystone among suite packages, atrium in suggest and require-dev, conflict refactor-circus/atrium is exactly "<0.2.0", tests.yml has a without-atrium job running composer remove --dev refactor-circus/atrium --no-update and composer test:without-atrium) and tests/WithoutAtrium/WithoutAtriumTest.php (which applies WithoutAtriumTestCase itself) with criterion 20's assertions, the PSR-4 prefix removal first.
- [x] 2.2 Add phpunit.without-atrium.xml, tests/WithoutAtrium/bootstrap.php (load the autoloader, drop the RefactorCircus\Atrium\ PSR-4 prefix, throw if findFile still resolves Atrium) and WithoutAtriumTestCase registering McpServiceProvider, KeenServiceProvider and ShopServiceProvider only, outside phpunit.xml.dist's suites.
- [x] 2.3 Add the conflict block and the test:without-atrium script to composer.json, call it from test after @test:unit, and mirror the composer.json changes into composer.local.json by hand (never regenerate it: it points refactor-circus/atrium at the screen-registry worktree aliased dev-main).
- [x] 2.4 Add the without-atrium job to .github/workflows/tests.yml.
- [x] 2.5 Run vendor/bin/pest and composer test:without-atrium; all green.

## Group 3: History page, Keen screens and History nav items
package: Keen
depends on: 1
files: src/Atrium/KeenPlugin.php, src/Atrium/Http/Controllers/HistoryUiController.php, src/Atrium/Http/Controllers/AuditUiController.php, resources/views/ui/history.blade.php, resources/views/ui/index.blade.php, resources/views/ui/show.blade.php, resources/views/components/audit/entries.blade.php, config/keen.php, lang/en/keen.php, tests/Feature/Ui/HistoryPageTest.php, tests/Feature/Ui/HistoryNavigationTest.php, tests/Feature/Ui/KeenScreensTest.php, tests/Feature/Ui/NothingRegisteredTest.php, tests/Feature/Ui/ScreenAccessTest.php, tests/Fixtures/Screens/ProbeSection.blade.php
criteria: 6, 7, 8, 13, 14, 15

- [x] 3.1 Write tests: HistoryPageTest (criteria 6, 7, 15 including the not-shadowed and ?source=other cases), HistoryNavigationTest (criterion 8 cases, groups defaults, query bound), KeenScreensTest (probes per slot, undeclared slots including history, tab open, row probes with $record), NothingRegisteredTest (D-032 structural checks with stated header-cell counts) and ScreenAccessTest (criterion 14).
- [x] 3.2 Add HistoryUiController::show ported from Atrium's HistoryController (404s, History::rules() validation, History::allows() 403, ListAuditEntriesAction read with ['source' => $package->key] + $validated) and the ui/history view.
- [x] 3.3 Add the history/{package} route named history.show in KeenPlugin::routes() and KeenPlugin::screen(string $key, string $kind): Screen with the access closure over KeenPlugin::authorizer().
- [x] 3.4 Declare and resolve keen.entries, keen.entry and keen.history in AuditUiController and HistoryUiController, render header.actions, screen-tabs, screen-tab-panel, before, after and aside per criterion 13, and add the optional screen and rows props to x-keen::audit.entries for row.actions.
- [x] 3.5 Add the keen.atrium.history.nav config (enabled, sort 90, exclude, groups with showroom and pennantplus defaults) and the lazily authorized "History" items in KeenPlugin::navigation().
- [x] 3.6 Run vendor/bin/pest; all green.

## Group 4: History section, activity tab and registration guard
package: Keen
depends on: 2, 3
files: src/Atrium/HistoryExtensions.php, src/KeenServiceProvider.php, config/keen.php, resources/views/ui/history-section.blade.php, resources/views/ui/activity-tab.blade.php, tests/Pest.php, tests/ArchTest.php, tests/Feature/Ui/HistoryExtensionsTest.php, tests/Feature/Ui/HistorySectionTest.php, tests/Feature/Ui/ActivityTabTest.php, tests/Feature/Ui/RegistrationGuardTest.php, tests/Feature/Ui/ResetsScreenExtensionsTest.php, tests/Fixtures/Screens/PreRegistryAtrium.php, tests/Fixtures/Screens/HostScreensServiceProvider.php
criteria: 2, 9, 10, 11, 12, 16, 17, 19

- [x] 4.1 Write tests: HistoryExtensionsTest (normalisation, custom slot, testing throws, production reports once and skips, malformed shapes, boot-time throw), HistorySectionTest (criteria 10 and 11 on fixture host screens, shipped exclude default, counting AuditTrail fake, query bounds), ActivityTabTest (criterion 12 including the 403 for a hidden ?tab=keen.activity), RegistrationGuardTest (criterion 2 with PreRegistryAtrium) and ResetsScreenExtensionsTest (D-031 direct hook calls), plus the Atrium-boundary arch rule in tests/ArchTest.php.
- [x] 4.2 Add the keen.atrium.history config (screens, slot, exclude ['keen.*', 'atrium.*'], tabs) with its banner, and the ui/history-section and ui/activity-tab views.
- [x] 4.3 Add final HistoryExtensions with registerIfSupported(), register() (validate, normalise, throw in local/testing or report once and skip elsewhere), the keen.history section, the keen.activity tabs and the shared visible() check.
- [x] 4.4 Call HistoryExtensions::registerIfSupported() from KeenServiceProvider::boot() behind the class_exists and keen.ui.enabled guard, by string class name.
- [x] 4.5 Apply ResetsScreenExtensions to Feature and Modes/RenamedTables in tests/Pest.php (criterion 17, noted for the PR list).
- [ ] 4.6 Run vendor/bin/pest; all green.

## Group 5: Docs and the full gate
package: Keen
depends on: 4
files: README.md, resources/boost/skills/keen-development/SKILL.md, CLAUDE.md, CHANGELOG.md, tests/Feature/DocsTest.php
criteria: 17, 18

- [ ] 5.1 Write tests: tests/Feature/DocsTest.php asserts README.md and the Boost skill contain neither x-atrium::audit nor atrium.history.show.
- [ ] 5.2 Update README, the Boost skill and CLAUDE.md "Role in the suite" for the History section, activity tab, nav items, atrium.keen.history.show, the keen.atrium.history config and its bad-entry behaviour, the Atrium conflict and the without-atrium CI lane.
- [ ] 5.3 Add the CHANGELOG.md entry naming the new route and components, the config keys, the replacements for Atrium's removed history UI and the refactor-circus/atrium <0.2.0 conflict, and list every changed existing test by file and test name for the PR (criterion 17).
- [ ] 5.4 Run vendor/bin/pest and composer test; all green.
