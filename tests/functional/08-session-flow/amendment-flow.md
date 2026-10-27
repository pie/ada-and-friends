# Amending an approved brief: check the triggers, record the row, carry on or resubmit

- Area: 08 Session flow (`begin`, `pickup`, `design`, `planning`); 09 Roles (Tess, Claudia read
  the Amendments table)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219937425454517
- Automate as: none (part A is a self-checking shell script; part B is a dry run for a person)
- Last run: 2026-10-27, Part A passed 0 failed on bash 5.2, bash 3.2, Python 3.8, Part B not run; see the PR

## Precondition

- Part A: this checkout (`REPO`) with the change in its working tree, and `origin/next-release`
  fetched (the script compares with the released skills). `git`, `tar`, `awk`.
- Part B: a practice project with `claude --plugin-dir <this checkout>/plugins/ada`, `ada@pie`
  off in its `.claude/settings.local.json` (`DEVELOPING.md`), on a scratch GitHub repo you own.
  Its `PROJECT.md` `## Brief approval` lists your login under `**Approvers:**`, and has
  `- **Re-approval triggers:** changes to the export format`. Start the session with
  `ADA_BRIEF_REQUIRE=1` so Ada treats you as needing approval. A brief has been agreed, submitted
  with `ada-brief submit`, and approved: you added `brief-approved` to its draft PR yourself, and
  `ada-brief status` says `approved @<you> <time>`. The step is in build.

## Steps

### Part A: the wording, checked

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, delete the line naming
   data repairs and migrations from the copy's `skills/begin/SKILL.md` trigger list, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL`.

