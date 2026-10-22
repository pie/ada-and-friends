# Tests

This repo has no automated test suite: it is bash, Python and markdown, with no site to drive.
Tess's test plan (`TEST-PLAN.md`) lists the checks for a step, and she runs them and reports them
in `TEST-REPORT.md` (P29). Her reasons for any Definition of Done item with no automated test go
there too.

There are no unit tests (no PHPUnit, no `tests/unit/`).

## Functional scripts

`tests/functional/<area number>-<area>/<slug>.md`, one per behaviour, numbered by the areas in
`.claude/team/TEST-AREAS.md`. Each has a Precondition, Steps, Expected, Observe at and Regression,
and a `Last run` line.

- Script checks carry a self-checking shell block: save it outside the repo, run it with
  `REPO=<this checkout> bash check.sh`, and read the `PASS` / `FAIL` lines and the exit code. They
  make their own scratch repos and compare with the released plugin exported from
  `origin/next-release`.
- Skill and agent checks are dry runs for a person, with the prompts to give and the files to
  open.

## Static checks

Run on every changed file:

```sh
bash -n plugins/ada/bin/<script>
python3 -m py_compile .github/scripts/release-bump.py
```

Also run changed scripts under bash 3.2 (`/bin/bash` on a Mac, or the `bash:3.2` Docker image) as
well as a current bash.

## Manual checks

- **Precondition** is a scratch git repo, made from nothing in the steps (`git init`, a commit,
  the files the check needs). Anything that pushes, labels or opens PRs uses a scratch GitHub
  repo the tester owns, never a client's.
- **Run the checkout's script by path**, e.g. `~/Repos/ada-and-friends/plugins/ada/bin/ada-onboard
  refresh`. The `ada-*` on the PATH is the installed plugin, not the change under test.
- **Expected** comes from the brief, `SYSTEM.md` or `API.md` (exact output lines, files, labels),
  never from running the code. Before handing over, break the code on purpose and check a step
  fails.
- **Observe at:** stdout and the exit code (`echo $?`; 2 is a usage or precondition error),
  `git status` / `git log` / `git diff` in the scratch repo, the files named, and the PR on GitHub.
- Skills and agents are checked by a dry run with `claude --plugin-dir`: the plan lists the
  prompts to give and the handoff files and PR to inspect.
