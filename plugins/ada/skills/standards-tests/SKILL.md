---
name: standards-tests
description: PIE's standard on tests (P29), split from the other P rules so Tess can be preloaded with only this. Preloaded into Bob, Claudia and Tess. The other P rules are in ada:standards; a project's own rules (S numbers) are in its .claude/team/STANDARDS.md.
user-invocable: false
---

# PIE standards: tests

One of the P rules, kept on its own so Tess carries only what she needs. The rest, with how rules
are numbered and cited, are in `ada:standards`.

## Tests

**P29. Every PR arrives with tests for what it changes.** Each Definition of Done item has at least
one test, of the strongest kind that can reach it:

1. **PHPUnit** (`tests/unit/`) for business logic, and anything about dates or money. A unit test
   fixes the clock (`set_now()`), so date edge cases are repeatable.
2. **PressTest** (Playwright, `pie/presstest.io`) for front-end flows a browser can drive, if the
   project has a suite (`PROJECT.md`).
3. **A functional script** (`tests/functional/`) where neither reaches: admin screens, emails,
   scheduled actions, exports. Written so someone who has never seen the change can run it and get
   the same answer.

As much as possible, not as little as passes: the regression next to the change gets a test too.
Where no test is possible, the PR says which item and why. Applies to new and changed behaviour;
existing code without tests is not a finding unless the diff changes it.

A case that should be automated but can't be yet (no PressTest access, code that reads the system
clock) is written as a functional script marked `Automate as: presstest` or `Automate as: unit`, so
it can be ported later.

Tests are written from the brief, not the code, by Tess; Bob does not edit them. A test that can't
fail (asserts nothing, or asserts what the code happens to do rather than what the brief says) is
not a test.
