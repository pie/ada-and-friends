# `ada-plan status` decides each unit's approval from its Areas: and shows the areas a brief waits on

Unit F's check: `ada-plan status` reads the `Areas:` line of each unit's draft brief
(`.claude/handoff/plan/<task>.md`), asks `ada-brief who <areas>` once for each distinct set of
areas, always asks `ada-brief status` for the open PR of a unit with areas, and shows the areas a
waiting or changes-requested brief is waiting on.

- Area: 03 Plans (`ada-plan status`); 01 Brief approval (`ada-brief who <areas>` and `status` as
  `ada-plan` reads them); 12 Platforms (bash 3.2, Python 3.8, a CRLF draft brief)
- Written by: Bob (sections 1 to 5, Build order 4); Tess (sections 6 to 9, from `TEST-PLAN.md`,
  checks 3, 4 to 10, 13, 14, 16, 19)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198400757442
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-23: 116 PASS, 0 failed on the host (bash 5.3), Docker bash 3.2 and Python 3.8; the break against the base prints 29 FAIL, 8a all PASS; On GitHub not run; see the PR

## Precondition

- This checkout (`REPO`) and the base branch's plugin before Unit F, commit `c79ff45` (`BASE`),
  exported by the script, to compare with.
- `git`, `tar`, Python 3.8+. The script makes its own scratch repo, `scratch repo` (a path with a
  space), with a bare `origin`, a plan of nine units, a draft brief for eight of them, and a worktree
  for Unit B. It never touches this checkout.
- `ada-plan` runs `"$here/ada-brief"`, so the script copies the plugin (the changed one and the
  base branch's) into scratch folders and puts a stub `ada-brief` in each `bin/`: `who <areas>` and
  `status <branch>` answer from files and log every call to `who.log` and `status.log`. `gh` is a
  stand-in on the PATH: `auth status` succeeds and `pr list --head <branch>` answers from a file.
  The real `ada-brief who` and `status` are covered by the files under
  `tests/functional/01-brief-approval/` and `01-brief-approval-areas/`.
- Section 8 runs `ada-plan` with the **real** `ada-brief` beside it (the plugin under test and the
  base, unchanged), and a stateful stand-in `gh` in `real <n>/state/`: PRs with an opener, labels
  and comments put in by the script at fixed times (the brief is pushed at 10:00; a label or
  comment at 11:00 is after it). Table T_F as the brief's Definition of Done: `content` approved
  by `@tester` (`<me>`, the login running `ada-plan`), `design` and `System behaviour
  (catch-all)` (`system`) by `@other`; `Approvers: @neil`. Before relying on a PR's state the
  script checks Unit D1's `ada-brief status` gives it, else `SETUP FAIL`; a `gh` call the stand-in
  can't answer (or any write) ends the run with `SETUP FAIL` and the call.
- `HOLD` lines would be checks waiting on Ada (`TEST-PLAN.md`, Escalate); there are none at
  present (the helper stays for the next open question). A `HOLD` prints what `ada-plan` gave and
  counts neither way.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, and under bash 3.2 and Python 3.8 as in "Platforms".
3. Break it on purpose, once: run it with `PLUGIN` set to the base branch's plugin
   (`git -C <this checkout> archive c79ff45 plugins/ada | tar -x -m -C <scratch>`,
   then `PLUGIN=<scratch>/plugins/ada`). It must print `FAIL` for every check of the areas, the
   usage header's in section 6, and section 7's and 8b's area texts; section 8a's lines `PASS`.
   Section 9's stderr line and its failing-`who` briefs `FAIL` too (the base doesn't read `Areas:`
   at all, so this shows the script can fail, not that the stderr line alone is checked).
4. On GitHub, below.

