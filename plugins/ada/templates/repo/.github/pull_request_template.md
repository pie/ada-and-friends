Asana:
Brief: <`.claude/briefs/<task id>.md`, approved by @login | approval not needed, and why>
Plan: <none | unit <ID> of `.claude/briefs/<Epic task id>-plan.md`; ships <independent | after #PR>>

<!-- A feature's last PR (feature branch into the release branch): list its parts' PRs here,
with the smoke-test result under Tested. It is checked like a release, by a smoke test, not a
code review. -->

## For the reviewer

Review: <about N minutes (small | medium | large), from `ada-size`; raised for a fragile area, and why>
Actual review time: <the reviewer fills this in, so the estimates can be tuned>

Three to five lines: where the review is (the changed logic, and any fragile area, by file and
function), what is cheap to skim, and what wasn't verified.

## What changed and why

## Built

Files touched, and any decision that wasn't in the brief.

## Reviewed

Rules checked (STANDARDS.md numbers), what was found, how it was resolved.

## Tested

- Tests supplied (S29), by Definition of Done item: unit / PressTest / functional script, with
  paths. Any item without one, and why:
- Verified (with the clock time for anything date-related):
- Not verified, and why:
- Failed and left: none

## Surfaces checked

- [ ] Checkout
- [ ] Emails
- [ ] Admin
- [ ] Exports / production sheets
- [ ] Other plugins' hooks
- [ ] Template `@version` headers

## Known gaps

Out-of-scope items found and logged, not fixed.

---

Built by Bob, reviewed by Claudia, tested by Tess. Ada and friends, Claude Code.
