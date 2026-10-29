# A clock left set is a leftover Ada names by `ada-clock status`, in `/ada:begin`, `/ada:pickup` and the checkpoint template

- Area: 08 Session flow (skills `begin`, `pickup`); 11 Templates (`templates/handoff/CHECKPOINT.md`)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219984586830313
- Automate as: none (part A is a self-checking shell script; part B is a dry run for a person)
- Last run: 29 Oct 2026, part A 0 failed (9 PASS); part B run headless, as expected (stopped at the not-onboarded gate), see the PR

## Precondition

- Part A: this checkout, with the change in its working tree.
- Part B: `claude` logged in; a scratch git repo made below (no site, no GitHub).

## Steps

### Part A: the text

1. Save the block below as `check.sh` in a scratch folder (not in this repo) and run
   `REPO=<this checkout> bash check.sh`.
2. Break it on purpose: run it with `REPO=` an export of ff93d6a
   (`git -C <this checkout> archive ff93d6a | tar -x -C <empty folder>`). The `ada-clock status`
   and "a changed clock" checks FAIL; the regression checks PASS.

```bash
#!/bin/bash
# The leftovers wording in begin, pickup and the CHECKPOINT template. PASS/FAIL; exit 1 if any FAIL.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
B=$REPO/plugins/ada/skills/begin/SKILL.md
P=$REPO/plugins/ada/skills/pickup/SKILL.md
C=$REPO/plugins/ada/templates/handoff/CHECKPOINT.md
for f in "$B" "$P" "$C"; do [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }; done
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
# has <file> <regex>...: some paragraph (blank-line separated) matches every regex, any case
has() { f=$1; shift; out=$(awk -v RS= '{gsub(/\n/," "); print}' "$f"); for re in "$@"; do out=$(printf '%s\n' "$out" | grep -Ei -- "$re"); done; [ -n "$out" ]; }
leftsec() { awk '/^## Leftovers on the local site/{p=1;next} p&&/^## /{exit} p' "$C"; }

t "begin: the leftovers name ada-clock status" has "$B" 'leftover' 'ada-clock status'
t "begin: 'a changed clock' gone" sh -c "! grep -qi 'a changed clock' '$B'"
t "pickup: the leftovers name ada-clock status" has "$P" 'leftover' 'ada-clock status'
t "pickup: 'a changed clock' gone" sh -c "! grep -qi 'a changed clock' '$P'"
t "CHECKPOINT template: the leftovers section names ada-clock status" sh -c "$(declare -f leftsec); C='$C'; leftsec | grep -q 'ada-clock status'"
t "CHECKPOINT template: 'a changed clock' gone" sh -c "! grep -qi 'a changed clock' '$C'"
# Regression
t "regression: CHECKPOINT keeps one 'Leftovers on the local site' heading" test "$(grep -c '^## Leftovers on the local site' "$C")" = 1
t "regression: begin still says leftovers are cleared before anything runs on the site" has "$B" 'leftover' 'clear' 'before anything runs'
t "regression: CHECKPOINT leftovers still by ID" sh -c "$(declare -f leftsec); C='$C'; leftsec | grep -q 'ID'"
echo "$fails failed"; [ "$fails" = 0 ]
```

### Part B: `/ada:pickup` with a clock left set

1. Make the scratch repo. `ada-onboard files` gives it the team files, so `/ada:pickup` isn't
   stopped first by a missing `PROJECT.md` (the project counts as onboarded for this check):

   ```sh
   mkdir -p ~/scratch/leftover-clock && cd ~/scratch/leftover-clock && git init -q
   git commit -q --allow-empty -m init && git switch -q -c change/leftover-clock
   bash <this checkout>/plugins/ada/bin/ada-onboard files
   git add -A && git commit -qm 'ada-onboard files'
   mkdir -p .claude && printf '{ "enabledPlugins": { "ada@pie": false } }\n' > .claude/settings.local.json
   bash <this checkout>/plugins/ada/bin/ada-handoff-init
   ```

2. Replace `.claude/handoff/CHECKPOINT.md` with:

   ```markdown
   # Checkpoint

   - Written: 2026-10-29
   - Step: Leftover clock dry run
   - Branch: change/leftover-clock

   ## Where we stopped

   Tess's run stopped part way through a date check.

   ## Still open

   None.

   ## Resume prompt

   `/ada:pickup`

   ## Leftovers on the local site

   - The clock was set with `ada-clock set "2026-11-06 12:30"` and not reset.
   ```

3. `claude --plugin-dir <this checkout>/plugins/ada`, then `/ada:pickup`. Read what Ada says
   before anything else happens, then quit.
4. `rm -rf ~/scratch/leftover-clock`.

## Expected

From the brief (Decisions, Ada; DoD 5) and Bob's approved Builder plan, item 5:

- Part A: every line `PASS`, `0 failed`, exit 0.
- Part B: Ada tells you there are leftovers on the local site, a clock left set among them, and
  names `ada-clock status` as how to check it; they are cleared before anything runs on the site.
  She runs nothing on a site (there is none) and doesn't go on to a role first.

## Observe at

- Part A: stdout and `echo $?`.
- Part B: Ada's first messages in the session.

## Regression

- `/ada:begin`'s other session-start steps and `/ada:pickup`'s `ada-worktree check` are
  unchanged: `08-session-flow/worktree-per-step.md` covers them.
- The CHECKPOINT template's headings are what `/ada:pickup` reads; only the leftovers line's
  wording changes.
