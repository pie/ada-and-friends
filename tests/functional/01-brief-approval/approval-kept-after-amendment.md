# Approval kept after an amendment that hits no trigger; reset after one that does

- Area: 01 Brief approval (`ada-brief status`, `submit`, plan parts through `commit_to`)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219937425454517
  (also fixes https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219575725022328)
- Automate as: none (a self-checking shell script, but it needs GitHub: it pushes, labels and
  opens PRs)
- Last run: 2026-10-27, sequences A and B passed, 0 failed (fails against the released plugin); see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree. The script runs this checkout's
  `ada-brief` by path, never the one on the PATH.
- `gh` logged in, network, `git`, `python3` (3.8+), `mktemp`.
- A scratch GitHub repo **you own** (never a client's, never this one), empty or with a
  `next-release` branch: `gh repo create <your login>/ada-brief-scratch --private`. The script
  refuses a repo not under your login. It makes a `next-release` branch if there isn't one, and
  per run two branches named `add/amend-<timestamp>` and `add/part-<timestamp>`, each with a
  draft PR into `next-release`.
- You are the approver: the scratch `PROJECT.md` lists your login under `**Approvers:**` and has
  a `**Re-approval triggers:**` bullet with an `@` in it. You add the `brief-approved` label
  through the API, so the `labeled` event is yours. If `status` doesn't say `approved` after your
  own label, the script stops with `SETUP FAIL` (TEST-PLAN.md, Escalate 7).
- Each step waits for GitHub to show the `labeled` event, and waits two seconds between a label
  and the next commit, so the order of events is unambiguous (approval order is tested by
  sequence, not by setting the clock: `TEST-AREAS.md`, Dates). A run takes three to five minutes.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> SCRATCH_REPO=<your login>/<scratch repo> bash check.sh`.
   `KEEP=1` leaves the PRs open on success too; on a failure they are always left open.
3. Break it on purpose, once: run it with `PLUGIN=<the released plugin>` (export it with
   `git archive origin/next-release plugins/ada | tar -x -C <folder>`). A4 and A6 must print
   `FAIL` (the released `submit` always resets approval).
4. Afterwards, delete the scratch repo if you don't want it: `gh repo delete <it>`.

```bash
#!/bin/bash
# ada-brief status and submit around amendments, on a scratch GitHub repo. PASS/FAIL; exit 1 if any FAIL.
# Env: REPO, PLUGIN (default $REPO/plugins/ada), SCRATCH_REPO=<your login>/<repo> (required), KEEP=1.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
AB=$PLUGIN/bin/ada-brief
BASH_BIN=$(command -v bash)
SR=${SCRATCH_REPO:-}
[ -n "$SR" ] || { echo "SETUP FAIL: set SCRATCH_REPO=<your login>/<scratch repo>"; exit 2; }
ME=$(gh api user --jq .login) || { echo "SETUP FAIL: gh is not logged in"; exit 2; }
case "$SR" in "$ME"/*) ;; *) echo "SETUP FAIL: $SR is not under your login ($ME): use a scratch repo you own"; exit 2;; esac
case "$SR" in */ada-and-friends) echo "SETUP FAIL: not this repo"; exit 2;; esac
gh api "repos/$SR" >/dev/null 2>&1 || { echo "SETUP FAIL: no repo $SR (gh repo create $SR --private)"; exit 2; }
W=$(mktemp -d); R=$W/r
gh repo clone "$SR" "$R" -- -q >/dev/null 2>&1 || { echo "SETUP FAIL: cannot clone $SR"; exit 2; }
git -C "$R" config user.name tester; git -C "$R" config user.email tester@example.invalid
if ! git -C "$R" ls-remote --exit-code --heads origin next-release >/dev/null 2>&1; then
  git -C "$R" checkout -q --orphan next-release
  git -C "$R" rm -rqf --cached . >/dev/null 2>&1
  printf '# Scratch repo for ada-brief tests\n' > "$R/README.md"
  git -C "$R" add README.md; git -C "$R" commit -qm "Scratch base"
  git -C "$R" push -q origin next-release || { echo "SETUP FAIL: cannot push next-release"; exit 2; }
fi
git -C "$R" fetch -q origin next-release

fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
run() { l=$1; shift; (cd "$R" && "$BASH_BIN" "$AB" "$@") > "$W/$l" 2>> "$W/stderr.log"; echo $? > "$W/$l.rc"; }
line() { sed -n "${2}p" "$W/$1"; }
first_is() { head -n 1 "$W/$1" | grep -Eq "$2"; }
has_label() { gh api "repos/$SR/issues/$1/labels" --jq '.[].name' | grep -qx brief-approved; }
ncom() { gh api "repos/$SR/issues/$1/comments?per_page=100" --jq length; }
lastcom() { gh api "repos/$SR/issues/$1/comments?per_page=100" --jq '.[-1].body'; }
norm() { tr -d '\r' | sed -e 's/[[:space:]]*$//' | awk 'NF{for(;b>0;b--)print "";print;next}{b++}'; }
prbody() { gh api "repos/$SR/pulls/$1" --jq .body | norm; }
head_of() { git -C "$R" ls-remote origin "refs/heads/$1" | cut -f1; }
nlabeled() { gh api "repos/$SR/issues/$1/events?per_page=100" --jq '[.[]|select(.event=="labeled" and .label.name=="brief-approved")]|length'; }
label() { # add brief-approved as yourself; wait until GitHub shows the event
  sleep 2; n0=$(nlabeled "$1")
  gh api -X POST "repos/$SR/issues/$1/labels" -f 'labels[]=brief-approved' >/dev/null
  i=0; while [ "$(nlabeled "$1")" = "$n0" ] && [ $i -lt 30 ]; do sleep 2; i=$((i+1)); done; sleep 2
}
contains() { case "$1" in *"$2"*) return 0;; esac; return 1; }
AMENDED='amended since approval; no re-approval needed'
NOAMEND='approved section changed with no amendment recorded'

