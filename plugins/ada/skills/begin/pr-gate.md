# Ada: the PR gate

Read from `/ada:begin` or `/ada:pickup` in the session that opens the PR, once Claudia and Tess
both say `Ready for PR: YES`.

## The gate

1. Run `ada-size <the PR's base>`. It ends with an estimated review time and level (small up to
   15 minutes, medium up to 45, large up to 90, then too big for one sitting). It doesn't know
   what's fragile: if the change is in a fragile area, raise the estimate and say why. Compare it
   with the brief's `Expected review:` line. If the step came out a level bigger than expected,
   or too big for one sitting, say so and ask the developer before going on: split it now, or
   ship it as it is with the reason in the PR.
2. Check for overlap again, since other work may have landed or opened during the build. The
   build isn't committed yet, so the check takes the changed files from the working tree,
   untracked ones included. Run it as one command, with no pipe:

   ```sh
   ada-brief overlap --gate
   ```

   It fetches the branches itself and takes this branch's files since it left
   `origin/<PR into>`, keeping paths with spaces or non-ASCII characters whole. Compare the
   result with the brief's `Overlaps:` line. Take any new overlap to the developer before you
   offer the PR, with the same options as for the brief. Record it and what was decided in
   `BUILD-LOG.md` and in the PR's "For the reviewer". If `ada-brief overlap` exits with anything
   other than 0, show the developer its `ada-brief:` message or the error and fix the cause or
   ask them how to go on; never read it as no overlap.
3. Summarise for the developer in a short paragraph: what was built, what the review found and how
   it was resolved, which tests are supplied, what was verified and what was not, and whether
   `SYSTEM.md` / `API.md` were updated (or why none was needed).
4. Ask: open the PR?
5. On yes:
   - Update `BUILD-LOG.md`.
   - Commit, credited as in **Commits and credit** below. Every commit says what changed and
     what was verified (P20). When the handoff `DESIGN.md` says `Status: agreed` and its
     `Project DESIGN.md:` line says `created` or `updated`, stage the project's `DESIGN.md` at
     the repo root too.
   - Push the branch.
   - Write the PR description to `.claude/handoff/PR.md`, following
     `.github/pull_request_template.md`. Fill every section; "none" is an answer, blank is not.
     Rules are cited by number. Not-verified items and known gaps are listed so the PIE reviewer
     can see them. Copy the Run record and the Manual test steps from TEST-REPORT.md. The Review line is the
     estimate from step 1, not the breakdown; leave the Actual review time line for the
     reviewer; and "For the reviewer" says where the review is: the changed logic, and any
     fragile area, by file and function.
   - Fill its `## Squash commit` section. It holds two fenced `text` blocks for the merger to
     paste into the squash-merge dialog:
     - **Title:** the PR title followed by ` (#<PR number>)`. Keep it short, imperative and
       specific; it becomes the commit on production.
     - **Message:** wrapped at 72 characters, in three parts and nothing else. First, what
       the change does and why, as a few lines or bullets. Then the `Asana:` link. End with
       the credit lines for every role that took part: `Co-authored-by:` for Bob and Tess,
       `Reviewed-by:` for Claudia (the lines in Commits and credit). What was verified and
       not verified stays in the PR description, not in this message.

     Write it as one commit covering the whole PR, not the step's commits pasted together.
     A new PR has no number until it's opened, so add it to the title once it has one. If a
     fix round changes what the PR does, update this section too.
   - Open the PR into the brief's `PR into:` branch (the one `PROJECT.md` names, or a part's
     feature branch), with `gh pr create` if `gh` is available.
     If the brief's draft PR already exists, use it instead (`ada-brief ready` keeps the
     Asana app's block):
     `ada-brief ready "<title>" .claude/handoff/PR.md`. Fill the PR's `Brief:` line from the brief's
     `Approval:` line, and its `Design:` line from `.claude/handoff/DESIGN.md`: the artifact link,
     `text only, in the brief` for a text-only design, or `none` when it isn't `Status: agreed`.
     Without `gh`, give the developer the compare link and the description to paste. PRs are
     squash-merged; the `## Squash commit` section is the commit the merger pastes.
   - If the PR isn't linked to its Asana task yet (the task has no attachment whose URL is the
     PR), ask the developer to paste the PR's URL into the task's Apps, GitHub, "Add GitHub pull
     request".
6. Write `CHECKPOINT.md`.

The PR is the gate. Nothing is merged by this team.

## Commits and credit

You make the team's commits; Bob and Tess don't commit. Stage files by name, the ones this step
changed; never `git add -A`, `git add .` or `git commit -a`, which sweep up whatever else is in
the working tree. The author stays the developer (their own
git identity), so the work is theirs. Each role is credited with a trailer instead of Claude
Code's default line, which the project's settings switch off:

| Commit | Trailers |
|---|---|
| The tests (`tests/`) | `Co-Authored-By: Tess (Ada and friends, Claude Code) <tess@ada.pie.co.de>` |
| The build (everything else) | `Co-Authored-By: Bob (Ada and friends, Claude Code) <bob@ada.pie.co.de>` and `Reviewed-by: Claudia (Ada and friends, Claude Code) <claudia@ada.pie.co.de>` |
| The brief, onboarding, the project's `DESIGN.md` (Iris), anything you write yourself | `Co-Authored-By: Ada (Claude Code) <ada@ada.pie.co.de>` |

Tests first, then the build, so the history shows the tests were written from the brief. Fix
rounds after a review are folded into the build commit, not committed one by one, with one
exception: a P30 formatting-only reformat stays its own commit (credited like the build), before
the build commit, so it can be reviewed and reverted on its own.

Cite the PIE rules as P (P1 onwards) and only the project's own rules as S (`STANDARDS.md`), in
commits, the PR and the handoff files. End the PR
description with: "Built by Bob, reviewed by Claudia, tested by Tess. Ada and friends, Claude
Code." Trim it to the roles that took part.
