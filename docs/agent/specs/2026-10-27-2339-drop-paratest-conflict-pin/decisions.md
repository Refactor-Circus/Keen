# Decisions

## D-001

- Question: Create the GitHub issue as drafted in ticket-draft.md, or redraft it?
- Choice: Create as drafted
- Rationale: none given (answered in RingMaster)
- Date: 2026-10-28
- Raised by: ticket

## D-002

- Question: Which standards apply to this composer.json-only change (drop the brianium/paratest conflict)?
- Choice: architecture/dependencies only
- Rationale: none given (answered in RingMaster)
- Date: 2026-10-28
- Raised by: shape

## D-003

- Question: Update and commit composer.lock with this change?
- Choice: Update and commit composer.lock, limited to pestphp/pest, brianium/paratest and their dependencies
- Rationale: entry was missing from this log; reconstructed from the spec.md addendum dated 2026-10-28 (D-003), original answer not re-verified
- Date: 2026-10-28
- Raised by: spec-review

## D-004

- Question: Approve the spec "Drop the paratest conflict pin" (remove the brianium/paratest conflict from composer.json, update and commit composer.lock for pest and paratest only)?
- Choice: Approve
- Rationale: none given (answered in RingMaster)
- Date: 2026-10-28
- Raised by: approval

## D-005

- Question: composer.lock is gitignored and has never been committed; should this change start tracking it, or change only composer.json?
- Choice: Keep composer.lock untracked, as .gitignore and Laravel package practice already have it: remove the conflict block from composer.json only, run the targeted update against a local untracked lock just to check criterion 4, commit composer.json alone, and amend criterion 6 / D-003 to match
- Rationale: none given (answered in RingMaster). Supersedes D-003.
- Date: 2026-10-28
- Raised by: build
