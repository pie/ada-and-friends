# `ada-plan`, `ada-brief submit` and `ada-handoff-init --archive-plan` use the main checkout's plan from any worktree

Step 1 of 25 Oct 2026, "One worktree per step": a plan spans several units and each unit is built
in its own worktree, so the plan lives in the main checkout's `.claude/handoff/plan/`, and
`ada-plan`, `ada-brief submit` (Amendment 2026-10-25) and `ada-handoff-init --archive-plan` find
it there wherever they run. A unit's worktree has no `plan/` of its own. Everything else in a worktree's handoff stays its own.

- Area: 03 Plans (`ada-plan`); 01 Brief approval (`ada-brief submit` from a worktree); 05 Handoff
  files (`ada-handoff-init`); 11 Templates (the BRIEF template still parses); 12 Platforms (bash
  3.2, a path with a space)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219769422658693
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 28 Oct 2026 (after the rebase onto PR #39), 0 failed (55 PASS) under bash 5 and Docker bash 3.2; the released-plugin break prints 24 FAIL (see the PR)

## Precondition

- This checkout (`REPO`), with `origin/next-release` fetched: the script compares the changed
  `ada-plan` with the released one there, run from the main checkout.
- `git`, `tar`, `cmp`, `cksum`, Python 3.8+. The script makes its own scratch repo,
  `scratch repo` (a path with a space), with a bare `origin`, and never touches this checkout.
- `ada-plan status` and `ada-brief submit` normally need GitHub. Here they run against a stand-in
  `gh` that the script writes: every `list` answers `[]` (no PRs), `auth` and `label create`
  succeed, `pr view` fails (no PR yet), `pr create` saves the description it is given to
  `pr-body` and prints a URL, anything else fails. `submit` pushes to the scratch bare `origin`.
  It logs each call to `gh.log` in the scratch folder. The same stand-in serves the released and the changed script,
  so their outputs can be compared. A real run against a scratch GitHub repo is in the test plan
  as a local check.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, and under bash 3.2 as in "bash 3.2" below.
3. Break it on purpose, once: run it with `PLUGIN` set to the **released** plugin
   (`git -C <this checkout> archive origin/next-release plugins/ada | tar -x -m -C <scratch>`, then
   `PLUGIN=<scratch>/plugins/ada`). It must print `FAIL` for every "from the unit's worktree"
   check except the `ada-handoff-init` regression ones.

```bash
#!/bin/bash
# The plan lives in the main checkout. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO (this checkout), BASE (default origin/next-release), PLUGIN (plugin under test,
# default $REPO/plugins/ada), OLD_PLUGIN (released plugin; default: exported from BASE),
# SH (how to run the script under test; default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
SH=${SH:-bash}
W=$(mktemp -d)
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -m -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
H=.claude/handoff
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
G()   { git -c user.name=t -c user.email=t@example.invalid "$@"; }
# run <dir> <plugin> <script> args...: that plugin's bin first on the PATH, then the stand-in gh.
run() { d=$1; p=$2; s=$3; shift 3
        (cd "$d" && PATH="$p/bin:$W/gh:$PATH" $SH "$p/bin/$s" "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()  { cat "$W/rc"; }
real() { (cd "$1" 2>/dev/null && pwd -P); }
norm() { sed -e "s|$U/||g" -e "s|$(real "$U")/||g" -e "s|$M/||g" -e "s|$(real "$M")/||g" "$1"; }   # worktree first: it is under M
sums() { (cd "$1" 2>/dev/null && find . -type f -print | LC_ALL=C sort | while IFS= read -r f; do cksum "$f"; done); }

# The stand-in gh.
mkdir -p "$W/gh"
cat > "$W/gh/gh" <<EOF
#!/bin/sh
echo "gh \$*" >> "$W/gh.log"
case " \$* " in
  *" list "*) echo '[]'; exit 0;;
  *" auth "*) exit 0;;
  *" label create "*) exit 0;;
  *" pr view "*) exit 1;;
  *" pr create "*) while [ \$# -gt 0 ]; do [ "\$1" = --body-file ] && cp "\$2" "$W/pr-body"; shift; done; echo https://example.invalid/pull/1; exit 0;;
esac
echo "stand-in gh: not handled: \$*" >&2; exit 1
EOF
chmod +x "$W/gh/gh"

# Setup: a scratch repo with PROJECT.md, main and next-release on a bare origin, a plan of two
# units in the main checkout, and Unit A's worktree made with plain git (not ada-worktree).
O="$W/origin.git"; M="$W/scratch repo"; U="$M/.claude/worktrees/widget-export"
git init -q --bare "$O"
git init -q "$M" && git -C "$M" symbolic-ref HEAD refs/heads/main
mkdir -p "$M/.claude/team"
cat > "$M/.claude/team/PROJECT.md" <<'EOF'
# Scratch

## Code and branches

- **Branch from:** `main`. **PR into:** `next-release`. **Test on:** a scratch repo.

## Brief approval

- **Approvers:** none
- **Trusted:** none
EOF
printf '.claude/handoff/\n.claude/worktrees/\n' > "$M/.gitignore"
G -C "$M" add -A && G -C "$M" commit -q -m init
git -C "$M" config user.name t; git -C "$M" config user.email t@example.invalid   # ada-brief submit commits with plain git
G -C "$M" branch next-release
G -C "$M" remote add origin "$O" && G -C "$M" push -q origin main next-release && G -C "$M" fetch -q origin
(cd "$M" && bash "$PLUGIN/bin/ada-handoff-init") > /dev/null 2>&1
printf 'MAIN-BRIEF-MARKER\n' >> "$M/$H/BRIEF.md"
run "$M" "$PLUGIN" ada-plan init
t "setup: ada-plan init in the main checkout exits 0" test "$(rc)" = 0
cat > "$M/$H/plan/PLAN.md" <<'EOF'
# Plan: Widgets and gadgets

Epic: https://app.asana.com/0/1/1219000000000009
Status: agreed

## Outcome

PLAN-MARKER: widgets go out and gadgets come in.

## Units

| ID | Unit | Task | Branch | Parent | Ships |
|---|---|---|---|---|---|
| A | Widget export | 1219000000000001 | add/widget-export | | independent |
| B | Gadget import | 1219000000000002 | add/gadget-import | | after Unit A |

## Amendments

| Date | What changed | Why | Agreed by |
|---|---|---|---|
| | | | |
EOF
G -C "$M" worktree add -q -b add/widget-export "$U" main
(cd "$U" && bash "$PLUGIN/bin/ada-handoff-init") > /dev/null 2>&1
printf 'UNIT-BRIEF-MARKER\n' >> "$U/$H/BRIEF.md"
t "setup: the unit's worktree has no plan/" test ! -e "$U/$H/plan"

# 1. check: the same plan from the main checkout and from the unit's worktree.
run "$M" "$OLD_PLUGIN" ada-plan check; norm "$W/out" > "$W/check.old"
t "baseline: the released ada-plan check passes in the main checkout" test "$(rc)" = 0
run "$U" "$OLD_PLUGIN" ada-plan check
t "baseline: the released ada-plan check finds no plan in the unit's worktree" test "$(rc)" != 0
run "$M" "$PLUGIN" ada-plan check; norm "$W/out" > "$W/check.main"
t "check from the main checkout: exits 0" test "$(rc)" = 0
t "check from the main checkout: output as released" cmp -s "$W/check.old" "$W/check.main"
run "$U" "$PLUGIN" ada-plan check; norm "$W/out" > "$W/check.unit"
echo "info: check from the unit's worktree:"; sed 's/^/info:   /' "$W/out"
t "check from the unit's worktree: exits 0" test "$(rc)" = 0
t "check from the unit's worktree: same output as from the main checkout (paths aside)" cmp -s "$W/check.main" "$W/check.unit"
t "check from the unit's worktree: names both units' branches" eval 'grep -q add/widget-export "$W/check.unit" && grep -q add/gadget-import "$W/check.unit"'
t "check: the unit's worktree still has no plan/" test ! -e "$U/$H/plan"

# 2. status (Definition of Done 6), with the stand-in gh.
: > "$W/gh.log"
run "$M" "$OLD_PLUGIN" ada-plan status; norm "$W/out" > "$W/status.old"; old_rc=$(rc)
run "$M" "$PLUGIN" ada-plan status; norm "$W/out" > "$W/status.main"
echo "info: status from the main checkout: exit $(rc), released exit $old_rc"; sed 's/^/info:   /' "$W/out" "$W/err"
t "status from the main checkout: exits 0 (if not, see gh.log: a stand-in gh gap is a SETUP problem)" test "$(rc)" = 0
t "status from the main checkout: output as released" cmp -s "$W/status.old" "$W/status.main"
run "$U" "$PLUGIN" ada-plan status; norm "$W/out" > "$W/status.unit"
echo "info: status from the unit's worktree: exit $(rc)"; sed 's/^/info:   /' "$W/out" "$W/err"
t "status from the unit's worktree: exits 0" test "$(rc)" = 0
t "status from the unit's worktree: same output as from the main checkout (paths aside)" cmp -s "$W/status.main" "$W/status.unit"
t "status from the unit's worktree: a line for Unit A" grep -Eq '^A[[:space:]]' "$W/status.unit"
t "status from the unit's worktree: a line for Unit B" grep -Eq '^B[[:space:]]' "$W/status.unit"
t "status: the unit's worktree still has no plan/" test ! -e "$U/$H/plan"
echo "info: gh calls:"; sed 's/^/info:   /' "$W/gh.log"

# 3. briefs and init from the unit's worktree write to, or find, the main checkout's plan/.
sums "$M/$H/plan" > "$W/plan.before"
run "$U" "$PLUGIN" ada-plan init
t "init from the unit's worktree: exit 2 (the main checkout's plan exists)" test "$(rc)" = 2
sums "$M/$H/plan" > "$W/plan.after"
t "init from the unit's worktree: the main checkout's plan unchanged" cmp -s "$W/plan.before" "$W/plan.after"
t "init: the unit's worktree still has no plan/" test ! -e "$U/$H/plan"
run "$U" "$PLUGIN" ada-plan briefs
t "briefs from the unit's worktree: exits 0" test "$(rc)" = 0
t "briefs: Unit A's brief drafted in the main checkout's plan/" test -f "$M/$H/plan/1219000000000001.md"
t "briefs: Unit B's brief drafted in the main checkout's plan/" test -f "$M/$H/plan/1219000000000002.md"
t "briefs: the unit's worktree still has no plan/" test ! -e "$U/$H/plan"

# 3b. Templates still parse (area 11): the BRIEF template, Branch: and Worktree: lines and all,
#     renders with ada-brief body exactly as the released template does. (body prints its marker
#     even with no brief, so the check is the Definition of Done item and the whole output.)
t "briefs: the drafted brief has a Worktree: line (the new BRIEF template)" grep -Eq '^(- )?Worktree:' "$M/$H/plan/1219000000000001.md"
mkbrief() { awk '{print} /^## Definition of done/{print ""; print "- [ ] BODY-MARKER: widgets go out."}' "$1" > "$2"; }
mkdir -p "$M/.claude/briefs" "$U/.claude/briefs"
mkbrief "$OLD_PLUGIN/templates/handoff/BRIEF.md" "$M/.claude/briefs/1219000000000001.md"
G -C "$M" add .claude/briefs && G -C "$M" commit -q -m "brief from the released template"
mkbrief "$PLUGIN/templates/handoff/BRIEF.md" "$U/.claude/briefs/1219000000000001.md"
G -C "$U" add .claude/briefs && G -C "$U" commit -q -m "brief from the new template"
run "$M" "$PLUGIN" ada-brief body 1219000000000001 main; cp "$W/out" "$W/body.old"
run "$M" "$PLUGIN" ada-brief body 1219000000000001 add/widget-export
t "ada-brief body, new template: exits 0" test "$(rc)" = 0
t "ada-brief body, new template: the Definition of Done item is there" grep -q BODY-MARKER "$W/out"
t "ada-brief body: the new template renders as the released one does" cmp -s "$W/body.old" "$W/out"

# 3c. ada-brief submit from the unit's worktree takes the main checkout's PLAN.md (Definition of
#     Done 12; Amendment 2026-10-25). The unit's brief names its plan in its Plan: line; submit
#     commits a copy of PLAN.md beside the brief, on the unit's branch, and shows the plan in the
#     draft PR's description. The unit's worktree has no plan/, so only the main checkout's can be it.
cat > "$U/$H/BRIEF.md" <<'EOF'
# Brief — Step 1: Widget export

Asana: https://app.asana.com/0/1/1219000000000001
Branch: add/widget-export
Worktree: .claude/worktrees/widget-export
PR into: main
Plan: .claude/briefs/1219000000000009-plan.md, Unit A
Ships: independent
Status: agreed
Approval: not needed (test)

## Outcome

Widgets go out. UNIT-BRIEF-MARKER

## Decisions

- A decision.

## Constraints

- Expected review: small.
- Out of scope: everything else.

## Build order

1. Build it.

## Definition of done

- [ ] It happens.
EOF
PLANF=.claude/briefs/1219000000000009-plan.md
sums "$M/$H/plan" > "$W/plan.b4"
run "$U" "$PLUGIN" ada-brief submit 1219000000000001 main
echo "info: submit from the unit's worktree: exit $(rc): $(cat "$W/out" "$W/err" | head -n 3 | tr '\n' ' ')"
t "submit from the unit's worktree: exits 0 (if not, see the message above: a stand-in gh gap is a SETUP problem)" test "$(rc)" = 0
t "submit: opens the draft PR (stand-in gh)" grep -q '^opened' "$W/out"
t "submit: the unit's own brief is committed as .claude/briefs/1219000000000001.md" eval 'git -C "$U" show HEAD:.claude/briefs/1219000000000001.md | grep -q UNIT-BRIEF-MARKER'
t "submit: the main checkout's PLAN.md is committed beside it as $PLANF" eval 'git -C "$U" show "HEAD:$PLANF" > "$W/plan.committed" 2>/dev/null && cmp -s "$W/plan.committed" "$M/$H/plan/PLAN.md"'
t "submit: the committed plan is the plan (PLAN-MARKER)" grep -q PLAN-MARKER "$W/plan.committed"
t "submit: the plan file is on the pushed branch" eval 'git -C "$O" show "add/widget-export:$PLANF" > /dev/null'
t "submit: the PR description shows the plan" grep -q 'The plan: 2 units' "$W/pr-body"
t "submit: the unit's worktree still has no plan/" test ! -e "$U/$H/plan"
sums "$M/$H/plan" > "$W/plan.aft"; t "submit: the main checkout's plan/ unchanged" cmp -s "$W/plan.b4" "$W/plan.aft"
run "$U" "$PLUGIN" ada-brief submit 1219000000000001 main
t "submit again: exits 0" test "$(rc)" = 0
t "submit again: the committed plan is still the main checkout's" eval 'git -C "$U" show "HEAD:$PLANF" | cmp -s - "$M/$H/plan/PLAN.md"'

# 4. Regression: everything but the plan stays the worktree's own.
run "$U" "$PLUGIN" ada-handoff-init
t "ada-handoff-init in the unit's worktree: exits 0" test "$(rc)" = 0
t "ada-handoff-init: creates nothing (the worktree's own files are there)" not grep -q '^created' "$W/out"
t "ada-handoff-init: the unit's brief is its own" grep -q UNIT-BRIEF-MARKER "$U/$H/BRIEF.md"
sums "$M/$H" > "$W/mainh.before"
run "$U" "$PLUGIN" ada-handoff-init --archive step-a
t "--archive step-a in the unit's worktree: exits 0" test "$(rc)" = 0
t "--archive: the unit's own brief archived in its own archive" grep -q UNIT-BRIEF-MARKER "$U/$H/archive/step-a/BRIEF.md"
t "--archive: nothing archived in the main checkout" test ! -e "$M/$H/archive/step-a"
sums "$M/$H" > "$W/mainh.after"
t "--archive: the main checkout's handoff and plan untouched" cmp -s "$W/mainh.before" "$W/mainh.after"
t "--archive: the unit's worktree still has no plan/" test ! -e "$U/$H/plan"

# 5. --archive-plan from the unit's worktree moves the main checkout's plan.
run "$U" "$PLUGIN" ada-handoff-init --archive-plan plan-1
echo "info: --archive-plan from the unit's worktree: exit $(rc): $(cat "$W/out" "$W/err" | head -n 2 | tr '\n' ' ')"
t "--archive-plan plan-1 from the unit's worktree: exits 0" test "$(rc)" = 0
t "--archive-plan: the main checkout has no plan/ now" test ! -e "$M/$H/plan"
t "--archive-plan: the plan is in the main checkout's archive/plan-1/plan/" grep -q PLAN-MARKER "$M/$H/archive/plan-1/plan/PLAN.md"
t "--archive-plan: the unit's drafted briefs went with it" test -f "$M/$H/archive/plan-1/plan/1219000000000001.md"
t "--archive-plan: the unit's worktree has no plan/ and no archive/plan-1" test ! -e "$U/$H/plan" -a ! -e "$U/$H/archive/plan-1"
t "--archive-plan: the main checkout's brief untouched" grep -q MAIN-BRIEF-MARKER "$M/$H/BRIEF.md"
t "--archive-plan: the unit's brief untouched" test -f "$U/$H/BRIEF.md"
run "$U" "$PLUGIN" ada-plan check
t "after --archive-plan, check from the unit's worktree: no plan, exit 2" test "$(rc)" = 2

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2

As in `tests/functional/02-onboarding/gitignore-worktrees-line.md` (export the released plugin to
`$S/old` first, then the same `docker run`), with this `check.sh`.

## Expected

Every line `PASS`, last line `0 failed`, exit 0, in both runs. The break in step 3 prints `FAIL`
for the checks from the unit's worktree, section 3c's among them (the released `ada-brief submit`
finds no `plan/` in the worktree, so it commits the brief without the plan). Put the `info:` lines (the outputs and the `gh` calls) in
the Run record.

Sources: the brief's Decisions ("A plan lives in the main checkout": `ada-plan` and
`ada-handoff-init --archive-plan` resolve `.claude/handoff/plan/` in the main worktree, the first
entry of `git worktree list --porcelain`, wherever they are run from) and Definition of Done 6
(`ada-plan status` from a unit's worktree reports the main checkout's plan; the unit's worktree
has no `plan/`); `API.md`, `ada-plan` (`init`: exit 2 if the plan exists; `briefs` drafts
`plan/<task>.md`) and `ada-handoff-init` (`--archive` moves `.claude/handoff/*.md`, the plan
stays; `--archive-plan` moves `plan/` to `archive/<name>/plan/`), and `ada-brief`
(`submit` "commits the brief (and plan) to `.claude/briefs/`, pushes, opens or updates the draft
PR"; a brief whose `Plan:` line names a plan file takes `PLAN.md` with it) with Definition of Done
12. A reading the brief doesn't
spell out, raised with Ada in `TEST-PLAN.md`: `--archive-plan` run from a worktree archives into
the main checkout's `archive/`, beside the plan it moved.

## Observe at

The script's output and exit code; `gh.log` in the scratch folder for the stand-in's calls. On a
failure the scratch folder is kept and named: look at `ls -R "<scratch>/scratch repo/.claude/handoff"`
and the same in `.claude/worktrees/widget-export`.

## Regression

Sections 1 and 2 compare `check` and `status` from the main checkout with the released script.
Section 4: a worktree's own handoff files, and `--archive`, still work on that worktree only.
`ada-handoff-init` in a repo with no worktrees is covered by
`tests/functional/05-handoff-files/design-md-copied-kept-archived.md`, section 6.
