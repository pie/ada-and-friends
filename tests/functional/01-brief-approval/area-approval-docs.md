# The docs describe brief approval by area, and their line references to `ada-brief` match it

Unit E of the plan "Brief approval by area": "Approvers never need approval" becomes "an author
needs no approval for the areas they approve, and Trusted approves every area" everywhere; the
README, DEVELOPING, `SYSTEM.md`, `API.md`, `TEST-AREAS.md` and the CHANGELOG describe the area
path with its readers; and every line reference to `bin/ada-brief` in `SYSTEM.md` and `API.md`
points at the line it describes.

- Area: 01 Brief approval (the docs of `ada-brief`); 08 Session flow and 13 Design (the
  TEST-AREAS rows); 11 Templates (the repo `PROJECT.md` template's wording)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198325269356
- Automate as: none (part A is a self-checking shell script; part B is read by a person)
- Last run: 2026-11-23, Part A 87 passed, 0 failed; Part B not run; see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree, and `origin/add/brief-approval-by-area`
  (`BASE`, the branch the step's PR goes into) fetched.
- `git`, `python3` (3.8+), `mktemp`, `awk`. No network, no GitHub login.

## Steps

### Part A: the mechanical checks

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: in a scratch copy of the checkout (`git worktree add`), change one
   line reference in `SYSTEM.md`'s ada-brief section that names a function, e.g. the `:N` after
   `` `read_areas()` ``, to `N+10`, and run `REPO=<the copy> bash check.sh`. That reference's
   line prints `FAIL`.

```bash
#!/bin/bash
# Unit E: the docs for brief approval by area, the line references to ada-brief, and the step's
# scope. PASS/FAIL per check; exit 1 if any FAIL, 2 on SETUP FAIL.
# Env: REPO (this checkout; default the repo of the current folder), BASE (the branch the step's
#      PR goes into; default origin/add/brief-approval-by-area), KEEP=1 (keep the scratch folder).
# bash 3.2 compatible.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
BASE=${BASE:-origin/add/brief-approval-by-area}
cd "$REPO" || { echo "SETUP FAIL: no $REPO"; exit 2; }
git rev-parse -q --verify "$BASE^{commit}" >/dev/null || { echo "SETUP FAIL: no $BASE (git fetch origin)"; exit 2; }
for tool in git python3 mktemp awk; do command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }; done
W=$(mktemp -d)
if [ "${KEEP:-}" = 1 ]; then echo "info: scratch folder $W"; else trap 'rm -rf "$W"' EXIT; fi
fails=0; passes=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; passes=$((passes+1)); else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { local name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
# heading_section <regex> <file>: the lines under the first heading matching regex (lower case,
# any level), up to the next heading of the same or a higher level.
heading_section() { awk -v re="$1" '/^#+ /{ match($0,/^#+/); l=RLENGTH; if(p&&l<=lv) exit; if(!p&&tolower($0)~re){p=1;lv=l;next} } p' "$2"; }
flat() { tr '\n' ' ' | tr -s ' \t' '  '; }
has()  { grep -qiE -- "$2" "$1"; }

# 1. "Approvers never need approval" is gone (Definition of Done 2; Decisions).
grep -rn "Approvers never need approval" plugins/ README.md DEVELOPING.md .claude/team/ > "$W/never"
t "grep -rn 'Approvers never need approval' plugins/ README.md DEVELOPING.md .claude/team/ prints nothing" test ! -s "$W/never"
sed 's/^/      /' "$W/never"
for f in README.md plugins/ada/templates/repo/.claude/team/PROJECT.md .claude/team/PROJECT.md; do
  flat < "$f" > "$W/f"
  t "$f: Trusted approves every area" has "$W/f" 'Trusted.{0,80}every area'
done
# Regression: this repo's own PROJECT.md still reads (begin runs who on it at every step).
bash plugins/ada/bin/ada-brief areas > "$W/o" 2> "$W/e"; c=$?
t "regression: this repo's ada-brief areas exits 0" test "$c" = 0
[ "$c" = 0 ] || sed 's/^/      /' "$W/e"

# 2. README's Brief approval (Definition of Done 10).
heading_section '^## brief approval' README.md | flat > "$W/readme"
t "README: a Brief approval section" test -s "$W/readme"
t "README Brief approval: areas, and how to list them (the table)" has "$W/readme" 'Area \| Slug \| Approved by'
t "README Brief approval: the approved:<slug> labels" has "$W/readme" 'approved:<'
t "README Brief approval: brief-approved still named (a project with no table)" has "$W/readme" 'brief-approved'
t "README Brief approval: the Areas: line of a brief" has "$W/readme" 'Areas:'
t "README Brief approval: who needs approval, an author for the areas they approve" has "$W/readme" '(approve|approves).{0,120}(no approval|need no|needs no|don.t need|doesn.t need)|(no approval|need no|needs no|don.t need|doesn.t need).{0,120}(they|you) approve'
t "README Brief approval: Trusted approves every area" has "$W/readme" 'Trusted.{0,80}every area'
t "README Brief approval: amendments go back by area (needed: <area>)" has "$W/readme" 'needed: <?(area|slug)'
t "README Brief approval: the catch-all" has "$W/readme" 'catch-all'

# 3. DEVELOPING's Brief approval: the area path in ada-brief (Definition of Done 10).
heading_section '^## brief approval' DEVELOPING.md | flat > "$W/dev"
t "DEVELOPING: a Brief approval section" test -s "$W/dev"
t "DEVELOPING Brief approval: ada-brief areas" has "$W/dev" 'ada-brief areas|`areas`'
t "DEVELOPING Brief approval: who <area>" has "$W/dev" 'who <area'
t "DEVELOPING Brief approval: the Areas: line" has "$W/dev" 'Areas:'
t "DEVELOPING Brief approval: the approved:<slug> labels" has "$W/dev" 'approved:<'
t "DEVELOPING Brief approval: status's area lines" has "$W/dev" '`area '

# 4. SYSTEM.md, API.md and TEST-AREAS.md (Definition of Done 11).
SY=.claude/team/SYSTEM.md; AP=.claude/team/API.md; TA=.claude/team/TEST-AREAS.md
heading_section '^### ada-brief' "$SY" | flat > "$W/sys"
for s in 'areas' 'who <area' 'Areas:' 'Author:' 'areas: ' '`area ' '(approval reset: '; do
  t "SYSTEM.md, ada-brief: describes $s" grep -qiF -- "$s" "$W/sys"
done
t "SYSTEM.md, ada-brief: names the skill that reads them (approval.md)" grep -qF 'approval.md' "$W/sys"
awk '/^- Parsing depends on template wording/{p=1} p&&/^$/{exit} p' "$SY" | flat > "$W/parse"
t "SYSTEM.md: 'Parsing depends on template wording' names the Areas: line" grep -qF 'Areas:' "$W/parse"
t "SYSTEM.md: 'Parsing depends on template wording' names the Author: line" grep -qF 'Author:' "$W/parse"
row() { grep -E "^\| \`$1" "$AP" | head -1; }
row 'who ' > "$W/who"; row 'areas`' > "$W/areas"; row 'status ' > "$W/status"; row 'submit ' > "$W/submit"; row 'compare ' > "$W/compare"
t "API.md: the who row no longer says nothing calls who <area> yet" not grep -qiF 'nothing calls `who <area>` yet' "$W/who"
t "API.md: the who row names a reader of who <area> (/ada:begin)" grep -qE '/ada:begin|approval\.md' "$W/who"
t "API.md: the areas row no longer says Nothing reads it yet" not grep -qiF 'nothing reads it yet' "$W/areas"
t "API.md: the areas row names its reader (/ada:begin)" grep -qE '/ada:begin|approval\.md' "$W/areas"
t "API.md: the status row no longer says nothing reads the area lines yet" not grep -qiF 'nothing reads the `area` lines yet' "$W/status"
t "API.md: the submit row has (approval reset: <slugs>)" grep -qF '(approval reset: ' "$W/submit"
t "API.md: the compare row has the areas: line" grep -qF 'areas: ' "$W/compare"
t "API.md: the status row has the Author: line" grep -qF 'Author:' "$W/status"
r01=$(grep -E '^\| 01 ' "$TA"); r08=$(grep -E '^\| 08 ' "$TA"); r13=$(grep -E '^\| 13 ' "$TA")
for s in 'areas' 'who <area' 'Areas:' 'Author:' 'areas:' '`area ' 'approval reset: '; do
  t "TEST-AREAS 01: scope includes $s" sh -c 'printf "%s\n" "$1" | grep -qiF -- "$2"' x "$r01" "$s"
done
t "TEST-AREAS 08: scope includes approval by area" sh -c 'printf "%s\n" "$1" | grep -qiE "by area|Areas:|area approval|areas"' x "$r08"
t "TEST-AREAS 13: scope includes the design area's approvers" sh -c 'printf "%s\n" "$1" | grep -qiE "design area|approver"' x "$r13"

# 5. CHANGELOG (Definition of Done 12; S105).
awk '/^## Unreleased/ { u = 1; next } u && /^## / { exit } u' CHANGELOG.md > "$W/unrel"
awk '/\*\*Brief approval by area\.\*\*/ { b = 1; print; next } b && /^- / { exit } b && /^$/ { exit } b' "$W/unrel" | flat > "$W/bullet"
t "CHANGELOG: the **Brief approval by area.** bullet under Unreleased" test -s "$W/bullet"
t "CHANGELOG bullet: no longer says nothing calls the new commands yet" not grep -qiF 'nothing calls the new commands yet' "$W/bullet"
t "CHANGELOG bullet: setting up areas (the table in PROJECT.md)" grep -qF 'PROJECT.md' "$W/bullet"
t "CHANGELOG bullet: onboarding asks for the areas" grep -qiE 'onboard' "$W/bullet"
t "CHANGELOG bullet: Ada writes a brief's Areas: line" grep -qiE 'Ada.{0,200}Areas:|Areas:.{0,200}Ada' "$W/bullet"
t "CHANGELOG bullet: what approvers do (the approved:<slug> label)" grep -qF 'approved:<slug>' "$W/bullet"
t "CHANGELOG bullet: Iris's designs go to the design area's approvers" grep -qiE 'design.{0,120}approver' "$W/bullet"
t "CHANGELOG: no new version heading (P31)" not sh -c "git diff '$BASE' -- CHANGELOG.md | grep -Eq '^\+## +v?[0-9]'"
t "plugin.json and marketplace.json unchanged (P31)" test -z "$(git diff "$BASE" -- plugins/ada/.claude-plugin/plugin.json .claude-plugin/marketplace.json)"

# 6. Scope: no script changes (Constraints, Out of scope: script changes are Units A to D2).
git diff --name-only "$BASE" -- plugins/ada/bin .github > "$W/scripts"
git ls-files --others --exclude-standard -- plugins/ada/bin .github >> "$W/scripts"
t "no change under plugins/ada/bin or .github" test ! -s "$W/scripts"
sed 's/^/      /' "$W/scripts"
t "git diff --check against $BASE" git diff --check "$BASE"

# 7. Line references to ada-brief in SYSTEM.md and API.md match the file (Definition of Done,
#    Amendment 2026-11-23). Every `:N` or `:N-M` in SYSTEM.md's ada-brief section and API.md's
#    ada-brief section, and every bin/ada-brief:N anywhere in the two files: N <= M <= the file's
#    length. Where a function or subcommand in backticks comes right before the reference
#    (`policy()` (`:147-164`), `area_report`, `:791-843`), its name is on line N, N+1 or N+2.
#    The rest are printed as info lines for a person to read (Part B).
python3 - plugins/ada/bin/ada-brief "$SY" "$AP" > "$W/refs" <<'PY'
import re, sys
src = open(sys.argv[1], encoding='utf-8').read().split('\n')
if src and src[-1] == '':
    src = src[:-1]
n = len(src)
subs = {'who', 'areas', 'submit', 'status', 'compare', 'body', 'ready', 'overlap'}
def section(lines, start):
    out, on = [], False
    for i, l in enumerate(lines):
        if on and re.match(r'#{1,3} ', l):
            break
        if l.startswith(start):
            on = True
        if on:
            out.append((i + 1, l))
    return out
def refs(path, start):
    lines = open(path, encoding='utf-8').read().split('\n')
    seen = set()
    found = []
    for num, l in section(lines, start):
        for m in re.finditer(r'`:(\d+)(?:-(\d+))?`', l):
            found.append((num, l, m))
    for i, l in enumerate(lines):
        for m in re.finditer(r'bin/ada-brief:(\d+)(?:-(\d+))?', l):
            found.append((i + 1, l, m))
    for num, l, m in found:
        key = (num, m.start())
        if key in seen:
            continue
        seen.add(key)
        a = int(m.group(1)); b = int(m.group(2) or m.group(1))
        tag = '%s:%d %s' % (path.split('/')[-1], num, m.group(0).strip('`'))
        if not (1 <= a <= b <= n):
            print('FAIL %s: outside ada-brief (%d lines)' % (tag, n)); continue
        pre = re.search(r'`([^`]+)`[ ,(]{0,3}$', l[:m.start()])
        name = None
        if pre:
            w = re.match(r'[A-Za-z_][\w-]*', pre.group(1))
            if w and ('()' in pre.group(1) or '_' in w.group(0) or w.group(0) in subs):
                name = w.group(0)
        if name:
            window = '\n'.join(src[a - 1:a + 2])
            if re.search(r'(^|[^\w-])' + re.escape(name) + r'([^\w-]|$)', window):
                print('PASS %s: %s is at line %d' % (tag, name, a))
            else:
                print('FAIL %s: %s is not on lines %d-%d (line %d: %s)' % (tag, name, a, a + 2, a, src[a - 1].strip()[:70]))
        else:
            print('INFO %s: line %d: %s' % (tag, a, src[a - 1].strip()[:90]))
refs(sys.argv[2], '### ada-brief')
refs(sys.argv[3], '### `ada-brief`')
PY
while IFS= read -r line; do
  case "$line" in
    PASS*) ok 0 "line reference ${line#PASS }" ;;
    FAIL*) ok 1 "line reference ${line#FAIL }" ;;
    INFO*) echo "info: line reference ${line#INFO }" ;;
  esac
