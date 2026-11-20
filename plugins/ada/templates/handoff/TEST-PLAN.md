# Test plan — Step N

Written by Tess from BRIEF.md and TEST-AREAS.md, before reading the code.

- Definition of Done items covered:
- TEST-AREAS areas touched:
- Clock positions used (only if the step changes date or time logic):

## Checks

Kind: **terminal** (Tess runs it), **local** (developer runs it on the local site with these exact
steps, or Tess does, in the browser or with WP-CLI, if the developer asks), **suite** (an
automated test).

| # | Kind | Precondition | Action | Expected | Observe at |
|---|---|---|---|---|---|
| 1 | terminal | | `php -l`, `phpcs` on changed files | clean | terminal |
| | | | | | |

## Tests supplied

One row per Definition of Done item (`P29`). Type: **unit**, **presstest** or **functional**.

| DoD item | Type | File | If none, why |
|---|---|---|---|
| | | | |

## Sequences

For delivery generation, pausing or payments: the chain of events, in order, and the expected
state after each.

## Regression

Behaviour next to the change that must not move.

## Left to the release run

What this step could reach but doesn't run, for the release run before the release PR into main:
the PressTest files, shared suites and clock positions in `TEST-AREAS.md`. One line each, or
`none`. A step runs the whole unit-test suite and only the PressTest files for what it changes.

-
