# Brief — Step N: <title>

Asana: <task URL>
Branch: <branch name>
Worktree: <.claude/worktrees/<slug>: the step's worktree, from ada-worktree new>
PR into: <the release branch PROJECT.md names | the feature branch this is a part of>
Plan: <none | .claude/briefs/<Epic task id>-plan.md, Unit <ID>>
Ships: <independent | after Unit <ID>, Unit <ID>: built once they are in production (a part: merged into the feature branch)>
Overlaps: <none | #N: <what we're doing about it>; ...>
Status: draft | agreed | awaiting approval | building | in review | testing | ready for PR
Approval: <not needed (why) | approved by @login on <date> | awaiting @approvers>

<!-- Overwritten each step. This is the contract, not a log; the log is BUILD-LOG.md. A unit of
a plan is drafted as .claude/handoff/plan/<task id>.md in the main checkout and copied here, into
the unit's worktree, when its build starts. The plan/ copy is then marked with this worktree and
no longer edited: amendments go here.
The guidance in comments like this one doesn't show in the draft PR. -->

## Outcome

<!-- One sentence: what the customer, the kitchen or the admin can do or see afterwards. -->

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

<!-- Each item observable: a thing Tess can see, with where to see it. -->

- [ ]
- [ ] phpcs clean on every touched function (P30); each touched file's phpcs count before → after
      in REVIEW-REQUEST

## Amendments

<!-- Changes to the agreed brief. Each one needs the developer's yes before it takes effect; an
entry without "Agreed by" is not part of the brief. Decisions, and Constraints less its
"- Out of scope:" bullet, are the approved sections: an approver approved them. Re-approval is
"not needed" or "needed: <trigger>", from the triggers in /ada:begin and PROJECT.md. A change
to an approved section with no row here needs approving again. -->

| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |
|---|---|---|---|---|
| | | | | |

## Builder questions

<!-- Bob writes. Ada answers inline. -->

## Builder plan

<!-- Bob writes, for anything beyond a small local change: functions to add or change, in which
files, in what order, using which existing API. -->

Ada: [ ] Approved  [ ] Redirect — see notes below