done < "$W/refs"
t "line references: at least one named reference was checked" grep -q '^PASS' "$W/refs"

echo "$passes passed, $fails failed"
[ "$fails" = 0 ] || exit 1
exit 0
```

### Part B: read by a person

1. For each `info: line reference …` line part A prints (a reference with no function or
   subcommand right before it), open `plugins/ada/bin/ada-brief` at that line and read the
   sentence in `SYSTEM.md` or `API.md` around the reference. Write down any whose lines don't do
   what the sentence says. (Definition of Done, Amendment 2026-11-23.)
2. README, Brief approval: a reader new to the project can tell (a) how a project sets up its
   areas (the table, or `/ada:onboard`), (b) who needs approval for which area (an author needs
   none for the areas they approve; Trusted approves every area), (c) what an approver does (adds
   `approved:<slug>`; `brief-approved` in a project with no table), and (d) that an amendment goes
   back only to the areas it touches. Plain British English, short sentences (S107).
3. DEVELOPING, Brief approval: the area path through `ada-brief` (`areas`, `who <area>...`,
   `Areas:` and `Author:`, the `approved:<slug>` labels, `status`'s `area` lines, `submit`'s
   `(approval reset: <slugs>)`).
4. The CHANGELOG's **Brief approval by area.** bullet tells a user how to set up areas and what
   changes for approvers, and doesn't contradict README.

## Expected

Part A: every line `PASS`; the last line `<n> passed, 0 failed`; exit 0. Part B: nothing written
down under 1; 2 to 4 all found. Put the number of `info: line reference` lines in the Run record.

Sources, all Unit E's brief (task 1220198325269356):

- Section 1: Definition of Done 2 (the grep prints nothing) and Decisions ("Approvers never need
  approval" goes everywhere: an author needs no approval for the areas they approve, and
  Trusted approves every area). This repo's `ada-brief areas` still exits 0: begin reads
  `PROJECT.md` here at every step.
- Sections 2 and 3: Definition of Done 10.
- Section 4: Definition of Done 11: `SYSTEM.md` and `API.md` describe `areas`, `who <area>`, the
  `Areas:` / `Author:` lines, `compare`'s `areas:` line, the `area` status lines and
  `(approval reset: <slugs>)`, with their readers (now the begin skill's `approval.md`, so the
  "nothing calls / reads … yet" notes go); `TEST-AREAS.md` area 01's scope includes them; Files
  expected to change (TEST-AREAS areas 08 and 13); Constraints, `SYSTEM.md` "Parsing depends on
  template wording" (S104: the template's `Areas:` and `Author:` lines are now parsed).
- Section 5: Definition of Done 12 and Constraints (S105: the bullet extended for users: how to
  set areas, what approvers do); Outcome (onboarding asks for the areas; Iris's designs go to
  the design area's approvers); P31 (no version change).
- Section 6: Constraints, Out of scope (script changes are Units A to D2).
- Section 7: Definition of Done, Amendment 2026-11-23 (agreed by Neil Miller).

## Observe at

The script's output and exit code; for part B, the files named.

## Regression

This repo's `ada-brief areas` (section 1); no change to `plugins/ada/bin/` or `.github/`
(section 6); no version moves (section 5).
