# The brief and `PROJECT.md` templates carry the areas, and the scripts still read them

Unit E of the plan "Brief approval by area": the brief template gains `Areas:` and `Author:`
header lines and an Amendments comment saying `needed: <area>[, <area>]`; the repo `PROJECT.md`
template's Brief approval section gains the areas table. `ada-brief body`, `compare` and `areas`,
`ada-plan check` and `briefs`, and `ada-onboard check` must still read what is made from them.

- Area: 11 Templates; 01 Brief approval (`body`, `compare`, `areas`, `who <area>`); 03 Plans
  (`check`, `briefs`); 02 Onboarding (`files`, `check`)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198325269356
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-23, 50 passed, 0 failed on the host, under bash 3.2 and under Python 3.8 (Docker); both breaks fail as expected; see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree. Commit `4b20da4` (`TBASE`, on
  `next-release`: the brief template's last change before Unit E) in its git.
- `git`, `python3` (3.8+), `mktemp`, `awk`, `cmp`. No network and no GitHub login: a stand-in
  `gh`, logged in as `tester`, with no PRs, goes first on the PATH.
- The script makes its own scratch repos and runs the scripts by path from `PLUGIN`, never the
  `ada-*` on the PATH.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`. A few seconds.
3. Under bash 3.2 and Python 3.8 (Docker), with `S` the scratch folder holding `check.sh`. A
   container can't read a worktree's git, so export the template before Unit E on the host first:

   ```sh
   git -C <this checkout> show 4b20da4:plugins/ada/templates/handoff/BRIEF.md > "$S/brief.before"
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 >/dev/null && REPO=/repo BRIEF_BEFORE=/s/brief.before bash /s/check.sh'
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
     sh -c 'apt-get -qq update >/dev/null && apt-get -qq install -y git >/dev/null && REPO=/repo BRIEF_BEFORE=/s/brief.before bash /s/check.sh'
   ```

   The first `info:` line names the bash and Python; the PASS/FAIL lines are the same as on the
   host.
4. Break it on purpose, twice, each in a fresh copy of `plugins/ada` in a scratch folder, run as
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`:
   - In the copy's `templates/handoff/BRIEF.md`, change `- Out of scope:` to `- Not in scope:`.
     "nothing changed since 4b20da4 but …", "body T1 shows: gadgets" and the body T2 / T3
     regression print `FAIL`.
   - In the copy's `templates/handoff/BRIEF.md`, change the `Areas:` line's value to `none`
     (no `<`). "the Areas: value is a <...> prompt" prints `FAIL`.

```bash
#!/bin/bash
# Unit E: the BRIEF and PROJECT templates with areas, as ada-brief, ada-plan and ada-onboard read
# them. PASS/FAIL per check; exit 1 if any FAIL, 2 on SETUP FAIL.
# Env: REPO (this checkout; default the repo of the current folder), PLUGIN (the plugin under test;
#      default $REPO/plugins/ada), TBASE (a commit whose BRIEF template is the one before Unit E;
#      default 4b20da4, on next-release), BRIEF_BEFORE (that template as a file, for a container,
#      which can't read a worktree's git), KEEP=1 (keep the scratch folder).
# bash 3.2 compatible: no mapfile, no [[ =~ ]], no ${x,,}, no associative arrays.
set -u
unset ADA_BRIEF_REQUIRE
for tool in git python3 mktemp awk cmp; do
  command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }
done
REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
TBASE=${TBASE:-4b20da4}
BASH_BIN=$(command -v bash)
W=$(mktemp -d)
if [ "${KEEP:-}" = 1 ]; then echo "info: scratch folder $W"; else trap 'rm -rf "$W"' EXIT; fi
echo "info: $("$BASH_BIN" --version | head -1); $(python3 --version 2>&1)"
BT=$PLUGIN/templates/handoff/BRIEF.md
PT=$PLUGIN/templates/repo/.claude/team/PROJECT.md
for f in "$BT" "$PT" "$PLUGIN/bin/ada-brief" "$PLUGIN/bin/ada-plan" "$PLUGIN/bin/ada-onboard"; do
  [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }
done
if [ -n "${BRIEF_BEFORE:-}" ]; then
  cp "$BRIEF_BEFORE" "$W/brief.before" || { echo "SETUP FAIL: no $BRIEF_BEFORE"; exit 2; }
else
  git -C "$REPO" show "$TBASE:plugins/ada/templates/handoff/BRIEF.md" > "$W/brief.before" 2>/dev/null \
    || { echo "SETUP FAIL: no BRIEF template at $TBASE"; exit 2; }
fi

fails=0; passes=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; passes=$((passes+1)); else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { local name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
G() { git -c user.name=t -c user.email=t@example.invalid -c init.defaultBranch=main "$@"; }
show() { echo "      exit $(cat "$W/c"); stdout:"; sed 's/^/        | /' "$W/o"; echo "      stderr:"; sed 's/^/        | /' "$W/e"; }
# is <name> <exit> <stdout>: the last run gave exactly that exit code and stdout ('' for none).
is() { if [ -z "$3" ]; then : > "$W/want"; else printf '%s\n' "$3" > "$W/want"; fi
  if [ "$(cat "$W/c")" = "$2" ] && cmp -s "$W/want" "$W/o"; then ok 0 "$1"; else ok 1 "$1"; echo "      expected exit $2; stdout:"; sed 's/^/        | /' "$W/want"; show; fi; }

# A stand-in gh, logged in as "tester", with no PRs: api user, auth status, pr list, and pr view
# (which says what the real gh says when the branch has no PR). Anything else fails.
mkdir -p "$W/gh"
cat > "$W/gh/gh" <<'EOF'
#!/bin/sh
case "$1 ${2:-}" in
  'api user') echo tester; exit 0;;
  'auth status') exit 0;;
  'pr list') echo '[]'; exit 0;;
  'pr view') echo "no pull requests found for branch \"${3:-}\"" >&2; exit 1;;
esac
echo "STUB-GAP: gh $*" >&2
exit 3
EOF
chmod +x "$W/gh/gh"
SP="$W/gh:$PATH"
# run <dir> <script> <args...>: this checkout's script in <dir>, the stand-in gh first on the PATH.
run() { local d=$1 s=$2; shift 2
  (cd "$d" && PATH="$SP" exec "$BASH_BIN" "$PLUGIN/bin/$s" "$@") > "$W/o" 2> "$W/e"; echo $? > "$W/c"
  cat "$W/e" >> "$W/stderr.all"; }
: > "$W/stderr.all"

# 1. The BRIEF template (Definition of Done 3; S104, Unit C's parser reads a header line).
awk '/^## /{exit} {print}' "$BT" > "$W/hdr"
t "BRIEF template: one Areas: line before the first ## heading" test "$(grep -c '^Areas:' "$W/hdr")" = 1
t "BRIEF template: one Author: line before the first ## heading" test "$(grep -c '^Author:' "$W/hdr")" = 1
t "BRIEF template: no Areas: or Author: line after the header" test "$(grep -cE '^(Areas|Author):' "$BT")" = 2
t "BRIEF template: the Areas: value is a <...> prompt (Unit C reads a < value as none)" grep -qE '^Areas: <.*>$' "$W/hdr"
t "BRIEF template: the Author: value is a <...> prompt" grep -qE '^Author: <.*>$' "$W/hdr"
next_line() { awk -v k="$1" 'f { print; exit } index($0, k) == 1 { f = 1 }' "$2"; }
t "BRIEF template: the line after Approval: is the Areas: line" sh -c "printf '%s\n' \"\$(awk 'f{print;exit} index(\$0,\"Approval:\")==1{f=1}' '$BT')\" | grep -q '^Areas:'"
t "BRIEF template: the Approval: prompt has the per-area form (approved: <area> by @x ...; <area> not needed (@y approves it))" grep -qE '^Approval: .*approved: [^ ]+ by @.*not needed \(@[^ )]* approves it\)' "$W/hdr"
t "BRIEF template: the line after Areas: is the Author: line" sh -c "printf '%s\n' \"\$(awk 'f{print;exit} index(\$0,\"Areas:\")==1{f=1}' '$BT')\" | grep -q '^Author:'"
amcomment() { awk '/^## Amendments/ { a = 1; next } a && /^## / { exit } a && /<!--/ { c = 1 } c { print } c && /-->/ { exit }' "$1"; }
amcomment "$BT" | tr '\n' ' ' | tr -s ' ' > "$W/amc"
t "BRIEF template: the Amendments comment says needed: <area>[, <area>]" grep -qF 'needed: <area>[, <area>]' "$W/amc"
t "BRIEF template: the Amendments comment still says not needed" grep -qF 'not needed' "$W/amc"
echo "info: the Amendments comment still says 'needed: <trigger>': $(grep -cF 'needed: <trigger>' "$W/amc")"
# Nothing else in the template moves: undo this step's four changes and the template is the one
# at TBASE, byte for byte. (render_body parses the title, Plan:, Overlaps:, the Constraints
# bullets and the Definition of Done items: SYSTEM.md, "Parsing depends on template wording".)
python3 - "$W/brief.before" "$BT" > "$W/brief.undone" <<'PY'
import sys
before = open(sys.argv[1], encoding='utf-8').read().split('\n')
new = open(sys.argv[2], encoding='utf-8').read().split('\n')
first = next((i for i, l in enumerate(new) if l.startswith('## ')), len(new))
new = [l for i, l in enumerate(new) if not (i < first and (l.startswith('Areas:') or l.startswith('Author:')))]
# The Approval: prompt gains the per-area form (Builder plan, item 1, settled by Ada): put back
# the line as it was before Unit E.
old_approval = next((l for l in before if l.startswith('Approval:')), None)
new = [old_approval if (i < first and l.startswith('Approval:') and old_approval is not None) else l for i, l in enumerate(new)]
def span(lines):
    if '## Amendments' not in lines:
        return None
    i = lines.index('## Amendments') + 1
    while i < len(lines) and '<!--' not in lines[i]:
        if lines[i].startswith('## '):
            return None
        i += 1
    j = i
    while j < len(lines) and '-->' not in lines[j]:
        j += 1
    return (i, j) if j < len(lines) else None
a, b = span(new), span(before)
if a and b:
    new = new[:a[0]] + before[b[0]:b[1] + 1] + new[a[1] + 1:]
sys.stdout.write('\n'.join(new))
PY
t "BRIEF template: nothing changed since $TBASE but the Approval:, Areas: and Author: lines and the Amendments comment" cmp -s "$W/brief.before" "$W/brief.undone"
cmp -s "$W/brief.before" "$W/brief.undone" || { echo "      diff (before, after undoing this step's lines):"; diff "$W/brief.before" "$W/brief.undone" | sed 's/^/        /'; }

# fill <template> <out> <Areas value or -> <Author value or -> [<row>]: a brief drafted from the
# template the way a developer fills it in. '-' leaves that line as the template has it. <row>
# is added to the Amendments table.
fill() { python3 - "$@" <<'PY'
import sys
src, out, areas, author = sys.argv[1:5]
row = sys.argv[5] if len(sys.argv) > 5 else ''
lines = open(src, encoding='utf-8').read().split('\n')
header = {'Asana': 'https://app.asana.com/0/1/1219000000000001', 'Branch': 'add/widgets',
          'Worktree': '.claude/worktrees/widgets', 'PR into': 'next-release', 'Plan': 'none',
          'Ships': 'independent', 'Overlaps': 'none', 'Status': 'agreed',
          'Approval': 'awaiting @client'}
if areas != '-': header['Areas'] = areas
if author != '-': header['Author'] = author
first = next(i for i, l in enumerate(lines) if l.startswith('## '))
res, sect, i = [], '', 0
while i < len(lines):
    l = lines[i]
    if i == 0 and l.startswith('# Brief'):
        res.append('# Brief — Step 1: Widgets'); i += 1; continue
    if i < first and ':' in l and l.split(':', 1)[0] in header:
        res.append(l.split(':', 1)[0] + ': ' + header[l.split(':', 1)[0]]); i += 1; continue
    if l.startswith('## '):
        sect = l[3:].strip().lower()
    if sect == 'outcome' and l == '## Outcome':
        res += [l, '', 'Widgets go out.']; i += 1; continue
    if sect == 'decisions' and l == '-':
        res.append('- Use the existing helper for widgets.'); i += 1; continue
    if sect == 'constraints' and l.startswith('- Expected review:'):
        res.append('- Expected review: small.'); i += 1
        while i < len(lines) and lines[i].startswith('  '): i += 1
        continue
    if sect == 'constraints' and l.startswith('- Out of scope:'):
        res.append('- Out of scope: gadgets.'); i += 1; continue
    if sect == 'build order' and l == '1.':
        res.append('1. Build the widgets.'); i += 1; continue
    if sect == 'definition of done' and l == '- [ ]':
        res.append('- [ ] Widgets show on the page.'); i += 1; continue
    if sect == 'amendments' and row and l.startswith('|') and not (i + 1 < len(lines) and lines[i + 1].startswith('|')):
        res += [l, row]; i += 1; continue
    res.append(l); i += 1
open(out, 'w', encoding='utf-8').write('\n'.join(res))
PY
}

# 2. A brief drafted from the template and committed: body and compare (Definition of Done 3;
#    Surfaces: ada-brief body on a brief drafted from the new template).
R=$W/r; mkdir -p "$R/.claude/team" "$R/.claude/briefs"; G init -q "$R"
STARTER=$(printf '%s\n' \
  '| Area | Slug | Approved by |' '|---|---|---|' \
  '| Wording and content | content | @client |' \
  '| Design and UI | design | @client |' \
  '| Price | price | Approvers |' \
  '| System behaviour (catch-all) | system | Approvers |' \
  '| Fragile areas | fragile | Approvers |' \
  '| Security, auth and payments | security | Approvers |' \
  '| New dependency, data repair or migration | dependencies | Approvers |')
{ printf '# Scratch\n\n## Code and branches\n\n- **Branch from:** `main`. **PR into:** `next-release`.\n\n'
  printf '## Brief approval\n\n- **Approvers:** @neil\n- **Trusted:** none\n\n%s\n' "$STARTER"; } > "$R/.claude/team/PROJECT.md"
ROW='| 2026-11-23 | The button text: Buy → Order now | The client asked | needed: content | Neil |'
B=.claude/briefs
fill "$BT" "$R/$B/T1.md" content @tester
fill "$BT" "$R/$B/T1copy.md" content @tester
fill "$BT" "$R/$B/T1row.md" content @tester "$ROW"
fill "$BT" "$R/$B/T2.md" - -                       # Areas: and Author: left as the template's prompts
fill "$BT" "$R/$B/T2row.md" - - "$ROW"
fill "$W/brief.before" "$R/$B/T3.md" - -           # the template before Unit E, filled the same way
printf '  \n\n' >> "$R/$B/T1copy.md"
mkdir -p "$W/old"; cp "$R/$B/T3.md" "$W/old/T3.md"
G -C "$R" checkout -q -b add/widgets && G -C "$R" add -A && G -C "$R" commit -q -m briefs || { echo "SETUP FAIL: scratch repo"; exit 2; }
t "drafted brief T1: Areas: content" grep -qx 'Areas: content' "$R/$B/T1.md"
t "drafted brief T1: Author: @tester" grep -qx 'Author: @tester' "$R/$B/T1.md"
run "$R" ada-brief body T1; cp "$W/o" "$W/body.T1"
t "body T1 (Areas: content): exit 0" test "$(cat "$W/c")" = 0
t "body T1: starts with the ada-brief marker" grep -q '^<!-- ada-brief: .claude/briefs/T1.md -->' "$W/body.T1"
for s in 'Widgets go out.' 'Use the existing helper for widgets.' 'gadgets' 'Widgets show on the page.'; do
  t "body T1 shows: $s" grep -qF -- "$s" "$W/body.T1"
done
[ "$(cat "$W/c")" = 0 ] || show
t "body T1 lists the approved:content label (render_body reads the template's Areas: line)" grep -qF 'approved:content' "$W/body.T1"
run "$R" ada-brief body T2; sed 's/T2\.md/TX.md/' "$W/o" > "$W/body.T2"; echo "$(cat "$W/c")" > "$W/c.T2"
run "$R" ada-brief body T3; sed 's/T3\.md/TX.md/' "$W/o" > "$W/body.T3"
t "body T2 (Areas: and Author: as prompts): exit 0" test "$(cat "$W/c.T2")" = 0
for s in 'Widgets go out.' 'Use the existing helper for widgets.' 'gadgets' 'Widgets show on the page.'; do
  t "body T2 shows: $s" grep -qF -- "$s" "$W/body.T2"
done
t "regression: body T2 (the new template, prompts left) is body T3's (the old template), byte for byte" cmp -s "$W/body.T3" "$W/body.T2"
cmp -s "$W/body.T3" "$W/body.T2" || diff "$W/body.T3" "$W/body.T2" | sed 's/^/        /'
run "$R" ada-brief compare "$B/T1.md" "$B/T1copy.md"
is "compare of two drafts (trailing blank lines on one): same" 0 same
run "$R" ada-brief compare "$B/T1.md" "$B/T1.md"
is "compare of a draft with itself: same" 0 same
run "$R" ada-brief compare "$B/T1.md" "$B/T1row.md"
is "compare, Areas: content, a row needed: content: reapprove and areas: content" 0 "$(printf 'reapprove needed: content\nareas: content')"
run "$R" ada-brief compare "$B/T2.md" "$B/T2row.md"
is "compare, Areas: left as the template's prompt: one line, no areas: line" 0 'reapprove needed: content'

# 3. Plans (Definition of Done 8; Surfaces: ada-plan check and ada-plan briefs with the new template).
M="$W/plan repo"; O="$W/origin.git"
git init -q --bare "$O"
G init -q "$M" && mkdir -p "$M/.claude/team" && printf '.claude/handoff/\n.claude/worktrees/\n' > "$M/.gitignore"
cp "$R/.claude/team/PROJECT.md" "$M/.claude/team/PROJECT.md"
G -C "$M" add -A && G -C "$M" commit -q -m init && G -C "$M" branch next-release \
  && G -C "$M" remote add origin "$O" && G -C "$M" push -q origin main next-release && G -C "$M" fetch -q origin \
  || { echo "SETUP FAIL: plan repo"; exit 2; }
run "$M" ada-handoff-init
run "$M" ada-plan init
t "ada-plan init: exit 0" test "$(cat "$W/c")" = 0
plan() { { printf '# Plan: Checkout\n\nEpic: https://app.asana.com/0/1/1219000000000009\nStatus: agreed\n\n'
  printf '## Outcome\n\nCheckout reads better.\n\n## Units\n\n%s\n' "$1"
  printf '\n## Amendments\n\n| Date | What changed | Why | Agreed by |\n|---|---|---|---|\n| | | | |\n'; } > "$M/.claude/handoff/plan/PLAN.md"; }
plan "$(printf '%s\n' '| ID | Unit | Task | Branch | Parent | Ships |' '|---|---|---|---|---|---|' \
  '| A | Checkout copy (content) | 1219000000000001 | add/checkout-copy | | independent |' \
  '| B | Delivery charge (price, system) | 1219000000000002 | add/delivery-charge | | after Unit A |')"
run "$M" ada-plan check
t "ada-plan check, a Unit cell 'Checkout copy (content)': exit 0" test "$(cat "$W/c")" = 0
t "ada-plan check: plan ...: 2 units" grep -qE '^plan .*: 2 units$' "$W/o"
t "ada-plan check: no problems" not grep -qi 'problem' "$W/o" "$W/e"
[ "$(cat "$W/c")" = 0 ] || show
run "$M" ada-plan briefs
t "ada-plan briefs: exit 0" test "$(cat "$W/c")" = 0
D1="$M/.claude/handoff/plan/1219000000000001.md"
t "ada-plan briefs: drafted 1219000000000001.md" test -f "$D1"
t "the drafted unit brief has an Areas: line" grep -q '^Areas:' "$D1"
t "the drafted unit brief has an Author: line" grep -q '^Author:' "$D1"
[ -f "$D1" ] && cp "$D1" "$M/d1copy.md"
run "$M" ada-brief compare .claude/handoff/plan/1219000000000001.md d1copy.md
is "compare of a drafted unit brief and its copy: same" 0 same
# Regression: the areas never become a column (ada-plan check requires the six columns).
plan "$(printf '%s\n' '| ID | Unit | Areas | Task | Branch | Parent | Ships |' '|---|---|---|---|---|---|---|' \
  '| A | Checkout copy | content | 1219000000000001 | add/checkout-copy | | independent |')"
run "$M" ada-plan check
t "regression: ada-plan check, an Areas column: exit 2" test "$(cat "$W/c")" = 2

# 4. The repo PROJECT.md template and the starter set (Definition of Done 4; Surfaces:
#    ada-onboard check on a project made from the new template).
awk '/^## Brief approval/ { p = 1; next } p && /^## / { exit } p' "$PT" > "$W/ptsect"
t "PROJECT template: the Brief approval section has the header | Area | Slug | Approved by | (Unit B's)" grep -qx '| Area | Slug | Approved by |' "$W/ptsect"
t "PROJECT template: keeps the - **Approvers:** line" grep -q '^- \*\*Approvers:\*\*' "$W/ptsect"
t "PROJECT template: keeps the - **Trusted:** line" grep -q '^- \*\*Trusted:\*\*' "$W/ptsect"
t "PROJECT template: no 'Approvers never need approval'" not grep -qF 'Approvers never need approval' "$PT"
for s in content design price system fragile security dependencies; do
  echo "info: the template's section names the slug $s: $(grep -c "| $s |" "$W/ptsect") time(s)"
done
P="$W/onboarded"; G init -q "$P"
run "$P" ada-onboard files PROJECT_NAME=Scratch
t "ada-onboard files: exit 0" test "$(cat "$W/c")" = 0
[ "$(cat "$W/c")" = 0 ] || show
run "$P" ada-onboard check
echo "info: ada-onboard check on the unfilled template: $(grep -c '^error' "$W/o") error line(s)"
grep '^error' "$W/o" | sed 's/^/info:   /'
# Fill in the Brief approval section as onboarding agrees it: Approvers @neil, Trusted none, and
# the starter set. A live table in the section is replaced; with none (or only one inside a
# comment) the table goes after the Trusted line.
python3 - "$P/.claude/team/PROJECT.md" "$STARTER" <<'PY' > "$W/fillnote"
import sys, re
path, table = sys.argv[1], sys.argv[2].rstrip('\n').split('\n')
lines = open(path, encoding='utf-8').read().split('\n')
s = next(i for i, l in enumerate(lines) if l.startswith('## Brief approval'))
e = next((i for i in range(s + 1, len(lines)) if lines[i].startswith('## ')), len(lines))
sect = lines[s + 1:e]
incomment, inside = False, []
for l in sect:
    start = incomment
    rest = l
    while True:
        if incomment:
            k = rest.find('-->')
            if k < 0: break
            incomment, rest = False, rest[k + 3:]
        else:
            k = rest.find('<!--')
            if k < 0: break
            incomment, rest = True, rest[k + 4:]
    inside.append(start)
def bullet(key, value):
    global sect, inside
    for i, l in enumerate(sect):
        if l.startswith('- **%s:**' % key):
            j = i + 1
            while j < len(sect) and sect[j].startswith('  '): j += 1
            sect = sect[:i] + ['- **%s:** %s' % (key, value)] + sect[j:]
            inside = inside[:i] + [False] + inside[j:]
            return i
    raise SystemExit('no %s line' % key)
bullet('Approvers', '@neil')
tr = bullet('Trusted', 'none')
h = next((i for i, l in enumerate(sect) if l.strip() == '| Area | Slug | Approved by |' and not inside[i]), None)
if h is not None:
    j = h
    while j < len(sect) and sect[j].startswith('|'): j += 1
    sect = sect[:h] + table + sect[j:]
    print('replaced the live table')
else:
    sect = sect[:tr + 1] + [''] + table + sect[tr + 1:]
    print('no live table in the template: added one after the Trusted line')
open(path, 'w', encoding='utf-8').write('\n'.join(lines[:s + 1] + sect + lines[e:]))
PY
echo "info: filling the starter set: $(cat "$W/fillnote")"
run "$P" ada-brief areas
is "ada-brief areas on the filled template: the seven slugs, system the catch-all" 0 "$(printf '%s\n' \
  'content: @client' 'design: @client' 'price: @neil' 'system: @neil (catch-all)' 'fragile: @neil' \
  'security: @neil' 'dependencies: @neil' 'trusted: none')"
run "$P" ada-brief who content
is "who content on the filled template, tester on no list" 0 "$(printf 'required tester\nneeds content: @client')"
run "$P" ada-brief who system price
is "who system price on the filled template" 0 "$(printf 'required tester\nneeds system: @neil\nneeds price: @neil')"
run "$P" ada-onboard check
t "ada-onboard check on the filled template: no error line" not grep -q '^error' "$W/o"
grep '^error' "$W/o" | sed 's/^/      /'

t "stand-in gh: no STUB-GAP (else fix the stand-in)" not grep -q STUB-GAP "$W/stderr.all"
echo "$passes passed, $fails failed"
[ "$fails" = 0 ] || exit 1
exit 0
```

## Expected

Every line `PASS`; the last line `<n> passed, 0 failed`; exit 0. Put the `info:` lines (the
Amendments comment's old `needed: <trigger>`, the slugs the unfilled template names, `ada-onboard
check`'s errors on the unfilled template, how the starter set was filled in) in the Run record:
the brief doesn't fix them. Both breaks print `FAIL` where step 4 says.

Sources, all Unit E's brief (task 1220198325269356) unless named:

- Section 1: Definition of Done 3 (`Areas: <…>` and `Author: <…>` after `Approval:`; the
  Amendments comment says `needed: <area>[, <area>]`); Decisions, The brief (two header lines
  after `Approval:`); Constraints, S104 (Unit C's parser: a header line, a `<` prompt reads as
  none) and Files expected to change, with Ada's ruling of 2026-11-23 on the Builder plan (the
  `Approval:` prompt gains the per-area form of Decisions, Approval). The template changes only
  in those four places, so the
  rest is byte for byte the template before Unit E); `SYSTEM.md`, "Parsing depends on template
  wording".
- Section 2: Definition of Done 3 (`ada-brief body` still prints the outcome, decisions, out of
  scope and Definition of Done; `compare` of two drafts prints `same`); Surfaces (S12). `API.md`,
  `compare`: the `areas:` second line only when `Areas:` is filled, not for the template's
  prompt; the first line `reapprove <the needed: cell>`. A brief whose `Areas:` is left as the
  prompt renders as one from the old template (`API.md`, `status` and the CHANGELOG: a brief
  without an `Areas:` line gets the output it got before). The body of a brief with `Areas:`
  lists its `approved:<slug>` labels (CHANGELOG, Unit D2: "the draft PR lists each label").
- Section 3: Definition of Done 8 (`Checkout copy (content)` in a Unit cell: `ada-plan check` is
  clean); Decisions, Plans (never a column: `ada-plan check` requires the six columns); Surfaces
  (`ada-plan briefs` with the new template).
- Section 4: Definition of Done 4 (the seven slugs, `system` ending ` (catch-all)`, exit 0);
  Constraints, S104 (the table header matches Unit B's: `| Area | Slug | Approved by |`);
  Decisions, Onboarding (the starter set's names and slugs); Surfaces (`ada-onboard check`, no
  `error` line); `API.md`, `areas` and `who <area>...` (exact output).

## Observe at

The script's output and exit code. A failing check prints the run's exit code, stdout and stderr
under it. `KEEP=1` keeps the scratch folder (named on the first line): `r/.claude/briefs/` holds
the drafted briefs, `body.T1` to `body.T3` the bodies, `onboarded/.claude/team/PROJECT.md` the
filled template.

## Regression

The body of a brief from the new template with `Areas:` and `Author:` left as prompts is byte for
byte the body of the same brief from the template before Unit E. `ada-plan check` still refuses a
seventh column. `tests/functional/01-brief-approval/overlaps-line-in-body.md` still judges every
other line of the template exactly (it undoes this step's four edits first).
