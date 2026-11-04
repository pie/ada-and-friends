# submit and status print and do exactly what the base branch's script does

- Area: 01 Brief approval (`ada-brief submit`, `status`, `who`; the label); 12 Platforms (bash 3.2,
  Python 3.8)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198400793919
- Automate as: none (a self-checking shell script, but it needs GitHub: it pushes, labels and
  opens PRs)
- Last run: 2026-11-04, clean on host bash 5.2, bash 3.2 and Python 3.8: 100 PASS, 0 FAIL each; both breaks print FAIL where Step 4 says; see the PR

The refactor into a list of areas (Unit A) changes no printed line, exit code, PR body, PR comment
or label. This script runs one sequence of `who`, `submit` and `status` twice on a scratch GitHub
repo, once with the base branch's `ada-brief` and once with this checkout's, records every
step's output and the PR's state after it, and compares the two records step by step. It also
checks the lines the brief's Definition of Done names, on both runs, so a scratch repo that
behaves oddly shows up as a failure of the base too.

## Precondition

- This checkout (`REPO`) with the change in its working tree, and the base branch
  (`add/brief-approval-by-area`, or `BASE=<ref>`) in its git. The script runs both scripts by
  path, never the `ada-brief` on the PATH.
- `gh` logged in, network, `git`, `python3` (3.8+), `mktemp`, `cmp`, `diff`.
- A scratch GitHub repo **you own** (never a client's, never this one):
  `gh repo create <your login>/ada-brief-scratch --private`. The script refuses a repo not under
  your login. It makes a `next-release` branch if there isn't one, and per run two branches,
  `add/diff-<timestamp>` and `add/diffp-<timestamp>`, each with a draft PR. It **deletes the
  repo's `brief-approved` label** before each run, so that `submit`'s label creation is compared
  too; don't point it at a repo whose labels you care about.
- You are the approver: the scratch `PROJECT.md` lists your login under `**Approvers:**`, and
  `ADA_BRIEF_REQUIRE=1` is set for every step except the last (the brief's Definition of Done).
  You add `brief-approved` and one PR comment through the API, as yourself.
- Approval order is by sequence, not by the clock (`TEST-AREAS.md`, Dates): each label and comment
  waits for GitHub to show it and two seconds pass before the next commit. Each run takes about
  five minutes; the script takes ten to twelve.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> SCRATCH_REPO=<your login>/<scratch repo> bash check.sh`.
   `KEEP=1` leaves the PRs open on success too; on a failure they are always left open.
3. Under bash 3.2 and under Python 3.8 (the changed Python in `status` and `after_submit` only
   runs against GitHub, so the offline checks don't reach it). The containers can't read a
   worktree's git, so export the base on the host first, and give the container your `gh` token
   through the environment (it is not written anywhere). With `S` the scratch folder holding
   `check.sh`:

   ```sh
   mkdir -p "$S/base" && git -C <this checkout> archive add/brief-approval-by-area plugins/ada | tar -x -C "$S/base"
   export GH_TOKEN=$(gh auth token) SCRATCH_REPO=<your login>/<scratch repo>
   docker run --rm -e GH_TOKEN -e SCRATCH_REPO -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 github-cli >/dev/null && gh auth setup-git && PLUGIN=/plugin BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   docker run --rm -e GH_TOKEN -e SCRATCH_REPO -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s python:3.8-slim \
     sh -c 'apt-get -qq update >/dev/null && apt-get -qq install -y git gh >/dev/null && gh auth setup-git && PLUGIN=/plugin BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   unset GH_TOKEN
   ```

   The `info:` lines at the top must say bash 3.2 and Python 3.8 respectively.
4. Break it on purpose, twice. Copy `plugins/ada` to a scratch folder, change the copy's
   `bin/ada-brief`, and run with `PLUGIN=<the copy>`:
   - `sed 's/approval kept/approval  kept/' <copy>/bin/ada-brief > x && mv x <copy>/bin/ada-brief`:
     `head d07`, `head d08` and their `same as base` lines must print `FAIL`.
   - On a fresh copy, `sed 's/brief-approved/brief-approvex/g'` the same way: `same as base:
     d03` (the repo's label) and the `head d06` approval line at least must print `FAIL`.
5. Afterwards, delete the scratch repo if you don't want it: `gh repo delete <it>`.

```bash
#!/bin/bash
# ada-brief who/submit/status: the base branch's script and this checkout's, the same sequence on a
# scratch GitHub repo, compared step by step. PASS/FAIL; exit 1 if any FAIL, 2 on SETUP FAIL.
# Env: REPO, PLUGIN (default $REPO/plugins/ada), BASE (default add/brief-approval-by-area),
#      BASE_PLUGIN (an exported base plugins/ada; default: git archive of BASE from REPO),
#      SCRATCH_REPO=<your login>/<repo> (required), KEEP=1.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE=${BASE:-add/brief-approval-by-area}
BASH_BIN=$(command -v bash)
LBL=brief-approved
SR=${SCRATCH_REPO:-}
[ -n "$SR" ] || { echo "SETUP FAIL: set SCRATCH_REPO=<your login>/<scratch repo>"; exit 2; }
ME=$(gh api user --jq .login) || { echo "SETUP FAIL: gh is not logged in"; exit 2; }
case "$SR" in "$ME"/*) ;; *) echo "SETUP FAIL: $SR is not under your login ($ME): use a scratch repo you own"; exit 2;; esac
case "$SR" in */ada-and-friends) echo "SETUP FAIL: not this repo"; exit 2;; esac
gh api "repos/$SR" >/dev/null 2>&1 || { echo "SETUP FAIL: no repo $SR (gh repo create $SR --private)"; exit 2; }
W=$(mktemp -d)
if [ -n "${BASE_PLUGIN:-}" ]; then BASE_AB=$BASE_PLUGIN/bin/ada-brief
else
  mkdir -p "$W/baseplugin"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/baseplugin" || { echo "SETUP FAIL: cannot export $BASE from $REPO"; exit 2; }
  BASE_AB=$W/baseplugin/plugins/ada/bin/ada-brief
fi
HEAD_AB=$PLUGIN/bin/ada-brief
[ -f "$BASE_AB" ] || { echo "SETUP FAIL: no base script at $BASE_AB"; exit 2; }
[ -f "$HEAD_AB" ] || { echo "SETUP FAIL: no script at $HEAD_AB"; exit 2; }
echo "info: $("$BASH_BIN" --version | head -n 1)"
echo "info: $(python3 --version 2>&1)"
cmp -s "$BASE_AB" "$HEAD_AB" && echo "info: the script under test is byte-identical to the base's: this run only proves the sequence"
export ADA_BRIEF_REQUIRE=1

fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
not() { ! "$@"; }
contains() { case "$1" in *"$2"*) return 0;; esac; return 1; }
want() { d=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$tag $d"; else ok 1 "$tag $d"
  [ "$tag" = base ] && echo "  (the base fails this too: the expectation or the scratch repo is wrong; report it)"; fi; }
