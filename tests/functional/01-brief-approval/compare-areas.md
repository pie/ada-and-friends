# `ada-brief compare` names the areas an amendment sends back; `Areas:` and `Author:` are read

- Area: 01 Brief approval (`ada-brief compare`, `brief_py()`); 12 Platforms (bash 3.2, Python 3.8, CRLF)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198325356405
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-11, 0 failed (267 PASS) on the host (bash 5.3, Python 3.14); Docker and the breaks not run; see the PR

Offline. Every pair of briefs is compared twice, by this checkout's `ada-brief` and by the base
branch's (`origin/add/brief-approval-by-area`). The first line must be the base's, word for word
(`compare()` is unchanged); a brief with `Areas:` whose verdict is `reapprove` gets exactly one
more line, `areas: <slug>, <slug>`; anything else must print what the base prints, byte for byte.
It also runs `brief_py()`'s Python on its own to call `brief_author()`, `brief_areas()` and
`header_value()` directly, and checks the usage header, the CHANGELOG bullet and `bash -n`.

## Precondition

- This checkout (`REPO`) with the change in its working tree, and the base branch in its git
  (`git fetch origin add/brief-approval-by-area`), or an exported base plugin (`BASE_PLUGIN`).
  The script runs both scripts by path, never the `ada-brief` on the PATH.
- `git`, `python3` (3.8+), `awk`, `sed`, `cmp`, `mktemp`, `tar`. No network and no GitHub login: a
  stub `gh` that records every call is put first on the PATH; the script checks it was never
  called.
- The script makes a scratch git repo to run in and writes the briefs outside it, passed by
  absolute path.

The base brief (`A.a`) is the one the Definition of Done names: a header with
`Areas: content, design`, one Decision (`DECISION-ONE`), Constraints (`CONSTRAINT-ONE`, an
`- Out of scope:` bullet), Outcome, Build order, Definition of done, and the five-column Amendments
table with the template's empty row. Each case changes one thing and is built three ways: `.a`
with `Areas: content, design`, `.n` with no `Areas:` line, `.p` with the template's prompt
`Areas: <the areas it touches>`. `.n` and `.p` must print what the base prints.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Under bash 3.2 and under Python 3.8. The containers can't read a worktree's git, so export the
   base on the host first. With `S` the scratch folder holding `check.sh`:

   ```sh
   mkdir -p "$S/base" && git -C <this checkout> archive origin/add/brief-approval-by-area plugins/ada | tar -x -C "$S/base"
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 >/dev/null && git config --global --add safe.directory "*" && REPO=/repo BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
     sh -c 'apt-get -qq update >/dev/null && apt-get -qq install -y git >/dev/null && git config --global --add safe.directory "*" && REPO=/repo BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   ```

   The `info:` lines at the top must say bash 3.2 and Python 3.8 respectively. The check that
   `tests/functional/01-brief-approval/` is unedited needs git, so it prints `info: … skipped`
   in the containers; it runs on the host.
4. Break it on purpose, three times, each with `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`:
   - The base itself: `PLUGIN="$S/base/plugins/ada"`. Every `.a` line that expects an `areas:`
     line, every `brief_py:` result and the usage header line must print `FAIL`; every `.n`,
     `.p` and `(base)` line, and the `.a` lines with no `areas:` line, must still print `PASS`.
   - Copy `plugins/ada` to a scratch folder and run `sed -i.bak 's/catch-all/catchall/g'
     <copy>/bin/ada-brief`. `no_row.a`, `no_row_con.a` and `crlf_no_row.a` must print `FAIL`.
   - On a fresh copy, change the compare code so the second line starts `area:` instead of
     `areas:`. Every `.a` case with an `areas:` line must print `FAIL`.

