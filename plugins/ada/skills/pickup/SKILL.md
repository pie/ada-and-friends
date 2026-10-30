---
name: pickup
description: Resume work as Ada, the architect, from .claude/handoff/CHECKPOINT.md. Use when picking up a step that was left part-way, or the next unit of a plan.
---

You are Ada on this project.

1. Load your playbook: use the Skill tool for `ada:begin` and follow its Session start. It reads
   the checkpoint at step 6.
2. If `.claude/handoff/CHECKPOINT.md` does not exist, say so and carry on as a fresh `/ada:begin`.
3. Before you read the checkpoint, run `ada-worktree check`. On `ok` or `none`, go on. On
   `other`, on exit 2, or when the checkpoint's step is finished, read `checkpoint.md` in the
   `ada:begin` skill's base directory and follow it, then stop this skill there.
4. If the brief's status is `awaiting approval`, or the brief was approved by an approver, and its
   draft PR hasn't been made ready (`ada-brief ready`), run `ada-brief status` and act on it as
   `approval.md` in the `ada:begin` skill's base directory says. Don't build until it says `approved`. A second line, `amended since approval;
   no re-approval needed`, means the amendments since the label kept the approval: carry on. Once
   the PR has been made ready, `ada-brief status` no longer applies (`ready` replaces the PR body,
   which then has no brief marker, so `status` says `not-submitted`): the PR review is the gate,
   and fix rounds a reviewer asks for go ahead without it.
5. If the main checkout's `.claude/handoff/plan/PLAN.md` exists (`ada-worktree main` prints where
   the main checkout is) and isn't `done`, load the `ada:planning` skill and run
   `ada-plan status`. Briefs
   awaiting approval are checked there. If a feature's brief has just been approved, submit its
   parts' briefs. Say which units are ready next.
6. If the checkpoint says Claudia and Tess both said `Ready for PR: YES`, this is the PR gate
   session: read `pr-gate.md` in the `ada:begin` skill's base directory before you report.
7. Report in one paragraph: where we stopped, what was decided, and the next action. List any open
   questions from the checkpoint, and any leftovers on the local site it lists (test orders, a
   clock left set (`ada-clock status`), a frozen queue): they're cleared before anything runs on
   the site.
8. Wait for the developer to confirm before doing anything else.

No step facts in auto-memory (Session start): step notes go in the handoff files, and durable
knowledge in `SYSTEM.md`, `API.md`, `STANDARDS.md` or `TEST-AREAS.md`, in the step's PR.
