# The Re-approval column, the triggers bullet, and the docs that describe them

- Area: 01 Brief approval (`who`, `body`); 11 Templates (`templates/handoff/BRIEF.md`, the
  onboard `PROJECT.md`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219937425454517
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-10-27, offline run passed 0 failed on bash 5.2, bash 3.2, Python 3.8; see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree, and `origin/next-release` fetched:
  the script compares with the released plugin exported from there.
- `git`, `tar`, `python3` (3.8+), `awk`, `mktemp`. No network and no GitHub login: `who` and
  `body` call `need_gh`, so a stub `gh` that is logged in as `tester` is put first on the PATH.
  Any other `gh` call prints `STUB-GAP` (fix the stub, not the code).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`. For bash 3.2, with `S` the scratch folder holding
   `check.sh`:

   ```sh
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 diffutils >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && REPO=/repo bash /s/check.sh'
   ```
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder twice. In the first copy,
   delete the `- **Re-approval triggers:**` bullet from `templates/repo/.claude/team/PROJECT.md`;
   in the second, delete ` Re-approval |` from the header row of `templates/handoff/BRIEF.md`'s
   Amendments table. Run `REPO=<this checkout> PLUGIN=<the copy> bash check.sh` for each: the
   first must print `FAIL` for "onboard template: … bullet", the second for "template:
   Amendments header …".

```bash
#!/bin/bash
# The Re-approval column, the triggers bullet, who/body regressions, docs. PASS/FAIL; exit 1 if any FAIL.
# Env: REPO, BASE (default origin/next-release), PLUGIN (default $REPO/plugins/ada), OLD_PLUGIN.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASH_BIN=$(command -v bash)
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
G() { git -c user.name=t -c user.email=t@example.invalid -c init.defaultBranch=init "$@"; }
sect() { awk -v h="$1" '$0==h{f=1;next} /^## /{f=0} f' "$2"; }       # body of a ## section

# 1. The brief template (D12, S104)
NT=$PLUGIN/templates/handoff/BRIEF.md; OT=$OLD_PLUGIN/templates/handoff/BRIEF.md
H5='| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |'
H4='| Date | What changed (old → new) | Why (evidence) | Agreed by |'
sect '## Amendments' "$NT" > "$W/amend.new"
t "template: Amendments header is exactly the five-column one" grep -qxF -- "$H5" "$W/amend.new"
t "template: Amendments separator has five columns" grep -qxE '\|( *:?-+:? *\|){5}' "$W/amend.new"
t "template: Amendments empty row has five cells" grep -qxE '\|( *\|){5}' "$W/amend.new"
t "template: no four-column header left" not grep -qxF -- "$H4" "$W/amend.new"
comments() { awk '/<!--/{c=1;s=""} c{s=s" "$0} /-->/{if(c)print s;c=0}' "$1"; }
approved_comment() { comments "$NT" | grep -i 'approv' | grep 'Decisions' | grep 'Constraints' | grep -qi 'out of scope'; }
t "template: a comment marks Decisions and Constraints, less Out of scope, as the approved sections" approved_comment
grep '^#' "$NT" > "$W/h.new"; grep '^#' "$OT" > "$W/h.old"
t "template: headings unchanged from the released template" cmp -s "$W/h.old" "$W/h.new"
fields() { awk '/^## /{exit} /^[A-Z][A-Za-z ]*:/{sub(/:.*/,""); print}' "$1"; }
fields "$NT" > "$W/f.new"; fields "$OT" > "$W/f.old"
t "template: header field names unchanged" cmp -s "$W/f.old" "$W/f.new"
sect '## Constraints' "$NT" > "$W/con.new"
t "template: Constraints still has '- Out of scope:'" grep -q '^- Out of scope:' "$W/con.new"
t "template: Constraints still has '- Expected review:'" grep -q '^- Expected review:' "$W/con.new"

# 2. The onboard PROJECT.md template (D13)
OP=$PLUGIN/templates/repo/.claude/team/PROJECT.md
sect '## Brief approval' "$OP" > "$W/ba.tmpl"
t "onboard template: Brief approval has a '- **Re-approval triggers:**' bullet" grep -q '^- \*\*Re-approval triggers:\*\*' "$W/ba.tmpl"
t "onboard template: Approvers and Trusted bullets still there" sh -c "grep -q '^- \*\*Approvers:\*\*' '$W/ba.tmpl' && grep -q '^- \*\*Trusted:\*\*' '$W/ba.tmpl'"
# The template's bullet as written: its first line and any indented continuation.
awk '/^- \*\*Re-approval triggers:\*\*/{f=1;print;next} f&&/^  /{print;next} {f=0}' "$W/ba.tmpl" > "$W/tmpl.bullet"

# 3. who with and without the bullet (D14)
mkdir -p "$W/stub"
cat > "$W/stub/gh" <<'STUB'
#!/bin/sh
case "$1 $2" in
  "auth status") exit 0 ;;
  "api user") echo tester ;;
  *) echo "STUB-GAP: gh $*" >&2; exit 3 ;;
