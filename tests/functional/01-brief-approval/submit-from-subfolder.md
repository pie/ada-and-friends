# `ada-brief submit` works when started by a relative path from a subfolder

PIE review fix round, Step 1 (PR #40, Anna's review, 28 Oct 2026): `submit` goes to the repo root
before it works out where `ada-worktree` lives (a plan, and the unit briefs drafted from it, live
in the main checkout), so a relative `$0` such as `ada/bin/ada-brief` run from `plugins/` resolves
against the wrong folder and `submit` fails. The script's own folder must be worked out before any
`cd`, as the other `bin/` scripts do. A brief that is part of a larger unit makes `submit` call
itself (`"$0" status <base>`), which has the same fault.

- Area: 01 Brief approval (`ada-brief submit`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219769422658693
- Automate as: none (the Steps block is a self-checking shell script with a stand-in `gh`)
- Last run: 28 Oct 2026, 0 failed (20 PASS) under bash 5, bash --posix and Docker bash 3.2; the pre-fix break prints 16 FAIL, the half-fixed break 3 FAIL (see the PR)

## Precondition

- This checkout (`REPO`), with the change in its working tree. The script runs this checkout's
  `ada-brief` by path, never the one on the PATH.
- `git`, `python3` (3.8+), `jq`, `mktemp`. No network and no GitHub login: each check makes a
  scratch repo (a path with a space) with a local bare `origin`, and puts a stand-in `gh` first on
  the PATH. The stand-in: `auth` and `label create` succeed; `repo view` prints `o/r`;
  `pr view main ...` (the base branch) answers PR number 7 only when the check sets `BASE_PR=1`,
  every other `pr view` fails (no PR yet); `pr create` saves the description it is given to
  `pr-body` and prints a URL; `api` and anything else fails with `stand-in gh: not handled`.
- The check copies the plugin into the scratch repo as `plugins/ada`, so that a relative path to
  it from a subfolder exists. The scratch repo's `PROJECT.md` says `Approvers: none`.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, and under bash 3.2 (below).
3. Break it on purpose: put the **pre-fix** script in a scratch copy of the plugin and run with it:
   ```
   cp -r <this checkout>/plugins/ada <scratch>/old
   git -C <this checkout> show HEAD:plugins/ada/bin/ada-brief > <scratch>/old/bin/ada-brief
   REPO=<this checkout> PLUGIN=<scratch>/old bash check.sh
   ```
   (Use the commit before the fix: `HEAD` until the fix is committed, then the commit before it.)
   It must print `FAIL` for every relative-path check (a to f) and `PASS` for the absolute-path
   control and the repo-root regression.
4. Break it a second way, to show check d reaches the `"$0" status` call on its own: in a scratch
   copy of the **fixed** `bin/ada-brief`, change `"$here/ada-brief" status` back to `"$0" status`.
   Only the three d lines must print `FAIL`.

```bash
#!/bin/bash
# ada-brief submit by a relative path from a subfolder. PASS/FAIL per check; exit 1 if any FAIL.
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
G()   { git -c user.name=t -c user.email=t@example.invalid "$@"; }
for tool in git python3 jq; do command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }; done

# The stand-in gh.
mkdir -p "$W/gh"
cat > "$W/gh/gh" <<EOF
#!/bin/sh
echo "gh \$*" >> "\${STUB_LOG:-/dev/null}"
case " \$* " in
  *" auth "*) exit 0;;
  *" label create "*) exit 0;;
  *" repo view "*) echo o/r; exit 0;;
  *" pr view main "*) [ "\${BASE_PR:-}" = 1 ] && { echo 7; exit 0; }; exit 1;;
  *" pr view "*) exit 1;;
  *" pr create "*) while [ \$# -gt 0 ]; do [ "\$1" = --body-file ] && cp "\$2" "\${STUB_BODY:-/dev/null}"; shift; done; echo https://example.invalid/pull/1; exit 0;;
esac
echo "stand-in gh: not handled: \$*" >&2; exit 1
EOF
chmod +x "$W/gh/gh"

BRIEF='# Brief — Step 1: Widget export

Asana: https://app.asana.com/0/1/1219000000000001
Branch: add/widget-export
PR into: main
Ships: independent
Status: agreed
Approval: not needed (test)

## Outcome

Widgets go out. SUBMIT-MARKER

## Decisions

- A decision.

## Constraints

- Expected review: small.

## Build order

1. Build it.

## Definition of done

- [ ] It happens.'

# setup <n>: a fresh scratch repo $M on branch add/widget-export, with a bare origin, the plugin
# copied in as plugins/ada, a docs/ folder, its own BRIEF.md, and a plan-folder brief for task 2.
setup() {
  n=$1; M="$W/$n/scratch repo"; O="$W/$n/origin.git"; mkdir -p "$W/$n"
  git init -q --bare "$O"
  git init -q "$M" && git -C "$M" symbolic-ref HEAD refs/heads/main
  git -C "$M" config user.name t; git -C "$M" config user.email t@example.invalid
  mkdir -p "$M/.claude/team" "$M/plugins" "$M/docs"
  cat > "$M/.claude/team/PROJECT.md" <<'EOF'
# Scratch

## Code and branches

- **Branch from:** `main`. **PR into:** `main`. **Test on:** a scratch repo.

## Brief approval

- **Approvers:** none
- **Trusted:** none
EOF
  printf '.claude/handoff/\n' > "$M/.gitignore"
  : > "$M/docs/.keep"
  cp -R "$PLUGIN" "$M/plugins/ada"
  G -C "$M" add -A && G -C "$M" commit -q -m init
  G -C "$M" remote add origin "$O" && G -C "$M" push -q origin main
  G -C "$M" checkout -q -b add/widget-export
  mkdir -p "$M/.claude/handoff/plan"
  printf '%s\n' "$BRIEF" > "$M/.claude/handoff/BRIEF.md"
  printf '%s\n' "$BRIEF" | sed 's/Step 1: Widget export/Step 2: Gadget import/; s/SUBMIT-MARKER/PLAN-DIR-MARKER/' > "$M/.claude/handoff/plan/1219000000000002.md"
  G -C "$M" branch add/gadget-import main
  export STUB_LOG="$W/$n/gh.log" STUB_BODY="$W/$n/pr-body"
}
# sub <dir-in-repo> <command...>: run the command from that folder, stand-in gh first on the PATH.
sub() { d=$1; shift; (cd "$M/$d" && PATH="$W/gh:$PATH" "$@") > "$W/$n/out" 2> "$W/$n/err"; echo $? > "$W/$n/rc"; }
rc() { cat "$W/$n/rc"; }
shows() { echo "info: $1: exit $(rc): $(cat "$W/$n/out" "$W/$n/err" | head -n 3 | tr '\n' ' ')"; }

# Control: an absolute path from the subfolder. This is what the older checks use; it must pass.
setup 0
sub plugins $SH "$M/plugins/ada/bin/ada-brief" submit 1219000000000001 main; shows "control, absolute path from plugins/"
t "control, absolute path from a subfolder: exits 0 (if not, see the info line: a stand-in gap is a SETUP problem)" test "$(rc)" = 0
t "control: opens the draft PR" grep -q '^opened' "$W/$n/out"

# a. The review's case: cd plugins && ada/bin/ada-brief submit ...
setup 1
sub plugins $SH ada/bin/ada-brief submit 1219000000000001 main; shows "a, relative path ada/bin/ada-brief from plugins/"
t "a. relative path from plugins/: exits 0" test "$(rc)" = 0
t "a. opens the draft PR" grep -q '^opened' "$W/$n/out"
t "a. no \"No such file\" error" not grep -qi 'no such file' "$W/$n/err"
t "a. the brief is committed on the pushed branch" eval 'git -C "$O" show "add/widget-export:.claude/briefs/1219000000000001.md" | grep -q SUBMIT-MARKER'
t "a. the PR description is made" test -s "$W/$n/pr-body"

# b. From the folder the script is in, with ./ (executed directly, as ada-brief is on the PATH).
setup 2
sub plugins/ada/bin env PATH="$W/gh:$PATH" ./ada-brief submit 1219000000000001 main; shows "b, ./ada-brief from plugins/ada/bin"
t "b. ./ada-brief from its own folder: exits 0" test "$(rc)" = 0
t "b. opens the draft PR" grep -q '^opened' "$W/$n/out"

# c. From a sibling folder, going up: cd docs && ../plugins/ada/bin/ada-brief submit ...
setup 3
sub docs $SH ../plugins/ada/bin/ada-brief submit 1219000000000001 main; shows "c, ../plugins/ada/bin/ada-brief from docs/"
t "c. relative path going up from docs/: exits 0" test "$(rc)" = 0
t "c. opens the draft PR" grep -q '^opened' "$W/$n/out"

# d. A brief that is part of a larger unit: submit calls "$0" status <base>; also by a relative path.
setup 4
BASE_PR=1 sub plugins $SH ada/bin/ada-brief submit 1219000000000001 main; shows "d, base branch has a PR, relative path from plugins/"
t "d. part of a larger unit, relative path: exits 0" test "$(rc)" = 0
t "d. opens the draft PR (the base's status was read)" grep -q '^opened' "$W/$n/out"
t "d. no \"No such file\" error" not grep -qi 'no such file' "$W/$n/err"

# e. A unit brief drafted in the plan folder, for a branch that isn't checked out: the plan folder
#    is found in the main checkout through ada-worktree, by a relative path from a subfolder.
setup 5
sub plugins $SH ada/bin/ada-brief submit 1219000000000002 main add/gadget-import; shows "e, plan-folder brief, relative path from plugins/"
t "e. plan-folder brief, relative path: exits 0" test "$(rc)" = 0
t "e. the plan folder's brief is committed on add/gadget-import" eval 'git -C "$O" show "add/gadget-import:.claude/briefs/1219000000000002.md" | grep -q PLAN-DIR-MARKER'
t "e. the checked-out branch is not touched (and submit ran)" eval 'test "$(rc)" = 0 && test "$(git -C "$M" rev-parse add/widget-export)" = "$(git -C "$M" rev-parse main)"'

# f. The same, from a subfolder of a subfolder, run under the script's own bash (no $SH wrapper).
setup 6
mkdir -p "$M/docs/deep/er"
sub docs/deep/er $SH ../../../plugins/ada/bin/ada-brief submit 1219000000000001 main; shows "f, three levels down"
t "f. relative path from docs/deep/er: exits 0" test "$(rc)" = 0

# Regression: the repo root, relative path, still works.
setup 7
sub . $SH plugins/ada/bin/ada-brief submit 1219000000000001 main; shows "regression, relative path from the repo root"
t "regression: relative path from the repo root: exits 0" test "$(rc)" = 0
t "regression: opens the draft PR" grep -q '^opened' "$W/$n/out"

echo "$fails failed"
[ "$fails" = 0 ] && rm -rf "$W" || echo "scratch kept: $W"
[ "$fails" = 0 ]
```

### bash 3.2

As in `tests/functional/02-onboarding/gitignore-worktrees-line.md` (the same `docker run` of a
bash 3.2 image, with this `check.sh`).

## Expected

Every line `PASS`, last line `0 failed`, exit 0, in both runs. The pre-fix break (step 3) prints
`FAIL` for every check in sections a to f, and `PASS` for the control and the repo-root regression.
Put the `info:` lines in the Run record.

Sources: the PIE review fix round in `REVIEW-FEEDBACK.md` (Must fix 1 and 2: `submit` fails when
started by a relative path from a subfolder; the script's own folder is worked out before any `cd`);
`API.md`, `ada-brief` (`submit` "commits the brief (and plan) to `.claude/briefs/`, pushes, opens or
updates the draft PR"; run from inside the project repo, which a subfolder is); S100/S101 (bash
scripts run anywhere the plugin does).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: `gh.log`,
`out`, `err` and `pr-body` are in each numbered subfolder.

## Regression

The absolute-path control and the repo-root run show the set-up is sound, so a `FAIL` in a to f is
the relative path. The submit behaviour from a worktree is in
`tests/functional/03-plans/plan-lives-in-main-checkout.md`, section 3c.