```bash
#!/bin/bash
# The amendment flow's wording in begin, pickup and design. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO, BASE (default origin/next-release), PLUGIN (default $REPO/plugins/ada), OLD_PLUGIN.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
W=$(mktemp -d)
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
flat() { tr '\n' ' ' < "$1" | tr -s ' ' > "$2"; }   # one line, single spaces: sentences across wrapped lines
has()  { grep -qiF -- "$2" "$1"; }

BG=$W/begin; PK=$W/pickup; DS=$W/design; OBG=$W/begin.old
flat "$PLUGIN/skills/begin/SKILL.md" "$BG"; flat "$PLUGIN/skills/pickup/SKILL.md" "$PK"
flat "$PLUGIN/skills/design/SKILL.md" "$DS"; flat "$OLD_PLUGIN/skills/begin/SKILL.md" "$OBG"

# begin: the flow (D13). Checked in the text begin gains over the released one, so words that
# were already there elsewhere ("fragile area", "customer") don't pass by accident.
ADD=$W/begin.added
diff "$OLD_PLUGIN/skills/begin/SKILL.md" "$PLUGIN/skills/begin/SKILL.md" | grep '^>' | sed 's/^> //' > "$W/added.raw"
flat "$W/added.raw" "$ADD"
echo "info: lines begin gained or changed: $(wc -l < "$W/added.raw" | tr -d ' ')"
t "begin (added text): the Re-approval column's 'not needed'" has "$ADD" 'not needed'
t "begin (added text): the Re-approval column's 'needed: '" has "$ADD" 'needed: '
t "begin (added text): names the Re-approval column" has "$ADD" 'Re-approval'
t "begin (added text): the project's own list, 'Re-approval triggers'" has "$ADD" 'Re-approval triggers'
t "begin (added text): what submit prints when approval is kept" has "$ADD" '(approval kept)'
# Builder question 6: begin runs submit for a 'not needed' amendment too (it posts the comment)
t "begin (added text): runs 'ada-brief submit' in the amendment flow" has "$ADD" 'ada-brief submit'
t "begin (added text): what submit prints when approval is reset" has "$ADD" '(approval reset)'
# begin: the default triggers (Decisions, "Default list, in begin"), in the added text
for k in 'fragile area' 'customer' 'pays' 'dependency' 'external service' 'data repair' 'migration' 'security' 'auth' 'payment' 'Expected review' 'revers'; do
  t "begin (added text): default trigger names '$k'" has "$ADD" "$k"
done
t "baseline: the released begin had no Re-approval column" not has "$OBG" 'Re-approval'
# begin: the old rule, that every amendment of an approved brief needs approval again, is gone
t "begin: no 'If the brief needed approval, an amendment does too'" not has "$BG" 'If the brief needed approval, an amendment does too'
t "begin: no 'only an approval given after the brief last changed counts'" not has "$BG" 'only an approval given after the brief last changed counts'
# pickup reads status's second line (D13)
t "pickup: names 'amended since approval'" has "$PK" 'amended since approval'
# the recheck applies only before the draft PR is made ready: ready replaces the PR body, so status
# would say not-submitted afterwards (Copilot review of PR #39); pickup's text must name that limit
t "pickup: the status recheck is tied to 'ada-brief ready' (only before the PR is made ready)" has "$PK" 'ada-brief ready'
# design agrees with begin (D13)
t "design: no longer 'which then needs agreeing (and approving, if the project needs it) again'" not has "$DS" 'which then needs agreeing (and approving, if the project needs it) again'
t "design: hands the re-approval question to the triggers" has "$DS" 'trigger'
# For the record: the other readers the brief names
echo "info: planning still says the briefs a plan change touches 'are submitted again': $(grep -c 'submitted again' "$PLUGIN/skills/planning/SKILL.md")"
echo "info: begin mentions 'amended since approval': $(grep -ciF 'amended since approval' "$BG")"
echo "info: claudia.md / tess.md mention 'Re-approval': $(grep -c 'Re-approval' "$PLUGIN/agents/claudia.md") / $(grep -c 'Re-approval' "$PLUGIN/agents/tess.md")"

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### Part B: a dry run (a person, 20 to 30 minutes)

In the practice project's session, in build, give these prompts in order. After each, open
`.claude/handoff/BRIEF.md` (Amendments), the draft PR (label, comments) and the session.

1. "Change the Outcome to say <a reworded outcome>." (a developer section; no trigger)
2. "Change the second Decision to use <another existing helper>." (an approved section; no
   trigger)
3. "We need to add <a new library> for the export." (a new dependency, and the project's own
   trigger "changes to the export format")
4. Approve it again (add `brief-approved` yourself), then end the session and start a fresh one
   with `/ada:pickup`.
5. By hand, change a Decision in the committed brief (`.claude/briefs/<task>.md`) without adding
   an Amendments row, commit only that file and push it; then `/ada:pickup` again.

## Expected

Part A: every line `PASS`, last line `0 failed`, exit 0. Record the `info:` lines in the Run
record. Step 3's broken copy prints `FAIL` for "data repair" and "migration".

Part B:

1. Ada asks for your yes before changing the Outcome (the brief is still a contract). On yes she
   records a row with `not needed` in Re-approval and your name under Agreed by, runs
   `ada-brief submit`, which prints `updated <url> (approval kept)`, and carries on: she doesn't
   stop to wait for approval. The label stays, and the PR gets one comment listing the row.
2. As 1: Ada checks the change against the default triggers and the project's own, finds none,
   records `not needed`, runs `ada-brief submit` (`(approval kept)`, one comment listing only
   this row), and carries on. The label stays.
3. Ada names the trigger (a new dependency, or the project's "changes to the export format"),
   records `needed: <trigger>` after your yes, runs `ada-brief submit`, which prints
   `updated <url> (approval reset)`; the label is gone, and she stops until `status` says
   `approved`.
4. Pickup runs `ada-brief status`: `approved @<you> <time>` with no second line (nothing changed
   since your label), and carries on with the build.
5. `status` prints `waiting` with the note naming
   `approved section changed with no amendment recorded`; pickup says so and stops.

Sources: the brief's Outcome ("only an amendment that hits a re-approval trigger stops for
approval"), Builder question 6 (`begin` runs `submit` for a `not needed` amendment too: it posts
the approver's comment), Decisions (the triggers are Ada's judgement, recorded in the row; the default list;
the project's optional bullet; `status` and `submit` outputs), Definition of Done item 13.

## Observe at

Part A: the script's output. Part B: the session transcript (does Ada stop or carry on, and what
she tells you), `.claude/handoff/BRIEF.md`'s Amendments rows, `.claude/handoff/CHECKPOINT.md`
after pickup, and the PR on GitHub: label, comments, timeline.

## Regression

A brief with no approval needed (`who` says `not-needed`) still records amendments with the
developer's yes and doesn't run `submit`. Changes requested by an approver still go back through
`submit` and reset approval (begin's `changes-requested` line).
