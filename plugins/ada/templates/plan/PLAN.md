# Plan: <title of the Epic>

Epic: <Asana Epic URL>
Status: draft | agreed | building | done

Written by Ada when a piece of work is too big for one PR. Each unit is one brief, one branch and
one PR, small enough for a short PIE review. `ada-plan` reads the Units table: keep its columns
as they are. Committed with every brief as `.claude/briefs/<Epic task id>-plan.md`; approving a
top-level brief approves its place here.

## Outcome

One sentence: what the customer, the kitchen or the admin can do or see when every unit has
shipped.

## Why this breakdown

What each unit gives on its own, and why the order is what it is. A unit that ships alone leaves
production working if the next one never comes: additive first (data, functions), callers next,
what the customer sees last.

## Units

One row per unit. **Top-level units** (no Parent) branch from production and PR into
next-release, as `PROJECT.md` names them. A top-level unit too big for one PR is a **feature**:
its branch holds its brief, and its **parts** (rows with it as Parent) branch from it and PR
back into it; it PRs into next-release when every part is in. Two levels at most.

Ships: `independent`, or `after <IDs>`. A top-level unit after another starts its build once
that one is in production. A part after another starts once that one is merged into the
feature branch.

| ID | Unit | Task | Branch | Parent | Ships |
|---|---|---|---|---|---|
| A | <title> | <Asana task id> | <branch> | | independent |

## Amendments

Changes to the agreed plan: a unit added, split, merged, dropped or reordered. Each needs the
developer's yes, and the briefs it touches are submitted again.

| Date | What changed | Why | Agreed by |
|---|---|---|---|
| | | | |
