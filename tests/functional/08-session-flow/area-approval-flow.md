# Ada records a brief's areas and author, and gets it approved only by the areas' approvers

Unit E of the plan "Brief approval by area": Ada judges the areas a brief touches, checks them
with `ada-brief areas`, writes `Areas:` and `Author:`, runs `ada-brief who <areas>`, and names
the approvers of the areas the developer doesn't approve. A developer who approves every area
touched needs no approval. A project with no areas table keeps today's `brief-approved`.

- Area: 08 Session flow (`begin`'s SKILL.md and `approval.md`, `pickup`, `planning`); 01 Brief
  approval (the readers of `who <area>`, `status`'s `area` lines)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198325269356
- Automate as: none (part A is a self-checking shell script; part B is a dry run for a person)
- Last run: 2026-11-23, Part A 28 passed, 0 failed; Part B not run; see the PR

## Precondition

- Part A: this checkout (`REPO`) with the change in its working tree, and
  `origin/add/brief-approval-by-area` (`BASE`, the branch the step's PR goes into) fetched: some
  checks read only the text this step adds to a file. `git`, `tar`, `awk`, `diff`.
- Part B: `claude` logged in, `gh` logged in as you (`<you>`). A scratch GitHub repo you own
  (never a client's), cloned, with a `README.md` holding one line of customer-facing text, e.g.
  `Thanks for your order.`, committed and pushed to `main`, and a `next-release` branch pushed.
  Onboard it from this checkout: `bash <this checkout>/plugins/ada/bin/ada-onboard files
  PROJECT_NAME=Scratch`, `ada-onboard settings` and `ada-onboard gitignore`, then in
  `.claude/team/PROJECT.md` set **Branch from:** to `main` and **PR into:** to `next-release`, and write
  this Brief approval section (`<other>` is any GitHub login that isn't yours; they need do
  nothing):

  ```
  - **Approvers:** @<you>
  - **Trusted:** none

  | Area | Slug | Approved by |
  |---|---|---|
  | Wording and content | content | @<other> |
  | Design and UI | design | @<other> |
  | System behaviour (catch-all) | system | Approvers |
  ```

  Commit and push. In `.claude/settings.local.json`, `ada@pie` is off (`DEVELOPING.md`). Start
  each session with `claude --plugin-dir <this checkout>/plugins/ada`, without
  `ADA_BRIEF_REQUIRE`.

## Steps

### Part A: the wording, checked

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, delete every line with
   `gh api user` from the copy's `skills/begin/approval.md`, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. "Author: is the login from gh api
   user" prints `FAIL`.

```bash
#!/bin/bash
# Unit E: getting a brief approved by area, in begin (SKILL.md, approval.md), pickup and planning.
# PASS/FAIL per check; exit 1 if any FAIL, 2 on SETUP FAIL.
# Env: REPO (this checkout), PLUGIN (default $REPO/plugins/ada), BASE (the branch the step's PR goes
#      into; default origin/add/brief-approval-by-area), OLD_PLUGIN (an exported base plugins/ada).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE=${BASE:-origin/add/brief-approval-by-area}
W=$(mktemp -d); trap 'rm -rf "$W"' EXIT
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
B=$PLUGIN/skills/begin/SKILL.md; A=$PLUGIN/skills/begin/approval.md
for f in "$B" "$A" "$PLUGIN/skills/pickup/SKILL.md" "$PLUGIN/skills/planning/SKILL.md"; do
  [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }
done
fails=0; passes=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; passes=$((passes+1)); else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { local name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
flat() { tr '\n' ' ' | tr -s ' \t' '  '; }
has()  { grep -qiE -- "$2" "$1"; }
hasF() { grep -qiF -- "$2" "$1"; }
heading_section() { awk -v re="$1" '/^#+ /{ match($0,/^#+/); l=RLENGTH; if(p&&l<=lv) exit; if(!p&&tolower($0)~re){p=1;lv=l;next} } p' "$2"; }
# added <file under plugins/ada>: the lines this step adds to it, against the base (one line).
added() { diff "$OLD_PLUGIN/$1" "$PLUGIN/$1" | sed -n 's/^> //p' | flat; }

# begin/SKILL.md: the brief's two header lines, in a sentence or two; the rest in approval.md.
flat < "$B" > "$W/skill"
t "begin/SKILL.md: under 26000 bytes" test "$(wc -c < "$B")" -lt 26000
t "begin/SKILL.md: names the brief's Areas: line" hasF "$W/skill" 'Areas:'
t "begin/SKILL.md: names the brief's Author: line" hasF "$W/skill" 'Author:'
t "begin/SKILL.md: still points at approval.md" hasF "$W/skill" '`approval.md`'

# approval.md, Getting the brief approved (Decisions: The brief, Approval).
heading_section '^## getting the brief approved' "$A" | flat > "$W/get"
t "approval.md: a Getting the brief approved section" test -s "$W/get"
t "Getting approved: Ada checks the areas against ada-brief areas" hasF "$W/get" 'ada-brief areas'
t "Getting approved: runs ada-brief who <areas>" has "$W/get" 'ada-brief who <'
t "Getting approved: writes Areas: before running who" has "$W/get" 'Areas:.{0,400}ada-brief who <'
t "Getting approved: writes Author:" hasF "$W/get" 'Author:'
t "Getting approved: Author: is the login from gh api user" hasF "$W/get" 'gh api user'
t "Getting approved: Author: is the one who opens the draft PR (runs submit)" has "$W/get" 'Author:.{0,300}(open|submit)'
t "Getting approved: reads who's needs <slug> lines" has "$W/get" '`needs |needs <'
t "Getting approved: reads status's area lines" has "$W/get" '`area |area <'
t "Getting approved: the Approval: line records each area (approved: <area> by @x)" has "$W/get" 'approved: [^ ]{1,20} by @'
t "Getting approved: and an area not needed (@y approves it)" has "$W/get" 'not needed \(@[^ ]* approves it\)'
t "Getting approved: an author needs no approval for the areas they approve" has "$W/get" '(no approval|need no|needs no|don.t need|doesn.t need|not need).{0,120}(they|you|the author|the developer) approve|(they|you|the author|the developer) approve.{0,120}(no approval|need no|needs no|don.t need|doesn.t need|not need)'
t "Getting approved: Trusted approves every area" has "$W/get" 'Trusted.{0,80}every area'
t "Getting approved: no areas table: the Areas: prompt left as it is" has "$W/get" '(no|without (an? )?)(areas )?table.{0,300}Areas:|Areas:.{0,300}(no|without (an? )?)(areas )?table'
t "Getting approved: no areas table: brief-approved as today" hasF "$W/get" 'brief-approved'
t "regression: Getting approved: an approver needs no approval (not needed)" has "$W/get" 'not needed'
t "regression: Getting approved still runs ada-brief status" hasF "$W/get" 'ada-brief status'
t "regression: approval.md no longer says 'Approvers never need approval'" not hasF "$A" 'Approvers never need approval'

# pickup, step 4: reads the area lines.
added skills/pickup/SKILL.md > "$W/pk"
t "pickup: the text this step adds speaks of areas" has "$W/pk" 'area'
flat < "$PLUGIN/skills/pickup/SKILL.md" > "$W/pickup"
t "pickup: reads status's area lines or the brief's Areas:" has "$W/pickup" '`area |area <|Areas:'

# planning: a unit's areas go on its brief's Areas: line; the Unit cell may name them, never a column.
flat < "$PLUGIN/skills/planning/SKILL.md" > "$W/plan"
added skills/planning/SKILL.md > "$W/pl"
t "planning: a unit's areas go on its brief's Areas: line" hasF "$W/plan" 'Areas:'
t "planning: the Unit cell may name them, e.g. (content)" hasF "$W/plan" '(content)'
t "planning: never as a column" has "$W/pl" 'column'
t "regression: planning keeps the six columns (ada-plan check)" hasF "$W/plan" 'ada-plan check'

echo "$passes passed, $fails failed"
[ "$fails" = 0 ] || exit 1
exit 0
```

### Part B: a dry run (a person, 30 to 45 minutes)

1. A wording-only change, by a developer who doesn't approve content. `/ada:begin`, then: "Change
   the thank-you line in README.md to 'Thanks, your order is in.' Nothing else." Agree the brief
   when Ada shows it. When she stops for approval, open `.claude/handoff/BRIEF.md`, the draft PR
   and the session, and run `<this checkout>/plugins/ada/bin/ada-brief status`. Close the PR and
   delete its branch.
2. A change by a developer who approves every area it touches. In `PROJECT.md`, change the content
   row's Approved by to `@<you>`; commit and push. Start a fresh session: `/ada:begin`, then the
   same request as step 1. Agree the brief.
3. A project with no areas table (regression). Delete the table from `PROJECT.md` (keep the two
   lines); commit and push. Start a fresh session with `ADA_BRIEF_REQUIRE=1`: `/ada:begin`, then
   the same request. Agree the brief. Close the PR and delete its branch afterwards.

## Expected

Part A: every line `PASS`, last line `<n> passed, 0 failed`, exit 0.

Part B:

1. The brief's header has `Areas: content` and `Author: @<you>` (your `gh api user` login), after
   the `Approval:` line. Before agreeing it, the transcript shows `ada-brief areas`, then
   `ada-brief who content` (after `Areas:` was written), which prints `required <you>` and
   `needs content: @<other>`. Ada tells you the brief needs approval for content and names
   `@<other>` as its approver; she does not name system's approvers. She runs `ada-brief submit`
   (`opened   <url>`), and stops. The PR has an `approved:content` label available and no
   `brief-approved` is asked for. `status`: first line `waiting`, and `area content: waiting
   (@<other>)`. The `Approval:` line says content awaits `@<other>`.
2. `Areas: content`, `Author: @<you>`; `ada-brief who content` prints `approver <you>`. Ada
   records `Approval: not needed` (naming that you approve content) and carries on to the build
   without running `ada-brief submit`: no draft PR is opened.
3. The `Areas:` line is left as the template's prompt (no areas); `Author: @<you>` is written.
   Ada runs `ada-brief who` with no areas (`required <you>`, under `ADA_BRIEF_REQUIRE=1`), submits,
   and asks for the `brief-approved` label as before; `status`'s first line is `waiting`, with no
   `area` lines.

Sources, all Unit E's brief (task 1220198325269356): Outcome; Decisions (The brief: the two header
lines, `ada-brief areas`, `who <areas>`, no `Areas:` line without a table, `Author:` always
written, the login from `gh api user`; Approval: `required` lists the `needs <slug>` areas, Ada
names their approvers, `status`'s `area` lines, the `Approval:` line per area; an author needs no
approval for the areas they approve, Trusted approves every area; Plans: the Unit cell, never a
column); Definition of Done 1 (`SKILL.md` under 26000 bytes) and 5; Constraints (`SKILL.md` holds
a sentence or two, the rest in `approval.md`). `API.md`: `who <area>...`'s first line and `needs`
lines; `status`'s `area` lines.

## Observe at

Part A: the script's output. Part B: the session transcript (the Bash calls to `ada-brief`, what
Ada tells you), `.claude/handoff/BRIEF.md`'s header, the draft PR on GitHub (labels, body),
`ada-brief status`.

## Regression

A project with no areas table: `Areas:` left as the prompt, `who` with no areas, `brief-approved`
(part B, step 3). `begin/SKILL.md` stays under 26000 bytes, and
`tests/functional/08-session-flow/begin-split-and-models.md` still passes. `approval.md` still runs
`ada-brief status` and treats an approver as needing no approval.
