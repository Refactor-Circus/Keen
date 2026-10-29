# Proposals from the post-mortem (awaiting decision)

Run `wf_d1b6b31a-a2d`. Rationale and evidence are in `post-mortem.md`.

## architecture/dependencies (amend, target: global)

# Dependencies

A package requires only what its core needs: shared runtime comes from the base package, other domains are reached through their public seams, and every optional integration is guarded so the package installs and runs without it.

## Between domains

- Change another domain's data only by calling its action (`app({Verb}{Entity}Action::class)->execute(...)`), never by writing its models directly — the action owns the transaction and the event pair. See [[actions-events]].
- React to another domain by listening to its action or lifecycle events, not by reaching into its services' internals.
- Depend on another domain's `Contracts/`, `Data/` and models (for reading); keep its `Support/` and private helpers out of reach.
- Framework-free core domains never import an integration domain; integrations depend on the core, not the reverse.
- Package-wide code at the top level ([[domain-modules]]) may be used by every domain.

## Composer `require`

- Shared runtime (base actions, request bases, event contracts, provider bases) comes only from the base package, which every package in the suite requires. Don't copy it into a package.
- Add a dependency only when the package's core behavior needs it, and only with approval.
- An optional integration is never in `require`. Keep it in `require-dev`, or unlisted when it would be circular (the integration itself requires this package).
- A `conflict` entry is a temporary pin against a known-bad upstream range. Record why it exists, and remove it once upstream is fixed: it blocks every consumer's resolution, not only this package's.

## Lock files

- A package never commits `composer.lock`; it is gitignored, so `composer.json` is the whole dependency contract. A dependency change ships as a `composer.json` diff only.
- Check that a change resolves against a local, untracked lock (a targeted update limited to the packages involved), not by committing the result.
- Before a spec or task says what the lock (or any file) will contain in the commit, check that the file is tracked: `git ls-files composer.lock` and `git check-ignore composer.lock`.
- Tests and tooling never read `composer.lock`. A fresh checkout or worktree has none. Assert on `composer.json`, or on what is installed through `Composer\InstalledVersions`.

```php
// Not this: fails wherever the gitignored lock is absent
$lock = json_decode(file_get_contents($root.'/composer.lock'), true);

// This: the contract, plus what is actually installed
$composer = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
expect($composer['require-dev'])->toHaveKey('refactor-circus/keen')
    ->and(InstalledVersions::isInstalled('refactor-circus/keen'))->toBeTrue();
```

## Optional integrations

- Reference an optional package's classes only behind a check that it is installed and enabled: `class_exists()` at the wiring point, or a single `active()` method that also checks its provider is loaded and the package's config allows it.
- Keep all those references in one integration class or an `Integration` domain, so static analysis ignores exactly one place.

```php
public function active(): bool
{
    return class_exists(OtherServiceProvider::class)
        && $this->app->getProvider(OtherServiceProvider::class) !== null
        && $this->config->get('{package}.other.enabled', true) === true;
}
```

## Feature flags

- Never require a feature-flag package (such as Pennant) of consumers; gating is the host application's concern.
- A package may ship a feature class for hosts that use one, with the flag package as a dev dependency only.
- Surfaces the package itself can switch off are config flags, checked strictly; see [[service-providers]].

## Reference implementation (Refactor Circus)

The base package is `refactor-circus/keystone`: `Actions\Action`, `Data\Data`, the `Contracts\Action*Event` contracts, `Models\Concerns\DispatchesModelEvents` and `Support\{ServiceProvider,PackageServiceProvider}`. `Keystone\Cortex\CortexIntegration::active()` is the guard for Cortex, which cannot be a dev dependency because it requires Keystone; `phpstan.neon.dist` ignores Cortex classes in that one file. Feature-flag management lives in `refactor-circus/pennantplus`, which plugs in like any other package. Every package's `.gitignore` lists `composer.lock`; local resolution runs through `composer local:update`, which writes the equally gitignored `composer.local.json` / `composer.local.lock`.

---

## tooling/composer-local (add, target: project)

# Composer local workflow

Resolve and inspect dependencies in Keen through `composer local:install` / `composer local:update`, which use the gitignored `composer.local.json` and `composer.local.lock` and keep the sibling checkouts symlinked into `vendor/`.

## Targeted updates

- Update only the packages a change touches: `composer local:update -- {vendor/a} {vendor/b} --with-dependencies`. Never run a full update for a scoped change.
- Don't run a bare `composer update {pkgs}` here. Without a `composer.lock` it cannot do a partial update, and it replaces the sibling symlinks with VCS installs.
- Neither `composer.local.json` nor `composer.local.lock` is ever committed. After an update, `git status --porcelain` lists only the files the change meant to touch.

## Checking resolution

- Use `composer show {vendor/package}` once per package. `composer show a b` reads `b` as a version constraint and fails.
- `composer why-not {package} {version}` also lists upstream requirements, such as another package's own minimum. Only a `refactor-circus/keen` line is a blocker from Keen.

```bash
composer local:update -- pestphp/pest brianium/paratest --with-dependencies
composer show pestphp/pest
composer show brianium/paratest
composer why-not brianium/paratest 7.26.1   # no refactor-circus/keen line
```