```bash
#!/bin/bash
# ada-plan status decides each unit's approval from its Areas:. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada), OLD_PLUGIN (the base branch's plugin; default: exported from BASE), BASE (default c79ff45), SH (how to run the
# scripts under test; default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-c79ff45}   # the base branch before Unit F (Tess: a fixed commit, so the compare still means something once F is merged)
SH=${SH:-bash}
W=$(mktemp -d)
H=.claude/handoff
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
G()   { git -c user.name=t -c user.email=t@example.invalid "$@"; }

# The plugin under test and the base branch's, each in its own folder with the stub ada-brief
# beside ada-plan (ada-plan runs "$here/ada-brief").
mkdir -p "$W/new" "$W/old"
cp -R "${PLUGIN:-$REPO/plugins/ada}" "$W/new/ada"
if [ -n "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old/plugins"; cp -R "$OLD_PLUGIN" "$W/old/plugins/ada"
else
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -m -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
fi
NEW=$W/new/ada; OLD=$W/old/plugins/ada

# The stub ada-brief. who <areas>: the answer is in who/<areas joined by "_", "none" for no areas>,
# "required me" when there is no file. status <branch>: the answer is in status/<branch, "/" as "_">,
# "not-submitted" when there is none. Each call is logged in who.log and status.log.
for p in "$NEW" "$OLD"; do
cat > "$p/bin/ada-brief" <<EOF
#!/bin/sh
case "\$1" in
  who)
    shift
    echo "who \$*" >> "$W/who.log"
    f="$W/who/\$(echo "\$*" | tr ' ' '_')"; [ -n "\$*" ] || f="$W/who/none"
    if [ -f "\$f" ]; then cat "\$f"; else echo "required me"; fi;;
  status)
    echo "status \$2" >> "$W/status.log"
    f="$W/status/\$(echo "\$2" | tr '/' '_')"
    if [ -f "\$f" ]; then cat "\$f"; else echo not-submitted; fi;;
  *) echo "stub ada-brief: not handled: \$*" >&2; exit 1;;
esac
EOF
chmod +x "$p/bin/ada-brief"
done
mkdir -p "$W/who" "$W/status" "$W/pr" "$W/gh"
# The stand-in gh: pr list --head <branch> answers from pr/<branch, "/" as "_">, else [].
cat > "$W/gh/gh" <<EOF
#!/bin/sh
case " \$* " in
  *" auth "*) exit 0;;
  *" pr list "*) while [ \$# -gt 0 ]; do [ "\$1" = --head ] && b=\$2; shift; done
                  f="$W/pr/\$(echo "\$b" | tr '/' '_')"; if [ -f "\$f" ]; then cat "\$f"; else echo '[]'; fi; exit 0;;
esac
echo "stand-in gh: not handled: \$*" >&2; exit 1
EOF
chmod +x "$W/gh/gh"

# Stub answers. Approver of content, not of design (who: "needs design: @other").
echo "approver me" > "$W/who/content"
printf 'required me\nneeds design: @other\n' > "$W/who/design"
echo "required me" > "$W/who/content_design"
pr() { printf '[{"number":%s,"state":"OPEN","isDraft":true,"mergeCommit":null,"url":"https://example.invalid/pull/%s"}]\n' "$1" "$1" > "$W/pr/$2"; }
pr 3 add_unit-c; pr 4 add_unit-d; pr 5 add_unit-e; pr 8 add_unit-h; pr 9 add_unit-i
printf 'waiting\narea content: approved @me 2026-11-20T10:00:00Z\narea design: waiting (@other)\nhttps://example.invalid/pull/3\n' > "$W/status/add_unit-c"
printf 'changes-requested\narea content: approved @me 2026-11-20T10:00:00Z\narea design: changes-requested\nhttps://example.invalid/pull/4\n' > "$W/status/add_unit-d"
printf 'approved @me 2026-11-20T10:00:00Z\narea content: approved @me 2026-11-20T10:00:00Z\nhttps://example.invalid/pull/5\n' > "$W/status/add_unit-e"
printf 'not-needed\narea content: not needed (@me approves it)\nhttps://example.invalid/pull/8\n' > "$W/status/add_unit-h"
printf 'waiting\nhttps://example.invalid/pull/9\n' > "$W/status/add_unit-i"

# Setup: a scratch repo on a bare origin, a plan of nine units in the main checkout, and a worktree.
O="$W/origin.git"; M="$W/scratch repo"; U="$M/.claude/worktrees/unit-b"
git init -q --bare "$O"
git init -q "$M" && git -C "$M" symbolic-ref HEAD refs/heads/main
mkdir -p "$M/.claude/team"
cat > "$M/.claude/team/PROJECT.md" <<'EOF'
# Scratch

## Code and branches

- **Branch from:** `main`. **PR into:** `next-release`. **Test on:** a scratch repo.
EOF
printf '.claude/handoff/\n.claude/worktrees/\n' > "$M/.gitignore"
G -C "$M" add -A && G -C "$M" commit -q -m init
G -C "$M" remote add origin "$O" && G -C "$M" push -q origin main && G -C "$M" fetch -q origin
mkdir -p "$M/$H/plan"
cat > "$M/$H/plan/PLAN.md" <<'EOF'
# Plan: Areas

Epic: https://app.asana.com/0/1/1219100000000000
Status: agreed

## Units

| ID | Unit | Task | Branch | Parent | Ships |
|---|---|---|---|---|---|
| A | Content only, no PR | 1219100000000001 | add/unit-a | | independent |
| B | Design only, no PR | 1219100000000002 | add/unit-b | | independent |
| C | Content and design, waiting | 1219100000000003 | add/unit-c | | independent |
| D | Same areas, other order, changes | 1219100000000004 | add/unit-d | | independent |
| E | Content, approved feature | 1219100000000005 | add/unit-e | | independent |
| F | A part of Unit E | 1219100000000006 | add/unit-f | E | independent |
| G | Areas line is the template prompt | 1219100000000007 | add/unit-g | | independent |
| H | Content, status says not-needed | 1219100000000008 | add/unit-h | | independent |
| I | No draft brief, open PR | 1219100000000009 | add/unit-i | | independent |

## Amendments

| Date | What changed | Why | Agreed by |
|---|---|---|---|
| | | | |
EOF
draft() { # <task> <Areas line, or empty for none>
  { echo "# Brief"; echo; echo "Asana: <task URL> (task $1)"; [ -z "$2" ] || echo "$2"; echo "Status: draft"; echo
    echo "<!-- Areas: not-a-slug -->"; echo "## Outcome"; echo; echo "Areas: design"; } > "$M/$H/plan/$1.md"; }
draft 1219100000000001 "Areas: content"
draft 1219100000000002 "Areas: design"
draft 1219100000000003 "Areas: content, design"
draft 1219100000000004 "Areas: Design, content, design"
draft 1219100000000005 "Areas: content"
draft 1219100000000006 ""
draft 1219100000000007 "Areas: <slug>, ..."
draft 1219100000000008 "Areas: content"
# Unit I has no draft brief at all.
G -C "$M" worktree add -q -b add/unit-b "$U" main
run() { d=$1; p=$2; shift 2; (cd "$d" && PATH="$W/gh:$PATH" $SH "$p/bin/ada-plan" "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc() { cat "$W/rc"; }
line() { grep -E "^$1[[:space:]]" "$2"; }
nxt()  { grep -A1 -E "^$1[[:space:]]" "$2" | tail -n 1; }
brief_of() { line "$1" "$2" | sed -e 's/.*brief: //'; }
next_of()  { nxt "$1" "$2" | sed -e 's/^ *next: //'; }
is()  { name=$1; got=$2; want=$3; if [ "$got" = "$want" ]; then ok 0 "$name"; else ok 1 "$name"; echo "info:   want: $want"; echo "info:   got:  $got"; fi; }

# 1. A plan whose units have no Areas: the same output as the base branch's script. The briefs
#    have no Areas: either, so ada-brief status prints no area lines (first and last line only).
mkdir -p "$W/drafts" && mv "$M/$H/plan"/1219*.md "$W/drafts/"
mv "$W/status" "$W/status.areas"; mkdir "$W/status"
for f in "$W/status.areas"/*; do sed -n -e '1p' -e '$p' "$f" > "$W/status/$(basename "$f")"; done
: > "$W/who.log"; : > "$W/status.log"
run "$M" "$OLD" status; cp "$W/out" "$W/status.old"; old_rc=$(rc)
: > "$W/who.log"; : > "$W/status.log"
run "$M" "$NEW" status; cp "$W/out" "$W/status.nodrafts"
echo "info: status with no drafts: exit $(rc), base exit $old_rc"; sed 's/^/info:   /' "$W/out" "$W/err"
t "no drafts: exits 0" test "$(rc)" = 0
t "no drafts: output as the base branch's script prints it" cmp -s "$W/status.old" "$W/status.nodrafts"
t "no drafts: ada-brief who ran once, with no areas" test "$(cat "$W/who.log")" = "who "
# ...and when who says the developer needs no approval, status is not asked for a unit without areas.
echo "not-needed me" > "$W/who/none"
: > "$W/status.log"; run "$M" "$OLD" status; cp "$W/out" "$W/status.old2"; cp "$W/status.log" "$W/status.log.old"
: > "$W/status.log"; run "$M" "$NEW" status
t "no drafts, who not-needed: output as the base branch's" cmp -s "$W/status.old2" "$W/out"
t "no drafts, who not-needed: no ada-brief status call, as the base branch" cmp -s "$W/status.log.old" "$W/status.log"
t "no drafts, who not-needed: the open PR's brief is not-needed" test "$(brief_of I "$W/out")" = "not-needed"
rm "$W/who/none"
rm -r "$W/status"; mv "$W/status.areas" "$W/status"
mv "$W/drafts"/*.md "$M/$H/plan/"

# 2. Units with Areas:.
: > "$W/who.log"; : > "$W/status.log"
run "$M" "$NEW" status; cp "$W/out" "$W/status.main"
echo "info: status, from the main checkout: exit $(rc)"; sed 's/^/info:   /' "$W/out" "$W/err"
S=$W/status.main
t "exits 0" test "$(rc)" = 0
t "three lines per unit" test "$(wc -l < "$S" | tr -d ' ')" = 27
is "Unit A (content, I approve it, no PR): brief not-needed" "$(brief_of A "$S")" "not-needed"
is "Unit B (design, no PR): brief not-submitted" "$(brief_of B "$S")" "not-submitted"
is "Unit B: next brief to submit" "$(next_of B "$S")" "brief to submit"
is "Unit C (content, design; submitted): brief waiting (design)" "$(brief_of C "$S")" "waiting (design)"
is "Unit C: next waiting for the brief to be approved (design)" "$(next_of C "$S")" "waiting for the brief to be approved (design)"
is "Unit D (changes requested): brief changes-requested (design)" "$(brief_of D "$S")" "changes-requested (design)"
is "Unit D: next is the changes-requested action, unchanged" "$(next_of D "$S")" "changes requested: go through them with the developer, then submit the brief again"
is "Unit E (every area approved): brief approved @me <time>" "$(brief_of E "$S")" "approved @me 2026-11-20T10:00:00Z"
is "Unit F (part of the approved Unit E): next brief to submit, not waiting for Unit E" "$(next_of F "$S")" "brief to submit"
is "Unit G (Areas: is the template prompt): as no areas, brief not-submitted" "$(brief_of G "$S")" "not-submitted"
is "Unit H (areas, who says not-needed, open PR): status says not-needed" "$(brief_of H "$S")" "not-needed"
t "Unit H: ada-brief status was asked for it" grep -qx 'status add/unit-h' "$W/status.log"
is "Unit I (no draft, open PR): brief waiting, no areas shown" "$(brief_of I "$S")" "waiting"
is "Unit I: next has no areas" "$(next_of I "$S")" "waiting for the brief to be approved"
t "Unit A (no PR): ada-brief status was not asked" eval '! grep -qx "status add/unit-a" "$W/status.log"'

# 3. ada-brief who runs once for each distinct set of areas.
echo "info: who calls:"; sed 's/^/info:   /' "$W/who.log"
t "who: four calls in all (content; design; content design; no areas)" test "$(wc -l < "$W/who.log" | tr -d ' ')" = 4
t "who: Units C and D (the same set in two orders) share one call, areas sorted" test "$(grep -cx 'who content design' "$W/who.log")" = 1
t "who: Units A, E, H (content) share one call" test "$(grep -cx 'who content' "$W/who.log")" = 1
t "who: Units B (design) one call" test "$(grep -cx 'who design' "$W/who.log")" = 1
t "who: Units F, G, I (no areas) share one call with none" test "$(grep -cx 'who ' "$W/who.log")" = 1

# 4. From a unit's worktree: the same output as from the main checkout.
run "$U" "$NEW" status
t "from the unit's worktree: exits 0" test "$(rc)" = 0
t "from the unit's worktree: same output as from the main checkout" cmp -s "$W/status.main" "$W/out"
t "from the unit's worktree: still no plan/" test ! -e "$U/$H/plan"

# 5. Platforms: the script parses under bash and Python 3.8's syntax (checked by running above).
t "bash -n ada-plan" bash -n "$REPO/plugins/ada/bin/ada-plan"

# ---- Sections 6 to 9 are Tess's (TEST-PLAN.md, checks 3, 4 to 10, 13, 14, 16, 19). ----
holds=0
HOLD() { echo "HOLD $1"; echo "info:   got: '$2'"; holds=$((holds+1)); }
blk3() { grep -A2 -E "^$1[[:space:]]" "$2"; }
crlf() { awk '{ printf "%s\r\n", $0 }' "$1" > "$1.x" && mv "$1.x" "$1"; }
ends() { case "$2" in *"$3") ok 0 "$1" ;; *) ok 1 "$1"; echo "info:   want the end: $3"; echo "info:   got:  $2" ;; esac; }

# 6. The usage header describes the areas shown (DoD 8); the CHANGELOG bullet (DoD 10).
awk 'NR == 1 { next } !/^#/ { exit } { print }' "$NEW/bin/ada-plan" > "$W/header"
t "usage header: names the Areas: line" grep -qF 'Areas:' "$W/header"
t "usage header: shows the areas after waiting or changes-requested" grep -Eq '(waiting|changes-requested) \(' "$W/header"
CL=${CHANGELOG:-$REPO/CHANGELOG.md}
awk '/^## Unreleased/ { u = 1; next } u && /^## / { exit } u' "$CL" > "$W/unrel" 2>/dev/null
awk '/\*\*Brief approval by area\.\*\*/ { b = 1 } b && /^$/ { exit } b && /^- / && !/Brief approval by area/ { exit } b' "$W/unrel" > "$W/bullet"
t "CHANGELOG: **Brief approval by area.** under Unreleased" test -s "$W/bullet"
t "CHANGELOG: the bullet mentions ada-plan status" grep -qF 'ada-plan status' "$W/bullet"

# 7. What brief: and next: show, from ada-brief status's lines (Decisions, "Brief state shown"),
#    with the stub ada-brief. X1 has Areas:, X2 none; Y1 and Y2 are their parts, so a part of an
#    area parent must show what a part of a plain parent shows (the decision word is line 1 alone).
M="$W/texts repo"
git init -q --bare "$W/texts.git"
git init -q "$M" && git -C "$M" symbolic-ref HEAD refs/heads/main
mkdir -p "$M/.claude/team" "$M/$H/plan"
cp "$W/scratch repo/.claude/team/PROJECT.md" "$M/.claude/team/PROJECT.md"
printf '.claude/handoff/\n.claude/worktrees/\n' > "$M/.gitignore"
G -C "$M" add -A && G -C "$M" commit -q -m init
G -C "$M" remote add origin "$W/texts.git" && G -C "$M" push -q origin main && G -C "$M" fetch -q origin
cat > "$M/$H/plan/PLAN.md" <<'EOF'
# Plan: Texts

Epic: https://app.asana.com/0/1/1219300000000000
Status: agreed

## Units

| ID | Unit | Task | Branch | Parent | Ships |
|---|---|---|---|---|---|
| X1 | Areas, open PR | 1219300000000001 | add/x1 | | independent |
| X2 | No areas, open PR | 1219300000000002 | add/x2 | | independent |
| Y1 | A part of X1 | 1219300000000003 | add/y1 | X1 | independent |
| Y2 | A part of X2 | 1219300000000004 | add/y2 | X2 | independent |

## Amendments

| Date | What changed | Why | Agreed by |
|---|---|---|---|
| | | | |
EOF
draft 1219300000000001 "Areas: content, design"
draft 1219300000000002 ""
pr 11 add_x1; pr 12 add_x2
U1=https://example.invalid/pull/11; U2=https://example.invalid/pull/12
# txt <name> <X1's status lines> <X2's status lines>: run ada-plan status with those answers.
txt() { TN=$1; printf '%s\n' "$2" > "$W/status/add_x1"; printf '%s\n' "$3" > "$W/status/add_x2"
  run "$M" "$NEW" status; cp "$W/out" "$W/t.out"
  [ "$(rc)" = 0 ] || { ok 1 "$TN: ada-plan status exits 0"; sed 's/^/info:   /' "$W/out" "$W/err"; }; }
yn() { blk3 "$1" "$W/t.out" | sed -e 's/X[12]/X#/g' -e 's/x[12]/x#/g' -e 's/Y[12]/Y#/g' -e 's/y[12]/y#/g' \
         -e 's/#[0-9][0-9]*/#N/g' -e 's/12193000000000[0-9][0-9]/TASK/g'; }
# nn <id>: its next: with the unit IDs made alike (an approved parent's next names its parts).
nn() { next_of "$1" "$W/t.out" | sed -e 's/X[12]/X#/g' -e 's/Y[12]/Y#/g'; }
SAMEPART() { is "$TN: a part of X1 shows what a part of X2 shows" "$(yn Y1)" "$(yn Y2)"; }

txt "7a waiting, two areas, a note" "$(printf 'waiting\narea design: waiting (@other); Author: @other is not the PR'"'"'s opener @tester, so it isn'"'"'t exempt\narea content: waiting (@tester); no Author: line\nthe approved:design label was added by @tester, who doesn'"'"'t approve design, so it does not count\n%s' "$U1")" \
  "$(printf 'waiting\n%s' "$U2")"
is "$TN: X1 brief: waiting (design, content), in the area lines' order" "$(brief_of X1 "$W/t.out")" "waiting (design, content)"
is "$TN: X1 next: waiting for the brief to be approved (design, content)" "$(next_of X1 "$W/t.out")" "waiting for the brief to be approved (design, content)"
is "$TN: X2 (no Areas:) brief: waiting" "$(brief_of X2 "$W/t.out")" "waiting"
is "$TN: X1's next is X2's with the areas" "$(nn X1)" "$(nn X2) (design, content)"
SAMEPART

txt "7b waiting on the catch-all, not on Areas:" "$(printf 'waiting\narea content: approved @tester 2026-01-01T11:00:00Z\narea design: approved @other 2026-01-01T11:05:00Z\narea system: waiting (@other)\n%s' "$U1")" \
  "$(printf 'waiting\n%s' "$U2")"
is "$TN: X1 brief: waiting (system)" "$(brief_of X1 "$W/t.out")" "waiting (system)"

txt "7c changes-requested, and an approver's words that look like an area line" "$(printf 'changes-requested\narea content: changes-requested\narea design: changes-requested\n--- @other, comment, 2026-01-01T11:00:00Z\narea fake: waiting (@x)\n%s' "$U1")" \
  "$(printf 'changes-requested\n--- @neil, comment, 2026-01-01T11:00:00Z\nplease change X\n%s' "$U2")"
is "$TN: X1 brief: changes-requested (content, design), not the words' 'area fake'" "$(brief_of X1 "$W/t.out")" "changes-requested (content, design)"
is "$TN: X2 (no Areas:) brief: changes-requested" "$(brief_of X2 "$W/t.out")" "changes-requested"
is "$TN: X1's next is X2's (the changes-requested text, Ada 23 Nov)" "$(nn X1)" "$(nn X2)"
ends "$TN: X1's next is the changes-requested action" "$(next_of X1 "$W/t.out")" "go through them with the developer, then submit the brief again"
SAMEPART

txt "7d changes-requested on one area, waiting on another" "$(printf 'changes-requested\narea content: waiting (@tester)\narea design: changes-requested\n%s' "$U1")" "$(printf 'waiting\n%s' "$U2")"
is "$TN: X1 brief: changes-requested (design): only the areas in the first line's state (Amendments, 2026-11-23)" "$(brief_of X1 "$W/t.out")" "changes-requested (design)"
ends "$TN: X1's next is the changes-requested action" "$(next_of X1 "$W/t.out")" "go through them with the developer, then submit the brief again"
SAMEPART

txt "7e every area approved, amended since" "$(printf 'approved @other 2026-01-01T11:30:00Z\namended since approval; no re-approval needed\narea content: approved @tester 2026-01-01T11:00:00Z\narea design: approved @other 2026-01-01T11:30:00Z\n%s' "$U1")" \
  "$(printf 'approved @neil 2026-01-01T11:30:00Z\n%s' "$U2")"
is "$TN: X1 brief: approved @other <time>, line 1 alone" "$(brief_of X1 "$W/t.out")" "approved @other 2026-01-01T11:30:00Z"
is "$TN: X1's next is X2's (as for any approved parent)" "$(nn X1)" "$(nn X2)"
SAMEPART

txt "7f not-needed with area lines" "$(printf 'not-needed\narea content: not needed (@tester approves it)\narea design: not needed (@tester approves it)\n%s' "$U1")" "$(printf 'waiting\n%s' "$U2")"
is "$TN: X1 brief: not-needed" "$(brief_of X1 "$W/t.out")" "not-needed"

# 8. The brief's scenario with the real ada-brief (Unit D1's who and status) and a stateful
#    stand-in gh: PRs with an opener, labels and comments at fixed times. Table T_F: content
#    approved by @tester (<me>, who runs ada-plan), design and system (catch-all) by @other;
#    Approvers @neil. A call the stand-in can't answer ends the run with SETUP FAIL.
RNEW=${PLUGIN:-$REPO/plugins/ada}
if [ -n "${OLD_PLUGIN:-}" ]; then RBASE=$OLD_PLUGIN; else
  mkdir -p "$W/rbase"; git -C "$REPO" archive "$BASE" plugins/ada | tar -x -m -C "$W/rbase" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  RBASE=$W/rbase/plugins/ada; fi
mkdir -p "$W/sgh"
cat > "$W/sgh/gh" <<'STUB'
#!/usr/bin/env python3
import json, os, re, subprocess, sys
from urllib.parse import unquote
D = os.environ['GH_STATE']
st = json.load(open(os.path.join(D, 'state.json')))
a = sys.argv[1:]
USER = os.environ.get('GH_USER', 'tester')
with open(os.path.join(D, 'log'), 'a') as f:
    f.write('gh ' + ' '.join(a) + '\n')
def unsupported(why):
    with open(os.path.join(D, 'unsupported'), 'a') as f:
        f.write(why + '   (gh ' + ' '.join(a) + ')\n')
    sys.stderr.write('stand-in gh: not handled: ' + why + '\n'); sys.exit(1)
def parse(args, valued):
    pos, o, i = [], {}, 0
    while i < len(args):
        x = args[i]
        if x in valued:
            o.setdefault(x, []).append(args[i + 1] if i + 1 < len(args) else ''); i += 2; continue
        if x.startswith('--') and '=' in x and x.split('=', 1)[0] in valued:
            k, v = x.split('=', 1); o.setdefault(k, []).append(v)
        elif x.startswith('-') and len(x) > 1:
            o.setdefault(x, []).append(True)
        else:
            pos.append(x)
        i += 1
    return pos, o
def one(o, *names):
    for n in names:
        if n in o:
            return o[n][-1]
    return None
def jq(obj, expr):
    toks = re.findall(r'\.([A-Za-z_][A-Za-z0-9_]*)|\[(\d*)\]', expr)
    if expr != '.' and ''.join('.' + n if n else '[' + i + ']' for n, i in toks) != expr.replace('.[', '['):
        unsupported('--jq ' + expr)
    vals = [obj]
    for n, i in toks:
        out = []
        for v in vals:
            if n:
                out.append(v.get(n) if isinstance(v, dict) else None)
            elif i == '':
                out.extend(v if isinstance(v, list) else [])
            else:
                out.append(v[int(i)] if isinstance(v, list) and int(i) < len(v) else None)
        vals = out
    return vals
def emit(obj, o):
    q = one(o, '-q', '--jq')
    if q is not None:
        for v in jq(obj, q):
            print(v if isinstance(v, str) else ('' if v is None else json.dumps(v)))
    else:
        print(json.dumps(obj))
    sys.exit(0)
def pick(obj, o):
    spec = one(o, '--json')
    if spec is None or spec is True:
        return obj
    out = {}
    for f in [x.strip() for x in spec.split(',') if x.strip()]:
        if f not in obj:
            unsupported('--json field ' + f)
        out[f] = obj[f]
    return out
def branch():
    r = subprocess.run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], stdout=subprocess.PIPE,
                       stderr=subprocess.DEVNULL, universal_newlines=True)
    return r.stdout.strip()
def find(sel):
    sel = branch() if sel is None else sel
    m = re.search(r'(?:^|/pull/)(\d+)$', sel)
    for pr in st['prs']:
        if (m and pr['number'] == int(m.group(1))) or pr['head'] == sel:
            return pr
    return None
def url(pr):
    return 'https://github.com/o/r/pull/%d' % pr['number']
def view(pr):
    return {'number': pr['number'], 'url': url(pr), 'title': pr['title'], 'body': pr['body'],
            'labels': [{'name': l} for l in pr['labels']], 'author': {'login': pr['opener']},
            'headRefName': pr['head'], 'baseRefName': pr['base'], 'state': 'OPEN',
            'isDraft': True, 'mergeCommit': None, 'mergedAt': None, 'closedAt': None,
            'createdAt': pr['created'], 'updatedAt': pr['created']}
def rest(pr):
    return {'number': pr['number'], 'html_url': url(pr), 'title': pr['title'], 'body': pr['body'],
            'labels': [{'name': l} for l in pr['labels']], 'user': {'login': pr['opener']},
            'head': {'ref': pr['head']}, 'base': {'ref': pr['base']}, 'state': 'open', 'draft': True}
cmd = a[:2]
if cmd == ['auth', 'status']:
    sys.exit(0)
if cmd == ['repo', 'view']:
    pos, o = parse(a[2:], {'--json', '-q', '--jq'})
    if one(o, '-q', '--jq') is None and one(o, '--json') is None:
        print('o/r'); sys.exit(0)
    emit(pick({'nameWithOwner': 'o/r', 'name': 'r', 'owner': {'login': 'o'},
               'url': 'https://github.com/o/r', 'defaultBranchRef': {'name': 'main'}}, o), o)
if cmd == ['pr', 'view']:
    pos, o = parse(a[2:], {'--json', '-q', '--jq', '--repo', '-R', '-t', '--template'})
    pr = find(pos[0] if pos else None)
    if pr is None:
        sys.stderr.write('no pull requests found for branch "%s"\n' % (pos[0] if pos else branch())); sys.exit(1)
    if one(o, '--json') is None:
        unsupported('pr view without --json')
    emit(pick(view(pr), o), o)
if cmd == ['pr', 'list']:
    pos, o = parse(a[2:], {'--head', '-H', '--base', '-B', '--state', '-s', '--limit', '-L', '--json',
                           '-q', '--jq', '--search', '-S', '--label', '-l', '--author', '-A', '--repo', '-R'})
    head, base = one(o, '--head', '-H'), one(o, '--base', '-B')
    prs = [p for p in st['prs'] if (head is None or p['head'] == head) and (base is None or p['base'] == base)]
    if (one(o, '--state', '-s') or 'open') in ('closed', 'merged'):
        prs = []
    if one(o, '--json') is None:
        unsupported('pr list without --json')
    emit([pick(view(p), o) for p in prs], o)
if a[:1] == ['api']:
    pos, o = parse(a[1:], {'-X', '--method', '-F', '-f', '--field', '--raw-field', '-H', '--header',
                           '-q', '--jq', '--input', '-t', '--template', '--hostname', '--cache',
                           '-p', '--preview'})
    if not pos:
        unsupported('api with no path')
    path = pos[0].lstrip('/').replace('{owner}/{repo}', 'o/r').replace(':owner/:repo', 'o/r').split('?')[0]
    method = (one(o, '-X', '--method') or 'GET').upper()
    if method != 'GET' or any(k in o for k in ('-F', '-f', '--field', '--raw-field', '--input')):
        unsupported('a write: ' + method + ' ' + path)
    if path == 'user':
        emit({'login': USER}, o)
    if path == 'repos/o/r':
        emit({'full_name': 'o/r', 'name': 'r', 'owner': {'login': 'o'}, 'default_branch': 'main'}, o)
    m = re.match(r'repos/o/r/(pulls|issues)/(\d+)(/[^/]+)?$', path)
    if not m:
        unsupported('api GET ' + path)
    kind, num, sub = m.group(1), int(m.group(2)), m.group(3) or ''
    pr = ([p for p in st['prs'] if p['number'] == num] or [None])[0]
    if pr is None:
        sys.stderr.write('HTTP 404: Not Found\n'); sys.exit(1)
    if sub == '':
        emit(rest(pr), o)
    if sub == '/events' and kind == 'issues':
        emit(pr['events'], o)
    if sub == '/labels' and kind == 'issues':
        emit([{'name': l} for l in pr['labels']], o)
    if sub == '/comments':
        emit(pr['comments'] if kind == 'issues' else pr['review_comments'], o)
    if sub == '/reviews' and kind == 'pulls':
        emit(pr['reviews'], o)
    unsupported('api GET ' + path)
unsupported('command')
STUB
chmod +x "$W/sgh/gh"

# ghs <op> ...: change the stand-in's state as GitHub would after a person's action.
ghs() { python3 - "$GH_STATE/state.json" "$@" <<'PY' || { echo "SETUP FAIL: $CASE: stand-in state ($*)"; exit 2; }
import json, sys
p, op = sys.argv[1:3]
x = sys.argv[3:]
st = json.load(open(p))
if op == 'newpr':
    head, task, who, when = x
    st['prs'].append({'number': len(st['prs']) + 1, 'head': head, 'base': 'main', 'title': 'Brief ' + task,
                      'body': '<!-- ada-brief: .claude/briefs/%s.md -->\n\nThe brief.\n' % task,
                      'opener': who, 'created': when, 'labels': [], 'events': [], 'comments': [],
                      'reviews': [], 'review_comments': []})
else:
    pr = [q for q in st['prs'] if q['head'] == x[0]][0]
    if op == 'label':
        name, who, when = x[1:]
        pr['labels'].append(name)
        pr['events'].append({'event': 'labeled', 'label': {'name': name}, 'actor': {'login': who}, 'created_at': when})
    elif op == 'comment':
        who, when, body = x[1:]
        pr['comments'].append({'user': {'login': who}, 'created_at': when, 'updated_at': when, 'body': body})
    else:
        sys.exit(1)
json.dump(st, open(p, 'w'))
PY
}
at() { GIT_COMMITTER_DATE=$1; GIT_AUTHOR_DATE=$1; export GIT_COMMITTER_DATE GIT_AUTHOR_DATE; }
T10=2026-01-01T10:00:00Z
T_F=$(printf '| Content | content | @tester |\n| Design | design | @other |\n| System behaviour (catch-all) | system | @other |')
rn=0
# rcase <name> [<table rows>]: a fresh scratch repo $M (a space in its path) on a bare origin, its own
# stand-in state, PROJECT.md (Approvers @neil, Trusted none, the table if given) on main.
rcase() { CASE=$1; rn=$((rn+1)); CW="$W/real $rn"; M="$CW/main repo"
  mkdir -p "$CW/state" "$M/.claude/team" "$M/$H/plan"; GH_STATE=$CW/state; export GH_STATE
  echo '{"prs": []}' > "$GH_STATE/state.json"
  { printf '# Scratch\n\n## Code and branches\n\n- **Branch from:** `main`. **PR into:** `main`.\n\n'
    printf '## Brief approval\n\n- **Approvers:** @neil\n- **Trusted:** none\n'
    if [ -n "${2:-}" ]; then printf '\n| Area | Slug | Approved by |\n|---|---|---|\n%s\n' "$2"; fi
    printf '\nApprovers never need approval.\n'; } > "$M/.claude/team/PROJECT.md"
  printf '.claude/handoff/\n.claude/worktrees/\n' > "$M/.gitignore"
  at 2026-01-01T09:00:00Z
  { git init -q --bare "$CW/origin.git" && git init -q "$M" && git -C "$M" symbolic-ref HEAD refs/heads/main \
    && G -C "$M" add -A && G -C "$M" commit -q -m init && G -C "$M" remote add origin "$CW/origin.git" \
    && G -C "$M" push -q origin main && G -C "$M" fetch -q origin; } || { echo "SETUP FAIL: $CASE: scratch repo"; exit 2; }
  echo "info: --- $CASE"; }
# rdraft <task> <branch> [<header line>...]: the unit's draft brief in the main checkout's plan/.
rdraft() { t_=$1; b_=$2; shift 2
  { printf '# Brief — T: Unit %s\n\nAsana: https://app.asana.com/0/1/%s\nBranch: %s\n' "$t_" "$t_" "$b_"
    for h_ in "$@"; do printf '%s\n' "$h_"; done
    printf 'Status: agreed\n\n## Outcome\n\nIt works.\n\n## Decisions\n\n- Decision one.\n\n'
    printf '## Constraints\n\n- Platforms: any.\n- Out of scope: other things.\n\n'
    printf '## Definition of done\n\n- [ ] It works.\n\n## Amendments\n\n'
    printf '| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |\n|---|---|---|---|---|\n| | | | | |\n'
  } > "$M/$H/plan/$t_.md"; }
# rplan <row>...: PLAN.md with those Units rows; u <id> <task> <branch> [<parent>] makes a row.
rplan() { { printf '# Plan: Widgets\n\nEpic: https://app.asana.com/0/1/1219200000000000\nStatus: agreed\n\n## Units\n\n'
  printf '| ID | Unit | Task | Branch | Parent | Ships |\n|---|---|---|---|---|---|\n'
  for r_ in "$@"; do printf '%s\n' "$r_"; done
  printf '\n## Amendments\n\n| Date | What changed | Why | Agreed by |\n|---|---|---|---|\n| | | | |\n'; } > "$M/$H/plan/PLAN.md"; }
u() { printf '| %s | Unit %s | %s | %s | %s | independent |' "$1" "$1" "$2" "$3" "${4:-}"; }
# mkpr <branch> <task> <opener>: push the draft as .claude/briefs/<task>.md at 10:00 and open its draft PR.
mkpr() { at $T10
  { G -C "$M" checkout -q -b "$1" main && mkdir -p "$M/.claude/briefs" && cp "$M/$H/plan/$2.md" "$M/.claude/briefs/$2.md" \
    && G -C "$M" add .claude/briefs && G -C "$M" commit -q -m "brief $2" && G -C "$M" push -q origin "$1" \
    && G -C "$M" checkout -q main; } || { echo "SETUP FAIL: $CASE: push $1"; exit 2; }
  ghs newpr "$1" "$2" "$3" $T10; }
RUNNER=tester
rrun() { (cd "$1" && PATH="$W/sgh:$PATH" GH_USER=$RUNNER exec $SH "$2/bin/ada-plan" status) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
# need <branch> <first line>: Unit D1's ada-brief status says that, or the checks below can't stand.
need() { g_=$( (cd "$M" && PATH="$W/sgh:$PATH" GH_USER=tester exec $SH "$RNEW/bin/ada-brief" status "$1") 2>/dev/null | sed -n 1p)
  [ "$g_" = "$2" ] || { echo "SETUP FAIL: $CASE: ada-brief status $1 gives '$g_', not '$2' (Unit D1's behaviour)"; exit 2; }; }
R1=1219200000000001; R2=1219200000000002; R3=1219200000000003; R4=1219200000000004
R5=1219200000000005; R6=1219200000000006; R7=1219200000000007; R8=1219200000000008

# 8a. DoD 1: drafts with no Areas: line, Areas: empty, the template's prompt, no draft at all; with
#     and without an areas table; as tester (who: required) and neil (who: approver).
for tbl in none table; do
  if [ $tbl = table ]; then rcase "8a no Areas:, with an areas table" "$T_F"; else rcase "8a no Areas:, no areas table"; fi
  rdraft $R2 add/b
  rdraft $R3 add/c 'Areas: <slugs from ada-brief areas>'
  rdraft $R4 add/d 'Areas:'
  rdraft $R5 add/e 'Author: @tester'
  rplan "$(u A $R1 add/a)" "$(u B $R2 add/b)" "$(u C $R3 add/c)" "$(u D $R4 add/d C)" "$(u E $R5 add/e)"
  mkpr add/b $R2 tester; mkpr add/c $R3 tester; mkpr add/e $R5 tester
  ghs label add/c brief-approved neil 2026-01-01T11:00:00Z
  ghs comment add/e neil 2026-01-01T11:00:00Z 'please change X'
  need add/b waiting; need add/c 'approved @neil 2026-01-01T11:00:00Z'; need add/e changes-requested
  for RUNNER in tester neil; do
    rrun "$M" "$RBASE"; cp "$W/out" "$W/r.old"; cp "$W/err" "$W/r.olderr"; old_rc=$(rc)
    rrun "$M" "$RNEW"
    t "$CASE, as $RUNNER: exits 0" test "$(rc)" = 0
    if cmp -s "$W/out" "$W/r.old" && cmp -s "$W/err" "$W/r.olderr" && [ "$(rc)" = "$old_rc" ]; then ok 0 "$CASE, as $RUNNER: stdout, stderr and exit as the base"
    else ok 1 "$CASE, as $RUNNER: stdout, stderr and exit as the base"; diff "$W/r.old" "$W/out" | sed 's/^/info:   /'; fi
  done
  RUNNER=tester; rrun "$M" "$RNEW"; S=$W/out
  is "$CASE: A (no draft, no PR) brief: not-submitted" "$(brief_of A "$S")" "not-submitted"
  is "$CASE: A next: brief to submit" "$(next_of A "$S")" "brief to submit"
  is "$CASE: B brief: waiting" "$(brief_of B "$S")" "waiting"
  is "$CASE: C brief: approved @neil <time>" "$(brief_of C "$S")" "approved @neil 2026-01-01T11:00:00Z"
  is "$CASE: D (Areas: empty) brief: not-submitted" "$(brief_of D "$S")" "not-submitted"
  is "$CASE: E brief: changes-requested" "$(brief_of E "$S")" "changes-requested"
  CRNEXT=$(next_of E "$S")
  RUNNER=neil; rrun "$M" "$RNEW"; S=$W/out
  is "$CASE, as neil: B brief: not-needed (no status asked)" "$(brief_of B "$S")" "not-needed"
  is "$CASE, as neil: C brief: not-needed" "$(brief_of C "$S")" "not-needed"
  RUNNER=tester
done

# 8b. DoD 2 to 5 in the brief's own words, then DoD 6.
rcase "8b the brief's scenario" "$T_F"
rdraft $R1 add/a 'Areas: content'
rdraft $R2 add/b 'Areas: design'
rdraft $R3 add/c 'Areas: content, design' 'Author: @tester'
rdraft $R4 add/d
rdraft $R5 add/e 'Areas: content' 'Author: @other'
rdraft $R6 add/f 'Areas: content' 'Author: @tester'
rdraft $R7 add/g 'Areas: design, content' 'Author: @opener'
rdraft $R8 add/h 'Areas: design'; crlf "$M/$H/plan/$R8.md"
rplan "$(u A $R1 add/a)" "$(u B $R2 add/b)" "$(u C $R3 add/c)" "$(u D $R4 add/d C)" "$(u E $R5 add/e)" \
      "$(u F $R6 add/f)" "$(u G $R7 add/g)" "$(u H $R8 add/h)"
mkpr add/c $R3 tester; mkpr add/e $R5 tester; mkpr add/f $R6 tester; mkpr add/g $R7 opener
need add/c waiting; need add/e waiting; need add/f not-needed; need add/g waiting
rrun "$M" "$RNEW"; S=$W/out
echo "info: 8b, no labels:"; sed 's/^/info:   /' "$W/out" "$W/err"
t "$CASE: exits 0" test "$(rc)" = 0
is "$CASE: A (Areas: content, no PR, <me> approves it) brief: not-needed" "$(brief_of A "$S")" "not-needed"
is "$CASE: B (Areas: design, no PR) brief: not-submitted" "$(brief_of B "$S")" "not-submitted"
is "$CASE: B next: brief to submit" "$(next_of B "$S")" "brief to submit"
is "$CASE: C (content, design; Author: @tester, opened by tester) brief: waiting (design)" "$(brief_of C "$S")" "waiting (design)"
is "$CASE: C next: waiting for the brief to be approved (design)" "$(next_of C "$S")" "waiting for the brief to be approved (design)"
is "$CASE: E (who: approver, PR open, Author: @other) status asked: waiting (content)" "$(brief_of E "$S")" "waiting (content)"
is "$CASE: F (Author: @tester, opened by tester) brief: not-needed" "$(brief_of F "$S")" "not-needed"
is "$CASE: G brief: waiting (design, content)" "$(brief_of G "$S")" "waiting (design, content)"
is "$CASE: G next: waiting for the brief to be approved (design, content)" "$(next_of G "$S")" "waiting for the brief to be approved (design, content)"
is "$CASE: H (CRLF draft, Areas: design) brief: not-submitted" "$(brief_of H "$S")" "not-submitted"
is "$CASE: H next: brief to submit" "$(next_of H "$S")" "brief to submit"
ghs comment add/c other 2026-01-01T11:00:00Z 'please change the design'; need add/c changes-requested
rrun "$M" "$RNEW"; S=$W/out
is "$CASE, after @other's comment: C brief: changes-requested (design)" "$(brief_of C "$S")" "changes-requested (design)"
is "$CASE, after @other's comment: C next as a unit without Areas: in that state (8a's E)" "$(next_of C "$S")" "$CRNEXT"
ghs label add/c approved:design other 2026-01-01T11:30:00Z; need add/c 'approved @other 2026-01-01T11:30:00Z'
rrun "$M" "$RNEW"; S=$W/out
echo "info: 8b, after approved:design:"; sed 's/^/info:   /' "$W/out" "$W/err"
is "$CASE, after approved:design: C brief: approved @other <time>" "$(brief_of C "$S")" "approved @other 2026-01-01T11:30:00Z"
next_of C "$S" > "$W/c.next"; blk3 D "$S" > "$W/d.blk"
cp "$W/out" "$W/main.out"
# DoD 6: from a worktree on C's branch, whose own BRIEF.md names other areas, and which has no plan/.
U="$M/.claude/worktrees/unit c"
G -C "$M" worktree add -q "$U" add/c || { echo "SETUP FAIL: $CASE: worktree"; exit 2; }
mkdir -p "$U/$H"; printf '# Brief — T: C\n\nAreas: design\nAuthor: @other\n\n## Outcome\n\nX.\n' > "$U/$H/BRIEF.md"
rrun "$U" "$RNEW"
t "$CASE, from the unit's worktree: exits 0" test "$(rc)" = 0
t "$CASE, from the unit's worktree: the same output as from the main checkout" cmp -s "$W/main.out" "$W/out"
t "$CASE, from the unit's worktree: it has no plan/" test ! -e "$U/$H/plan"

# 8c. DoD 5: the same parent without Areas:, approved through brief-approved; its part and its next
#     must read as 8b's.
rcase "8c a parent without Areas:, approved" "$T_F"
rdraft $R3 add/c; rdraft $R4 add/d
rplan "$(u C $R3 add/c)" "$(u D $R4 add/d C)"
mkpr add/c $R3 tester; ghs label add/c brief-approved neil 2026-01-01T11:30:00Z
need add/c 'approved @neil 2026-01-01T11:30:00Z'
rrun "$M" "$RNEW"; S=$W/out
is "$CASE: C brief: approved @neil <time>" "$(brief_of C "$S")" "approved @neil 2026-01-01T11:30:00Z"
is "$CASE: C's next is 8b's area-approved C's" "$(next_of C "$S")" "$(cat "$W/c.next")"
is "$CASE: the part D's three lines are 8b's" "$(blk3 D "$S")" "$(cat "$W/d.blk")"

# 9. When ada-brief who fails (exits non-zero: e.g. a draft's Areas: names an area the project doesn't
#    list), ada-plan keeps the "required" answer and says so on stderr: "ada-plan: ada-brief who <areas>:
#    <its stderr>", once for each distinct set. stdout keeps its shape (three lines per unit) and the exit is 0.
#    9a: a stub who that exits 2 (against a twin whose who answers "required" with exit 0);
#    9b: the real ada-brief with an unknown area.
for v in fail twin; do
  cp -R "$NEW" "$W/new9$v"
  cat > "$W/new9$v/bin/ada-brief" <<EOF
#!/bin/sh
case "\$1" in
  who) shift
       case " \$* " in
         *" nosuch "*) if [ $v = fail ]; then echo "stub: unknown area nosuch" >&2; exit 2; else echo "required me"; exit 0; fi;;
       esac
       echo "approver me";;
  status) echo not-submitted;;
  *) exit 1;;
esac
EOF
  chmod +x "$W/new9$v/bin/ada-brief"
done
rcase "9a who exits 2 (stub)" "$T_F"
rdraft $R1 add/p 'Areas: nosuch'; rdraft $R2 add/q 'Areas: nosuch'; rdraft $R3 add/r 'Areas: content'; rdraft $R4 add/s
rplan "$(u P $R1 add/p)" "$(u Q $R2 add/q)" "$(u R $R3 add/r)" "$(u S $R4 add/s)"
rrun "$M" "$W/new9fail"; S=$W/out; cp "$W/err" "$W/e9a"; rc9=$(rc)
rrun "$M" "$W/new9twin"; cp "$W/out" "$W/o9twin"; cp "$W/err" "$W/e9twin"
echo "info: 9a, who fails:"; sed 's/^/info:   /' "$S" "$W/e9a"
t "$CASE: exits 0" test "$rc9" = 0
is "$CASE: stderr is the one line, for two units with the same set" "$(cat "$W/e9a")" "ada-plan: ada-brief who nosuch: stub: unknown area nosuch"
t "$CASE: three lines per unit" test "$(wc -l < "$S" | tr -d ' ')" = 12
is "$CASE: P (unknown area) brief: not-submitted, as required" "$(brief_of P "$S")" "not-submitted"
is "$CASE: P next: brief to submit" "$(next_of P "$S")" "brief to submit"
is "$CASE: Q (the same unknown area) brief: not-submitted" "$(brief_of Q "$S")" "not-submitted"
is "$CASE: R (content; who approver) brief: not-needed, who answered" "$(brief_of R "$S")" "not-needed"
is "$CASE: S (no areas; who approver) brief: not-needed" "$(brief_of S "$S")" "not-needed"
t "$CASE: stdout as when who answers required with exit 0" cmp -s "$S" "$W/o9twin"
is "$CASE: that twin run (exit 0) prints nothing on stderr" "$(cat "$W/e9twin")" ""

rcase "9b who exits non-zero (real ada-brief, an area the project doesn't list)" "$T_F"
rdraft $R1 add/a 'Areas: nosuch'; rdraft $R2 add/b 'Areas: content'; rdraft $R3 add/c 'Areas: nosuch'
rplan "$(u A $R1 add/a)" "$(u B $R2 add/b)" "$(u C $R3 add/c)"
rrun "$M" "$RNEW"; S=$W/out
echo "info: 9b:"; sed 's/^/info:   /' "$S" "$W/err"
t "$CASE: exits 0" test "$(rc)" = 0
is "$CASE: stderr is ada-brief's own message, once for two units, prefixed" "$(cat "$W/err")" "ada-plan: ada-brief who nosuch: ada-brief: unknown area nosuch; ada-brief areas lists them"
t "$CASE: three lines per unit" test "$(wc -l < "$S" | tr -d ' ')" = 9
is "$CASE: A (unknown area, no PR) brief: not-submitted, as required" "$(brief_of A "$S")" "not-submitted"
is "$CASE: A next: brief to submit" "$(next_of A "$S")" "brief to submit"
is "$CASE: B (content; tester approves it) brief: not-needed" "$(brief_of B "$S")" "not-needed"
is "$CASE: C (the same unknown area) brief: not-submitted" "$(brief_of C "$S")" "not-submitted"

cat "$W"/real*/state/unsupported > "$W/unsup" 2>/dev/null
if [ -s "$W/unsup" ]; then echo "SETUP FAIL: the stand-in gh was asked for what it doesn't handle (extend the stand-in, not the expectations):"; sort -u "$W/unsup" | sed 's/^/  /'; echo "scratch kept at $W"; exit 2; fi
echo "$holds held"
echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### Platforms

As in `tests/functional/02-onboarding/gitignore-worktrees-line.md` (export the base branch's
plugin to `$S/old` first), with this `check.sh` and `OLD_PLUGIN` set, since a worktree's `.git`
points outside the mount:

```sh
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 >/dev/null && git config --global --add safe.directory "*" && REPO=/repo PLUGIN=/repo/plugins/ada OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
  sh -c 'apt-get update -qq >/dev/null; apt-get install -y -qq git >/dev/null; git config --global --add safe.directory "*"; REPO=/repo PLUGIN=/repo/plugins/ada OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