```bash
#!/bin/bash
# ada-brief compare with Areas: (Unit C), and brief_py()'s brief_author()/brief_areas()/header_value().
# PASS/FAIL per check; exit 1 if any FAIL, 2 on SETUP FAIL.
# Env: REPO, PLUGIN (default $REPO/plugins/ada), BASE (default origin/add/brief-approval-by-area),
#      BASE_PLUGIN (an exported base plugins/ada; default: git archive of BASE from REPO).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE=${BASE:-origin/add/brief-approval-by-area}
BASH_BIN=$(command -v bash)
W=$(mktemp -d)
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }

if [ -n "${BASE_PLUGIN:-}" ]; then BASE_AB=$BASE_PLUGIN/bin/ada-brief
else
  mkdir -p "$W/baseplugin"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/baseplugin" || { echo "SETUP FAIL: cannot export $BASE from $REPO"; exit 2; }
  BASE_AB=$W/baseplugin/plugins/ada/bin/ada-brief
fi
AB=$PLUGIN/bin/ada-brief
[ -f "$BASE_AB" ] || { echo "SETUP FAIL: no base script at $BASE_AB"; exit 2; }
[ -f "$AB" ] || { echo "SETUP FAIL: no script at $AB"; exit 2; }
echo "info: $("$BASH_BIN" --version | head -n 1)"
echo "info: $(python3 --version 2>&1)"
cmp -s "$BASE_AB" "$AB" && echo "info: the script under test is byte-identical to the base's: the areas checks must FAIL"

# A stub gh that records any call: compare must not need GitHub.
mkdir -p "$W/stub"
printf '#!/bin/sh\necho "gh $*" >> "%s/gh.log"\nexit 3\n' "$W" > "$W/stub/gh"
chmod +x "$W/stub/gh"; : > "$W/gh.log"
SP="$W/stub:$PATH"
R=$W/r; mkdir -p "$R"; git init -q "$R"
B=$W/b; mkdir -p "$B"

# ---- The briefs --------------------------------------------------------------------------
mk() { # mk <file> <header line(s) or -> [row ...]: no rows = the template's empty row
  f=$1; a=$2; shift 2
  {
  printf '# Brief — Step 1: Test compare with areas\n\nAsana: https://app.asana.com/0/1/2\n'
  printf 'Branch: add/test\nPR into: next-release\nPlan: none\nShips: independent\n'
  [ "$a" = - ] || printf '%s\n' "$a"
  cat <<'MID'
Status: agreed
Approval: approved by @boss on 5 Nov 2026

## Outcome

OUTCOME-ONE happens.

## Decisions

- DECISION-ONE: the only decision.

## Constraints

- CONSTRAINT-ONE: use the helpers.
- Out of scope: OOS-ONE.

## Build order

1. BUILD-ONE.

## Definition of done

- [ ] DOD-ONE happens.

## Amendments

<!-- Changes to the agreed brief. Each one needs the developer's yes before it takes effect. -->

| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |
|---|---|---|---|---|
MID
  if [ $# = 0 ]; then printf '%s\n' '| | | | | |'; else for r in "$@"; do printf '%s\n' "$r"; done; fi
  printf '\n## Builder questions\n\n<!-- Bob writes. Ada answers inline. -->\n\n## Builder plan\n'
  } > "$B/$f"
}
v() { # v <file> <header or -> <sed script or -> [row ...]
  vn=$1; vh=$2; ve=$3; shift 3
  mk "$vn.raw" "$vh" "$@"
  if [ "$ve" = - ]; then cp "$B/$vn.raw" "$B/$vn"; else sed -e "$ve" "$B/$vn.raw" > "$B/$vn"; fi
}
crlf() { awk '{ printf "%s\r\n", $0 }' "$B/$1" > "$B/$2"; }
HA='Areas: content, design'
HP='Areas: <the areas it touches>'
DEC='s/DECISION-ONE:/DECISION-1B:/'
CON='s/CONSTRAINT-ONE:/CONSTRAINT-1B:/'
OUT='s/OUTCOME-ONE/OUTCOME-TWO/'
R_C='| 5 Nov | a → b | c | needed: content | Neil |'
R_CD='| 5 Nov | a → b | c | needed: content, design | Neil |'
R_D='| 5 Nov | a → b | c | needed: Design | Neil |'
R_DC='| 5 Nov | d → e | f | needed: design, content | Neil |'
R_CP='| 5 Nov | a → b | c | needed: content, price | Neil |'
R_WS='| 5 Nov | a → b | c | needed:  Content ,  design  | Neil |'
R_REV='| 5 Nov | a → b | c | needed: it reverses a Decision | Neil |'
R_PRICE='| 5 Nov | a → b | c | needed: price | Neil |'
R_OK='| 5 Nov | a → b | c | not needed | Neil |'
R_NOAG='| 5 Nov | a → b | c | not needed | |'
R_NEEDNOAG='| 5 Nov | a → b | c | needed: content | |'
R_EMPTY='| 5 Nov | a → b | c | | Neil |'
R_FOUR='| 5 Nov | a → b | c | Neil |'
R_PRIOR='| 1 Nov | x → y | from before the approval | needed: content | Neil |'
# The first lines (compare() is unchanged; API.md, compare row)
NOAMEND='reapprove approved section changed with no amendment recorded'
NOAG='reapprove amendment with no Agreed by'
NODEC='reapprove amendment with no re-approval decision'

# ---- Running compare, base and this checkout --------------------------------------------
run() { # run <script> <out prefix> <approved> <current>
  (cd "$R" && PATH="$SP" "$BASH_BIN" "$1" compare "$3" "$4") > "$2.out" 2> "$2.err"; echo $? > "$2.rc"
}
shown() { tr -d '\r' < "$1" | tr '\n' '|'; }
pair() { # pair <id> <approved> <current> <first line, or = for the base's> <areas value, or - for no line>
  id=$1; ap=$B/$2; cu=$B/$3; f1=$4; ar=$5
  run "$BASE_AB" "$W/b.$id" "$ap" "$cu"; run "$AB" "$W/h.$id" "$ap" "$cu"
  bf=$(head -n 1 "$W/b.$id.out")
  if [ "$f1" = = ]; then f1=$bf; echo "info: $id: the base prints '$bf' (either reason is accepted)"
  else t "$id (base): the base script prints '$f1' first (got: $bf)" test "$bf" = "$f1"; fi
  if [ "$ar" = - ]; then
    t "$id: prints what the base prints, byte for byte, first line '$f1', no 'areas:' line (got: $(shown "$W/h.$id.out"))" \
      sh -c 'cmp -s "$1" "$2" && [ "$(head -n 1 "$1")" = "$3" ] && ! grep -q "^areas:" "$1"' _ "$W/h.$id.out" "$W/b.$id.out" "$f1"
  else
    printf '%s\nareas: %s\n' "$f1" "$ar" > "$W/e.$id"
    t "$id: prints '$f1' then 'areas: $ar', nothing more (got: $(shown "$W/h.$id.out"))" cmp -s "$W/e.$id" "$W/h.$id.out"
  fi
  t "$id: exit 0, stderr as the base's (got exit $(cat "$W/h.$id.rc"))" \
    sh -c '[ "$(cat "$1.rc")" = 0 ] && [ "$(cat "$2.rc")" = 0 ] && cmp -s "$1.err" "$2.err"' _ "$W/h.$id" "$W/b.$id"
}
cas() { # cas <name> <sed or -> <first line or => <areas with Areas: content, design, or -> [row ...]
  cn=$1; ce=$2; cf=$3; ca=$4; shift 4
  v "$cn.a" "$HA" "$ce" "$@"; v "$cn.n" - "$ce" "$@"; v "$cn.p" "$HP" "$ce" "$@"
  pair "$cn.a" A.a "$cn.a" "$cf" "$ca"
  pair "$cn.n" A.n "$cn.n" "$cf" -
  pair "$cn.p" A.p "$cn.p" "$cf" -
}
mk A.a "$HA"; mk A.n -; mk A.p "$HP"

# 1. The Definition of Done's pairs, each with Areas:, without it, and with the template's prompt
cas need_content         -      'reapprove needed: content'                content          "$R_C"
cas need_content_dec     "$DEC" 'reapprove needed: content'                content          "$R_C"
cas need_content_design  "$DEC" 'reapprove needed: content, design'        'content, design' "$R_CD"
cas need_Design          "$DEC" 'reapprove needed: Design'                 design           "$R_D"
cas need_reverses        "$DEC" 'reapprove needed: it reverses a Decision' 'content, design' "$R_REV"
cas need_price           "$DEC" 'reapprove needed: price'                  'content, design' "$R_PRICE"
cas no_row               "$DEC" "$NOAMEND"                                 catch-all
cas no_agreed_by         "$DEC" "$NOAG"                                    'content, design' "$R_NOAG"
cas not_needed           "$DEC" allowed                                    -                "$R_OK"

# 2. Around them: same and allowed get no line; the boundaries of row_areas() and reapprove_areas()
cas same                 -      same                                       -
cas dev_section_only     "$OUT" allowed                                    -
cas no_row_con           "$CON" "$NOAMEND"                                 catch-all
cas needed_rows_union    "$DEC" 'reapprove needed: Design'                 'content, design' "$R_D" "$R_C"
cas row_order_in_cell    "$DEC" 'reapprove needed: design, content'        'content, design' "$R_DC"
cas one_slug_not_listed  "$DEC" 'reapprove needed: content, price'         'content, design' "$R_CP"
cas spaces_in_cell       "$DEC" =                                          'content, design' "$R_WS"
cas not_needed_then_need "$DEC" 'reapprove needed: Design'                 design           "$R_OK" "$R_D"
cas need_then_trigger    "$DEC" 'reapprove needed: content'                'content, design' "$R_C" "$R_REV"
cas empty_reapproval     "$DEC" "$NODEC"                                   'content, design' "$R_EMPTY"
cas four_cell_row        "$DEC" "$NODEC"                                   'content, design' "$R_FOUR"
cas needed_no_agreed_by  "$DEC" =                                          'content, design' "$R_NEEDNOAG"

# 3. Sequences: only rows added since approval count; only the current brief's Areas: counts
v P.a "$HA" - "$R_PRIOR"; v P.n - - "$R_PRIOR"
v prior_then_design.a "$HA" "$DEC" "$R_PRIOR" "$R_D"; v prior_then_design.n - "$DEC" "$R_PRIOR" "$R_D"
pair prior_then_design.a P.a prior_then_design.a 'reapprove needed: Design' design
pair prior_then_design.n P.n prior_then_design.n 'reapprove needed: Design' -
v prior_then_ok.a "$HA" "$DEC" "$R_PRIOR" "$R_OK"
pair prior_then_ok.a P.a prior_then_ok.a allowed -
v new_area "$(printf 'Areas: content, design, price')" "$DEC" "$R_PRICE"
pair new_area_added_in_amendment A.a new_area 'reapprove needed: price' price
v areas_added "$HA" "$DEC" "$R_C"
pair approved_without_areas A.n areas_added 'reapprove needed: content' content
v areas_dropped - "$DEC" "$R_C"
pair current_without_areas A.a areas_dropped 'reapprove needed: content' -
v body_areas - "$DEC;s/^OUTCOME-ONE happens\./OUTCOME-ONE happens.\\
Areas: content, design/" "$R_C"
v body_A - 's/^OUTCOME-ONE happens\./OUTCOME-ONE happens.\
Areas: content, design/'
pair areas_line_after_first_heading body_A body_areas 'reapprove needed: content' -

# 4. Parsing the Areas: line: CRLF, case, spaces, duplicates, order
v messy.lf 'Areas:  Content ,design, content' "$DEC" "$R_REV"; crlf messy.lf messy_crlf
pair crlf_messy_areas A.a messy_crlf 'reapprove needed: it reverses a Decision' 'content, design'
pair lf_messy_areas A.a messy.lf 'reapprove needed: it reverses a Decision' 'content, design'
v crnr.lf 'Areas:  Content ,design, content' "$DEC"; crlf crnr.lf crnr
pair crlf_no_row.a A.a crnr "$NOAMEND" catch-all
crlf need_content.n need_content_crlf.n; crlf A.n A_crlf.n
pair crlf_no_areas.n A_crlf.n need_content_crlf.n 'reapprove needed: content' -
v order 'Areas: design, content' "$DEC" "$R_REV"
pair areas_order_kept A.a order 'reapprove needed: it reverses a Decision' 'design, content'
v order2 'Areas: design, content' "$DEC" "$R_C" "$R_D"
pair union_in_areas_order A.a order2 'reapprove needed: content' 'design, content'
v author.a "$(printf 'Areas: content, design\nAuthor: @NeilPIE')" "$DEC" "$R_OK"
pair author_line_allowed A.a author.a allowed -
v author2.a "$(printf 'Areas: content, design\nAuthor: @NeilPIE')" "$DEC" "$R_C"
pair author_line_reapprove A.a author2.a 'reapprove needed: content' content
# An Areas: line with only commas counts as no line: the base's output, byte for byte, no areas: line
v commas_row.a 'Areas: , ,' "$DEC" "$R_C"
pair commas_only_with_row A.a commas_row.a 'reapprove needed: content' -
v commas_norow.a 'Areas: , ,' "$DEC"
pair commas_only_no_row A.a commas_norow.a "$NOAMEND" -
pair commas_only_both_briefs commas_row.a commas_row.a same -

# 5. brief_py()'s Python on its own: brief_author(), brief_areas(), header_value()
awk '/^brief_py[[:space:]]*\(\)/{p=1} p{print} p&&/^}[[:space:]]*$/{exit}' "$AB" > "$W/brief_py.sh"
if [ -s "$W/brief_py.sh" ] && "$BASH_BIN" -c '. "$1" && brief_py' _ "$W/brief_py.sh" > "$W/brief_py.py" 2> "$W/brief_py.err" \
   && python3 -c 'import ast,sys; ast.parse(open(sys.argv[1], encoding="utf-8").read())' "$W/brief_py.py" 2>> "$W/brief_py.err"; then
  ok 0 "brief_py: a top-level function in ada-brief, printing Python that parses"
  cat > "$W/drive.py" <<'PY'

def _txt(header, crlf=False, body=None):
    lines = ['# Brief — Step 1: Test', '', 'Asana: https://app.asana.com/0/1/2', 'Branch: add/test']
    lines += header + ['Status: agreed', '', '## Outcome', '', 'OUTCOME-ONE happens.']
    lines += ([body] if body else []) + ['', '## Decisions', '', '- DECISION-ONE: the only decision.', '']
    s = '\n'.join(lines) + '\n'
    return s.replace('\n', '\r\n') if crlf else s

_MESSY = 'Areas:  Content ,design, content'
_PROMPT = 'Areas: <the areas it touches>'
_cases = [
    ("brief_author: 'Author: @NeilPIE'", lambda: brief_author(_txt(['Author: @NeilPIE'])), 'neilpie'),
    ("brief_author: 'Author: Neil'", lambda: brief_author(_txt(['Author: Neil'])), None),
    ("brief_author: 'Author: <login>'", lambda: brief_author(_txt(['Author: <login>'])), None),
    ("brief_author: no Author: line", lambda: brief_author(_txt([])), None),
    ("brief_author: 'Author: @NeilPIE', CRLF", lambda: brief_author(_txt(['Author: @NeilPIE'], crlf=True)), 'neilpie'),
    ("brief_author: 'Author:  @NeilPIE ' (spaces round the value)", lambda: brief_author(_txt(['Author:  @NeilPIE '])), 'neilpie'),
    ("brief_author: 'Author: @alice, @bob' (two logins)", lambda: brief_author(_txt(['Author: @alice, @bob'])), None),
    ("brief_author: 'Author: @NeilPIE' only after the first ##", lambda: brief_author(_txt([], body='Author: @NeilPIE')), None),
    ("brief_author: beside an Areas: line", lambda: brief_author(_txt(['Areas: content', 'Author: @NeilPIE'])), 'neilpie'),
    ("brief_areas: '%s'" % _MESSY, lambda: brief_areas(_txt([_MESSY])), ['content', 'design']),
    ("brief_areas: '%s', CRLF" % _MESSY, lambda: brief_areas(_txt([_MESSY], crlf=True)), ['content', 'design']),
    ("brief_areas: 'Areas: design, content' (order kept)", lambda: brief_areas(_txt(['Areas: design, content'])), ['design', 'content']),
    ("brief_areas: 'Areas: , ,' (only commas)", lambda: brief_areas(_txt(['Areas: , ,'])), None),
    ("brief_areas: 'Areas:' with nothing after it", lambda: brief_areas(_txt(['Areas:'])), None),
    ("brief_areas: no Areas: line", lambda: brief_areas(_txt([])), None),
    ("brief_areas: the template's prompt", lambda: brief_areas(_txt([_PROMPT])), None),
    ("brief_areas: an Areas: line only after the first ##", lambda: brief_areas(_txt([], body='Areas: content, design')), None),
    ("header_value: (text, 'Branch')", lambda: header_value(_txt([]), 'Branch'), 'add/test'),
    ("header_value: (text, 'Areas'), no line", lambda: header_value(_txt([]), 'Areas'), None),
    ("header_value: (text, 'Areas'), the template's prompt", lambda: header_value(_txt([_PROMPT]), 'Areas'), None),
]
for _name, _fn, _want in _cases:
    try:
        _got = _fn()
    except Exception as _e:
        _got = 'raised %s: %s' % (type(_e).__name__, _e)
    if type(_got) is type(_want) and _got == _want:
        print('PASS brief_py: %s -> %r' % (_name, _want))
    else:
        print('FAIL brief_py: %s: want %r, got %r' % (_name, _want, _got))
PY
  cat "$W/brief_py.py" "$W/drive.py" | python3 - > "$W/py.out" 2> "$W/py.err"
  cat "$W/py.out"
  n=$(grep -c '^FAIL' "$W/py.out"); fails=$((fails + n))
  t "brief_py: the Python run printed all 20 results, no traceback" sh -c '[ "$(grep -cE "^(PASS|FAIL)" "$1")" = 20 ] && ! grep -q Traceback "$2"' _ "$W/py.out" "$W/py.err"
else
  ok 1 "brief_py: a top-level function in ada-brief, printing Python that parses ($(head -n 1 "$W/brief_py.err"))"
fi

# 6. The usage header, the CHANGELOG bullet, bash -n, the folder unedited
awk 'NR==1{next} /^#/{print; next} {exit}' "$AB" > "$W/head.h"
awk 'NR==1{next} /^#/{print; next} {exit}' "$BASE_AB" > "$W/base.h"
grep -qF 'areas:' "$W/base.h" && echo "info: the base's usage header already says 'areas:'"
t "usage header: documents compare's 'areas:' line ($(wc -l < "$W/head.h" | tr -d ' ') header lines)" grep -qF 'areas:' "$W/head.h"
awk '/^## Unreleased/{u=1; next} u&&/^## /{exit} u&&/^- \*\*Brief approval by area\.\*\*/{p=1; print; next} p&&/^- /{p=0} p&&/^[[:space:]]/{print}' "$REPO/CHANGELOG.md" > "$W/cl"
t "CHANGELOG: Unreleased has the **Brief approval by area.** bullet" test -s "$W/cl"
t "CHANGELOG: the bullet mentions the Areas: line" grep -qF 'Areas:' "$W/cl"
t "CHANGELOG: the bullet mentions the Author: line" grep -qF 'Author:' "$W/cl"
t "bash -n plugins/ada/bin/ada-brief" "$BASH_BIN" -n "$AB"
if git -C "$REPO" rev-parse --verify -q "$BASE" >/dev/null 2>&1; then
  git -C "$REPO" diff --name-only "$BASE" -- tests/functional/01-brief-approval/ > "$W/d01"
  git -C "$REPO" ls-files --others --exclude-standard -- tests/functional/01-brief-approval/ >> "$W/d01"
  # compare-areas.md is new; re-approval-column-and-docs.md's header check changed (Amendment, 10 Nov 2026);
  # area-status.md is new (Unit D1, DoD 1 as amended 2026-11-11)
  grep -vxE 'tests/functional/01-brief-approval/(compare-areas|re-approval-column-and-docs|area-status)\.md' "$W/d01" > "$W/d01.cand"
  # A script whose only change is its 'Last run' line (a re-run records itself) is unedited: compare
  # each other file with $BASE's copy, both with their '- Last run:' lines removed. A real edit still
  # differs; a new file (not in $BASE) still counts as a change.
  : > "$W/d01.other"
  while IFS= read -r f; do
    [ -n "$f" ] || continue
    if git -C "$REPO" show "$BASE:$f" 2>/dev/null | grep -v '^- Last run:' > "$W/d01.base" \
       && grep -v '^- Last run:' "$REPO/$f" > "$W/d01.now" 2>/dev/null && cmp -s "$W/d01.base" "$W/d01.now"; then :; else echo "$f" >> "$W/d01.other"; fi
  done < "$W/d01.cand"
  t "tests/functional/01-brief-approval/: no script but compare-areas.md, re-approval-column-and-docs.md and area-status.md differs from $BASE beyond its Last run line (got: $(tr '\n' ' ' < "$W/d01.other"))" test ! -s "$W/d01.other"
else
  echo "info: the tests/functional/01-brief-approval/ unedited check skipped: no $BASE in $REPO's git"
fi

# 7. Across every run
t "no Python traceback on stderr in any compare run" not grep -q Traceback "$W"/h.*.err
t "offline: compare never called gh" test ! -s "$W/gh.log"

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0, no `SETUP FAIL`, under bash 5, bash 3.2 and
Python 3.8. Record the `info:` lines in the Run record (which reason the base gives for
`needed_no_agreed_by` and `spaces_in_cell`; this checkout must give the same).

For each case `<name>`:

- `<name>.a` (base brief and current brief both with `Areas: content, design`): when the verdict
  is `reapprove`, stdout is exactly two lines, the first line as below and `areas: <slugs>`; for
  `same` and `allowed`, what the base prints and no `areas:` line.
- `<name>.n` (no `Areas:` line) and `<name>.p` (the template's prompt): stdout byte for byte what
  the base branch's script prints for the same pair, and no `areas:` line.
- `(base)`: the base script's first line is the one the brief gives (`compare()` is unchanged).
  A `(base)` line that fails means the expectation is wrong, not the change: report it.
- Every run: exit 0, stderr the same as the base's.

| Case | Added rows (Re-approval cell) | First line | `areas:` (`.a`) |
|---|---|---|---|
| `need_content`, `need_content_dec` | `needed: content` (the DoD's row; without and with a Decision change) | `reapprove needed: content` | `content` |
| `need_content_design` | `needed: content, design` | `reapprove needed: content, design` | `content, design` |
| `need_Design` | `needed: Design` | `reapprove needed: Design` | `design` |
| `need_reverses` | `needed: it reverses a Decision` | `reapprove needed: it reverses a Decision` | `content, design` |
| `need_price` | `needed: price` (not on `Areas:`) | `reapprove needed: price` | `content, design` |
| `no_row`, `no_row_con` | none; a Decision / a Constraint changed | `reapprove approved section changed with no amendment recorded` | `catch-all` |
| `no_agreed_by` | `not needed`, no Agreed by | `reapprove amendment with no Agreed by` | `content, design` |
| `not_needed` | `not needed`, agreed | `allowed`, then the row | none |
| `same` | none, nothing changed | `same` | none |
| `dev_section_only` | none; Outcome changed | `allowed` | none |
| `needed_rows_union` | `needed: Design`, then `needed: content` | `reapprove needed: Design` | `content, design` (`Areas:` order) |
| `row_order_in_cell` | `needed: design, content` | `reapprove needed: design, content` | `content, design` |
| `one_slug_not_listed` | `needed: content, price` | `reapprove needed: content, price` | `content, design` |
| `spaces_in_cell` | `needed:  Content ,  design  ` | the base's | `content, design` |
| `not_needed_then_need` | `not needed`, then `needed: Design` | `reapprove needed: Design` | `design` |
| `need_then_trigger` | `needed: content`, then `needed: it reverses a Decision` | `reapprove needed: content` | `content, design` |
| `empty_reapproval` | empty Re-approval cell | `reapprove amendment with no re-approval decision` | `content, design` |
| `four_cell_row` | a four-cell row | `reapprove amendment with no re-approval decision` | `content, design` |
| `needed_no_agreed_by` | `needed: content`, no Agreed by | the base's | `content, design` |

The pairs outside the table:

- `prior_then_design.a`: the approved brief already had a `needed: content` row; the added row is
  `needed: Design`: `reapprove needed: Design`, `areas: design`. `.n` as the base.
  `prior_then_ok.a`: the added row is `not needed`: `allowed`, as the base.
- `new_area_added_in_amendment`: the current brief's `Areas:` gains `price` with a `needed: price`
  row: `areas: price`.
- `approved_without_areas` (only the current brief has `Areas:`): `areas: content`.
  `current_without_areas` (only the approved one has it): as the base, no `areas:` line.
  `areas_line_after_first_heading` (`Areas:` only in the Outcome): as the base.
- `crlf_messy_areas` (CRLF) and `lf_messy_areas`, `Areas:  Content ,design, content`:
  `areas: content, design`. `crlf_no_row.a`: `areas: catch-all`. `crlf_no_areas.n`: as the base.
- `areas_order_kept` (`Areas: design, content`): `areas: design, content`;
  `union_in_areas_order` (rows `needed: content`, `needed: Design`): `areas: design, content`.
- `commas_only_with_row` (current brief `Areas: , ,`, added row `needed: content`): exactly the
  base's output, `reapprove needed: content` and no `areas:` line. `commas_only_no_row` (same, no
  row, a Decision changed): the base's `reapprove approved section changed with no amendment
  recorded` and no `areas:` line. `commas_only_both_briefs`: `same`, as the base.
- `author_line_allowed`, `author_line_reapprove`: an `Author:` line changes nothing:
  `allowed` as the base, and `areas: content`.
- `brief_py:` lines: `brief_author()` gives `'neilpie'` for `Author: @NeilPIE` (also CRLF, and
  with spaces round the value, and beside an `Areas:` line), `None` for `Author: Neil`,
  `Author: <login>`, no line, two logins, and a line after the first `##`. `brief_areas()` gives
  `['content', 'design']` for `Areas:  Content ,design, content` (LF and CRLF), `['design',
  'content']` for `Areas: design, content`, and `None` with no line, the template's prompt, a
  line after the first `##`, an empty value, or only commas (`Areas: , ,`). `header_value(text, 'Branch')` gives `'add/test'`;
  `header_value(text, 'Areas')` gives `None` with no line and for the prompt.