S=$(date +%Y%m%d%H%M%S); B=add/amend-$S; P=add/part-$S; T1=9${S}1; T2=9${S}2
D1='DECISION-ONE: the first decision.';  D1B='DECISION-1B: the first decision.'; D1C='DECISION-1C: the first decision.'
D2='DECISION-TWO: the second decision.'; D2B='DECISION-2B: the second decision.'
RA1='| 27 Oct 2026 | ROW-A1 DECISION-ONE → DECISION-1B | wording | not needed | Neil |'
RA2='| 27 Oct 2026 | ROW-A2 DECISION-TWO → DECISION-2B | needs a library | needed: a new dependency | Neil |'
RA3='| 27 Oct 2026 | ROW-A3 DECISION-1B → DECISION-1C | wording again | not needed | Neil |'
RB1='| 27 Oct 2026 | ROW-B1 DECISION-ONE → DECISION-1B | wording | not needed | Neil |'
RB2='| 27 Oct 2026 | ROW-B2 DECISION-TWO → DECISION-2B | customer sees it | needed: something the customer sees changes | Neil |'
brief() { # brief <file> <task> <branch> <decision 1> <decision 2> <extra DoD token or -> [row ...]
  f=$1; task=$2; br=$3; d1=$4; d2=$5; dx=$6; shift 6; mkdir -p "$(dirname "$f")"
  { printf '# Brief — Step 1: Scratch amendment test %s\n\n' "$task"
    printf 'Asana: https://app.asana.com/0/1/%s\nBranch: %s\nPR into: next-release\nPlan: none\nShips: independent\n' "$task" "$br"
    printf 'Status: agreed\nApproval: required\n\n## Outcome\n\nA scratch brief for testing amendments.\n\n'
    printf '## Decisions\n\n- %s\n- %s\n\n' "$d1" "$d2"
    printf '## Constraints\n\n- Expected review: small.\n- Out of scope: everything else.\n\n'
    printf '## Build order\n\n1. Nothing.\n\n## Definition of done\n\n- [ ] DOD-BASE happens.\n'
    [ "$dx" = - ] || printf -- '- [ ] %s happens.\n' "$dx"
    printf '\n## Amendments\n\n| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |\n|---|---|---|---|---|\n'
    if [ $# = 0 ]; then echo '| | | | | |'; else for r in "$@"; do printf '%s\n' "$r"; done; fi
    printf '\n## Builder questions\n\n## Builder plan\n'; } > "$f"
}
push_brief() { # commit and push the current brief without submit, as a plain commit would
  sleep 2; mkdir -p "$R/.claude/briefs"; cp "$R/.claude/handoff/BRIEF.md" "$R/.claude/briefs/$T1.md"
  git -C "$R" add ".claude/briefs/$T1.md"; git -C "$R" commit -qm "$1"; git -C "$R" push -q origin "$B"
}

# ---- Sequence A: the current branch ------------------------------------------------------
git -C "$R" checkout -q -b "$B" origin/next-release
mkdir -p "$R/.claude/team"
printf '# Scratch\n\n## Brief approval\n\n- **Approvers:** @%s\n- **Trusted:** none\n- **Re-approval triggers:** changes to the @payments flow; a new export\n' "$ME" > "$R/.claude/team/PROJECT.md"
printf '.claude/handoff/\n' > "$R/.gitignore"
git -C "$R" add .gitignore .claude/team/PROJECT.md; git -C "$R" commit -qm "Scratch project"

# A1 submit a new brief
brief "$R/.claude/handoff/BRIEF.md" "$T1" "$B" "$D1" "$D2" -
run a1 submit "$T1" next-release
U1=$(awk 'NR==1{print $2}' "$W/a1"); N1=${U1##*/}
case "$N1" in ''|*[!0-9]*) echo "SETUP FAIL: A1 submit didn't open a PR:"; cat "$W/a1" "$W/stderr.log"; exit 2;; esac
t "A1 submit: 'opened <url>'" first_is a1 "^opened +https://github.com/$SR/pull/$N1\$"
# A2 status before any label
run a2 status
t "A2 status before the label: waiting (got: $(line a2 1))" test "$(line a2 1)" = waiting
# A3 label, then status
label "$N1"; run a3 status; L1=$(line a3 1)
if ! first_is a3 "^approved @$ME [0-9]{4}-[0-9]{2}-[0-9]{2}T"; then
  echo "SETUP FAIL: status after your own label says '$L1', not approved @$ME (TEST-PLAN.md Escalate 7). PR left open: $U1"; exit 2
fi
ok 0 "A3 status after the label: '$L1'"
t "A3 status: no '$AMENDED' line before any amendment" not grep -qF "$AMENDED" "$W/a3"
# A4 submit the same brief again (D9; Asana 1219575725022328)
h0=$(head_of "$B"); b0=$(prbody "$N1"); c0=$(ncom "$N1")
run a4 submit "$T1" next-release
t "A4 unchanged resubmit: 'updated <url> (approval kept)' (got: $(line a4 1))" first_is a4 "^updated +$U1 \\(approval kept\\)\$"
t "A4: brief-approved label still on" has_label "$N1"
t "A4: branch head unchanged" test "$(head_of "$B")" = "$h0"
t "A4: PR body unchanged" test "$(prbody "$N1")" = "$b0"
t "A4: no new comment" test "$(ncom "$N1")" = "$c0"
# A5 status: still approved, no second line
run a5 status
t "A5 status: first line as in A3 (got: $(line a5 1))" test "$(line a5 1)" = "$L1"
t "A5 status: no '$AMENDED' line (brief unchanged)" not grep -qF "$AMENDED" "$W/a5"
# A5b the same brief, but the PR body has gone stale (as when only PLAN.md changed): submit PATCHes
# the body back, keeps the label and posts no comment (Amendments row 3)
good=$(prbody "$N1")
gh api -X PATCH "repos/$SR/pulls/$N1" -f body="$(gh api "repos/$SR/pulls/$N1" --jq .body)
STALE-BODY-LINE" >/dev/null
t "A5b setup: the PR body now differs" contains "$(prbody "$N1")" STALE-BODY-LINE
h0=$(head_of "$B"); c0=$(ncom "$N1")
run a5b submit "$T1" next-release
t "A5b stale body, same brief: 'updated <url> (approval kept)' (got: $(line a5b 1))" first_is a5b "^updated +$U1 \\(approval kept\\)\$"
t "A5b: brief-approved label still on" has_label "$N1"
t "A5b: PR body is 'ada-brief body' again" test "$(prbody "$N1")" = "$good"
t "A5b: the stale line is gone" not contains "$(prbody "$N1")" STALE-BODY-LINE
t "A5b: no new comment" test "$(ncom "$N1")" = "$c0"
t "A5b: branch head unchanged" test "$(head_of "$B")" = "$h0"
run a5bs status
t "A5b status: first line as in A3, no '$AMENDED' line" test "$(line a5bs 1)|$(grep -cF "$AMENDED" "$W/a5bs")" = "$L1|0"
# A6 a 'not needed' amendment: Decision changed, DoD item added, one agreed row (D10)
sleep 2
brief "$R/.claude/handoff/BRIEF.md" "$T1" "$B" "$D1B" "$D2" DOD-A6 "$RA1"
h0=$(head_of "$B"); c0=$(ncom "$N1")
run a6 submit "$T1" next-release
t "A6 'not needed' amendment: 'updated <url> (approval kept)' (got: $(line a6 1))" first_is a6 "^updated +$U1 \\(approval kept\\)\$"
t "A6: brief-approved label still on" has_label "$N1"
t "A6: exactly one new comment" test "$(ncom "$N1")" = $((c0+1))
t "A6: the comment lists the added row" contains "$(lastcom "$N1")" 'ROW-A1'
t "A6: the amended brief was pushed" test "$(head_of "$B")" != "$h0"
git -C "$R" fetch -q origin "$B"
t "A6: the committed brief has the change" sh -c "git -C '$R' show 'origin/$B:.claude/briefs/$T1.md' | grep -q DECISION-1B"
run a6body body "$T1"
t "A6: PR body is the new 'ada-brief body'" test "$(prbody "$N1")" = "$(norm < "$W/a6body")"
t "A6: PR body shows the added DoD item" contains "$(prbody "$N1")" DOD-A6
# A6b submit again, nothing changed since the 'allowed' submit: kept, no second comment (Ada's ruling
# on Should Fix 1; Amendments row 3 'same'). The PR body already equals 'ada-brief body', so no PATCH
# is needed either.
h0=$(head_of "$B"); b0=$(prbody "$N1"); c0=$(ncom "$N1")
run a6b submit "$T1" next-release
t "A6b second submit, nothing changed: 'updated <url> (approval kept)' (got: $(line a6b 1))" first_is a6b "^updated +$U1 \\(approval kept\\)\$"
t "A6b: brief-approved label still on" has_label "$N1"
t "A6b: no second comment (the count is still the one A6 posted)" test "$(ncom "$N1")" = "$c0"
t "A6b: branch head unchanged" test "$(head_of "$B")" = "$h0"
t "A6b: PR body unchanged" test "$(prbody "$N1")" = "$b0"
# A7 status: approved as before, second line (D7)
run a7 status
t "A7 status: first line as in A3 (got: $(line a7 1))" test "$(line a7 1)" = "$L1"
t "A7 status: second line '$AMENDED' (got: $(line a7 2))" test "$(line a7 2)" = "$AMENDED"
# A8 who and status without the triggers bullet (D14)
ME_LC=$(printf '%s' "$ME" | tr 'A-Z' 'a-z')
run a8who who
t "A8 who with the triggers bullet: 'approver $ME_LC' (got: $(line a8who 1))" test "$(line a8who 1)" = "approver $ME_LC"
grep -v 'Re-approval triggers' "$R/.claude/team/PROJECT.md" > "$W/p"; cp "$W/p" "$R/.claude/team/PROJECT.md"
run a8who2 who; run a8 status
git -C "$R" checkout -q -- .claude/team/PROJECT.md
t "A8 who without the bullet: the same (got: $(line a8who2 1))" test "$(line a8who2 1)" = "approver $ME_LC"
t "A8 status without the bullet: the same two lines as A7" test "$(line a8 1)|$(line a8 2)" = "$(line a7 1)|$(line a7 2)"
# A9 a 'needed:' amendment pushed without submit: status waits and names the trigger (D8)
brief "$R/.claude/handoff/BRIEF.md" "$T1" "$B" "$D1B" "$D2B" DOD-A6 "$RA1" "$RA2"
push_brief "Amend: a new dependency"
run a9 status
t "A9 status after a 'needed:' amendment: waiting (got: $(line a9 1))" test "$(line a9 1)" = waiting
t "A9 status: the note names 'needed: a new dependency'" grep -qF 'needed: a new dependency' "$W/a9"
t "A9: the label is still on (no submit yet)" has_label "$N1"
# A10 submit it: approval reset (D11)
c0=$(ncom "$N1")
run a10 submit "$T1" next-release
t "A10 'needed:' amendment: 'updated <url> (approval reset)' (got: $(line a10 1))" first_is a10 "^updated +$U1 \\(approval reset\\)\$"
t "A10: brief-approved label removed" not has_label "$N1"
t "A10: no comment posted" test "$(ncom "$N1")" = "$c0"
# A11 status: waiting
run a11 status
t "A11 status after the reset: waiting (got: $(line a11 1))" test "$(line a11 1)" = waiting
# A12 approved again
label "$N1"; run a12 status; L2=$(line a12 1)
t "A12 status after re-approval: approved @$ME (got: $L2)" first_is a12 "^approved @$ME [0-9]{4}-"
t "A12 status: no '$AMENDED' line" not grep -qF "$AMENDED" "$W/a12"
# A13 an approved-section change with no new row, pushed without submit
brief "$R/.claude/handoff/BRIEF.md" "$T1" "$B" "$D1C" "$D2B" DOD-A6 "$RA1" "$RA2"
push_brief "Change a Decision with no amendment"
run a13 status
t "A13 status: waiting (got: $(line a13 1))" test "$(line a13 1)" = waiting
t "A13 status: the note names '$NOAMEND'" grep -qF "$NOAMEND" "$W/a13"
# A14 record it as 'not needed' and submit: kept; the comment lists only the new row
sleep 2
brief "$R/.claude/handoff/BRIEF.md" "$T1" "$B" "$D1C" "$D2B" DOD-A6 "$RA1" "$RA2" "$RA3"
c0=$(ncom "$N1")
run a14 submit "$T1" next-release
t "A14: 'updated <url> (approval kept)' (got: $(line a14 1))" first_is a14 "^updated +$U1 \\(approval kept\\)\$"
t "A14: brief-approved label still on" has_label "$N1"
t "A14: exactly one new comment" test "$(ncom "$N1")" = $((c0+1))
lc=$(lastcom "$N1")
t "A14: the comment lists the new row" contains "$lc" 'ROW-A3'
t "A14: the comment leaves out rows from before the re-approval" not sh -c "printf '%s' \"\$1\" | grep -Eq 'ROW-A1|ROW-A2'" _ "$lc"
run a14s status
t "A14 status: first line as in A12" test "$(line a14s 1)" = "$L2"
t "A14 status: second line '$AMENDED'" test "$(line a14s 2)" = "$AMENDED"
# A14b again, nothing changed since A14's 'allowed' submit: kept, no second comment, nothing pushed
h0=$(head_of "$B"); b0=$(prbody "$N1"); c0=$(ncom "$N1")
run a14b submit "$T1" next-release
t "A14b second submit, nothing changed: 'updated <url> (approval kept)' (got: $(line a14b 1))" first_is a14b "^updated +$U1 \\(approval kept\\)\$"
t "A14b: brief-approved label still on" has_label "$N1"
t "A14b: no second comment" test "$(ncom "$N1")" = "$c0"
t "A14b: branch head unchanged" test "$(head_of "$B")" = "$h0"
t "A14b: PR body unchanged" test "$(prbody "$N1")" = "$b0"

# ---- Sequence B: a plan part, submitted without checking its branch out --------------------
git -C "$R" branch "$P" origin/next-release
PF=$R/.claude/handoff/plan/$T2.md
brief "$PF" "$T2" "$P" "$D1" "$D2" -
run b1 submit "$T2" next-release "$P"
U2=$(awk 'NR==1{print $2}' "$W/b1"); N2=${U2##*/}
case "$N2" in
  ''|*[!0-9]*) echo "SETUP FAIL B: submit with a branch didn't open a PR (is the part's brief read from .claude/handoff/plan/$T2.md? TEST-PLAN.md Escalate 8):"; cat "$W/b1" "$W/stderr.log"; exit 2 ;;
  *)
  ok 0 "B1 plan part submit: $(line b1 1)"
  label "$N2"; git -C "$R" fetch -q origin "$P"; run b2 status "$P"; Lb=$(line b2 1)
  t "B2 status <part>: approved @$ME (got: $Lb)" first_is b2 "^approved @$ME [0-9]{4}-"
  sleep 2
  brief "$PF" "$T2" "$P" "$D1B" "$D2" DOD-B "$RB1"
  hB=$(git -C "$R" rev-parse HEAD); c0=$(ncom "$N2")
  run b3 submit "$T2" next-release "$P"
  t "B3 part 'not needed' amendment: 'updated <url> (approval kept)' (got: $(line b3 1))" first_is b3 "^updated +$U2 \\(approval kept\\)\$"
  t "B3: brief-approved label still on" has_label "$N2"
  t "B3: exactly one new comment" sh -c "test \"\$1\" = $((c0+1))" _ "$(ncom "$N2")"
  t "B3: the comment lists the row" contains "$(lastcom "$N2")" 'ROW-B1'
  t "B3: the checked-out branch didn't move" test "$(git -C "$R" rev-parse HEAD)" = "$hB"
  git -C "$R" fetch -q origin "$P"
  t "B3: the part's branch has the amended brief" sh -c "git -C '$R' show 'origin/$P:.claude/briefs/$T2.md' | grep -q DECISION-1B"
  run b4 status "$P"
  t "B4 status <part>: first line as in B2" test "$(line b4 1)" = "$Lb"
  t "B4 status <part>: second line '$AMENDED'" test "$(line b4 2)" = "$AMENDED"
  c0=$(ncom "$N2"); hP=$(git -C "$R" ls-remote origin "refs/heads/$P" | cut -f1)
  run b4b submit "$T2" next-release "$P"
  t "B4b part, second submit, nothing changed: 'updated <url> (approval kept)' (got: $(line b4b 1))" first_is b4b "^updated +$U2 \\(approval kept\\)\$"
  t "B4b: brief-approved label still on" has_label "$N2"
  t "B4b: no second comment" sh -c "test \"\$1\" = $c0" _ "$(ncom "$N2")"
  t "B4b: the part's branch head unchanged" sh -c "test \"\$(git -C '$R' ls-remote origin 'refs/heads/$P' | cut -f1)\" = '$hP'"
  sleep 2
  brief "$PF" "$T2" "$P" "$D1B" "$D2B" DOD-B "$RB1" "$RB2"
  run b5 submit "$T2" next-release "$P"
  t "B5 part 'needed:' amendment: 'updated <url> (approval reset)' (got: $(line b5 1))" first_is b5 "^updated +$U2 \\(approval reset\\)\$"
  t "B5: brief-approved label removed" not has_label "$N2"
  ;;
