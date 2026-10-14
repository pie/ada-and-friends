---
name: planning
description: Ada's playbook for work too big for one PR - breaking it into units and features, drafting their briefs and branches, and working through the plan unit by unit. Ada loads it only when a plan is recommended or under way.
user-invocable: false
---

# Plans

Loaded from `ada:begin` when the work doesn't fit one PR, or when `.claude/handoff/plan/PLAN.md`
is under way. Everything in `ada:begin` still applies; this adds what a plan needs.

If the work doesn't fit, recommend a plan, show the developer the breakdown, and agree it before
any brief is written:

- **Units ship on their own.** Merged and released, production works and makes sense if the next
  unit never comes. Additive first (data, functions, settings), callers next, what the customer
  sees last, or behind a setting that is off. A refactor is its own unit, before the change that
  needs it.
- **A unit small enough for one PR** is top-level: its own brief, its own branch from the
  production branch, one PR into the release branch (`PROJECT.md` names both).
- **A unit that only works whole but is too big for one PR is a feature.** Its branch, from
  production, holds its brief. Its **parts** branch from it and PR back into it, each with its
  own brief, each small and reviewable alone; they needn't ship alone, because the feature
  branch keeps them out of the release branch. When every part is in, the feature branch PRs
  into the release branch. Two levels at most: a part that needs splitting means the plan is
  wrong.
- **Each unit ships `independent`, or `after` others.** A top-level unit after another is built
  once that one is in production; a part after another, once that one is merged into the feature
  branch. Prefer independent: every `after` is a wait.
- **Each unit and part is an Asana task:** the work is an Epic, units are its subtasks, a
  feature's parts are subtasks of the feature's task. Creating tasks is the developer's call:
  with their yes, create them with the Asana tools if you have them; otherwise give them the list
  and ask for the task IDs.

Then:

1. `ada-plan init`, and fill in `.claude/handoff/plan/PLAN.md`: the Outcome, why this breakdown,
   and the Units table. `ada-plan check` until it's clean.
2. `ada-plan briefs` drafts a brief per unit, `.claude/handoff/plan/<task id>.md`, with its
   branch, base, plan and order filled in. Write each one as **The brief** in `ada:begin` says. A
   feature's Build order is its parts, and its Definition of Done is what the smoke test checks
   when they're all in.
3. Confirm the plan and the briefs with the developer.
4. `ada-plan branches` creates every unit's branch.
5. Approval, if the project needs it (`ada:begin`): submit each top-level brief with
   `ada-brief submit <task id> <its base> <its branch>`, without checking the branch out. Each one
   carries the plan, so the approver sees the whole breakdown. A feature's parts are submitted
   once the feature's brief is approved; `ada-brief` refuses them before then.
6. Ask the developer to link each PR to its Asana task: on the task, under Apps, GitHub, paste
   the PR's URL into "Add GitHub pull request". Give them each task with its PR URL. You can't do
   it with the Asana tools, but you can check: a linked task has an attachment whose URL is the
   PR (`get_attachments` on the task). The app may also add a block to the end of the PR's
   description; `ada-brief` keeps it when it replaces the description.
7. Write `CHECKPOINT.md`, then work through the plan.

**Name units in full.** In the plan, the briefs, the PRs, Asana and when you talk to the
developer, another unit is "Unit D", never "D" alone. The draft PRs link "Unit D" to its PR; a
bare letter doesn't link, and "A" reads as a word.

The plan is agreed like a brief: changing a unit (adding, splitting, merging, dropping or
reordering) needs the developer's yes, goes under the plan's Amendments, and the briefs it
touches are submitted again.

**Working through a plan.** One unit at a time in a checkout. Units that are ready at the same
time can be built in parallel only in separate worktrees, one session each, if the developer
asks.

1. `ada-plan status` says what's ready: approved (or no approval needed), and not waiting on
   another unit. Pick with the developer.
2. Check the working tree (`ada:begin`, Session start, 8), and check out the unit's branch. Bring it up to
   date: merge `origin/<production>` into a top-level unit's branch, or the feature branch into a
   part's. Merge, never rebase: the branch is pushed. A conflict in code is Bob's, as the first
   item of the build.
3. Copy the approved brief, `.claude/briefs/<task id>.md` on the branch (or the plan's draft if
   no approval was needed), to `.claude/handoff/BRIEF.md`. From here it is an ordinary step, with
   the unit's ID as step N: Tess, Bob, Claudia, Tess, and the PR gate. The PR goes into the
   brief's `PR into:` branch.
4. After its PR: archive the step (`ada-handoff-init --archive <date>-<unit slug>`; the plan
   stays), write the checkpoint, and go back to 1.

**A feature's last PR.** When every part is merged into the feature branch, merge
`origin/<production>` into it, then spawn Tess in run mode on the feature's brief as a smoke test:
the Definition of Done, all the parts together. Claudia doesn't review it; she reviewed each
part. PIE treats it like a release: a functional smoke test, not a code review. Its PR description
lists the parts' PRs and the smoke-test result, then `ada-brief ready` as usual.

When every unit has shipped, set the plan's status to `done` and archive it:
`ada-handoff-init --archive-plan <date>-<Epic slug>`.

**Branches are updated twice:** when their build starts, and before they are merged. The
developer usually does the second in GitHub; a conflict comes back to you as a fix round.
