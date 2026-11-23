# Amending an approved brief: check the triggers, record the row, carry on or resubmit

- Area: 08 Session flow (`begin`, `pickup`, `design`, `planning`); 09 Roles (Tess, Claudia read
  the Amendments table); 01 Brief approval (`submit`'s `(approval reset: <slugs>)`, `status`'s
  `area` lines)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219937425454517
- Automate as: none (part A is a self-checking shell script; parts B, C and D are dry runs for a person)
- Last run: 2026-11-23, Part A 0 failed (39 PASS) on the host; Parts B, C, D not run; see the PR

## Precondition

- Part A: this checkout (`REPO`) with the change in its working tree, and `origin/next-release`
  fetched (the script compares with the released skills). `git`, `tar`, `awk`.
- Part B (Unit E): a practice project with `claude --plugin-dir <this checkout>/plugins/ada`,
  `ada@pie` off in its `.claude/settings.local.json` (`DEVELOPING.md`), on a scratch GitHub repo
  you own. Its `PROJECT.md` `## Brief approval` has `- **Approvers:** @<you>`,
  `- **Trusted:** none` and this table, with no `price` row:

  ```
  | Area | Slug | Approved by |
  |---|---|---|
  | Wording and content | content | @<you> |
  | Design and UI | design | @<you> |
  | System behaviour (catch-all) | system | Approvers |
  ```

  Start the session with `ADA_BRIEF_REQUIRE=1`, so you aren't exempt as the brief's author. A
  brief for a small change to a confirmation message has been agreed with `Areas: content,
  system` and `Author: @<you>`, submitted with `ada-brief submit`, and approved: you added
  `approved:content` and `approved:system` to its draft PR yourself, and `ada-brief status` says
  `approved @<you> <time>` with an `area content: approved …` and an `area system: approved …`
  line. The step is in build.
- Part C: the same, but a second practice project whose `PROJECT.md` `## Brief approval` lists
  your login under `**Approvers:**`, has no areas table, and has
  `- **Re-approval triggers:** changes to the export format`. Start the session with
  `ADA_BRIEF_REQUIRE=1`. A brief has been agreed (its `Areas:` line left as the template's
  prompt), submitted with `ada-brief submit`, and approved: you added `brief-approved` to its
  draft PR yourself, and `ada-brief status` says `approved @<you> <time>`. The step is in build.

- Part D (Amendment 2026-11-23, areas judged by name): as Part B, but the table's first row has
  its slug renamed, `| Wording and content | copy | @<you> |`, so the project has no `content`
  slug. The brief has `Areas: copy, system`, submitted and approved with `approved:copy` and
  `approved:system` on the PR. `ada-brief areas` prints `copy: …`. The step is in build.

## Steps