## Expected

Every line `PASS` (no `HOLD` lines at present), last line `0 failed`, exit 0, in all
three runs. The break in step 3 prints
`FAIL` for the checks that depend on the areas (8 of them against the base branch's plugin).

| Unit | Areas | State | `brief:` | `next:` |
|---|---|---|---|---|
| A | content (I approve it) | no PR | `not-needed` | |
| B | design | no PR | `not-submitted` | `brief to submit` |
| C | content, design | draft PR, design waiting | `waiting (design)` | `waiting for the brief to be approved (design)` |
| D | Design, content, design | draft PR, changes requested on design | `changes-requested (design)` | the changes-requested text, as before |
| E | content | draft PR, approved | `approved @me 2026-11-20T10:00:00Z` | `parts to merge: F` |
| F | none (a part of E) | no PR | `not-submitted` | `brief to submit` |
| G | `<slug>, ...` (the prompt) | no PR | `not-submitted` (as no areas) | |
| H | content | draft PR, status says `not-needed` | `not-needed`; `status` was asked | |
| I | no draft brief | draft PR, `waiting` | `waiting` (no areas) | `waiting for the brief to be approved` |
| J | content, design | draft PR, changes requested on design, content waiting | `changes-requested (design)` (only the areas in the first line's state) | the changes-requested text |

`ada-brief who` is called four times in all (`content`, `design`, `content design`, and none),
units C and D sharing one call. With no draft briefs at all, the output is the base branch's, also
when `who` says `not-needed` (no `status` call for a unit without areas). From Unit B's worktree
the output is the main checkout's.

Sources: the brief's Decisions (areas from the draft's `Areas:`; `who` once for each distinct set;
a unit with areas always runs `status`; `brief:` and `next:` with the areas) and Definition of
Done; `API.md`, `ada-plan` (`status`) and `ada-brief` (`who`, `status`).

Sections 6 to 9 (Tess):

- 6: the usage header names `Areas:` and shows `waiting (` or `changes-requested (` (DoD 8); the
  CHANGELOG's **Brief approval by area.** bullet mentions `ada-plan status` (DoD 10).
- 7 (stub `ada-brief`): `brief:` is `status`'s line 1 plus, for `waiting` and
  `changes-requested`, the slugs of its `area <slug>: …` lines in their order: `waiting (design,
  content)`; `waiting (system)` for a catch-all line not on `Areas:`; `changes-requested (content,
  design)`, never a slug from an approver's words after a `--- @…` line; `approved @other <time>`
  and `not-needed` as line 1 alone. `next:` for `waiting` is the plain unit's with ` (<areas>)`;
  for `changes-requested` and `approved` it is the plain unit's (Ada, 23 Nov: changes-requested
  keeps "… go through them with the developer, then submit the brief again"). A part of an area
  parent shows what a part of a plain parent shows, in every state.
