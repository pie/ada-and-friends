# `ada-brief compare`: is an amended brief still approved?

- Area: 01 Brief approval (`ada-brief compare`); 12 Platforms (bash 3.2, Python 3.8, CRLF)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219937425454517
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-10-27, offline run passed 0 failed on bash 5.2, bash 3.2, Python 3.8; see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree. The script runs this checkout's
  `ada-brief` by path, never the one on the PATH.
- `git`, `python3` (3.8+), `awk`, `sed`, `mktemp`. No network and no GitHub login: `compare` is
  offline (the brief's Decisions). A stub `gh` that records every call is put first on the PATH;
  the script checks it was never called.
- The script makes a scratch git repo to run in (every script `cd`s to its repo root) and writes
  the briefs outside it, passed by absolute path.

The briefs are made from one approved brief: a header, Outcome, Decisions (`DECISION-ONE`,
`DECISION-TWO`), Constraints (`CONSTRAINT-ONE`, `- Expected review: small.`, a two-line
`- Out of scope:` bullet, then `CONSTRAINT-AFTER`), Build order, Definition of done, Amendments
(five columns, or four for the old format, with the template's empty row), Builder questions and
Builder plan. Each case changes one thing.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Under bash 3.2 and under Python 3.8, with `S` the scratch folder holding `check.sh`:

   ```sh
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && REPO=/repo bash /s/check.sh'
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
     sh -c 'apt-get -qq update >/dev/null && apt-get -qq install -y git >/dev/null && git config --global --add safe.directory "*" && python3 --version && REPO=/repo bash /s/check.sh'
   ```
4. Break it on purpose, once: copy `plugins/ada` to a scratch folder, and in the copy's
   `bin/ada-brief` change the `compare` code's literal `not needed` to `not-needed`. Run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. The `allowed` cases with a
   `not needed` row must print `FAIL`. (Running it against the released plugin, which has no `compare`, fails every
   case, which proves less.)

```bash
#!/bin/bash
# ada-brief compare. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO, PLUGIN (default $REPO/plugins/ada).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASH_BIN=$(command -v bash)
W=$(mktemp -d)
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }

# A stub gh that records any call: compare must not need GitHub.
mkdir -p "$W/stub"
printf '#!/bin/sh\necho "gh $*" >> "%s/gh.log"\nexit 3\n' "$W" > "$W/stub/gh"
chmod +x "$W/stub/gh"; : > "$W/gh.log"
SP="$W/stub:$PATH"

R=$W/r; mkdir -p "$R"; git init -q "$R"
B=$W/b; mkdir -p "$B"

# ---- The briefs --------------------------------------------------------------------------
H5='| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |'
S5='|---|---|---|---|---|'; E5='| | | | | |'
H4='| Date | What changed (old → new) | Why (evidence) | Agreed by |'
S4='|---|---|---|---|'; E4='| | | | |'
mk() { # mk <file> <4|5> [row ...]: the brief; no rows = the template's empty row
  f=$1; c=$2; shift 2
  {
  cat <<'TOP'
# Brief — Step 1: Test the compare subcommand

Asana: https://app.asana.com/0/1/2
Branch: add/test
PR into: next-release
Plan: none
Ships: independent
Status: agreed
Approval: approved by @boss on 27 Oct 2026

## Outcome

OUTCOME-ONE happens.

## Decisions

- DECISION-ONE: the first decision.
- DECISION-TWO: the second decision.

## Constraints

- CONSTRAINT-ONE: use the helpers.
- Expected review: small.
- Out of scope: OOS-ONE, and the rest of it
  OOS-CONT across a second line.
- CONSTRAINT-AFTER: a bullet after Out of scope.

## Build order

1. BUILD-ONE.

## Definition of done

- [ ] DOD-ONE happens.

## Amendments

<!-- Changes to the agreed brief. Each one needs the developer's yes before it takes effect; an
entry without "Agreed by" is not part of the brief. -->

TOP
  if [ "$c" = 5 ]; then printf '%s\n%s\n' "$H5" "$S5"; else printf '%s\n%s\n' "$H4" "$S4"; fi
  if [ $# = 0 ]; then
    if [ "$c" = 5 ]; then printf '%s\n' "$E5"; else printf '%s\n' "$E4"; fi
  else
    for r in "$@"; do printf '%s\n' "$r"; done
  fi
  cat <<'END'

## Builder questions

<!-- Bob writes. Ada answers inline. -->

BQ-ONE

## Builder plan

<!-- Bob writes, for anything beyond a small local change. -->

BP-ONE

Ada: [ ] Approved  [ ] Redirect — see notes below
END
  } > "$B/$f"
}
v() { # v <name> <4|5> <sed script or -> [row ...]
  n=$1; c=$2; e=$3; shift 3
  mk "$n.raw" "$c" "$@"
  if [ "$e" = - ]; then cp "$B/$n.raw" "$B/$n"; else sed -e "$e" "$B/$n.raw" > "$B/$n"; fi
}
DEC='s/DECISION-ONE:/DECISION-1B:/'
DEC2='s/DECISION-TWO:/DECISION-2B:/'
CON='s/CONSTRAINT-ONE:/CONSTRAINT-1B:/'
EXP='s/Expected review: small/Expected review: medium/'
AFT='s/CONSTRAINT-AFTER:/CONSTRAINT-AFTER-B:/'
OUT='s/OUTCOME-ONE/OUTCOME-TWO/'
R_OK='| 27 Oct 2026 | DECISION-ONE → DECISION-1B | Bob found the helper (REVIEW-REQUEST.md) | not needed | Neil |'
R_OK2='| 27 Oct 2026 | CONSTRAINT-ONE → CONSTRAINT-1B | evidence two | not needed (wording only) | Neil |'
R_NEED='| 27 Oct 2026 | DECISION-TWO → DECISION-2B | needs a library | needed: a new dependency | Neil |'
R_NOAG='| 27 Oct 2026 | DECISION-ONE → DECISION-1B | evidence | not needed | |'
R_NOAG2='| 27 Oct 2026 | DECISION-TWO → DECISION-2B | evidence three | not needed | |'
R_EMRA='| 27 Oct 2026 | DECISION-ONE → DECISION-1B | evidence | | Neil |'
R_OLD4='| 27 Oct 2026 | DECISION-ONE → DECISION-1B | evidence | Neil |'
R_PRIOR='| 20 Oct 2026 | old decision → DECISION-TWO | from before the approval | needed: a Decision is reversed | Neil |'
R_OTHER='| 27 Oct 2026 | DECISION-ONE → DECISION-1B | evidence | maybe later | Neil |'
R_BOTH='| 27 Oct 2026 | DECISION-TWO → DECISION-2B | needs a library | needed: a new dependency | |'
# The exact first lines (Amendments row 2).
NOAMEND='reapprove approved section changed with no amendment recorded'
NEED='reapprove needed: a new dependency'
NOAG='reapprove amendment with no Agreed by'
NODEC='reapprove amendment with no re-approval decision'

# ---- Running compare ---------------------------------------------------------------------
cmpb() { # cmpb <label> <approved> <current>
  (cd "$R" && PATH="$SP" "$BASH_BIN" "$PLUGIN/bin/ada-brief" compare "$2" "$3") > "$W/o.$1" 2> "$W/o.$1.err"
  echo $? > "$W/o.$1.rc"
}
first() { head -n 1 "$W/o.$1"; }
rc()    { cat "$W/o.$1.rc"; }
is() { # is <label> <approved> <current> <exact first line>
  cmpb "$1" "$B/$2" "$B/$3"
  t "$1: prints '$4', exit 0 (got: $(first "$1"), exit $(rc "$1"))" test "$(first "$1")|$(rc "$1")" = "$4|0"
}
rows() { # rows <label> [token ...]: after the first line, one '| … |' row per added row, naming each token; nothing else
  l=$1; shift
  tail -n +2 "$W/o.$l" | tr -d '\r' > "$W/o.$l.rows"
  n=$(wc -l < "$W/o.$l.rows" | tr -d ' '); r=0
  case "$(first "$l")" in same|allowed|"reapprove "?*) ;; *) r=1;; esac
  [ "$n" = $# ] || r=1
  if [ "$n" != 0 ] && grep -qvE '^\|.*\|[[:space:]]*$' "$W/o.$l.rows"; then r=1; fi
  for k in "$@"; do grep -qF -- "$k" "$W/o.$l.rows" || r=1; done
  ok $r "$l: $# more line(s) after the first, the added rows as '| … |' (got $n)"
}
info() { cmpb "$1" "$B/$2" "$B/$3"; echo "info: $1: $(first "$1") (exit $(rc "$1"))"; }
crlf() { awk '{ printf "%s\r\n", $0 }' "$B/$1" > "$B/$2"; }

v A5 5 -; v A4 4 -

# 1. same (D1; Builder question 6: trailing whitespace per line, CR included, and trailing blank
#    lines at the end of the file are ignored)
cp "$B/A5" "$B/same_copy"; is same_copy A5 same_copy same; rows same_copy
awk '{ if (/DECISION-ONE|^## |^\| Date|OUTCOME|^Status:/) printf "%s \t \n", $0; else print }' "$B/A5" > "$B/same_ws"
is same_ws A5 same_ws same
is same_ws_back same_ws A5 same
crlf A5 same_crlf;                               is same_crlf A5 same_crlf same
is same_crlf_back same_crlf A5 same
{ cat "$B/A5"; printf '\n\n'; } > "$B/same_eof";  is same_eof_blank_lines A5 same_eof same
{ cat "$B/same_ws"; printf ' \n\t\n\n'; } > "$B/same_all.lf"; crlf same_all.lf same_all
is same_crlf_spaces_and_blank_lines A5 same_all same
cp "$B/A4" "$B/old_copy"; is old_same A4 old_copy same
cmpb template_abs "$PLUGIN/templates/handoff/BRIEF.md" "$PLUGIN/templates/handoff/BRIEF.md"
t "template_abs: the brief template against itself is 'same', exit 0" test "$(first template_abs)|$(rc template_abs)" = "same|0"

# 2. allowed: developer sections only, no row (D2)
v dev_outcome 5 "$OUT";                          is dev_outcome A5 dev_outcome allowed; rows dev_outcome
v dev_oos 5 's/OOS-ONE/OOS-TWO/';                is dev_oos A5 dev_oos allowed
v dev_oos_cont 5 's/OOS-CONT/OOS-CONT-TWO/';     is dev_oos_cont A5 dev_oos_cont allowed
v dev_build 5 's/BUILD-ONE/BUILD-TWO/';          is dev_build A5 dev_build allowed
v dev_dod 5 's/DOD-ONE/DOD-TWO/';                is dev_dod A5 dev_dod allowed
awk '{print} /DOD-ONE/{print "- [ ] DOD-NEW also happens."}' "$B/A5" > "$B/dev_dod_add"; is dev_dod_add A5 dev_dod_add allowed
v dev_bplan 5 's/BP-ONE/BP-TWO/';                is dev_bplan A5 dev_bplan allowed
v dev_bq 5 's/BQ-ONE/BQ-TWO/';                   is dev_bq A5 dev_bq allowed
v dev_status 5 's/^Status: agreed/Status: in build/'; is dev_status A5 dev_status allowed
v dev_header 5 's/^Approval: .*/Approval: re-checked/;s/^Branch: add\/test/Branch: add\/test-two/'; is dev_header A5 dev_header allowed
v dev_all 5 "$OUT;s/OOS-ONE/OOS-TWO/;s/OOS-CONT/OOS-CONT-TWO/;s/BUILD-ONE/BUILD-TWO/;s/DOD-ONE/DOD-TWO/;s/BP-ONE/BP-TWO/;s/BQ-ONE/BQ-TWO/;s/^Status: agreed/Status: in build/"
is dev_all A5 dev_all allowed
v dev_row_only 5 - "$R_OK";                      is dev_row_only A5 dev_row_only allowed; rows dev_row_only 'Bob found the helper'
v dev_crlf.lf 5 "$OUT"; crlf dev_crlf.lf dev_crlf; is dev_outcome_crlf A5 dev_crlf allowed
v ws_dec 5 's/DECISION-ONE: the first decision\./DECISION-ONE: the first decision.  /;s/OUTCOME-ONE/OUTCOME-TWO/'
is trailing_space_in_decisions_plus_outcome A5 ws_dec allowed

# 3. allowed: approved sections changed, every added row agreed and 'not needed' (D3)
v ok_dec 5 "$DEC" "$R_OK";                       is ok_dec A5 ok_dec allowed; rows ok_dec 'Bob found the helper'
v ok_con 5 "$CON" "$R_OK2";                      is ok_con A5 ok_con allowed
v ok_both 5 "$DEC;$CON;$OUT" "$R_OK" "$R_OK2";   is ok_both A5 ok_both allowed; rows ok_both 'Bob found the helper' 'evidence two'
v A5_prior 5 - "$R_PRIOR"
v ok_prior 5 "$DEC" "$R_PRIOR" "$R_OK";          is ok_prior_row_kept A5_prior ok_prior allowed
rows ok_prior_row_kept 'Bob found the helper'
v ok_blank 5 "$DEC" "$R_OK" "$E5";               is ok_extra_empty_row A5 ok_blank allowed; rows ok_extra_empty_row 'Bob found the helper'
v ok_migrated 5 "$DEC" "$R_OK";                  is ok_old_table_then_new A4 ok_migrated allowed
v prior_crlf.lf 5 "$OUT" "$R_PRIOR"; crlf prior_crlf.lf prior_crlf
is prior_row_kept_in_crlf_copy A5_prior prior_crlf allowed; rows prior_row_kept_in_crlf_copy
# Amendments row 4, the known gap: once an agreed 'not needed' row is added, a second approved-
# section change with no row of its own still compares as 'allowed' (rows are not mapped to changes).
v gap 5 "$DEC;$DEC2" "$R_OK";                    is known_gap_second_change_unrecorded A5 gap allowed

# 4. reapprove: a 'needed:' row, or an approved section changed with no row (D4; Amendments 1, 2)
v re_need 5 "$DEC2" "$R_NEED";                   is re_need A5 re_need "$NEED"; rows re_need
v re_need_mixed 5 "$DEC;$DEC2" "$R_OK" "$R_NEED"; is re_need_beside_agreed A5 re_need_mixed "$NEED"
v re_need_row_only 5 - "$R_NEED";                is re_need_row_only A5 re_need_row_only "$NEED"
v re_dec 5 "$DEC";                               is re_dec A5 re_dec "$NOAMEND"; rows re_dec
v re_con 5 "$CON";                               is re_con A5 re_con "$NOAMEND"
v re_expected_review 5 "$EXP";                   is re_expected_review A5 re_expected_review "$NOAMEND"
v re_after_oos 5 "$AFT";                         is re_bullet_after_out_of_scope A5 re_after_oos "$NOAMEND"
v re_empty_rows 5 "$DEC" "$E5" "$E5";            is re_only_empty_rows A5 re_empty_rows "$NOAMEND"
awk '{print} /^- DECISION-TWO/{print "- DECISION-THREE: a new decision."}' "$B/A5" > "$B/re_add"
is re_decision_added A5 re_add "$NOAMEND"
sed -e '/^- DECISION-TWO/d' "$B/A5" > "$B/re_del"; is re_decision_deleted A5 re_del "$NOAMEND"
v re_dec_out 5 "$DEC;$OUT";                      is re_dec_and_outcome A5 re_dec_out "$NOAMEND"
v re_after_prior 5 "$DEC" "$R_PRIOR";            is re_prior_row_only A5_prior re_after_prior "$NOAMEND"
v re_inner 5 's/DECISION-ONE: the first/DECISION-ONE:  the first/'; is re_inner_space_counts A5 re_inner "$NOAMEND"
v re_dec_crlf.lf 5 "$DEC"; crlf re_dec_crlf.lf re_dec_crlf; is re_dec_crlf A5 re_dec_crlf "$NOAMEND"

# 5. reapprove: no 'Agreed by'; no re-approval decision; old four-column rows; no crash (D5; Amendment 2)
v re_noagree 5 "$DEC" "$R_NOAG";                 is re_no_agreed_by A5 re_noagree "$NOAG"; rows re_no_agreed_by
v re_noagree2 5 "$DEC;$DEC2" "$R_OK" "$R_NOAG2"; is re_no_agreed_by_beside_agreed A5 re_noagree2 "$NOAG"
v re_empty_ra 5 "$DEC" "$R_EMRA";                is re_empty_reapproval_cell A5 re_empty_ra "$NODEC"
v re_other_ra 5 "$DEC" "$R_OTHER";               is re_other_reapproval_cell A5 re_other_ra "$NODEC"
v old_row 4 "$DEC" "$R_OLD4";                    is re_old_format_row A4 old_row "$NODEC"
v old_row5 5 "$DEC" "$R_OLD4";                   is re_four_cell_row_in_new_table A5 old_row5 "$NODEC"
v old_dev 4 "$OUT";                              is old_format_developer_change A4 old_dev allowed

# 6. Rows before sections, first failing row in table order (Amendment 1)
v rf_need 5 "$OUT" "$R_NEED";                    is rows_first_needed_outcome_only A5 rf_need "$NEED"
v rf_noag 5 "$OUT" "$R_NOAG";                    is rows_first_no_agreed_by_outcome_only A5 rf_noag "$NOAG"
v rf_old 4 "$OUT" "$R_OLD4";                     is rows_first_old_format_outcome_only A4 rf_old "$NODEC"
v rf_exp 5 "$EXP" "$R_OK" "$R_NEED";             is rows_first_needed_with_review_change A5 rf_exp "$NEED"
v ord_1 5 "$DEC;$DEC2" "$R_NOAG" "$R_NEED";      is order_no_agreed_by_then_needed A5 ord_1 "$NOAG"
v ord_2 5 "$DEC;$DEC2" "$R_NEED" "$R_NOAG";      is order_needed_then_no_agreed_by A5 ord_2 "$NEED"
v ord_3 5 "$DEC;$DEC2" "$R_EMRA" "$R_NEED";      is order_no_decision_then_needed A5 ord_3 "$NODEC"
v ord_4 5 "$DEC;$DEC2" "$R_OK" "$R_OTHER" "$R_NOAG2"; is order_agreed_then_no_decision_then_no_agreed_by A5 ord_4 "$NODEC"
# One row failing two ways: the brief doesn't say which reason. Either is accepted.
v both 5 "$DEC2" "$R_BOTH"; cmpb row_fails_two_ways "$B/A5" "$B/both"
case "$(first row_fails_two_ways)|$(rc row_fails_two_ways)" in "$NEED|0"|"$NOAG|0") r=0;; *) r=1;; esac
ok $r "row_fails_two_ways: '$NEED' or '$NOAG', exit 0 (got: $(first row_fails_two_ways))"
echo "info: row_fails_two_ways printed: $(first row_fails_two_ways)"

# 7. usage and unreadable files: exit 2, stderr 'ada-brief: …', nothing on stdout (D6)
use() { # use <label> [args...]
  l=$1; shift
  (cd "$R" && PATH="$SP" "$BASH_BIN" "$PLUGIN/bin/ada-brief" compare "$@") > "$W/u.$l" 2> "$W/u.$l.err"; r=$?
  e=$(head -n 1 "$W/u.$l.err")
  case "$e" in "ada-brief: "?*) m=0;; *) m=1;; esac
  # Only counts if compare exists: the released script also exits 2, for an unknown subcommand.
  t "$l: exit 2, empty stdout, stderr 'ada-brief: …' (got exit $r: $e)" test "$r|$m|$(wc -c < "$W/u.$l" | tr -d ' ')|$(first same_copy)" = "2|0|0|same"
}
use usage_no_args
use usage_one_arg "$B/A5"
use usage_three_args "$B/A5" "$B/A5" "$B/A5"
use missing_approved "$B/nope.md" "$B/A5"
use missing_current "$B/A5" "$B/nope.md"
use directory_given "$B" "$B/A5"
cp "$B/A5" "$B/locked"; chmod 000 "$B/locked"
if [ -r "$B/locked" ]; then echo "info: unreadable-file case skipped (running as root)"; else use unreadable_current "$B/A5" "$B/locked"; fi
chmod 600 "$B/locked"
(cd "$R" && PATH="$SP" "$BASH_BIN" "$PLUGIN/bin/ada-brief" no-such-subcommand) > /dev/null 2>&1
t "regression: an unknown subcommand still exits 2" test $? = 2

# 8. Across every run
t "no Python traceback on stderr in any run" not grep -q Traceback "$W"/o.*.err "$W"/u.*.err
t "offline: compare never called gh" test ! -s "$W/gh.log"

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0, under bash 5, bash 3.2 and Python 3.8. Record
the `info:` line in the Run record (which reason a row with neither `Agreed by` nor a `not needed`
decision gets; either is accepted). Step 4's broken copy prints `FAIL` for `dev_row_only` and
the `ok_*` cases at least.

The first line is exactly one of (Decisions, as amended by Amendments rows 1 and 2):

- `same`: the files match once each line's trailing whitespace (CR included) and the blank lines
  at the end of the file are ignored (Builder question 6);
- `reapprove needed: <trigger>` (the cell as written), `reapprove amendment with no Agreed by`, or
  `reapprove amendment with no re-approval decision` (an empty or other Re-approval cell, or a
  four-cell row), for the first failing added row in table order, whatever sections changed;
- `reapprove approved section changed with no amendment recorded`, when no added row fails but
  Decisions or Constraints (less `- Out of scope:`) changed and no row was added;
- `allowed`, followed by the added rows, one per line, as `| … |` table rows (Builder question 3).
  After `same` and `reapprove` nothing more is printed.

Sources: the brief's Decisions ("The approved sections", "The decision is one offline
subcommand": "a row is a non-empty data row", "rows are compared as text", exit 0 for all three,
exit 2 on usage or unreadable files), Amendments rows 1 (rows before sections, table order), 2
(the reason lines) and 4 (the known gap, `known_gap_second_change_unrecorded`), Builder questions
3 and 6, its Definition of Done (items 1 to 6), its Surfaces ("an old-format row must read as
needing re-approval, not crash"), and `API.md` (every script exits 2 with `<script>: <message>` on
stderr).

Readings the brief doesn't spell out, asserted here: whitespace inside a line still counts
(`re_inner_space_counts`); three arguments is a usage error.

## Observe at

The script's output and exit code. On a failure the scratch folder is kept: `b/` holds the
briefs (`A5`, `A4` the approved ones, the rest named after their case), `o.<case>` and
`o.<case>.err` what `compare` printed, `gh.log` any `gh` call.

## Regression

An unknown subcommand still exits 2. `who`, `status`, `submit` and `body` are in
`re-approval-column-and-docs.md` and `approval-kept-after-amendment.md`.
