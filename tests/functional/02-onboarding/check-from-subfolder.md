# `ada-onboard check` works when started by a relative path from a subfolder

PIE review fix round 2, Step 1 (PR #40, 28 Oct 2026): `check` goes to the repo root, then runs
`ada-brief who` to report on the brief-approval policy. A relative `$0` such as `ada/bin/ada-onboard`
run from `plugins/` resolves against the wrong folder after that `cd`, so `check` prints
`error    brief approval: ... No such file ...` for a repo whose approval policy is fine. The script's
own folder must be worked out before any `cd`, as `ada-brief`, `ada-plan` and the other `bin/`
scripts do.

- Area: 02 Onboarding and refresh (`ada-onboard check`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219769422658693
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 28 Oct 2026, 0 failed (25 PASS) under bash 5, bash --posix and Docker bash 3.2; the pre-fix break prints 8 FAIL as expected (see the PR)

## Precondition

- This checkout (`REPO`), with the change in its working tree. The script runs this checkout's
  `ada-onboard` by path, never the one on the PATH.
- `git`, `python3` (3.8+), `mktemp`. No network and no GitHub login: the scratch repo's `PROJECT.md`
  says `Approvers: none`, so `ada-brief who` needs no `gh`.
- The script makes a scratch repo (a path with a space) and copies the plugin into it as
  `plugins/ada`, so that a relative path to it from a subfolder exists. The repo has a valid
  `PROJECT.md` with a "Brief approval" section, so the only `check` output about approval that is
  right is none at all. Other lines (`missing  ...` for the files the scratch repo lacks) are
  expected and are not asserted on.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, and under bash 3.2 (below).
3. Break it on purpose: put the **pre-fix** script in a scratch copy of the plugin and run with it:
   ```
   cp -r <this checkout>/plugins/ada <scratch>/old
   git -C <this checkout> show HEAD:plugins/ada/bin/ada-onboard > <scratch>/old/bin/ada-onboard
   REPO=<this checkout> PLUGIN=<scratch>/old bash check.sh
   ```
   (Use the commit before the fix: `HEAD` until the fix is committed, then the commit before it.)
   It must print `FAIL` for every relative-path check (a to d) and `PASS` for the absolute-path
   control and the repo-root regression.

```bash
#!/bin/bash
# ada-onboard check by a relative path from a subfolder. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada),
# SH (how to run the script under test; default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
SH=${SH:-bash}
W=$(mktemp -d)
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
for tool in git python3; do command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }; done

# The repo: valid PROJECT.md with a Brief approval section (Approvers: none), the plugin as plugins/ada.
M="$W/scratch repo"
mkdir -p "$M/.claude/team" "$M/plugins" "$M/docs/deep/er"
git init -q "$M"
cat > "$M/.claude/team/PROJECT.md" <<'EOF'
# Scratch

## Code and branches

- **Branch from:** `main`. **PR into:** `main`. **Test on:** a scratch repo.

## Brief approval

- **Approvers:** none
- **Trusted:** none
EOF
cp -R "$PLUGIN" "$M/plugins/ada"

# Setup sanity: ada-brief who itself says not-needed, so any approval error from check is the path.
t "setup: ada-brief who (absolute path) prints not-needed" eval '(cd "$M" && test "$($SH "$M/plugins/ada/bin/ada-brief" who 2>/dev/null)" = not-needed)'

# run <label> <dir-in-repo> <command...>: run check from that folder; keep stdout, stderr and exit code.
run() {
  label=$1; d=$2; shift 2
  (cd "$M/$d" && "$@" check) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"
  echo "info: $label: exit $(cat "$W/rc"): $(grep -ihE 'brief approval|no such file' "$W/out" "$W/err" | head -n 2 | tr '\n' ' ')"
}
clean() {   # the four things that must be true when the approval policy is fine
  t "$1: exits 0" test "$(cat "$W/rc")" = 0
  t "$1: no 'error ... brief approval' line" not grep -q 'brief approval' "$W/out" "$W/err"
  t "$1: no \"No such file\" error anywhere" not grep -qi 'no such file' "$W/out" "$W/err"
  t "$1: check ran (it lists the missing team files)" grep -q '^missing  \.claude/team/SYSTEM\.md' "$W/out"
}

# Control: an absolute path from a subfolder. Must pass.
run "control, absolute path from plugins/" plugins $SH "$M/plugins/ada/bin/ada-onboard"
clean "control, absolute path from a subfolder"

# a. The review's case: cd plugins && ada/bin/ada-onboard check
run "a, relative path ada/bin/ada-onboard from plugins/" plugins $SH ada/bin/ada-onboard
clean "a. relative path from plugins/"

# b. From the folder the script is in, with ./ (executed directly, as it is on the PATH).
run "b, ./ada-onboard from plugins/ada/bin" plugins/ada/bin env ./ada-onboard
clean "b. ./ada-onboard from its own folder"

# c. From a sibling folder, going up.
run "c, ../plugins/ada/bin/ada-onboard from docs/" docs $SH ../plugins/ada/bin/ada-onboard
clean "c. relative path going up from docs/"

# d. Three levels down.
run "d, three levels down" docs/deep/er $SH ../../../plugins/ada/bin/ada-onboard
clean "d. relative path from docs/deep/er"

# Regression: the repo root, relative path, still works.
run "regression, relative path from the repo root" . $SH plugins/ada/bin/ada-onboard
clean "regression: relative path from the repo root"

echo "$fails failed"
[ "$fails" = 0 ] && rm -rf "$W" || echo "scratch kept: $W"
[ "$fails" = 0 ]
```

### bash 3.2

As in `tests/functional/02-onboarding/gitignore-worktrees-line.md` (the same `docker run` of a
bash 3.2 image, with this `check.sh`).

## Expected

Every line `PASS`, last line `0 failed`, exit 0, in both runs. The pre-fix break (step 3) prints
`FAIL` for the two assertions on approval and "No such file" in each of a to d (8 in all; the
`exits 0` and "check ran" lines still pass, as `check` exits 0 and goes on to the end), and
`PASS` for the control and the repo-root regression. Put the `info:` lines in the Run record.

Sources: the PIE review fix round 2 in `REVIEW-FEEDBACK.md` (Must fix 1 and 3: `ada-onboard check`
reports `error brief approval` when started by a relative path from a subfolder); `SYSTEM.md` and
`API.md` (`ada-onboard check` reports a missing or unfilled file, and a failing `ada-brief who` as
`error    brief approval`; a repo with a valid "Brief approval" section, here `Approvers: none`,
has nothing to report); S100/S101 (bash scripts run anywhere the plugin does).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named; `out`, `err`
and `rc` in it are those of the last `check` run. The `info:` lines show each run's exit code and
any approval or "No such file" text.

## Regression

The absolute-path control and the repo-root run show the set-up is sound, so a `FAIL` in a to d is
the relative path. `ada-brief submit` by a relative path is in
`tests/functional/01-brief-approval/submit-from-subfolder.md`.
