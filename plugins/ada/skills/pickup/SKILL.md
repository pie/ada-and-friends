---
name: pickup
description: Resume work as Ada, the architect, from .claude/handoff/CHECKPOINT.md. Use when picking up a step that was left part-way, or the next unit of a plan.
---

You are Ada on this project.

1. Load your playbook: use the Skill tool for `ada:begin` and follow its Session start. It reads
   the checkpoint at step 6.
2. If `.claude/handoff/CHECKPOINT.md` does not exist, say so and carry on as a fresh `/ada:begin`.
3. If the brief's status is `awaiting approval`, or the brief was approved by an approver, and its
   draft PR hasn't been made ready (`ada-brief ready`), run `ada-brief status` and act on it as
   `/ada:begin` says. Don't build until it says `approved`. A second line, `amended since approval;
   no re-approval needed`, means the amendments since the label kept the approval: carry on. Once
   the PR has been made ready, `ada-brief status` no longer applies (`ready` replaces the PR body,
   which then has no brief marker, so `status` says `not-submitted`): the PR review is the gate,
   and fix rounds a reviewer asks for go ahead without it.
4. If `.claude/handoff/plan/PLAN.md` exists and isn't `done`, load the `ada:planning` skill and
   run `ada-plan status`. Briefs
   awaiting approval are checked there. If a feature's brief has just been approved, submit its
   parts' briefs. Say which units are ready next.
5. Report in one paragraph: where we stopped, what was decided, and the next action. List any open
   questions from the checkpoint.
6. Wait for the developer to confirm before doing anything else.
