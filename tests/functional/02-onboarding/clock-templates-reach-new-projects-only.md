# The repo templates set the clock with `ada-clock` and keep the four clock rules; new projects get them, existing projects keep their own copies

- Area: 11 Templates (`templates/repo/.claude/team/TEST-AREAS.md` and `PROJECT.md`); 02 Onboarding
  and refresh (`files`, `refresh`, `check`)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219984586830313
- Automate as: none (the Steps block is a self-checking shell script)
- Last run: 29 Oct 2026, 0 failed (26 PASS); the ff93d6a break prints 12 FAIL (see the PR)

## Precondition

- This checkout (`REPO`), with the change in its working tree.
- `git`, `tar`, `cmp`; Python 3.8+ (for `ada-onboard files`).
- The script exports the plugin as it was before this step (`BASE`, default `ff93d6a`) to onboard
  an "existing project" with the old templates. Scratch repos only; no GitHub.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose: `REPO=<this checkout> PLUGIN=<an export of ff93d6a>/plugins/ada bash check.sh`
   (export as in the block). The template checks FAIL; the `baseline:` and existing-project
   checks still PASS.

```bash
#!/bin/bash
# The repo templates' clock text, and what onboarding does with it. PASS/FAIL; exit 1 if any FAIL.
# Env: REPO, PLUGIN (default $REPO/plugins/ada), BASE (default ff93d6a), OLD_PLUGIN.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE=${BASE:-ff93d6a}
W=$(mktemp -d "${TMPDIR:-/tmp}/clock-templates.XXXXXX") || exit 2
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
NEWB=$PLUGIN/bin/ada-onboard; OLDB=$OLD_PLUGIN/bin/ada-onboard
TA=$PLUGIN/templates/repo/.claude/team/TEST-AREAS.md
PJ=$PLUGIN/templates/repo/.claude/team/PROJECT.md
fails=0
pass() { echo "PASS $1"; }
fail() { echo "FAIL $1"; fails=$((fails+1)); }
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then pass "$tn"; else fail "$tn"; fi; }

sec() { awk '/^## Dates and the clock/{p=1;next} p&&/^## /{exit} p' "$1"; }   # the section's body
flat() { tr '\n' ' '; }
bare() { sec "$1" | flat | sed 's/<[^>]*>//g'; }       # the section with every <prompt> removed
in_sec()  { sec "$1" | grep -Fq -- "$2"; }
in_bare() { bare "$1" | grep -Eiq -- "$2"; }
in_bare_cs() { bare "$1" | grep -Eq -- "$2"; }
clockline() { awk '/Clock-sensitive behaviour/{p=1;print;next} p&&(/^- \*\*/||/^$/||/^#/){exit} p' "$1"; }
sudo_other() { sec "$1" | grep -i 'sudo' | grep -v 'apt install faketime'; }

# The TEST-AREAS template (DoD 4)
t "TEST-AREAS template: one 'Dates and the clock' heading" test "$(grep -c '^## Dates and the clock' "$TA")" = 1
t "TEST-AREAS template: no 'sudo date' anywhere" sh -c "! grep -q 'sudo date' '$TA'"
t "TEST-AREAS template: no sudo in the clock section (bar installing faketime)" test -z "$(sudo_other "$TA")"
t "TEST-AREAS template: names ada-clock set" in_sec "$TA" 'ada-clock set'
t "TEST-AREAS template: names ada-clock status" in_sec "$TA" 'ada-clock status'
t "TEST-AREAS template: names ada-clock reset" in_sec "$TA" 'ada-clock reset'
t "rule outside the prompt: freeze the runners first" in_bare "$TA" 'freez|frozen'
t "rule outside the prompt: actions one at a time ..." in_bare "$TA" 'one at a time'
t "rule outside the prompt: ... by ID" in_bare_cs "$TA" '[ (`]IDs?[ ,.;:)`]'
t "rule outside the prompt: never wp action-scheduler run" in_bare "$TA" "(never|not|n't)[^.]*wp action-scheduler run"
t "rule outside the prompt: payment tests on real time" in_bare "$TA" 'payment[^.]*real (time|clock)|real (time|clock)[^.]*payment'

# The PROJECT template's Clock line
t "PROJECT template: the Clock-sensitive line names ada-clock" sh -c "$(declare -f clockline); clockline '$PJ' | grep -q 'ada-clock'"

# The checks can fail: the templates before this step
OTA=$OLD_PLUGIN/templates/repo/.claude/team/TEST-AREAS.md
t "baseline: $BASE's TEST-AREAS template has no ada-clock in its clock section" sh -c "$(declare -f sec); ! sec '$OTA' | grep -q 'ada-clock'"
t "baseline: $BASE's clock section tells the developer to run sudo" test -n "$(sudo_other "$OTA")"

# A new project gets the new templates
newrepo() { R=$W/$1; mkdir -p "$R"; git -C "$R" init -q; git -C "$R" -c user.name=t -c user.email=t@example.com commit -q --allow-empty -m init; }
newrepo fresh;   (cd "$W/fresh" && bash "$NEWB" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/fresh.out" 2>&1
newrepo freshold; (cd "$W/freshold" && bash "$OLDB" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/freshold.out" 2>&1
t "new project: TEST-AREAS.md created" grep -Eq '^created +\.claude/team/TEST-AREAS\.md' "$W/fresh.out"
t "new project: its TEST-AREAS.md names ada-clock status" in_sec "$W/fresh/.claude/team/TEST-AREAS.md" 'ada-clock status'
t "new project: its TEST-AREAS.md has no sudo date" sh -c "! grep -q 'sudo date' '$W/fresh/.claude/team/TEST-AREAS.md'"
t "new project: its PROJECT.md Clock line names ada-clock" sh -c "$(declare -f clockline); clockline '$W/fresh/.claude/team/PROJECT.md' | grep -q 'ada-clock'"
t "new project: 'Placeholders left:' unchanged from $BASE" test "$(grep '^Placeholders left' "$W/fresh.out")" = "$(grep '^Placeholders left' "$W/freshold.out")"
ec() { (cd "$1" && bash "$2" check 2>&1) | grep -c '^ *error'; }
t "new project: check's error lines no more than $BASE's" test "$(ec "$W/fresh" "$NEWB")" -le "$(ec "$W/freshold" "$OLDB")"

# An existing project, onboarded before this step, keeps its own copies
newrepo edited; E=$W/edited
(cd "$E" && bash "$OLDB" files PROJECT_NAME=Scratch CODE_DIR=src) >/dev/null 2>&1
echo 'Edited by the project.' >> "$E/.claude/team/TEST-AREAS.md"
echo 'Edited by the project.' >> "$E/.claude/team/PROJECT.md"
git -C "$E" add -A && git -C "$E" -c user.name=t -c user.email=t@example.com commit -q -m onboarded
(cd "$E" && bash "$NEWB" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/edited-files.out" 2>&1
t "existing project: files says kept for TEST-AREAS.md" grep -Eq '^kept +\.claude/team/TEST-AREAS\.md' "$W/edited-files.out"
t "existing project: files says kept for PROJECT.md" grep -Eq '^kept +\.claude/team/PROJECT\.md' "$W/edited-files.out"
t "existing project: both unchanged after files" git -C "$E" diff --quiet -- .claude/team/TEST-AREAS.md .claude/team/PROJECT.md
(cd "$E" && bash "$NEWB" refresh) > "$W/edited-refresh.out" 2>&1
t "existing project: both unchanged after refresh" git -C "$E" diff --quiet -- .claude/team/TEST-AREAS.md .claude/team/PROJECT.md

newrepo plain; U=$W/plain
(cd "$U" && bash "$OLDB" files PROJECT_NAME=Scratch CODE_DIR=src) >/dev/null 2>&1
git -C "$U" add -A && git -C "$U" -c user.name=t -c user.email=t@example.com commit -q -m onboarded
paths() { grep -E '^(current|refreshed|declined|edited|unknown) ' | awk '{print $2}' | sort; }
po=$(cd "$U" && bash "$OLDB" refresh 2>/dev/null | paths)
pn=$(cd "$U" && bash "$NEWB" refresh 2>/dev/null | paths)
t "refresh's list unchanged from $BASE ($(echo $po))" test "$po" = "$pn"
t "untouched existing project: TEST-AREAS.md and PROJECT.md unchanged after refresh" git -C "$U" diff --quiet -- .claude/team/TEST-AREAS.md .claude/team/PROJECT.md

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

From the brief (Decisions, Templates; DoD 4), `API.md` (`ada-onboard`; Never: onboarding never
overwrites a project's file) and Bob's approved Builder plan, item 2 (refresh's list not extended):

- The template `TEST-AREAS.md`'s "Dates and the clock" section says how the clock is set with
  `ada-clock set`, `status` and `reset`; it has no `sudo date` and no `sudo` except, at most, the
  one-off `sudo apt install faketime`.
- The four rules are in that section **outside** the `<…>` prompt, so they survive a project's
  drafting: freeze the runners first; actions one at a time by ID; never
  `wp action-scheduler run`; payment tests on real time.
- The template `PROJECT.md`'s Clock-sensitive line points at `ada-clock`.
- A fresh `ada-onboard files` copies both; its `Placeholders left:` line is the same as before the
  step (no new placeholder); `check` reports no more `error` lines than before.
- A project onboarded before the step keeps its own `TEST-AREAS.md` and `PROJECT.md` byte for
  byte through `files` (`kept`) and `refresh`; `refresh` lists the same templates as before.
- Every line `PASS`, `0 failed`, exit 0. Against ff93d6a's plugin (step 3), the template and
  new-project checks FAIL.

## Observe at

stdout and `echo $?`; on a FAIL, the kept scratch folder: `fresh/`, `edited/`, `plain/` and the
`*.out` files beside them.

## Regression

- `refresh` still refreshes only placeholder-free templates (`SYSTEM.md`, ada-onboard): the PR
  template. `02-onboarding/refresh-adds-design-line.md` checks its five outcomes.
- The "Dates and the clock" heading is kept: projects' copies and Tess's plan mode refer to it.
