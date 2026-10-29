# History section by capability — Shaping Notes

## Scope

Keen owns where audit history appears in the dashboard. Today six packages embed `<x-atrium::audit-trail>` in about 22 views, and each renders nothing unless Keen is bound. After this change, Keen registers one History section with Atrium's screen extensions registry, and it attaches to every screen that declares an audited subject (Atrium spec `2026-10-29-2037-screen-extensions-registry`, D-003).

In scope:

- **Registration.** From `KeenServiceProvider::boot()`, only when Atrium is installed (Keen only suggests Atrium; the add-on checking for its host is the allowed direction), register a "History" section for every screen whose declaration includes an audited subject.
- **What the section renders.** The declared package key, plus subject, scope or package-wide, and the optional action prefix. It keeps today's `audit-trail` behaviour:
  - shown only when the package audits (`Package::audits()`)
  - shown only when the viewer passes `History::allows()`
  - "view all" links to `atrium.history.show`
- **Activity tab.** Register the `activity` tab on `roster.organization`, the organization-scoped history that Roster's built-in `activity` tab used to show. Gate it on `roster.audit.view` through the host's access check. This uses the per-screen form, because a tab is a screen-specific choice. Spec writing decides whether this generalises to "an activity tab on any screen that declares a scope".
- **Component ownership.** Open for spec review: whether `x-atrium::audit-trail` and `audit/entries` stay in Atrium (rendered by Keen's section) or move into Keen as `keen::history`. Today the Atrium component reads only Keystone contracts.

## Decisions

- D-001: Keen attaches by capability and never names other packages' screens for the History section. (User, 2026-10-29.)
- D-002: placement follows the screen's declared slot (each host declares where its audited section goes), so layouts stay with the host.
- D-003: the `activity` tab on `roster.organization` is the one per-screen registration, which is allowed because it targets a string key with no Roster import.
- D-004: the `audit-trail` component moves from Atrium into Keen, as the view behind Keen's History section. It is composed from `x-atrium::*` components, with no stylesheet. `x-atrium::audit.entries` stays in Atrium. (User, 2026-10-29.)
- D-005: the organization activity tab registers as `keen.activity` (vendor-prefixed, Atrium D-008). Old `?tab=activity` links fall back to the default tab.

## Context

- **Depends on:** Atrium `screen-extensions-registry`. Coordinate with the host specs, Roster `adopt-atrium-screen-registry` and Showroom, Polycart, PennantPlus, Cortex and Impex, which remove their inline trails and declare subjects. Until a host declares a subject, its history simply doesn't show, and nothing breaks.
- **Out of scope (section B):** `historyNavItem()`, the history page, and the `atrium.keen.entries.show` link check.
- **References:** see `references.md`.

## Standards

- architecture/dependencies: Keen registers itself and only checks for its host (Atrium).
- architecture/public-api: section and tab keys (`keen.history`, `activity`) become public.
- atrium/components: no package CSS.
- http/authorization: `History::allows()` plus the host's screen authorization first.
- testing/test-layers: feature tests with a fake host screen declaring a subject, plus one per real host in that host's suite.