norm() { tr -d '\r' | sed -E -e "s#$D#<W>#g" -e "s/$S/<S>/g" -e 's#/pull/[0-9]+#/pull/<n>#g' -e 's/#[0-9]+/#<n>/g' \
  -e 's/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?(Z|[+-][0-9]{2}:?[0-9]{2})/<time>/g' \
  -e 's/[0-9a-f]{40}/<sha>/g' -e 's/[[:space:]]*$//'; }
l1() { head -n 1 "$D/o/$1.out"; }
l2() { sed -n 2p "$D/o/$1.out"; }
out_has() { grep -qF -- "$2" "$D/o/$1.out"; }
has_label() { gh api "repos/$SR/issues/$1/labels" --jq '.[].name' | grep -qx "$LBL"; }
ncom() { gh api "repos/$SR/issues/$1/comments?per_page=100" --jq length; }
lastcom() { gh api "repos/$SR/issues/$1/comments?per_page=100" --jq 'if length > 0 then .[-1].body else "" end'; }
prbody() { gh api "repos/$SR/pulls/$1" --jq .body; }
head_of() { git -C "$R" ls-remote origin "refs/heads/$1" | cut -f1; }
nlabeled() { gh api "repos/$SR/issues/$1/events?per_page=100" --jq "[.[]|select(.event==\"labeled\" and .label.name==\"$LBL\")]|length"; }
label() { # add the label as yourself; wait until GitHub shows the event
  sleep 2; n0=$(nlabeled "$1")
  gh api -X POST "repos/$SR/issues/$1/labels" -f "labels[]=$LBL" >/dev/null
  i=0; while [ "$(nlabeled "$1")" = "$n0" ] && [ $i -lt 30 ]; do sleep 2; i=$((i+1)); done; sleep 2
}
comment() { # post a PR comment as yourself; wait until it is listed
  sleep 2; c=$(ncom "$1")
  gh api -X POST "repos/$SR/issues/$1/comments" -f body="$2" >/dev/null
  i=0; while [ "$(ncom "$1")" = "$c" ] && [ $i -lt 30 ]; do sleep 2; i=$((i+1)); done; sleep 2
}
project() { # project <approvers value>
  mkdir -p "$R/.claude/team"
  printf '# Scratch\n\n## Brief approval\n\n- **Approvers:** %s\n- **Trusted:** none\n' "$1" > "$R/.claude/team/PROJECT.md"
}
brief() { # brief <task> <branch> <PR into> <decision 1> <decision 2> [row ...]
  task=$1; br=$2; into=$3; d1=$4; d2=$5; shift 5; mkdir -p "$R/.claude/handoff"
  { printf '# Brief — Step 1: Scratch refactor test %s\n\n' "$task"
    printf 'Asana: https://app.asana.com/0/1/%s\nBranch: %s\nPR into: %s\nPlan: none\nShips: independent\n' "$task" "$br" "$into"
    printf 'Status: agreed\nApproval: required\n\n## Outcome\n\nA scratch brief for comparing ada-brief with its base.\n\n'
    printf '## Decisions\n\n- %s\n- %s\n\n' "$d1" "$d2"
    printf '## Constraints\n\n- Expected review: small.\n- Out of scope: everything else.\n\n'
    printf '## Build order\n\n1. Nothing.\n\n## Definition of done\n\n- [ ] DOD-BASE happens.\n'
    printf '\n## Amendments\n\n| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |\n|---|---|---|---|---|\n'
    if [ $# = 0 ]; then echo '| | | | | |'; else for r in "$@"; do printf '%s\n' "$r"; done; fi
    printf '\n## Builder questions\n\n## Builder plan\n'; } > "$R/.claude/handoff/BRIEF.md"
}
push_brief() { # commit and push the current brief without submit, as a plain commit would
  sleep 2; mkdir -p "$R/.claude/briefs"; cp "$R/.claude/handoff/BRIEF.md" "$R/.claude/briefs/$T1.md"
  git -C "$R" add ".claude/briefs/$T1.md"; git -C "$R" commit -qm "$1"; git -C "$R" push -q origin "$B"
}
snap() { # the step's output and the state of both PRs, the repo's label and the branch heads
  o=$D/o/$1
  { echo "rc $(cat "$o.rc")"; echo '--- stdout'; norm < "$o.out"; echo '--- stderr'; norm < "$o.err"
    for k in 1 2; do eval "n=\${N$k:-}"; [ -n "$n" ] || continue
      echo "--- PR $k"
      gh api "repos/$SR/pulls/$n" --jq '"state \(.state) draft \(.draft) base \(.base.ref) head \(.head.ref)"' | norm
      gh api "repos/$SR/issues/$n/labels" --jq '.[].name' | sort | sed 's/^/label /'
      echo "comments $(ncom "$n")"
      echo '--- last comment'; lastcom "$n" | norm
      echo '--- body'; prbody "$n" | norm
    done
    echo '--- repo label'
    lab=$(gh api "repos/$SR/labels/$LBL" --jq '"\(.name) \(.color) \(.description)"' 2>/dev/null) || lab=none
    echo "$lab"
    echo '--- heads'
    for k in B P; do eval "b=\$$k"; h=$(head_of "$b"); pv=$(cat "$D/prev$k" 2>/dev/null)
      if [ -z "$h" ]; then echo "$k none"; elif [ "$h" = "$pv" ]; then echo "$k same"; else echo "$k moved"; fi
      printf '%s' "$h" > "$D/prev$k"; done
  } > "$D/s/$1"
}
run_ab() { id=$1; shift; (cd "$R" && "$BASH_BIN" "$AB" "$@") > "$D/o/$id.out" 2> "$D/o/$id.err"; echo $? > "$D/o/$id.rc"; }
step() { run_ab "$@"; snap "$1"; }
step_nr() { id=$1; shift; (unset ADA_BRIEF_REQUIRE; cd "$R" && "$BASH_BIN" "$AB" "$@") > "$D/o/$id.out" 2> "$D/o/$id.err"; echo $? > "$D/o/$id.rc"; snap "$id"; }

D1='DECISION-ONE: the first decision.';  D1B='DECISION-1B: the first decision.'; D1C='DECISION-1C: the first decision.'
D2='DECISION-TWO: the second decision.'; D2B='DECISION-2B: the second decision.'
R1='| 4 Nov 2026 | ROW-1 DECISION-ONE → DECISION-1B | wording | not needed | Neil |'
R2='| 4 Nov 2026 | ROW-2 DECISION-TWO → DECISION-2B | it reverses one | needed: it reverses a Decision | Neil |'
R3='| 4 Nov 2026 | ROW-3 DECISION-1B → DECISION-1C | needs a library | needed: a new dependency | Neil |'
AMENDED='amended since approval; no re-approval needed'
NOTE_PRE="@$ME approved an earlier version; the brief has changed since:"
ME_LC=$(printf '%s' "$ME" | tr 'A-Z' 'a-z')

seqrun() { # seqrun <tag> <ada-brief>
  tag=$1; AB=$2; D=$W/$tag; R=$D/r; mkdir -p "$D/o" "$D/s"; N1=; N2=
  sleep 1; S=$(date +%Y%m%d%H%M%S); B=add/diff-$S; P=add/diffp-$S; T1=9${S}1; T2=9${S}2
  gh repo clone "$SR" "$R" -- -q >/dev/null 2>&1 || { echo "SETUP FAIL: cannot clone $SR"; exit 2; }
  git -C "$R" config user.name tester; git -C "$R" config user.email tester@example.invalid
  if ! git -C "$R" ls-remote --exit-code --heads origin next-release >/dev/null 2>&1; then
    git -C "$R" checkout -q --orphan next-release; git -C "$R" rm -rqf --cached . >/dev/null 2>&1
    printf '# Scratch repo for ada-brief tests\n' > "$R/README.md"
    git -C "$R" add README.md; git -C "$R" commit -qm "Scratch base"
    git -C "$R" push -q origin next-release || { echo "SETUP FAIL: cannot push next-release"; exit 2; }
  fi
  git -C "$R" fetch -q origin next-release
  gh api -X DELETE "repos/$SR/labels/$LBL" >/dev/null 2>&1
  git -C "$R" checkout -q -b "$B" origin/next-release
  project "@$ME"; printf '.claude/handoff/\n' > "$R/.gitignore"
  git -C "$R" add .gitignore .claude/team/PROJECT.md; git -C "$R" commit -qm "Scratch project"

  # d01 who, d02 status before any PR
  step d01 who
  want "d01 who: 'required $ME_LC' (got: $(l1 d01))" sh -c "printf '%s\n' \"\$1\" | grep -iqx \"required \$2\"" _ "$(l1 d01)" "$ME"
  step d02 status
  # d03 submit a new brief: opened, a draft PR, the label exists
  brief "$T1" "$B" next-release "$D1" "$D2"
  run_ab d03 submit "$T1" next-release
  U1=$(awk 'NR==1{print $2}' "$D/o/d03.out"); N1=${U1##*/}
  case "$N1" in ''|*[!0-9]*) snap d03; ok 1 "$tag d03 submit opened a PR (got: $(l1 d03)); stopping this run"; N1=; return;; esac
  snap d03
  want "d03 submit: 'opened   <url>' (got: $(l1 d03))" sh -c "printf '%s\n' \"\$1\" | grep -Eqx \"opened +https://github.com/\$2/pull/\$3\"" _ "$(l1 d03)" "$SR" "$N1"
  want "d03: the repo has the $LBL label" gh api "repos/$SR/labels/$LBL"
  want "d03: the PR is a draft" test "$(gh api "repos/$SR/pulls/$N1" --jq .draft)" = true
  # d04 status: waiting
  step d04 status
  want "d04 status: waiting (got: $(l1 d04))" test "$(l1 d04)" = waiting
  # d05 an approver's PR comment, newer than the brief: changes-requested, the words in a --- block, the URL last
  comment "$N1" 'CHANGES-PLEASE: a scratch reviewer note.'
  step d05 status
  want "d05 status after your comment: changes-requested (got: $(l1 d05))" test "$(l1 d05)" = changes-requested
  want "d05 status: a '--- @$ME' block" grep -q "^--- @$ME" "$D/o/d05.out"
  want "d05 status: the comment's words" out_has d05 CHANGES-PLEASE
  want "d05 status: the PR URL last" test "$(tail -n 1 "$D/o/d05.out")" = "$U1"
  # d06 label: approved @you <time>, no second line
  label "$N1"; step d06 status; L1=$(l1 d06)
  want "d06 status after the label: 'approved @$ME <time>' (got: $L1)" sh -c "printf '%s\n' \"\$1\" | grep -Eq \"^approved @\$2 [0-9]{4}-[0-9]{2}-[0-9]{2}T\"" _ "$L1" "$ME"
  want "d06 status: no '$AMENDED' line" not out_has d06 "$AMENDED"
  # d07 submit unchanged: kept, nothing else moves
  c0=$(ncom "$N1"); step d07 submit "$T1" next-release
  want "d07 unchanged submit: 'updated  <url> (approval kept)' (got: $(l1 d07))" test "$(l1 d07)" = "updated  $U1 (approval kept)"
  want "d07: label on" has_label "$N1"
  want "d07: no new comment" test "$(ncom "$N1")" = "$c0"
  # d08 a 'not needed' amendment: kept, exactly one comment listing the row
  sleep 2; brief "$T1" "$B" next-release "$D1B" "$D2" "$R1"
  c0=$(ncom "$N1"); step d08 submit "$T1" next-release
  want "d08 'not needed' amendment: 'updated  <url> (approval kept)' (got: $(l1 d08))" test "$(l1 d08)" = "updated  $U1 (approval kept)"
  want "d08: label on" has_label "$N1"
  want "d08: exactly one new comment" test "$(ncom "$N1")" = $((c0+1))
  want "d08: the comment lists the row" contains "$(lastcom "$N1")" ROW-1
  # d09 status: approved as before, and the amended line
  step d09 status
  want "d09 status: first line as d06 (got: $(l1 d09))" test "$(l1 d09)" = "$L1"
  want "d09 status: second line '$AMENDED' (got: $(l2 d09))" test "$(l2 d09)" = "$AMENDED"
  # d10 a 'needed: it reverses a Decision' amendment: reset, label gone, no comment
  sleep 2; brief "$T1" "$B" next-release "$D1B" "$D2B" "$R1" "$R2"
  c0=$(ncom "$N1"); step d10 submit "$T1" next-release
  want "d10 'needed:' amendment: 'updated  <url> (approval reset)' (got: $(l1 d10))" test "$(l1 d10)" = "updated  $U1 (approval reset)"
  want "d10: label gone" not has_label "$N1"
  want "d10: no comment posted" test "$(ncom "$N1")" = "$c0"
  # d11 status: waiting
  step d11 status
  want "d11 status after the reset: waiting (got: $(l1 d11))" test "$(l1 d11)" = waiting
  # d12 approved again
  label "$N1"; step d12 status
  want "d12 status after re-approval: approved @$ME (got: $(l1 d12))" sh -c "printf '%s\n' \"\$1\" | grep -Eq \"^approved @\$2 [0-9]{4}-\"" _ "$(l1 d12)" "$ME"
  # d13 a 'needed:' amendment pushed without submit: waiting, the note names the trigger
  brief "$T1" "$B" next-release "$D1C" "$D2B" "$R1" "$R2" "$R3"; push_brief "Amend: a new dependency"
  step d13 status
  want "d13 status: waiting (got: $(l1 d13))" test "$(l1 d13)" = waiting
  want "d13 status: the note '$NOTE_PRE needed: a new dependency'" out_has d13 "$NOTE_PRE needed: a new dependency"
  want "d13: label still on (no submit yet)" has_label "$N1"
  # d14 submit it: reset
  step d14 submit "$T1" next-release
  want "d14: 'updated  <url> (approval reset)' (got: $(l1 d14))" test "$(l1 d14)" = "updated  $U1 (approval reset)"
  want "d14: label gone" not has_label "$N1"
  # d15, d16 a label from someone who isn't an approver: waiting; submit resets
  project '@octocat'; git -C "$R" add .claude/team/PROJECT.md; git -C "$R" commit -qm "Approver: someone else"
  label "$N1"; step d15 status
  want "d15 status, label by a non-approver: waiting (got: $(l1 d15))" test "$(l1 d15)" = waiting
  step d16 submit "$T1" next-release
  want "d16 submit, no approver's label: 'updated  <url> (approval reset)' (got: $(l1 d16))" test "$(l1 d16)" = "updated  $U1 (approval reset)"
  want "d16: label gone" not has_label "$N1"
  project "@$ME"; git -C "$R" add .claude/team/PROJECT.md; git -C "$R" commit -qm "Approver: you again"
  # d17 a branch on top of B: submit refused while B's brief is waiting
  git -C "$R" checkout -q -b "$P" "$B"
  brief "$T2" "$P" "$B" "$D1" "$D2"
  step d17 submit "$T2" "$B"
  want "d17 submit onto a waiting base: refused, non-zero exit (rc $(cat "$D/o/d17.rc"))" not test "$(cat "$D/o/d17.rc")" = 0
  want "d17: no PR opened for $P" test "$(gh pr list -R "$SR" --head "$P" --state all --json number --jq length)" = 0
  # d18 approve B; status <B> from the other branch
  label "$N1"; step d18 status "$B"
  want "d18 status $B: approved @$ME (got: $(l1 d18))" sh -c "printf '%s\n' \"\$1\" | grep -Eq \"^approved @\$2 [0-9]{4}-\"" _ "$(l1 d18)" "$ME"
  # d19 submit onto the approved base: opened
  run_ab d19 submit "$T2" "$B"
  U2=$(awk 'NR==1{print $2}' "$D/o/d19.out"); N2=${U2##*/}
  case "$N2" in ''|*[!0-9]*) N2=;; esac
  snap d19
  want "d19 submit onto an approved base: 'opened   <url>' (got: $(l1 d19))" sh -c "printf '%s\n' \"\$1\" | grep -Eqx \"opened +https://github.com/\$2/pull/[0-9]+\"" _ "$(l1 d19)" "$SR"
  # d20 status on the new branch: waiting
  step d20 status
  want "d20 status on $P: waiting (got: $(l1 d20))" test "$(l1 d20)" = waiting
  # d21 no approvers and no ADA_BRIEF_REQUIRE: who and status (compared with the base only)
  project none
  step_nr d21 who
  step_nr d22 status
  git -C "$R" checkout -q -- .claude/team/PROJECT.md
  want "no Python traceback from ada-brief" not sh -c "cat \"\$1\"/o/*.err | grep -q Traceback" _ "$D"
}

seqrun base "$BASE_AB"
seqrun head "$HEAD_AB"

# Every step's record from this checkout's script must match the base's, byte for byte
for f in "$W/base/s/"*; do st=${f##*/}
  if [ ! -f "$W/head/s/$st" ]; then ok 1 "same as base: $st (not reached by this checkout's run)"; continue; fi
  if cmp -s "$f" "$W/head/s/$st"; then ok 0 "same as base: $st"
  else ok 1 "same as base: $st"; diff "$f" "$W/head/s/$st" | head -n 20 | sed 's/^/    /'; fi
done
for f in "$W/head/s/"*; do [ -f "$W/base/s/${f##*/}" ] || ok 1 "same as base: ${f##*/} (not reached by the base run)"; done

echo "$fails failed"
if [ "$fails" = 0 ] && [ -z "${KEEP:-}" ]; then
  for t in base head; do
    for f in "$W/$t/o/d03.out" "$W/$t/o/d19.out"; do
      n=$(awk 'NR==1{print $2}' "$f" 2>/dev/null); n=${n##*/}
      case "$n" in ''|*[!0-9]*) ;; *) gh api -X PATCH "repos/$SR/pulls/$n" -f state=closed >/dev/null;; esac
    done
    for b in $(git -C "$W/$t/r" for-each-ref --format='%(refname:short)' refs/heads/add/); do
      git -C "$W/$t/r" push -q origin --delete "$b" 2>/dev/null
    done
  done
  rm -rf "$W"; echo "closed the PRs and deleted the branches"; exit 0
fi
echo "scratch kept at $W (base/ and head/: r the clone, o each step's output, s each step's record); PRs left open"
[ "$fails" = 0 ]
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0, no `SETUP FAIL`, under the local bash, bash 3.2
and Python 3.8 (step 3). There are two kinds of line:

- `base dNN …` and `head dNN …`: the lines the Definition of Done names, checked on each run.
  A `base` line that fails means the expectation or the scratch repo is wrong, not the change;
  it is reported to Ada, never fixed by editing the expectation.
- `same as base: dNN`: the step's exit code, stdout and stderr, and afterwards each PR's state,
  draft flag, base, labels, comment count, last comment and description, the repo's
  `brief-approved` label (name, colour, description) and whether each branch head moved, are the
  same for both scripts once URLs, PR numbers, times, commit hashes and the run's timestamp are
  normalised. On a difference the first 20 lines of the diff follow (`<` base, `>` this checkout).

Step 4's breaks print `FAIL` where it says.

Sources: the brief's Outcome ("nothing a developer or approver sees changes") and Decisions
("Refactor only: no change to any printed line, exit code, PR body, PR comment or label";
`approval()` "same return"; `status` "with the same first line, notes, `---` blocks and URL as
today"; `after_submit` "prints the action and URL as today"; label creation per area); its
Definition of Done item 2 (`opened   <url>`; `waiting`; `approved @<tester> <time>`; `updated  <url>
(approval kept)` and one comment after a `not needed` amendment; `updated  <url> (approval reset)`
and the label gone after `needed: it reverses a Decision`; "each line and the PR's state match the
base branch's script on the same steps") and item 5 (bash 3.2, Python 3.8); `SYSTEM.md`, "ada-brief:
brief approval through GitHub" (`status`: `changes-requested` on an approver's newer comment, the
note `@<login> approved an earlier version; the brief has changed since: <reason>`, a label not
last added by an approver doesn't count; `submit`: `reset` on no approver's label, the label created
if missing, the base's brief must be approved first); `API.md`, `ada-brief` (`who` `required
<login>` under `ADA_BRIEF_REQUIRE=1`; `status`'s first-line words, the URL last). d02, d21 and d22
(`not-submitted`, `not-needed`) are compared with the base only: the brief fixes no new value for
them.

## Observe at

The script's output and exit code; the PRs it prints, on GitHub: labels, description, comments,
timeline. On a failure the scratch folder is kept: `base/` and `head/`, each with `r/` the clone,
`o/<step>.out|.err|.rc` the raw output, and `s/<step>` the normalised record that was compared.

## Regression

The surfaces that read `ada-brief`'s output (the brief's Constraints, S12): `status`'s first line
(read by `skills/begin/approval.md`, `/ada:pickup`, `submit`'s own base check, d17 to d19, and
`ada-plan status`'s `startswith('approved')`), `submit`'s `opened` / `updated … (approval
kept|reset)` lines, the PR body and the amendment comment. All are in the per-step records.
