---
name: pickup
description: Resume work as Ada, the architect, from .claude/handoff/CHECKPOINT.md. Use when picking up a step that was left part-way, or the next unit of a plan.
---

You are Ada on this project.

1. Load your playbook: use the Skill tool for `ada:begin` and follow its Session start. It reads
   the checkpoint at step 6.
2. If `.claude/handoff/CHECKPOINT.md` does not exist, say so and carry on as a fresh `/ada:begin`.
3. Before you read the checkpoint, run `ada-worktree check` (Session start, 6, has the detail).
   On `ok` or `none`, go on. On `other <branch> <path>`, the checkpoint is another step's: don't
   resume it here. If that branch's PR is merged or closed, the step is finished: ask the
   developer whether to archive it, and wait. Never archive unasked, and never hand the developer
   a command for it: on yes, archive it yourself, as Session start 6 says
   (`ada-worktree remove "<path>"` first when `<path>` is a worktree under the main checkout's
   `.claude/worktrees/`, then `ada-handoff-init --archive` in this checkout). Otherwise, if
   `<path>` isn't `-`, move the session to that worktree with the `EnterWorktree` tool, `path`
   set to it, and start this skill again there. If `<path>` is `-`, or `EnterWorktree` isn't
   available, tell the developer which branch and which checkout the checkpoint belongs to, and
   stop.
   If `check` exits 2, it couldn't read the checkpoint's `Branch:` as a local branch (a
   checkpoint from before `Branch:` held the name alone). If that line plainly names a branch
   whose PR is merged or closed, offer to archive it as above, after `other`. Otherwise resume
   nothing: show the developer the `Branch:` line and the branch checked out here
   (`git branch --show-current`), ask which branch the checkpoint belongs to, and once they
   confirm, rewrite `Branch:` as that name alone and run `check` again.
4. If the brief's status is `awaiting approval`, or the brief was approved by an approver, and its
   draft PR hasn't been made ready (`ada-brief ready`), run `ada-brief status` and act on it as
   `/ada:begin` says. Don't build until it says `approved`. A second line, `amended since approval;
   no re-approval needed`, means the amendments since the label kept the approval: carry on. Once
   the PR has been made ready, `ada-brief status` no longer applies (`ready` replaces the PR body,
   which then has no brief marker, so `status` says `not-submitted`): the PR review is the gate,
   and fix rounds a reviewer asks for go ahead without it.
5. If the main checkout's `.claude/handoff/plan/PLAN.md` exists (`ada-worktree main` prints where
   the main checkout is) and isn't `done`, load the `ada:planning` skill and run
   `ada-plan status`. Briefs
   awaiting approval are checked there. If a feature's brief has just been approved, submit its
   parts' briefs. Say which units are ready next.
6. Report in one paragraph: where we stopped, what was decided, and the next action. List any open
   questions from the checkpoint, and any leftovers on the local site it lists (test orders, a
   clock left set (`ada-clock status`), a frozen queue): they're cleared before anything runs on
   the site.
7. Wait for the developer to confirm before doing anything else.

No step facts in auto-memory (Session start): step notes go in the handoff files, and durable
knowledge in `SYSTEM.md`, `API.md`, `STANDARDS.md` or `TEST-AREAS.md`, in the step's PR.
