# Domain Modules

Atrium mirrors the `mono` application's domain-module layout: code lives in self-contained modules under `src/Domains/{Domain}/`, namespace `JayI\Atrium\Domains\{Domain}`.

## Layout

```
src/Domains/{Domain}/
├── {Domain}ServiceProvider.php   # extends JayI\Atrium\Support\ServiceProvider
├── routes.php                    # route definitions, loaded inside the dashboard group
├── Models/                       # {Entity}Model.php
├── Policies/                     # beside Models/
├── Data/                         # value objects (NavItem, WidgetDefinition, SearchResult, ...)
├── Actions/ Events/              # actions, action events and model lifecycle events
├── Http/{Controllers,Requests,Middleware}/
├── Services/                     # registries and managers bound as singletons
└── Contracts/ Concerns/ Exceptions/ Console/ Support/   # only when used
```

- Domains: `Access`, `Dashboard`, `Navigation`, `Plugins`, `Search`, `Settings`, `Widgets`. Create a subdirectory only when it holds something.
- Package-wide code stays at the top level: `Atrium`, `AtriumServiceProvider`, `Facades\Atrium`, `Actions\Action`, `Http\Requests\Request`, the event contracts in `Contracts\`, `Console\Commands\InstallCommand`, and `Support\` (`Icons`, `StyleRegistry`, `ServiceProvider`, `Models\Concerns\DispatchesModelEvents`).
- Migrations stay in `database/migrations`, views in `resources/views`, translations in `lang`. There is one config file, `config/atrium.php`; domains read from it and never ship their own.

## Registration

- `AtriumServiceProvider` (the class in `extra.laravel.providers`) merges the config, registers `JayI\Atrium\Domains\DomainServiceProvider`, and keeps cross-cutting wiring: policies, views, migrations, translations, Blade components, publish tags and `atrium:install`.
- `DomainServiceProvider` lists every domain provider in a private `$providers` array and registers them in a loop. Adding a domain means adding its provider there.
- A domain provider binds its own singletons and loads its `routes.php` with `loadDashboardRoutesFrom()`, which wraps the file in the shared group (`atrium.path`, `atrium.domain`, `atrium.middleware`, the `atrium.` name prefix).

## Naming

| Type | Pattern | Example |
|------|---------|---------|
| Model | `{Entity}Model` | `Domains\Dashboard\Models\DashboardModel` |
| Action | `{Verb}{Entity}Action` | `CreateDashboardAction` |
| Action event | `{Entity}{Verb}ActionEvent` | `DashboardCreatedActionEvent` |
| Model event | `{Entity}{Hook}Event` | `DashboardWidgetDeletedEvent` |
| Controller | `{Entity}Controller` | `DashboardController` |
| Request | `{Verb}{Entity}Request` | `StoreDashboardRequest` |

## Stored identifiers

A model that moves or is renamed keeps its previous class name as its morph alias (`Relation::morphMap` in the domain provider), so polymorphic columns and audit records written under the old name still resolve. `DashboardServiceProvider` maps `JayI\Atrium\Models\Dashboard` and `JayI\Atrium\Models\DashboardWidget`; a test proves an old stored value resolves.
