# References

- **Current component:** `Atrium/resources/views/components/audit-trail.blade.php` (props `source`, `subject`, `scope`, `action`, `limit`, `title`) and `components/audit/entries.blade.php`.
- **Contracts:** Keystone `Audit/Contracts/AuditTrail`, `Audit/History` (`allows`, `available`) and `Packages/Package::audits()`.
- **Keen binding:** `Keen/src/Domains/Audit/Services/KeenAuditTrail.php` and `KeenServiceProvider`.
- **Roster activity tab being replaced:** `Roster/resources/views/ui/organizations/show.blade.php:321-322` (`<x-atrium::audit-trail source="roster" :scope="$organization" />`) and the `roster.audit.view` permission.
- **Inline trails being replaced (about 22):**
  - Roster: roles, teams and users overview
  - Cortex: virtual-agents index and form, override partial
  - Impex: runs index and show, messages show
  - Polycart: carts index and show
  - PennantPlus: atrium index
  - Showroom: products index plus 9 show pages
