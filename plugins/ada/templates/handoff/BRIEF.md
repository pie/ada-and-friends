# Brief — Step N: <title>

Asana: <task URL>
Branch: <branch name>
PR into: <the release branch PROJECT.md names | the feature branch this is a part of>
Plan: <none | .claude/briefs/<Epic task id>-plan.md, unit <ID>>
Ships: <independent | after <unit IDs>: built once they are in production (a part: merged into the feature branch)>
Status: draft | agreed | awaiting approval | building | in review | testing | ready for PR
Approval: <not needed (why) | approved by @login on <date> | awaiting @approvers>

Overwritten each step. This is the contract, not a log; the log is BUILD-LOG.md. A unit of a
plan is drafted as .claude/handoff/plan/<task id>.md and copied here when its build starts.

## Outcome

One sentence: what the customer, the kitchen or the admin can do or see afterwards.

## Decisions

-

## Constraints

- SYSTEM.md sections that apply:
- API.md entries to use:
- Files expected to change:
- Expected review: <small | medium | large: the behaviour and functions the reviewer reads
  closely, and any fragile area; checked against ada-size at the PR gate>
- Surfaces to check (S12):
- Out of scope:

## Build order

1.

## Definition of done

Each item observable: a thing Tess can see, with where to see it.

- [ ]
- [ ] phpcs clean on every touched function (P30); each touched file's phpcs count before → after
      in REVIEW-REQUEST

## Amendments

Changes to the agreed brief. Each one needs the developer's yes before it takes effect; an entry
without "Agreed by" is not part of the brief.

| Date | What changed (old → new) | Why (evidence) | Agreed by |
|---|---|---|---|
| | | | |

## Builder questions

(Bob writes. Ada answers inline.)

## Builder plan

(Bob writes, for anything beyond a small local change: functions to add or change, in which
files, in what order, using which existing API.)

Ada: [ ] Approved  [ ] Redirect — see notes below