- 8a (real `ada-brief`, DoD 1): drafts without `Areas:` (no line, `Areas:` empty, the template's
  `<…>` prompt, no draft), with and without a table, as `tester` and `neil`: stdout, stderr and
  exit as the base; `not-submitted` / `brief to submit`, `waiting`, `approved @neil <time>`,
  `changes-requested`; as `neil` (an approver) `not-needed`.
- 8b (DoD 2 to 4, 6): A `not-needed`; B `not-submitted`, `brief to submit`; C `waiting (design)`,
  `waiting for the brief to be approved (design)`, then after `@other`'s comment
  `changes-requested (design)` with the plain unit's `next:`, then after `approved:design` by
  `@other` `approved @other 2026-01-01T11:30:00Z`; E (`who` says approver, but `Author: @other`)
  `waiting (content)`, since a unit with areas always asks `status`; F `not-needed`; G `waiting
  (design, content)`; H (a CRLF draft) `not-submitted`. From a worktree on C's branch, with its
  own `BRIEF.md` naming other areas: the main checkout's output.
- 8c (DoD 5): a parent without `Areas:` approved through `brief-approved`: its `next:` and its
  part's three lines are 8b's.
- 9 (`ada-brief who` fails, Bob's fix round): `ada-plan` keeps the `required` answer, prints
  `ada-plan: ada-brief who <areas>: <stderr>` on stderr once for each distinct set, exits 0, and
  stdout keeps its three lines per unit. 9a: a stub `who` that exits 2 for `nosuch` (two units share
  the set, so one stderr line; the units show `not-submitted` / `brief to submit`; stdout is the
  same as a twin whose `who` answers `required` with exit 0, whose stderr is empty). 9b: the real
  `ada-brief` with `Areas: nosuch`: stderr is `ada-plan: ada-brief who nosuch: ada-brief: unknown
  area nosuch; ada-brief areas lists them`, the unit `not-submitted`, a `content` unit `not-needed`.

## On GitHub

The Definition of Done in its own words, for a person (TEST-PLAN.md, check 18). A scratch GitHub
repo you own, never a client's; `gh` logged in as you (`<me>`); a second login `@other` who can
comment and add labels there. `P=<this checkout>/plugins/ada/bin`. Wait a few seconds between a
push and a label or comment, since times are compared to the second.

1. On `main`: `.claude/team/PROJECT.md` with `**Branch from:** main`, `**PR into:** main`,
   and a Brief approval section with `- **Approvers:** @other`, `- **Trusted:** none` and the table
   `| Content | content | @<me> |`, `| Design | design | @other |`, `| System behaviour
   (catch-all) | system | @other |`; `.gitignore` with `.claude/handoff/` and
   `.claude/worktrees/`. Commit, push; `gh label create approved:design`.
2. `bash $P/ada-plan init`; a plan of units A (`add/f-a`), B (`add/f-b`), C (`add/f-c`) and D
   (`add/f-d`, Parent C). Draft briefs in `.claude/handoff/plan/<task>.md`: A `Areas: content`;
   B `Areas: design`; C `Areas: content, design` and `Author: @<me>`; D none.
3. For C: a branch `add/f-c`, `.claude/handoff/BRIEF.md` a copy of its draft (with `Branch:
   add/f-c`, `Plan:` naming the plan's committed path), `bash $P/ada-brief submit <C's task> main`.
4. `bash $P/ada-plan status`: A `brief: not-needed`; B `brief: not-submitted`, `next: brief to
   submit`; C `brief: waiting (design)`, `next: waiting for the brief to be approved (design)`.
5. As `@other`, comment on C's PR: C `brief: changes-requested (design)`.
6. As `@other`, add `approved:design` to C's PR: C `brief: approved @other <time>`; D's `next:` as
   for any approved parent.
7. `git worktree add .claude/worktrees/f-c add/f-c`; `bash $P/ada-plan status` from there: the same
   output as step 6.
8. Close the PR (`gh pr close <n> --delete-branch`).

## Observe at

The script's output and exit code; `who.log` and `status.log` in the scratch folder; for section
8, `real <n>/state/state.json` (the stand-in's PRs) and `real <n>/state/log` (every `gh` call). On a
failure the scratch folder is kept and named.

## Regression

Section 1 compares a plan with no `Areas:` with the base branch's script, with `who` answering
`required` and `not-needed`. Section 4 compares the output from a unit's worktree with the main
checkout's. Section 8a compares plans without `Areas:` with the base using the real `ada-brief`.
Also run, unedited, `tests/functional/03-plans/plan-lives-in-main-checkout.md`.