esac
STUB
chmod +x "$W/stub/gh"
SP="$W/stub:$PATH"
R=$W/r; mkdir -p "$R/.claude/team" "$R/.claude/briefs"; G init -q "$R"
TRIG='- **Re-approval triggers:** changes to the @tester payments flow; a new export'
TRIG2='  or anything @boss asks to see again'
proj() { # proj <approvers> <trusted> <none|after|between|before|template>
  { printf '# Scratch\n\n## Brief approval\n\nWhose briefs need approving.\n\n'
    [ "$3" = before ] && printf '%s\n%s\n' "$TRIG" "$TRIG2"
    printf -- '- **Approvers:** %s\n' "$1"
    [ "$3" = between ] && printf '%s\n%s\n' "$TRIG" "$TRIG2"
    printf -- '- **Trusted:** %s\n' "$2"
    [ "$3" = after ] && printf '%s\n' "$TRIG"
    [ "$3" = template ] && cat "$W/tmpl.bullet"
    printf '\nApprovers never need approval.\n\n## Environments\n\nNone.\n'; } > "$R/.claude/team/PROJECT.md"
}
who() { (cd "$R" && PATH="$SP" "$BASH_BIN" "$1/bin/ada-brief" who 2>"$W/who.err"); }
for pol in 'none|none|not-needed' '@boss|none|required tester' '@tester|none|approver tester' '@boss|@tester|trusted tester'; do
  a=${pol%%|*}; rest=${pol#*|}; tr_=${rest%%|*}; want=${rest#*|}
  proj "$a" "$tr_" none; new=$(who "$PLUGIN"); old=$(who "$OLD_PLUGIN")
  t "who, Approvers $a, Trusted $tr_, no bullet: '$want' (got: $new)" test "$new" = "$want"
  t "regression: who, Approvers $a, Trusted $tr_, no bullet: as released" test "$new" = "$old"
  for pos in after between before template; do
    proj "$a" "$tr_" $pos; got=$(who "$PLUGIN"); rc=$?
    t "who, Approvers $a, Trusted $tr_, bullet $pos: '$want', exit 0 (got: $got)" test "$got|$rc" = "$want|0"
  done
done
proj '@boss' 'none' after
t "who: ADA_BRIEF_REQUIRE=1 with the bullet is still 'required tester'" test "$(ADA_BRIEF_REQUIRE=1 who "$PLUGIN")" = "required tester"
t "stub: no STUB-GAP from who (else fix the stub)" not grep -q STUB-GAP "$W/who.err"

# 4. body: an old brief renders as before; a new-format row doesn't break it (S104, Surfaces)
proj none none none
brief() { # brief <task> <4|5> <row or ->
  { printf '# Brief — Step 1: Test the column\n\nAsana: https://app.asana.com/0/1/2\n'
    printf 'Branch: add/test\nPR into: next-release\nPlan: none\nShips: independent\n'
    printf 'Status: agreed\nApproval: not needed (test)\n\n## Outcome\n\nSomething happens.\n\n'
    printf '## Decisions\n\n- A decision.\n\n## Constraints\n\n- Expected review: small.\n'
    printf -- '- Out of scope: everything else.\n\n## Build order\n\n1. Build it.\n\n'
    printf '## Definition of done\n\n- [ ] It happens.\n\n## Amendments\n\n'
    if [ "$2" = 5 ]; then printf '%s\n|---|---|---|---|---|\n' "$H5"; else printf '%s\n|---|---|---|---|\n' "$H4"; fi
    if [ "$3" = - ]; then [ "$2" = 5 ] && echo '| | | | | |' || echo '| | | | |'; else printf '%s\n' "$3"; fi
    printf '\n## Builder questions\n\n## Builder plan\n'; } > "$R/.claude/briefs/$1.md"
}
brief T4 4 -
brief T5 5 '| 27 Oct 2026 | A decision → ROWTOKEN | evidence | not needed | Neil |'
brief T6 4 '| 27 Oct 2026 | A decision → OLDTOKEN | evidence | Neil |'
G -C "$R" checkout -q -b add/test; G -C "$R" add -A; G -C "$R" commit -q -m briefs
body() { (cd "$R" && PATH="$SP" "$BASH_BIN" "$1/bin/ada-brief" body "$2") > "$W/$3" 2> "$W/$3.err"; echo $? > "$W/$3.rc"; }
for k in T4 T5 T6; do body "$PLUGIN" $k new.$k; body "$OLD_PLUGIN" $k old.$k; done
t "regression: body of a brief with the old empty four-column table is the released output" cmp -s "$W/old.T4" "$W/new.T4"
t "body of a brief with a filled five-column row: exit 0 and the marker" sh -c "test \"\$(cat '$W/new.T5.rc')\" = 0 && grep -q '^<!-- ada-brief: .claude/briefs/T5.md -->' '$W/new.T5'"
t "body of a brief with a filled four-column row: exit 0 and the marker" sh -c "test \"\$(cat '$W/new.T6.rc')\" = 0 && grep -q '^<!-- ada-brief: .claude/briefs/T6.md -->' '$W/new.T6'"
echo "info: body shows the five-column row's change: $(grep -c ROWTOKEN "$W/new.T5") line(s); released body did: $(grep -c ROWTOKEN "$W/old.T5") line(s)"
t "stub: no STUB-GAP from body (else fix the stub)" not grep -q STUB-GAP "$W"/new.T*.err

# 5. Docs, usage header, CHANGELOG (D16)
U=$PLUGIN/bin/ada-brief
awk 'NR==1{next} /^#/{print;next} {exit}' "$U" > "$W/usage"
t "usage header: names compare" grep -q 'compare' "$W/usage"
awk 'NR==1{next} /^#/{print;next} {exit}' "$OLD_PLUGIN/bin/ada-brief" > "$W/usage.old"
t "usage header: the released one didn't (baseline)" not grep -q 'compare' "$W/usage.old"
awk '/^### `ada-brief`/{f=1;next} /^#{2,3} /{f=0} f' "$REPO/.claude/team/API.md" > "$W/api"
t "API.md ada-brief: a compare row" grep -q '^| `compare' "$W/api"
t "API.md ada-brief: compare's 'reapprove'" grep -qF 'reapprove' "$W/api"
t "API.md ada-brief: submit's '(approval kept)'" grep -qF '(approval kept)' "$W/api"
t "API.md ada-brief: status's 'amended since approval'" grep -qF 'amended since approval' "$W/api"
awk '/^### ada-brief: brief approval through GitHub/{f=1;next} /^#{2,3} /{f=0} f' "$REPO/.claude/team/SYSTEM.md" > "$W/sys"
t "SYSTEM.md ada-brief section: names compare" grep -q 'compare' "$W/sys"
t "SYSTEM.md ada-brief section: names '(approval kept)'" grep -qF '(approval kept)' "$W/sys"
t "SYSTEM.md ada-brief section: the fixed trap 'Resubmitting an unchanged brief still resets approval' is gone" not grep -qF 'Resubmitting an unchanged brief still resets approval' "$W/sys"
# The new trap (Amendments row 4): rows aren't mapped to changes, so a later unrecorded change
# after an agreed 'not needed' row still compares as 'allowed'. Its words aren't fixed by the brief.
tr '\n' ' ' < "$W/sys" | tr -s ' ' > "$W/sys.flat"
t "SYSTEM.md ada-brief section: the trap that a later change with no row of its own still gives 'allowed'" sh -c "grep -qF 'allowed' '$W/sys.flat' && grep -Eqi 'not mapped|row of its own|which row covers' '$W/sys.flat'"
t "TEST-AREAS.md row 01: names compare" sh -c "grep '^| 01 |' '$REPO/.claude/team/TEST-AREAS.md' | grep -q compare"
awk '/^## \[?Unreleased\]?/{f=1;next} /^## /{f=0} f' "$REPO/CHANGELOG.md" > "$W/unrel"
t "CHANGELOG Unreleased: an entry about amendments or re-approval" grep -Eqi 're-approv|amendment|compare' "$W/unrel"

# 6. Static (D15, S100, API.md Never)
t "bash -n ada-brief" bash -n "$U"
diff "$OLD_PLUGIN/bin/ada-brief" "$U" | grep '^>' > "$W/added"
echo "info: lines ada-brief gained or changed: $(wc -l < "$W/added" | tr -d ' ')"
B4='mapfile|readarray|declare -A|local -A|declare -n|local -n|coproc|&>>|\|&|\[\[ +-v |\$\{[A-Za-z_][A-Za-z0-9_]*(\[[^]]*\])?(,,?|\^\^?)\}'
# Comment lines (a '>' from diff, then optional spaces, then '#') are not code: skip them (Bob's question 1)
grep -Ev '^>[[:space:]]*#' "$W/added" > "$W/added.code"
t "no bash 4 features in the added lines (comment lines skipped)" not grep -Eq "$B4" "$W/added.code"
t "bash-4 check self-test: a code line using mapfile is caught" sh -c 'printf "%s\n" "> mapfile -t x < f" | grep -Ev "^>[[:space:]]*#" | grep -Eq "$1"' _ "$B4"
t "bash-4 check self-test: a code line using \${x,,} is caught" sh -c 'printf "%s\n" "> y=\${x,,}" | grep -Ev "^>[[:space:]]*#" | grep -Eq "$1"' _ "$B4"
t "bash-4 check self-test: a comment line naming mapfile is skipped" not sh -c 'printf "%s\n" ">   # no mapfile here" | grep -Ev "^>[[:space:]]*#" | grep -Eq "$1"' _ "$B4"
t "no 'gh pr edit' in the added lines" not grep -q 'gh pr edit' "$W/added"
echo "info: added lines that name python3 (should only be through the PY block): $(grep -c 'python3' "$W/added")"

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0, under bash 5 and bash 3.2. Record the `info:`
lines in the Run record: whether the description shows the new row (the brief doesn't say), how
many lines `ada-brief` gained, and any added `python3` mention (each should be inside the
existing `PY` block's use, not a direct call). Step 3's broken copies print `FAIL` where it says.

The SYSTEM.md trap check looks for the idea, not fixed words (the brief gives none): `allowed`,
and one of "not mapped", "row of its own" or "which row covers". If it fails, read the section's
traps before calling it a defect: the trap may be there in other words.

Sources: the brief's Decisions (the exact column header; Re-approval values; "a project adds its
own on an optional `- **Re-approval triggers:**` bullet in PROJECT.md's `## Brief approval`
section; `policy()` doesn't read it and must not fail on it"; template and parsers change
together, S104), its Definition of Done (items 12, 13's onboard bullet, 14's `who`, 15, 16), its
Surfaces (existing four-column briefs), `SYSTEM.md` (`who`'s outputs; the "unchanged resubmit"
trap this step fixes), Amendments row 4 (the new trap), `API.md` (`who` and `body` output; Never: bash 4 features, `gh pr edit`).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept: `amend.new` the
template's Amendments section, `ba.tmpl` and `tmpl.bullet` the onboard template's section and
bullet, `r/.claude/team/PROJECT.md` the last policy tried, `old.T*` / `new.T*` the released and
new descriptions, `api`, `sys`, `unrel` and `usage` the doc sections read, `added` the lines
`ada-brief` gained.

## Regression

`who` without the bullet is byte for byte the released `who`; `body` of an old-format brief is
the released description; the brief template's headings, field names and the `Out of scope` and
`Expected review` bullets are unchanged. `status` with and without the bullet needs GitHub:
`approval-kept-after-amendment.md`, step A8.
