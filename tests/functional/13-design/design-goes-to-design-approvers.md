# Iris's design goes to the design area's approvers

Unit E of the plan "Brief approval by area": when `ada-brief who` says an area needs approval, Ada
asks the developer to share the design artifact with the design area's approvers (the
catch-all's when the project has no design area), as the developer decides. Going back to design
partway through a step sends the brief back to the design area.

- Area: 13 Design (`begin`'s `design-step.md`, `/ada:design`); 08 Session flow
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198325269356
- Automate as: none (part A is a self-checking shell script; part B is a dry run for a person)
- Last run: 2026-11-23, Part A 8 passed, 0 failed; Part B not run; see the PR

## Precondition

- Part A: this checkout (`REPO`) with the change in its working tree, and
  `origin/add/brief-approval-by-area` (`BASE`) fetched. `git`, `tar`, `awk`, `diff`.
- Part B: as `tests/functional/08-session-flow/area-approval-flow.md`, Precondition, Part B (a
  scratch GitHub repo you own, onboarded from this checkout, `claude --plugin-dir`), with a small
  `index.html` home page committed, and a person at the canvas (the Design type available in
  claude.ai). Two GitHub logins that aren't yours: `<client>` and `<sys>`; neither need do
  anything. The Brief approval section:

  ```
  - **Approvers:** @<sys>
  - **Trusted:** none

  | Area | Slug | Approved by |
  |---|---|---|
  | Wording and content | content | @<you> |
  | Design and UI | design | @<client> |
  | System behaviour (catch-all) | system | Approvers |
  ```

## Steps

### Part A: the wording, checked

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, delete every line with
   `catch-all` from the copy's `skills/begin/design-step.md`, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. "the catch-all's approvers when
   there's no design area" prints `FAIL`.

```bash
#!/bin/bash
# Unit E: Iris's designs go to the design area's approvers (begin's design-step.md, the design
# skill). PASS/FAIL per check; exit 1 if any FAIL, 2 on SETUP FAIL.
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
DS=skills/begin/design-step.md; DG=skills/design/SKILL.md
for f in "$PLUGIN/$DS" "$PLUGIN/$DG"; do [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }; done
fails=0; passes=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; passes=$((passes+1)); else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { local name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
flat() { tr '\n' ' ' | tr -s ' \t' '  '; }
has()  { grep -qiE -- "$2" "$1"; }
added() { diff "$OLD_PLUGIN/$1" "$PLUGIN/$1" | sed -n 's/^> //p' | flat; }

flat < "$PLUGIN/$DS" > "$W/ds"; added "$DS" > "$W/dsadd"
t "design-step.md: the artifact is shared with the design area's approvers" has "$W/ds" "design area.{0,40}approvers|approvers of the design area|\`design\` area.{0,40}approvers"
t "design-step.md: the catch-all's approvers when there's no design area" has "$W/ds" "catch-all.{0,200}(no|without a) design area|(no|without a) design area.{0,200}catch-all"
t "design-step.md: only when who says an area needs approval" has "$W/dsadd" "who|needs|required"
t "design-step.md: as the developer decides" has "$W/ds" "developer.{0,80}decid|decid.{0,80}developer"
t "design-step.md: still asks the developer to share the artifact" has "$W/ds" "share"
t "design-step.md: the text this step adds names the design area" has "$W/dsadd" "design area|\`design\`|needed: design|approved:design"
flat < "$PLUGIN/$DG" > "$W/dg"; added "$DG" > "$W/dgadd"
t "design skill: back to design partway, re-approval goes to the design area" has "$W/dgadd" "design area|\`design\`|needed: design|approved:design"
t "regression: design skill: Iris still doesn't share or publish beyond the developer" has "$W/dg" "(publish|share).{0,80}beyond the developer"

echo "$passes passed, $fails failed"
[ "$fails" = 0 ] || exit 1
exit 0
```

### Part B: a dry run (a person at the canvas, 40 to 60 minutes)

1. `/ada:begin`, then: "Add an opening-hours panel to the home page, under the heading." Accept
   the design step when Ada offers it, pick a design with Iris, and agree the brief.
2. Partway through the build, say: "The panel needs a 'Closed today' state too." Accept going back
   to design.
3. A project with no design area: in `PROJECT.md`, delete the `design` row; commit and push. Start
   a fresh session and repeat step 1 with "Add a delivery-areas panel to the home page." Stop once
   Ada has said who to share the design with. Close each PR and delete its branch afterwards.

## Expected

Part A: every line `PASS`, last line `<n> passed, 0 failed`, exit 0.

Part B:

1. The brief's `Areas:` includes `design`. The transcript shows `ada-brief who` with `design`
   among the areas; its output has `needs design: @<client>`. Ada asks you to share the design
   artifact with `@<client>`, and doesn't name `@<sys>` (the system approvers) for it. Sharing is
   your decision: neither Ada nor Iris shares it.
2. Iris designs only the new state in the same artifact. Ada records the amendment with
   Re-approval `needed: design` (after your yes); `ada-brief submit` prints
   `(approval reset: design)`; she asks you to share the updated artifact with `@<client>`.
3. With no design row, Ada asks you to share the artifact with the catch-all's approvers,
   `@<sys>`.

If the Design type isn't available (the text-only fallback), there is no artifact to share:
record steps 1 to 3 as Not verified, with the reason.

Sources, all Unit E's brief (task 1220198325269356): Outcome ("Iris's designs go to the design
area's approvers"); Decisions, Design (when `who` says an area needs approval, the design
artifact is shared with the design area's approvers, the catch-all's when there's no design
area, as the developer decides) and Amendments (something the customer sees → the content or
design area); Definition of Done 7; Files expected to change (`design-step.md`: the approver sees
the design; back to design partway, re-approval; `skills/design/SKILL.md`: back to design,
approval).

## Observe at

Part A: the script's output. Part B: the session transcript (the `ada-brief` calls and what Ada
asks you), `.claude/handoff/BRIEF.md` (`Areas:`, Amendments), `.claude/handoff/DESIGN.md`, the
draft PR's labels.

## Regression

Iris still never shares or publishes an artifact beyond the developer (part A). A project with no
areas table: `tests/functional/13-design/design-step-text-checks.md`, item 16, and the Q4 check.