- The usage header (the comment block at the top of `ada-brief`) contains `areas:`; the
  CHANGELOG's Unreleased has a `- **Brief approval by area.**` bullet mentioning `Areas:` and
  `Author:`; `bash -n` is clean; no file in `tests/functional/01-brief-approval/` but this one,
  `re-approval-column-and-docs.md` (the Amendment of 10 Nov 2026) and `area-status.md` (Unit D1) differs from the base; no traceback; `gh` never called.

Step 4's breaks print `FAIL` where it says.

Sources: the brief's Outcome, Decisions (header lines before the first `##`, `<` means none,
slugs lowercased, order kept, duplicates dropped; `brief_author()`, `brief_areas()`,
`header_value()`; `row_areas()`'s three outcomes; `reapprove_areas()`'s union in `Areas:` order,
`catch-all`, `[]` for `same` and `allowed`; `compare()` unchanged; the `areas:` second line only
on `reapprove` with `Areas:`; only the current brief's `Areas:` counts), Constraints (CRLF
briefs, S100; Python 3.8; offline), and Definition of Done items 2 to 13; `API.md`, the `compare`
row (the first-line words).

Readings the brief doesn't spell out, asserted here: `header_value()` takes the name without its
colon (`'Branch'`); the template's prompt gives `brief_areas()` `None`, as no line does; spaces
round `Author:`'s value don't count; a CRLF brief reads the same through `brief_author()` and
`brief_areas()` called directly (S100); an `Author:` line changes nothing `compare` prints.

## Observe at

The script's output and exit code. On a failure the scratch folder is kept: `b/` holds the
briefs (`A.a`, `A.n`, `A.p`, `P.a`, `P.n` the approved ones, the rest named after their case),
`h.<case>.out|.err|.rc` this checkout's `compare`, `b.<case>.*` the base's, `e.<case>` the
expected stdout, `brief_py.py` and `py.out` the direct calls, `gh.log` any `gh` call.

## Regression

The `.n` and `.p` runs of every case are the regression: without `Areas:` (every brief and draft
PR before Unit E), `compare` prints what it did before. `compare.md` in this folder covers the
first line in depth and must pass unedited; `status` and `submit` on a brief without `Areas:`
are in `../01-brief-approval-areas/submit-and-status-offline-stub-gh.md`.
