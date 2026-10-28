# Testing Ada and friends

What Tess verifies, how, and the areas the plugin is divided into for coverage. Numbers are stable
identifiers; refer to areas by number.

## What can be run

There is **no automated test suite and no CI check** in this repo (as of 0.6.0): no bats, no
shell or Python tests, no shellcheck config, and the only workflow is the release
(`.github/workflows/release.yml`). PHP tooling from the onboarding templates does not apply.

- **Static:** `bash -n plugins/ada/bin/<script>` on each changed script;
  `python3 -m py_compile .github/scripts/release-bump.py`. `shellcheck` if it is installed (it
  isn't required, and nobody has agreed its warnings yet).
- **bash 3.2:** run changed scripts under bash 3.2 (`/bin/bash` on a Mac, or a `bash:3.2` Docker
  image) as well as the local bash: `bash plugins/ada/bin/<script> …`.
- **Scratch repos:** each script is checked by running it **by path from this checkout**
  (`<checkout>/plugins/ada/bin/<script>`, not the copy on the PATH) inside a throwaway git repo.
  Anything that pushes, labels or opens PRs (`ada-brief submit`/`status`/`ready`, `ada-plan
  status`) needs a scratch GitHub repo the tester owns; never a client repo.
- **Skills and agents:** a dry run with `claude --plugin-dir <checkout>/plugins/ada` in a scratch
  or practice project, with `ada@pie` off in its `.claude/settings.local.json`
  (`DEVELOPING.md:15-22`). Observe the handoff files, commits and PR it produces.

## Dates and the clock

Only two things read the clock: `ada-brief status` compares label and comment times with the
brief's last commit time, and `release-bump.py` dates the CHANGELOG heading. Test approval order
by sequence (label before / after a resubmit), not by setting the clock.

## The areas

| # | Area | Scope | How to observe |
|---|---|---|---|
| 01 | Brief approval (`ada-brief`) | `who` for each policy and login; `submit` new and update: approval kept for an unchanged brief or a `not needed` amendment (one PR comment), reset for `needed:` or an unrecorded change, plan parts too; label by approver / non-approver / before a resubmit; `status` words, and the `amended since approval` second line; `compare` (`same`, `allowed`, each `reapprove` reason, four-column rows, CRLF, exit 2); `body` (with `Overlaps:`); `ready`; Asana block kept; `overlap` waiting and open groups, marks, no `gh`, over 100 PRs or files; `overlap --gate` (changed and untracked files, merge-base failure) | Scratch GitHub repo: the PR, its labels and body; first line of `status` |
| 02 | Onboarding and refresh (`ada-onboard`) | `files` (never overwrites, placeholders, hashes), `refresh` all five outcomes and both flags, `gitignore`, `settings` merge into an existing file, `check` | Scratch repo: `git status`, `git diff`, `.claude/team/.template-hashes`, `.claude/settings.json` |
| 03 | Plans (`ada-plan`) | Units table validation, `briefs`, `branches` (parents first, from origin), `status` next actions | Scratch repo (GitHub for `status`): `git branch`, the drafted briefs, `status` output |
| 04 | Review size (`ada-size`) | Kinds, moved code, untracked files, real index untouched, estimate level | Scratch repo with a known diff; `git status` unchanged afterwards |
| 05 | Handoff files (`ada-handoff-init`) | Copy missing, `--archive`, `--archive-plan`, refusals | `ls -R .claude/handoff` |
| 06 | Production access (`prod-wp`) | Refusal of anything not read-only; quoting through the gateway | `prod-wp config`; a refused command; only against a practice install |
| 07 | Release workflow | Label to version, CHANGELOG move, `plugin.json`, tag, release notes, `next-release` restored; empty Unreleased refused | `python3 .github/scripts/release-bump.py 9.9.9 /tmp/notes.md` in a scratch copy; the workflow only on a fork |
| 08 | Session flow (skills `begin`, `pickup`, `planning`) | Session start steps, approval stop, role spawning, phase breaks, PR gate, checkpoint | Dry run with `--plugin-dir`; the handoff files and the PR |
| 09 | Roles (agents `bob`, `claudia`, `tess`) | Each role does only its job, writes its handoff file, stops on refusals | Dry run; the handoff files each writes |
| 10 | Investigation (`bughunt`) | Read-only, writes `FINDINGS.md` | Dry run on a practice project |
| 11 | Templates (`templates/`) | Repo, handoff and plan templates still parse with the scripts | `ada-plan check`, `ada-brief body` on drafts made from them; `ada-onboard check` |
| 12 | Platforms | bash 3.2, Python 3.8, Windows Git Bash (CRLF, `py -3`) | The relevant area's checks run on that platform |
| 13 | Design (`design`, Iris; the design step in `begin` and `planning`) | Offered after the requirements when the test passes, not offered for back-end-only work; the canvas route (`quickstart` intent `design`, one artifact iterated); the text-only fallback (no Design type); look first's sources in order (the `PROJECT.md` design guide, supplied designs, the local site), including a new build with no site, a supplied design that fails P32 to P39, and a PDF designed to its page size; standalone `/ada:design`, then `/ada:begin` confirming its requirements; returning to design partway through a step (Bob's `UI not specified`, Claudia's Must Fix, Tess's flag); `DESIGN.md` written and the brief finished from it | Dry run with `--plugin-dir` on a practice or PIE project, with a person at the canvas: the artifact link, `DESIGN.md`, the brief, `REVIEW-REQUEST.md` |
| 14 | PressTest runs (`presstest`) | Suite and staging site from `PROJECT.md` (code span, word, `none`, the template prompt); `local.env` parsed not executed, env overrides, secrets never printed; local runs only on `.local`/`.test` sites; staging only from `PROJECT.md`, with its own settings, never local ones; production refused; the container recreated only when the domain isn't mapped; the result line and exit status | `tests/functional/14-presstest/`: stand-in `docker` and presstest.io clone; then once on a real Local site with Docker |