esac

t "no Python traceback from ada-brief" not grep -q Traceback "$W/stderr.log"
echo "$fails failed"
echo "PRs: $U1 ${U2:-}"
if [ "$fails" = 0 ] && [ -z "${KEEP:-}" ]; then
  for n in $N1 ${N2:-}; do case "$n" in *[!0-9]*|'') ;; *) gh api -X PATCH "repos/$SR/pulls/$n" -f state=closed >/dev/null;; esac; done
  git -C "$R" push -q origin --delete "$B" "$P" 2>/dev/null
  rm -rf "$W"; echo "closed the PRs and deleted the branches"; exit 0
fi
echo "scratch kept at $W; PRs left open"; [ "$fails" = 0 ]
```

## Expected

Every line `PASS`, `0 failed`, exit 0, and no `SETUP FAIL`. Step 3, against the released
plugin, prints `FAIL` for A4, A5b and A6 at least (A6b, A14b and B4b too, since the released `submit`
resets approval every time).

Sources: the brief's Decisions (`status`: "the first line stays `approved @login <time>` … and a
second line says `amended since approval; no re-approval needed`", `reapprove` "behave as today
(`waiting`, with its note), adding the reason to the note"; `submit` on an existing PR: `allowed`
"PATCH the body, keep the label, post a PR comment listing the added Amendments rows",
`reapprove` "the label goes"; "the approved version is the
brief as committed at or before … the approver's last `labeled` event"; "it must work for plan
parts too"), Amendments row 2 (the reasons: `needed: <trigger>`, `approved section changed with
no amendment recorded`) and row 3 (`same`: PATCH the body if its text differs, keep the label,
no comment; A4 and A5b), Builder question 6 (after `submit` has removed the label, `status` says
`waiting` as today and needn't name the trigger: A11 asserts only `waiting`), its Definition of
Done (items 7 to 11, 14), Ada's ruling on Should Fix 1 (a second `submit` with nothing changed since
an `allowed` submit posts no second comment: A6b, A14b, B4b), and `API.md` (`opened <url>`, `updated <url> (approval …)`, `status`
first-line words). "As today" for `reapprove` is read as no comment posted. A5b stands in for "only
`PLAN.md` changed" by editing the PR body directly: the brief doesn't give the plan table's
format, and the rule is about the body's text, whatever made it differ.

## Observe at

The script's output and exit code; the PRs it prints, on GitHub: their labels, description,
comments and timeline. On a failure the scratch folder is kept: `r/` the clone, `<step>` and
`<step>.rc` each command's output and exit code, `stderr.log` everything `ada-brief` printed on
stderr.

## Regression

`status` first-line words (S103); no second line when the brief hasn't changed since the label
(A3, A5, A5b, A12); `opened` for a new PR (A1, B1); a `needed:` amendment still resets (A10, B5);
`waiting` before any label (A2) and after a reset (A11); a repeated `submit` posts no repeated
comment (A6b, A14b, B4b).