### Part A: the wording, checked

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, delete the default trigger
   list (the six bullets after "The default triggers.") from the copy's `skills/begin/approval.md`,
   and run `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. All 12 "approval.md: default
   trigger names" checks must print `FAIL`.

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
hasw() { grep -qiwF -- "$2" "$1"; }   # whole word: 'auth' must not match 'Author'

BG=$W/begin; PK=$W/pickup; DS=$W/design; OBG=$W/begin.old
# begin is SKILL.md plus the files it reads on demand (approval.md holds the amendment flow), on both sides.
allbegin() { cat "$1/skills/begin/SKILL.md"; for f in "$1"/skills/begin/*.md; do [ "$f" = "$1/skills/begin/SKILL.md" ] || { echo; cat "$f"; }; done; }
allbegin "$PLUGIN" > "$W/begin.all"; allbegin "$OLD_PLUGIN" > "$W/begin.old.all"
flat "$W/begin.all" "$BG"; flat "$PLUGIN/skills/pickup/SKILL.md" "$PK"
flat "$PLUGIN/skills/design/SKILL.md" "$DS"; flat "$W/begin.old.all" "$OBG"
AP=$W/approval; flat "$PLUGIN/skills/begin/approval.md" "$AP"   # approval.md alone: it holds the Re-approval triggers

# begin: the flow (D13). Once the step is released, $BASE already has the flow, so "text begin gains
# over the released one" is empty: the checks read all of begin's text. (Before release they read only
# the added text, so words already there elsewhere, like "fragile area", could not pass by accident;
# the distinctive phrases below still can't.)
ADD=$BG
t "begin (added text): the Re-approval column's 'not needed'" has "$ADD" 'not needed'
t "begin (added text): the Re-approval column's 'needed: '" has "$ADD" 'needed: '
t "begin (added text): names the Re-approval column" has "$ADD" 'Re-approval'
t "begin (added text): the project's own list, 'Re-approval triggers'" has "$ADD" 'Re-approval triggers'
t "begin (added text): what submit prints when approval is kept" has "$ADD" '(approval kept)'
# Builder question 6: begin runs submit for a 'not needed' amendment too (it posts the comment)
t "begin (added text): runs 'ada-brief submit' in the amendment flow" has "$ADD" 'ada-brief submit'
t "begin (added text): what submit prints when approval is reset" has "$ADD" '(approval reset)'
# begin: the default triggers (Decisions, "Default list, in begin"), in approval.md alone, the file that
# holds them. Not all of begin: most of these words also appear in SKILL.md ('fragile area', 'customer',
# 'pays', 'auth', 'payment', 'Expected review'), so a check on all of begin passes with the list deleted.
# 'auth' is matched as a whole word: approval.md must say "Author:" (Decisions, The brief), which a
# plain substring match would take for the trigger, so the check could not fail with the list deleted.
for k in 'fragile area' 'customer' 'pays' 'dependency' 'external service' 'data repair' 'migration' 'security' 'auth' 'payment' 'Expected review' 'revers'; do
  if [ "$k" = auth ]; then t "approval.md: default trigger names '$k'" hasw "$AP" "$k"
  else t "approval.md: default trigger names '$k'" has "$AP" "$k"; fi
done
# Unit E (brief approval by area, task 1220198325269356): Re-approval names areas. In approval.md's
# Amending section alone. Each default trigger maps to an area (Decisions, Amendments): near <term>
# <n> <regex>: some window of n characters either side of an occurrence of <term> matches <regex>.
AM=$W/amending; awk '/^## Amending/{p=1;next} p&&/^## /{exit} p' "$PLUGIN/skills/begin/approval.md" | tr '\n' ' ' | tr -s ' ' > "$AM"
near() { awk -v t="$1" -v n="$2" -v re="$3" '{ s=tolower($0); o=0; while ((i=index(substr(s,o+1),t))>0) { p=o+i; st=p-n; if(st<1)st=1; if (substr(s,st,2*n+length(t)) ~ re) f=1; o=p } } END { exit !f }' "$4"; }
count() { grep -oiF -- "$1" "$AM" | wc -l | tr -d ' '; }
t "Amending (Unit E): Re-approval is needed: <area>" has "$AM" 'needed: <area'
t "Amending (Unit E): something the customer sees -> the content area" near 'customer' 200 'content' "$AM"
t "Amending (Unit E): something the customer sees -> or the design area" near 'customer' 200 'design' "$AM"
t "Amending (Unit E): pays -> price" near 'pays' 150 'price' "$AM"
t "Amending (Unit E): a new dependency, data repair or migration -> dependencies" has "$AM" 'dependencies'
t "Amending (Unit E): security, auth or payment -> security (the slug as well as the trigger)" test "$(count security)" -ge 2
t "Amending (Unit E): a fragile area the brief didn't name -> fragile (the slug as well as the trigger)" test "$(count fragile)" -ge 2
t "Amending (Unit E): a reversed Decision -> the catch-all" near 'revers' 250 'catch-all' "$AM"
t "Amending (Unit E): a higher Expected review -> the catch-all" near 'expected review' 250 'catch-all' "$AM"
t "Amending (Unit E): a project without that area -> the catch-all" grep -qiE '(without|no|doesn.t have|lacks?|isn.t).{0,80}area.{0,160}catch-all|catch-all.{0,160}(without|no|doesn.t have|lacks?|isn.t).{0,80}area' "$AM"
t "Amending (Unit E): an area not on Areas: is added to it in the same amendment" grep -qiE 'Areas:.{0,240}same amendment|same amendment.{0,240}Areas:' "$AM"
t "Amending (Unit E): reads submit's (approval reset: <slugs>)" has "$AM" '(approval reset: '
# Amendment 2026-11-23: a trigger goes to the area that covers it, judged from the areas' names as well
# as their slugs (the starter slug is the default); the catch-all only when no area fits.
t "Amending (Amendment): areas are judged from their names as well as their slugs" grep -qiE 'names?.{0,120}slugs?|slugs?.{0,120}names?' "$AM"
t "Amending (Amendment): the catch-all only when no area fits" grep -qiE '(no|none|nothing).{0,60}(area|row).{0,40}(fits|covers|matches|suits)' "$AM"
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

### Part B: a dry run with an areas table (a person, 30 to 40 minutes)

Unit E: an amendment goes back only to its areas.

In the practice project's session (Precondition, Part B), in build, give these prompts in order.
After each, open `.claude/handoff/BRIEF.md` (its `Areas:` line and Amendments), the draft PR
(labels, comments) and the session, and run `<this checkout>/plugins/ada/bin/ada-brief status`
yourself.

1. "Change the wording of the confirmation message in the Decisions to 'Thanks, your order is in'."
   (wording the customer sees; nothing else)
2. Add `approved:content` to the PR again yourself, end the session and start a fresh one with
   `/ada:pickup`.
3. "Reverse the second Decision: <its opposite>." (a reversed Decision)
4. Add `approved:system` again yourself. "Make the confirmation message's button red." (how
   something the customer sees looks; `design` is not on the brief's `Areas:`)
5. Add `approved:design` yourself. "Add a 50p charge for paper bags to the order total."
   (something the customer pays; the project has no `price` area)

### Part C: a dry run with no areas table (a person, 20 to 30 minutes)

The flow before Unit E, which a project with no areas table keeps.

In the practice project of Precondition, Part C, in build, give these prompts in order. After
each, open `.claude/handoff/BRIEF.md` (Amendments), the draft PR (label, comments) and the session.

1. "Change the Outcome to say <a reworded outcome>." (a developer section; no trigger)
2. "Change the second Decision to use <another existing helper>." (an approved section; no
   trigger)
3. "We need to add <a new library> for the export." (a new dependency, and the project's own
   trigger "changes to the export format")
4. Approve it again (add `brief-approved` yourself), then end the session and start a fresh one
   with `/ada:pickup`.
5. By hand, change a Decision in the committed brief (`.claude/briefs/<task>.md`) without adding
   an Amendments row, commit only that file and push it; then `/ada:pickup` again.

### Part D: a dry run with a renamed area (a person, 10 to 15 minutes)

In the practice project of Precondition, Part D, in build, give this prompt:

1. "Change the wording of the confirmation message in the Decisions to 'Thanks, your order is in'."
   (wording the customer sees; the table has no `content` slug, but its `copy` row is Wording and
   content)

## Expected

Part A: every line `PASS`, last line `0 failed`, exit 0. Record the `info:` lines in the Run
record. Step 3's broken copy prints `FAIL` for all 12 "approval.md: default trigger names" checks.

Part B (Unit E, Definition of Done 6, and Decisions, Amendments):

1. Ada names the trigger (something the customer sees) and its area, `content`. After your yes
   she records a row with Re-approval `needed: content` and your name under Agreed by, and runs
   `ada-brief submit`, which prints `updated  <url> (approval reset: content)`. On the PR,
   `approved:content` is gone and `approved:system` is still on. She stops until the content
   area is approved again. `status`: first line `waiting`; `area content: waiting (@<you>)`;
   `area system: approved @<you> <time>`.
2. Pickup runs `ada-brief status`: first line `approved @<you> <time>`, and carries on with the
   build.
3. Ada names the trigger (a reversed Decision) and records `needed: system` (the catch-all's
   slug), after your yes. `submit` prints `updated  <url> (approval reset: system)`;
   `approved:system` is gone, `approved:content` stays. She stops.
4. Ada records `needed: design`, and adds `design` to the brief's `Areas:` line in the same
   amendment (`Areas: content, system, design`, in any order). `submit` prints
   `(approval reset: design)`; the PR now has an `approved:design` label to add, and
   `approved:content` and `approved:system` stay on. `status`: `area design: waiting (@<you>)`.
5. The project has no `price` area, so Ada records `needed: system` (the catch-all), not
   `needed: price`, and doesn't add `price` to `Areas:`. `submit` prints
   `(approval reset: system)`; only `approved:system` is gone.

Part D (Amendment 2026-11-23: a trigger goes to the area that covers it, judged from the areas'
names as well as their slugs):

1. Ada names the trigger (something the customer sees) and the area `copy`, because its name is
   Wording and content; she does not send it to `system` (the catch-all) and does not add `content`
   to `Areas:`. After your yes she records `needed: copy`, runs `ada-brief submit`, which prints
   `updated  <url> (approval reset: copy)`. On the PR, `approved:copy` is gone and
   `approved:system` is still on.

Part C, the flow with no areas table (unchanged by Unit E):

1. Ada asks for your yes before changing the Outcome (the brief is still a contract). On yes she
   records a row with `not needed` in Re-approval and your name under Agreed by, runs
   `ada-brief submit`, which prints `updated <url> (approval kept)`, and carries on: she doesn't
   stop to wait for approval. The label stays, and the PR gets one comment listing the row.
2. As 1: Ada checks the change against the default triggers and the project's own, finds none,
   records `not needed`, runs `ada-brief submit` (`(approval kept)`, one comment listing only
   this row), and carries on. The label stays.
3. Ada names the trigger (a new dependency, or the project's "changes to the export format"),
   records `needed: <trigger>` after your yes (with no areas table the trigger name stays: Unit
   E's Builder plan, settled by Ada on 2026-11-23), runs `ada-brief submit`, which prints `updated <url> (approval reset)`;
   the label is gone, and she stops until `status` says `approved`.
4. Pickup runs `ada-brief status`: `approved @<you> <time>` with no second line (nothing changed
   since your label), and carries on with the build.
5. `status` prints `waiting` with the note naming
   `approved section changed with no amendment recorded`; pickup says so and stops.

Sources: Amendments of 2026-11-23 to Unit E's brief (a trigger to the area that covers it, by name
as well as slug; the catch-all only when no area fits). The brief of 18 Nov 2026 (Outcome: "only an amendment that hits a re-approval trigger
stops for approval"; Builder question 6, `begin` runs `submit` for a `not needed` amendment too;
the default list; the project's optional bullet; `status` and `submit` outputs; Definition of
Done 13). Unit E's brief (task 1220198325269356): Decisions, Amendments (Re-approval `not needed`
or `needed: <area>[, <area>]`; each default trigger's area; the catch-all in a project without
that area; an area not on `Areas:` added in the same amendment; `(approval reset: <slugs>)`), and
Definition of Done 6. `API.md` (`submit`'s `(approval reset: <slug>, ...)`; `status`'s `area`
lines).

## Observe at

Part A: the script's output. Parts B, C and D: the session transcript (does Ada stop or carry on,
and what she tells you), `.claude/handoff/BRIEF.md`'s `Areas:` line and Amendments rows,
`.claude/handoff/CHECKPOINT.md` after pickup, `ada-brief status`, and the PR on GitHub: labels,
comments, timeline.

## Regression

A brief with no approval needed (`who` says `not-needed`) still records amendments with the
developer's yes and doesn't run `submit`. Changes requested by an approver still go back through
`submit` and reset approval (begin's `changes-requested` line). A project with no areas table
keeps the whole flow of Part C, with `brief-approved`.
