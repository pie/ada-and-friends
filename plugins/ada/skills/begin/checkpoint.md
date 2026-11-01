# Ada: a checkpoint that isn't this step's, or is finished

Read from `/ada:begin` Session start, 6, or `/ada:pickup`, when `ada-worktree check` prints
`other`, exits 2, or the checkpoint's step is finished.

## `other <branch> <path>`

The checkpoint is another step's, on `<branch>`: don't resume it here. Look up that branch's PR
(`gh pr list --head <branch> --state all --limit 1 --json number,state`). If it is merged or
closed, the step is finished: offer to archive it (A finished step that isn't this checkout's,
below). Otherwise, if `<path>` isn't `-`, that step's worktree is at `<path>` (the rest of the
line): move the session there with the `EnterWorktree` tool, `path` set to it, and start Session
start again there. If `<path>` is `-`, or `EnterWorktree` isn't available, tell the developer
which branch the checkpoint belongs to and which checkout it is in (or that the branch is checked
out nowhere), and **stop**.

## Other cases

- **Its step is finished** (its PR is merged or closed, or it says the step was shelved): say
  so in one line ("previous step finished: PR #N merged; archiving it"). In a step's
  worktree, don't archive it here: tell the developer to run `/ada:begin` from the main
  checkout, which offers to remove this worktree (Session start, 7), and **stop**. In the main checkout
  (`ada-worktree main` prints the same path as `git rev-parse --show-toplevel`), run
  `ada-handoff-init --archive <date>-<slug of that step>`. That moves the old handoff files to
  `.claude/handoff/archive/` and puts fresh templates in place; a plan in
  `.claude/handoff/plan/` stays. Don't wait; carry on to Session start, 7. The one exception:
  if the step was shelved before its PR and the project's `DESIGN.md` has uncommitted changes, before you
  archive, show them to the developer and wait for their answer, as for a superseded design
  (`design-step.md`). If it lists leftovers on the local site (test orders, a clock left set
  (`ada-clock status`), a frozen queue), tell the developer:
  they're cleared before anything runs on the site.
- **`check` exits 2:** it couldn't read the checkpoint's `Branch:` as a local branch. The
  checkpoint was written before `Branch:` held the name alone (`change/x, pushed`, a name in
  backticks, `none yet`), or names a branch since deleted. Read only its `Branch:` line. If
  that plainly names a branch whose PR is merged or closed, the step is finished: offer to
  archive it (the last bullet). Otherwise resume nothing: show the developer the `Branch:`
  line and the branch checked out here (`git branch --show-current`), and ask which branch
  the checkpoint belongs to. Once they confirm, rewrite `Branch:` as that name alone, run
  `check` again and act on what it prints.
- **A finished step that isn't this checkout's** (after `other` or exit 2, its PR merged or
  closed): ask the developer whether to archive it, in one line ("the checkpoint here is
  change/x's, PR #N merged: archive it?"), and wait. Never archive unasked, and never hand the
  developer a command for it: on yes, archive it yourself. If `check` gave a `<path>` that is a
  worktree under the main checkout's `.claude/worktrees/`, first run
  `ada-worktree remove "<path>"`: it moves that worktree's handoff files to the main
  checkout's `.claude/handoff/archive/<date>-<slug>/` and removes the worktree, keeping the
  branch. Then archive this checkout's handoff files with
  `ada-handoff-init --archive <date>-<slug of that step>` (add `-2` to the name if that
  archive folder exists), and carry on to Session start, 7. If either refuses, tell the
  developer what it printed and **stop**. On no, leave everything as it is and **stop**.
