# Tests

Every PR supplies tests for what it changes (P29). This repo has no automated test suite yet, so
the tests are **committed functional scripts**, run by hand. Tess writes them from the brief; Bob
builds until they pass and never edits them.

```
tests/
  functional/
    TEMPLATE.md
    01-<area>/       one folder per area, numbered as in .claude/team/TEST-AREAS.md
```

There are no unit tests (no PHPUnit, no `tests/unit/`).

## Static checks

Run on every changed file:

```sh
bash -n plugins/ada/bin/<script>
python3 -m py_compile .github/scripts/release-bump.py
```

Also run changed scripts under bash 3.2 (`/bin/bash` on a Mac) as well as a current bash.

## Functional scripts

- One file per behaviour, in the folder for its area, from `functional/TEMPLATE.md`. Add to an
  existing script for the same behaviour rather than writing a near-copy.
- **Precondition** is a scratch git repo, made from nothing in the steps (`git init`, a commit,
  the files the script needs). Anything that pushes, labels or opens PRs uses a scratch GitHub
  repo the tester owns, never a client's.
- **Run the checkout's script by path**, e.g. `~/Repos/ada-and-friends/plugins/ada/bin/ada-onboard
  refresh`. The `ada-*` on the PATH is the installed plugin, not the change under test.
- **Expected** comes from the brief, `SYSTEM.md` or `API.md` (exact output lines, files, labels),
  never from running the code. Before handing over, break the code on purpose and check a step
  fails.
- **Observe at:** stdout and the exit code (`echo $?`; 2 is a usage or precondition error),
  `git status` / `git log` / `git diff` in the scratch repo, the files named, and the PR on GitHub.
- Skills and agents are checked by a dry run with `claude --plugin-dir`; the script lists the
  prompts to give and the handoff files and PR to inspect.
- `Automate as: shell` marks a script that should become an automated test once there is a
  harness.
