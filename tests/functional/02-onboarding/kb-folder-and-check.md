# `ada-onboard kb` creates a product's knowledge-base folder without overwriting; `check` flags a product's missing folder and unfilled `config.json`, nothing new for a site, and nothing for a product onboarded before Gmail and syncing were removed

Unit B1 of the knowledge-base plan (Asana 1219987986280083): onboarding records whether a project
is a `product` or a `site`, and for a product creates its knowledge-base folder from
`plugins/ada/templates/knowledge-base/`. A project with no `Kind:` line, or `Kind: site`, must
behave exactly as before.

Unit B2 (Asana 1220058548622782) removes Gmail and syncing: the template has no `sync-state.json`,
its `config.json` holds only `asana.projectName` and `asana.projectGid`, its README has no Syncing
section and gains `## When to write an entry`, and onboarding asks no mailbox or Gmail question. A
product onboarded before (with `sync-state.json` and `gmail` keys) keeps its files, and nothing
errors or warns on them.

- Area: 02 Onboarding and refresh (`kb`, `check`, `files`, `refresh`, the onboard skill's text);
  11 Templates (the `PROJECT.md` template's two bullets, the knowledge-base template folder);
  12 Platforms (bash 3.2, Python 3.8, a CRLF `PROJECT.md`)
- Asana: https://app.asana.com/0/0/1220058587596437/f (B1), https://app.asana.com/0/0/1220058548622782/f (B2)
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 13 Nov 2026, 0 failed (284 passed) under bash 5.2, bash 3.2 and Python 3.8; the 6f0dd66 break 32 FAIL as expected (see the PR)

The skill's behaviour in a real onboarding (what it asks, what it writes) is the dry run in
`onboard-asks-kind-and-creates-knowledge-base.md`.

## Precondition

- This checkout (`REPO`), with the change in its working tree. The script runs this checkout's
  `ada-onboard` by path, never the one on the PATH.
- `git`, `tar`, `cmp`, `python3` (3.8+). No network, no GitHub login: every scratch
  `PROJECT.md` says `Approvers: none`, so `ada-brief who` needs no `gh`.
- Two commits must be in this checkout, and the script exports each one's plugin:
  - `BASE` (default `11a2540`, the 0.13.0 release, before Unit B1): what a site, or a repo with no
    `Kind:` line, got before the knowledge base existed. `check`, `files` and `refresh` are
    compared with it.
  - `B1` (default `6f0dd66`, Unit B1 (#58), the tip of `add/knowledge-base` this step branches
    from): the plugin before this step. Its `kb` makes "a product onboarded before" (with
    `sync-state.json` and `gmail` keys); `check`, `skills/begin/SKILL.md`, `pr-gate.md`, the
    agents, the skills and the version numbers are compared with it.
- The scratch repos sit in a folder whose name has a space.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Run it under bash 3.2 and Python 3.8 ("bash 3.2" and "Python 3.8" below).
4. Break it on purpose: `REPO=<this checkout> PLUGIN=<an export of 6f0dd66>/plugins/ada bash check.sh`
   (`git -C <this checkout> archive 6f0dd66 plugins/ada | tar -x -C <empty folder>`): the plugin
   as Unit B1 left it, with Gmail and syncing. See Expected for what FAILs.

```bash
#!/bin/bash
# ada-onboard kb; check's knowledge-base lines, for a new product and one onboarded before Unit B2;
# the Kind and Knowledge base bullets; the onboard skill, API.md and CHANGELOG text; what Unit B2
# removes (Gmail, sync) and must not add. PASS/FAIL per check; exit 1 if any FAIL, 2 if set-up fails.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada),
#      BASE (before Unit B1, default 11a2540), B1 (Unit B1, before this step, default 6f0dd66),
#      OLD_PLUGIN, B1_PLUGIN (BASE's and B1's plugins/ada; exported if unset),
#      SH (how to run the script under test, default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE=${BASE:-11a2540}
B1=${B1:-6f0dd66}
SH=${SH:-bash}
for tool in git python3 tar cmp; do command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }; done
T=$(mktemp -d) || exit 2
W="$T/kb scratch"; mkdir "$W" || exit 2
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
if [ -z "${B1_PLUGIN:-}" ]; then
  mkdir "$W/b1"
  git -C "$REPO" archive "$B1" plugins/ada | tar -x -C "$W/b1" || { echo "SETUP FAIL: cannot export $B1"; exit 2; }
  B1_PLUGIN=$W/b1/plugins/ada
fi
git -C "$REPO" show "$B1:.claude-plugin/marketplace.json" > "$W/b1-marketplace.json" 2>/dev/null || { echo "SETUP FAIL: cannot read $B1:.claude-plugin/marketplace.json"; exit 2; }
NEWB=$PLUGIN/bin/ada-onboard; OLDB=$OLD_PLUGIN/bin/ada-onboard; B1B=$B1_PLUGIN/bin/ada-onboard
KBT=$PLUGIN/templates/knowledge-base
PJT=$PLUGIN/templates/repo/.claude/team/PROJECT.md
SKILL=$PLUGIN/skills/onboard/SKILL.md
API=$REPO/.claude/team/API.md
CL=$REPO/CHANGELOG.md
FILES="README.md INDEX.md config.json issues/.gitkeep"
NF=4
WANT=$(printf '%s\n' $FILES | LC_ALL=C sort)
CR=$(printf '\r')
fails=0; passes=0
pass() { echo "PASS $1"; passes=$((passes+1)); }
fail() { echo "FAIL $1"; fails=$((fails+1)); }
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then pass "$tn"; else fail "$tn"; fi; }
not() { ! "$@"; }
info() { echo "info: $*"; }
esc() { printf '%s' "$1" | sed 's/[.]/\\./g'; }
tree() { (cd "$1" 2>/dev/null && find . -type f | sed 's|^\./||' | LC_ALL=C sort); }
gitc() { git -c user.name=t -c user.email=t@example.com "$@"; }
newrepo() { mkdir -p "$W/$1"; git -C "$W/$1" init -q; gitc -C "$W/$1" commit -q --allow-empty -m init; }
lines() { if [ -s "$1" ]; then grep -c . "$1"; else echo 0; fi; }
# para <file> <regex>...: some paragraph (blank-line separated) of <file> matches every regex, any case
para() { pf=$1; shift; out=$(awk -v RS= '{gsub(/\n/," "); print}' "$pf"); for re in "$@"; do out=$(printf '%s\n' "$out" | grep -Ei -- "$re"); done; [ -n "$out" ]; }
# items <file>: one line per top-level list item (-, * or 1.), continuation lines joined
items() { awk '/^([-*]|[0-9]+\.)[[:space:]]/{ if (b != "") print b; b=$0; next } /^[[:space:]]+[^[:space:]]/ && b != "" { sub(/^[[:space:]]+/," "); b=b $0; next } { if (b != "") print b; b="" } END { if (b != "") print b }' "$1"; }
# itemn <file> <regex>...: the number of the first list item of <file> matching every regex, any case
itemn() { itf=$1; shift; out=$(items "$itf" | awk '{print NR "\t" $0}'); for re in "$@"; do out=$(printf '%s\n' "$out" | grep -Ei -- "$re"); done; printf '%s\n' "$out" | grep . | head -1 | cut -f1; }
# kb <dir> [args]: the script under test's kb, run from <dir>; stdout, stderr, exit code in $W
kb() { d=$1; shift; (cd "$d" && $SH "$NEWB" kb "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc() { cat "$W/rc"; }
created() { grep -Eq "^created +$(esc "$1")/$(esc "$2")\$" "$W/out"; }   # created <folder>/<file>, from the repo root
kept()    { grep -Eq "^kept +$(esc "$1")/$(esc "$2")\$" "$W/out"; }
# bullet <file> <name>: the "- **<name>:**" bullet in "## The project", continuation lines joined
bullet() { awk -v k="$2" '/^## /{s=($0=="## The project")} s && index($0,"- **" k ":**")==1 {p=1; b=$0; next} p && /^[[:space:]]+[^[:space:]-]/ {sub(/^[[:space:]]+/," "); b=b $0; next} p {print b; p=0} END{if(p)print b}' "$1" | tr -s ' '; }
# braw <file> <name>: the same bullet as the template's lines have it (not joined)
braw() { awk -v k="$2" '/^## /{s=($0=="## The project")} s && index($0,"- **" k ":**")==1 {p=1; print; next} p && /^[[:space:]]+[^[:space:]-]/ {print; next} {p=0}' "$1"; }
# proj <repo> <kind bullet or ""> <knowledge base bullet or "">: a filled-in PROJECT.md
proj() {
  mkdir -p "$1/.claude/team"
  { printf '%s\n' '# Scratch' '' '## The project' '' '- **What it is:** a scratch repo.' '- **Known issues live in:** nowhere.'
    [ -z "$2" ] || printf '%s\n' "$2"
    [ -z "$3" ] || printf '%s\n' "$3"
    printf '%s\n' '' '## Code and branches' '' '- **Branch from:** `main`. **PR into:** `main`.' '' '## Brief approval' '' '- **Approvers:** none' '- **Trusted:** none'
  } > "$1/.claude/team/PROJECT.md"
}
# chk <dir> <script> <outfile>: check's stdout and stderr in <outfile>, exit code in <outfile>.rc
chk() { (cd "$1" && $SH "$2" check) > "$3" 2>&1; echo $? > "$3.rc"; }
# cmpchk <label> <dir> [<old script>]: check before (default BASE's) and after (under test) from <dir>
cmpchk() { chk "$2" "${3:-$OLDB}" "$W/old.chk"; chk "$2" "$NEWB" "$W/new.chk"; info "$1: check exit before $(cat "$W/old.chk.rc"), after $(cat "$W/new.chk.rc")"; }
extra() { grep -vxFf "$W/old.chk" "$W/new.chk"; }   # lines check prints now that it didn't before
gone()  { grep -vxFf "$W/new.chk" "$W/old.chk"; }   # lines it printed before and doesn't now
# same <label> <dir> [<old script>]: check prints exactly what the old script printed, same exit
same() {
  cmpchk "$1" "$2" "${3:-}"
  t "$1: check prints nothing new" test -z "$(extra)"
  t "$1: check drops nothing" test -z "$(gone)"
  t "$1: check's exit status as before" test "$(cat "$W/old.chk.rc")" = "$(cat "$W/new.chk.rc")"
}
# same2 <label> <dir>: same as before Unit B1 (BASE) and as before this step (B1)
same2() { same "$1 (vs $BASE)" "$2" "$OLDB"; same "$1 (vs $B1)" "$2" "$B1B"; }
# onenew <label> <dir> <word> <text>: check prints exactly one new line, "<word>  ...<text>..."
onenew() {
  cmpchk "$1" "$2"; extra > "$W/x"
  info "$1: new line(s): $(tr '\n' '|' < "$W/x")"
  t "$1: exactly one new line" test "$(lines "$W/x")" = 1
  t "$1: it names $4" grep -qF -- "$4" "$W/x"
  t "$1: with check's word '$3' (API.md)" grep -Eq "^$3 +" "$W/x"
  t "$1: no carriage return in it" not grep -q "$CR" "$W/x"
  t "$1: check drops nothing" test -z "$(gone)"
}
# fill <file> <n>: replace the first n {{KEY}} placeholders (0: all) with a value
fill() { python3 - "$1" "$2" <<'EOF'
import re, sys
p, n = sys.argv[1], int(sys.argv[2])
s = open(p, encoding='utf-8').read()
s = re.sub(r'\{\{[A-Z0-9_]+\}\}', 'filled-value', s, count=n)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
EOF
}
# nosync <file>: names no Gmail, mailbox, /ada:kb-sync, sync-state.json or lastSync
nosync() { not grep -Eqi 'gmail|mailbox|kb-sync|sync-state|lastsync' "$1"; }
pyver=$(python3 -c 'import sys; print("%d.%d" % sys.version_info[:2])')
info "bash $BASH_VERSION, python3 $pyver, before B1 = $BASE, before this step = $B1, plugin = $PLUGIN"

echo "== 0. Static (S100, S101, S102)"
t "bash -n bin/ada-onboard" bash -n "$NEWB"
mkdir "$W/py"
awk -v d="$W/py" -v m1="<<'PY'" -v m2="<<-'PY'" '(index($0,m1)||index($0,m2)) && !p {n++; f=d "/heredoc" n ".py"; p=1; next} p && /^[[:space:]]*PY[[:space:]]*$/ {p=0; close(f); next} p {print > f}' "$NEWB"
nh=$(ls "$W/py" | grep -c '\.py$')
t "setup: found the Python heredocs in bin/ada-onboard ($nh)" test "$nh" -ge 3
for f in "$W/py"/*.py; do
  [ -f "$f" ] || continue
  t "$(basename "$f") compiles under Python $pyver" python3 -c 'import sys; compile(open(sys.argv[1], encoding="utf-8").read(), sys.argv[1], "exec")' "$f"
done
awk 'NR==1{next} /^#/{print; next} {exit}' "$NEWB" > "$W/hdr"
t "usage header has an 'ada-onboard kb' entry (S102)" grep -Eq '^#[[:space:]]+ada-onboard kb( |$)' "$W/hdr"
t "usage header gives the default folder knowledge-base" grep -q 'knowledge-base' "$W/hdr"
t "B2: bin/ada-onboard names neither /ada:kb-sync nor sync-state.json (comments, file lists)" not grep -Eqi 'kb-sync|sync-state' "$NEWB"
t "B2: bin/ada-onboard says nothing of Gmail or lastSync" not grep -Eqi 'gmail|lastsync' "$NEWB"

echo "== 1. The template folder"
t "templates/knowledge-base/ exists" test -d "$KBT"
t "it holds exactly README.md, INDEX.md, config.json, issues/.gitkeep" test "$(tree "$KBT")" = "$WANT"
t "B2: no sync-state.json in the template" test ! -e "$KBT/sync-state.json"
t "not under templates/repo/, which every project gets" test -z "$(cd "$PLUGIN/templates/repo" && find . \( -name 'knowledge-base' -o -name sync-state.json -o -name '.gitkeep' -path '*issues*' \) -print)"
t "B2: config.json parses and has only asana.projectName and asana.projectGid, two different {{KEY}} placeholders" python3 - "$KBT/config.json" <<'EOF'
import json, re, sys
d = json.load(open(sys.argv[1], encoding='utf-8'))
ok = isinstance(d, dict) and list(d) == ["asana"] and isinstance(d["asana"], dict) and sorted(d["asana"]) == ["projectGid", "projectName"]
if ok:
    v = [d["asana"]["projectName"], d["asana"]["projectGid"]]
    ok = all(isinstance(x, str) and re.match(r'^\{\{[A-Z0-9_]+\}\}$', x) for x in v) and len(set(v)) == 2
sys.exit(0 if ok else 1)
EOF
t "B2: no {{GMAIL_ anywhere in the template folder" not grep -rq '{{GMAIL_' "$KBT"
R=$KBT/README.md
t "README: no 'pasted into an AI tool' line (B1)" not grep -Eqi 'pasted into an? AI' "$R"
hasall() { hf=$1; shift; for w in "$@"; do grep -Fqi -- "$w" "$hf" || return 1; done; }
t "README: Entry format section" grep -Eq '^## Entry format' "$R"
t "README: the entry's front-matter fields title, category, status, tags, sources, each as a 'field:' line" sh -c "for f in title category status tags sources; do grep -Eq \"^\$f:\" '$R' || exit 1; done"
t "README: the entry's four sections Symptoms, Cause, Solution, How to diagnose" sh -c "for h in Symptoms Cause Solution 'How to diagnose'; do grep -Eq \"^## \$h\\\$\" '$R' || exit 1; done"
t "README: sources are IDs only, and code references carry no line numbers" hasall "$R" 'IDs only' 'no line numbers'
t "README: a no-customer-data rule, under its own heading" sh -c "grep -Eq '^## No customer data' '$R' && grep -Eqi 'customer (data|names?|e-?mails?|details)|personal data|no customer' '$R'"
t "README: the category list: all eight, in one paragraph (bug, conflict, configuration, how-to, feature, licensing, support-process, development)" para "$R" 'categories' '`bug`' '`conflict`' '`configuration`' '`how-to`' '`feature`' '`licensing`' '`support-process`' '`development`'
t "README: the status list: all five, in one paragraph (resolved, workaround, fixed-in-release, open-bug, unresolved)" para "$R" 'statuses' '`resolved`' '`workaround`' '`fixed-in-release`' '`open-bug`' '`unresolved`'
t "B2: README has no Syncing section (no heading with 'sync' in it)" not grep -Eqi '^#+ .*sync' "$R"
t "B2: README names none of /ada:kb-sync, sync-state.json, lastSync" not grep -Eqi 'kb-sync|sync-state|lastsync' "$R"
t "B2: README says nothing of syncing (no word sync, syncs, syncing, synced)" not grep -Eiqw 'sync|syncs|syncing|synced' "$R"
grep -ni 'gmail' "$R" > "$W/rgmail"
info "README lines naming Gmail: $(tr '\n' '|' < "$W/rgmail")"
t "B2: README: exactly one line names Gmail" test "$(lines "$W/rgmail")" = 1
t "B2: README: that line keeps gmail:<thread id> as a source form (entries written before)" grep -Eqi 'gmail:<thread[ _-]?id>' "$W/rgmail"
t "B2: README: one '## When to write an entry' heading" test "$(grep -c '^## When to write an entry[[:space:]]*$' "$R")" = 1
awk '/^## When to write an entry[[:space:]]*$/{p=1;next} p&&/^##? /{exit} p' "$R" > "$W/when"
items "$W/when" > "$W/whenitems"
info "When to write an entry: $(lines "$W/whenitems") list item(s)"
p1=$(itemn "$W/when" 'only' 'creat' 'updat' 'investigation' 'technical information' 'future issue')
p2=$(itemn "$W/when" 'already exists' 'update' 'existing entry' 'duplicate')
p3=$(itemn "$W/when" 'problem' 'root cause' 'investigation steps' 'resolution' 'workaround' 'technical context')
p4=$(itemn "$W/when" 'no reusable technical knowledge' 'skip')
info "When to write an entry: the four points are list items ${p1:-none}, ${p2:-none}, ${p3:-none}, ${p4:-none}"
t "B2: point 1: only create or update an entry when the investigation produces technical information that could help resolve a future issue" test -n "$p1"
t "B2: point 2: check whether the knowledge already exists; if so update the existing entry, not a duplicate" test -n "$p2"
t "B2: point 3: capture problem, root cause (if known), investigation steps, resolution or workaround, technical context" test -n "$p3"
t "B2: point 4: if no reusable technical knowledge was gained, skip creating an entry" test -n "$p4"
t "B2: the four points are four different list items of the section" test "$(printf '%s\n' "$p1" "$p2" "$p3" "$p4" | grep . | LC_ALL=C sort -u | grep -c .)" = 4
t "B2: awaiting-customer is Unit B4's: not in README or INDEX" sh -c "! grep -qi 'awaiting-customer' '$R' && ! grep -qi 'awaiting-customer' '$KBT/INDEX.md'"
t "INDEX: an example area heading" grep -Eq '^##+ ' "$KBT/INDEX.md"
t "INDEX: the status key names all five statuses (resolved, workaround, fixed in release, open bug, unresolved)" para "$KBT/INDEX.md" 'status' 'resolved' 'workaround' 'fixed.in.release' 'open.bug' 'unresolved'
t "INDEX: the status key has five different icons (symbols, not letters)" python3 - "$KBT/INDEX.md" <<'EOF'
import sys, unicodedata
s = open(sys.argv[1], encoding='utf-8').read()
icons = {c for c in s if unicodedata.category(c) == 'So'}
sys.exit(0 if len(icons) >= 5 else 1)
EOF
t "B2: INDEX names no Gmail, mailbox, kb-sync, sync-state or lastSync" nosync "$KBT/INDEX.md"
t "issues/.gitkeep is there to keep the empty folder" test -f "$KBT/issues/.gitkeep"

echo "== 2. kb, default folder: create, run again, never overwrite"
newrepo "kb default"; A="$W/kb default"
kb "$A"
info "kb first run: exit $(rc): $(tr '\n' '|' < "$W/out")"
t "kb: exit 0" test "$(rc)" = 0
for f in $FILES; do
  t "kb: 'created' line for knowledge-base/$f" created knowledge-base "$f"
  t "kb: knowledge-base/$f is the template's file, byte for byte" cmp -s "$KBT/$f" "$A/knowledge-base/$f"
done
t "kb: one 'created' line per file, $NF in all" test "$(grep -c '^created' "$W/out")" = $NF
t "kb: no 'kept' line on a first run" not grep -q '^kept' "$W/out"
t "kb: knowledge-base/ holds exactly the $NF files" test "$(tree "$A/knowledge-base")" = "$WANT"
t "B2: kb: no sync-state.json made, and no line names it" sh -c "test ! -e '$A/knowledge-base/sync-state.json' && ! grep -qi 'sync-state' '$W/out' '$W/err'"
t "B2: kb: no {{GMAIL_ anywhere in the folder" not grep -rq '{{GMAIL_' "$A/knowledge-base"
t "kb: nothing else in the repo changed" test -z "$(git -C "$A" status --porcelain | grep -v '^?? knowledge-base/$')"
gitc -C "$A" add -A; gitc -C "$A" commit -q -m kb >/dev/null 2>&1
kb "$A"
info "kb second run: exit $(rc): $(tr '\n' '|' < "$W/out")"
t "kb again: exit 0" test "$(rc)" = 0
for f in $FILES; do t "kb again: 'kept' line for knowledge-base/$f" kept knowledge-base "$f"; done
t "kb again: no 'created' line" not grep -q '^created' "$W/out"
t "kb again: changes nothing (git status clean)" test -z "$(git -C "$A" status --porcelain)"
# Never overwrites: an edited file is kept as edited; a deleted one comes back.
echo 'Edited by the project.' >> "$A/knowledge-base/README.md"
cp "$A/knowledge-base/README.md" "$W/readme.edited"
rm "$A/knowledge-base/INDEX.md"
kb "$A"
info "kb after an edit and a delete: exit $(rc): $(tr '\n' '|' < "$W/out")"
t "kb after edit/delete: 'created' for the deleted INDEX.md" created knowledge-base INDEX.md
t "kb after edit/delete: 'kept' for the edited README.md" kept knowledge-base README.md
t "kb after edit/delete: the edited README.md unchanged" cmp -s "$W/readme.edited" "$A/knowledge-base/README.md"
t "kb after edit/delete: INDEX.md back as the template's" cmp -s "$KBT/INDEX.md" "$A/knowledge-base/INDEX.md"
t "kb after edit/delete: 'kept' for the other two" sh -c "grep -Eq '^kept +knowledge-base/config\.json\$' '$W/out' && grep -Eq '^kept +knowledge-base/issues/\.gitkeep\$' '$W/out'"

echo "== 3. kb <path>, a trailing slash, a subfolder, bad arguments"
newrepo "kb path"; B="$W/kb path"
kb "$B" docs/kb
info "kb docs/kb: exit $(rc): $(tr '\n' '|' < "$W/out")"
t "kb docs/kb: exit 0" test "$(rc)" = 0
for f in $FILES; do t "kb docs/kb: 'created' and the template's copy for docs/kb/$f" sh -c "grep -Eq '^created +docs/kb/$(esc "$f")\$' '$W/out' && cmp -s '$KBT/$f' '$B/docs/kb/$f'"; done
t "kb docs/kb: docs/kb/ holds exactly the $NF files" test "$(tree "$B/docs/kb")" = "$WANT"
t "kb docs/kb: no knowledge-base/ at the root" test ! -e "$B/knowledge-base"
newrepo "kb slash"; C="$W/kb slash"
kb "$C" knowledge-base/
t "kb knowledge-base/: exit 0" test "$(rc)" = 0
t "kb knowledge-base/: the $NF files in knowledge-base/" test "$(tree "$C/knowledge-base")" = "$WANT"
t "kb knowledge-base/: no '//' in its output" not grep -q '//' "$W/out"
newrepo "kb sub"; D0="$W/kb sub"; mkdir -p "$D0/sub/dir"
kb "$D0/sub/dir"
t "kb from a subfolder: the folder goes at the repo root (S102: cd to the top level)" sh -c "test -f '$D0/knowledge-base/config.json' && test ! -e '$D0/sub/dir/knowledge-base'"
newrepo "kb bad"; E="$W/kb bad"
kb "$E" one two
t "kb with two arguments: exit 2 (usage)" test "$(rc)" = 2
t "kb with two arguments: nothing created" test -z "$(git -C "$E" status --porcelain)"
t "kb with two arguments: message starts 'ada-onboard: ' (S102)" grep -q '^ada-onboard: ' "$W/err"
kb "$E" ../outside
info "kb ../outside: exit $(rc): $(tr '\n' '|' < "$W/err")"
t "kb ../outside: nothing written outside the repo" test ! -e "$W/outside"
t "kb ../outside: refused, exit 2" test "$(rc)" = 2
kb "$E" "$W/absolute"
info "kb <absolute path outside the repo>: exit $(rc): $(tr '\n' '|' < "$W/err")"
t "kb <absolute path outside the repo>: nothing written there" test ! -e "$W/absolute"
t "kb <absolute path outside the repo>: refused, exit 2" test "$(rc)" = 2
t "kb refusals: nothing created in the repo" test -z "$(git -C "$E" status --porcelain)"
newrepo "kb file"; F="$W/kb file"; echo x > "$F/afile"; gitc -C "$F" add -A; gitc -C "$F" commit -q -m file >/dev/null 2>&1
kb "$F" afile/kb
info "kb afile/kb (afile is a file): exit $(rc): $(tr '\n' '|' < "$W/err")"
t "kb afile/kb: refused, exit 2 (S102), not a traceback's exit 1" test "$(rc)" = 2
t "kb afile/kb: message starts 'ada-onboard: ' (S102)" grep -q '^ada-onboard: ' "$W/err"
t "kb afile/kb: no Python traceback" not grep -q 'Traceback' "$W/err"
t "kb afile/kb: nothing on stdout (no 'created' line)" test ! -s "$W/out"
t "kb afile/kb: nothing written (git status clean, afile still a file with its content)" sh -c "test -z \"\$(git -C '$F' status --porcelain)\" && test -f '$F/afile' && test \"\$(cat '$F/afile')\" = x"
kb "$F" afile/deeper/kb
t "kb afile/deeper/kb (a file further up the path): refused, exit 2, no traceback, nothing written" sh -c "test \"\$(cat '$W/rc')\" = 2 && grep -q '^ada-onboard: ' '$W/err' && ! grep -q Traceback '$W/err' && test -z \"\$(git -C '$F' status --porcelain)\""
kb "$F" afile
t "kb afile (the path itself is a file): refused, exit 2, nothing written" sh -c "test \"\$(cat '$W/rc')\" = 2 && grep -q '^ada-onboard: ' '$W/err' && test -z \"\$(git -C '$F' status --porcelain)\""
newrepo "kb link"; L="$W/kb link"; mkdir "$W/link target" "$W/link target/sub"
ln -s "$W/link target" "$L/link"; ln -s "$W/link nowhere" "$L/dangling"
gitc -C "$L" add -A; gitc -C "$L" commit -q -m links >/dev/null 2>&1
t "setup: the scratch repo holds a symlink to a folder outside it" test -L "$L/link"
kb "$L" link
info "kb link (a symlink to a folder outside the repo): exit $(rc): $(tr '\n' '|' < "$W/err")"
t "kb link: refused, exit 2" test "$(rc)" = 2
t "kb link: message starts 'ada-onboard: ' (S102)" grep -q '^ada-onboard: ' "$W/err"
t "kb link: nothing written in the folder it points to, outside the repo" test -z "$(find "$W/link target" -type f)"
t "kb link: nothing written in the repo, and no 'created' line" sh -c "test -z \"\$(git -C '$L' status --porcelain)\" && ! grep -q '^created' '$W/out'"
kb "$L" link/sub
t "kb link/sub (through the symlink): refused, exit 2, nothing written outside" sh -c "test \"\$(cat '$W/rc')\" = 2 && grep -q '^ada-onboard: ' '$W/err' && test -z \"\$(find '$W/link target' -type f)\""
kb "$L" link/new/kb
t "kb link/new/kb (through the symlink, folders that don't exist yet): refused, exit 2, nothing created outside" sh -c "test \"\$(cat '$W/rc')\" = 2 && test ! -e '$W/link target/new'"
kb "$L" dangling
t "kb dangling (a symlink to a path outside the repo that doesn't exist): refused, exit 2, nothing created there" sh -c "test \"\$(cat '$W/rc')\" = 2 && test ! -e '$W/link nowhere'"
t "kb symlink refusals: nothing created in the repo" test -z "$(git -C "$L" status --porcelain)"
# Controls: the same repos, a good path, so the refusals above aren't just "kb doesn't work here".
kb "$F" other/kb
t "control: kb other/kb in the repo with the file: exit 0, $NF files" sh -c "test \"\$(cat '$W/rc')\" = 0 && test \"\$(grep -c '^created' '$W/out')\" = $NF && test -f '$F/other/kb/config.json'"
mkdir "$L/real"; kb "$L" real/kb
t "control: kb real/kb in the repo with the symlinks: exit 0, $NF files, inside the repo" sh -c "test \"\$(cat '$W/rc')\" = 0 && test \"\$(grep -c '^created' '$W/out')\" = $NF && test -f '$L/real/kb/config.json'"

echo "== 4. check: nothing new unless Kind is product with a Knowledge base path"
K_WANT="- **Kind:** <one of \`product\` (a plugin or theme PIE sells and supports) or \`site\` (a client's website)>"
B_WANT="- **Knowledge base:** \`none\` <or, for a product, the folder's path, e.g. \`knowledge-base/\`>"
newrepo "check"; D="$W/check"; mkdir -p "$D/sub"
proj "$D" "" ""
chk "$D" "$NEWB" "$W/setup.chk"
t "setup: a filled-in PROJECT.md checks ok" grep -Eq '^ok +\.claude/team/PROJECT\.md' "$W/setup.chk"
t "setup: no brief-approval error" not grep -qi 'brief approval' "$W/setup.chk"
same2 "a. no Kind or Knowledge base line (a repo onboarded before)" "$D"
proj "$D" '- **Kind:** `site`' '- **Knowledge base:** `none`'
same2 "b. Kind site, Knowledge base none" "$D"
proj "$D" '- **Kind:** `site`' '- **Knowledge base:** `knowledge-base/`'
same2 "c. Kind site with a path, no folder" "$D"
proj "$D" '- **Kind:** `product`' '- **Knowledge base:** `none`'
same2 "d. Kind product, Knowledge base none" "$D"
proj "$D" "$K_WANT" '- **Knowledge base:** `knowledge-base/`'
same2 "e. Kind still the template prompt (unset), a path, no folder" "$D"
proj "$D" '- **Kind:** `product`' '- **Knowledge base:** `knowledge-base/`'
onenew "f. Kind product, Knowledge base knowledge-base/, no folder" "$D" missing knowledge-base
proj "$D" '- **Kind:** `product`' '- **Knowledge base:** `docs/kb/`'
onenew "g. Kind product, Knowledge base docs/kb/, no folder" "$D" missing docs/kb
proj "$D" '- **Kind:** product (sold and supported by PIE)' '- **Knowledge base:** docs/kb/ (the support notes)'
onenew "h. values as first words, not code spans" "$D" missing docs/kb
proj "$D" '- **Kind:** `product`' '- **Knowledge base:** `knowledge-base/`'
awk '{printf "%s\r\n", $0}' "$D/.claude/team/PROJECT.md" > "$W/crlf" && mv "$W/crlf" "$D/.claude/team/PROJECT.md"
onenew "i. CRLF PROJECT.md (Windows checkout)" "$D" missing knowledge-base
proj "$D" '- **Kind:** `product`' '- **Knowledge base:** `knowledge-base/`'
onenew "j. from a subfolder" "$D/sub" missing knowledge-base

echo "== 5. Sequence, a new product: no folder -> kb -> fill config.json"
newrepo "sequence"; S="$W/sequence"
proj "$S" '- **Kind:** `product`' '- **Knowledge base:** `knowledge-base/`'
onenew "5.1 product, no folder yet" "$S" missing knowledge-base
kb "$S"
t "5.2 kb: exit 0" test "$(rc)" = 0
onenew "5.2 after kb, config.json unfilled" "$S" 'fill in' config.json
t "5.2 the missing-folder line is gone" not grep -q '^missing.*knowledge-base' "$W/new.chk"
t "B2: 5.2 the fill-in line names no Gmail placeholder" not grep -qi 'gmail' "$W/x"
t "B2: 5.2 nothing asks for sync-state.json" not grep -qi 'sync-state' "$W/new.chk"
fill "$S/knowledge-base/config.json" 1 2>/dev/null
onenew "5.3 one placeholder filled, one left" "$S" 'fill in' config.json
fill "$S/knowledge-base/config.json" 0 2>/dev/null
t "setup: config.json is there and has no {{KEY}} left" sh -c "test -f '$S/knowledge-base/config.json' && ! grep -q '{{' '$S/knowledge-base/config.json'"
same2 "5.4 every placeholder filled (a freshly onboarded product)" "$S"
t "B2: 5.4 no sync-state.json in the folder, and check names none" sh -c "test ! -e '$S/knowledge-base/sync-state.json' && ! grep -qi 'sync-state' '$W/new.chk'"
newrepo "sequence path"; S2="$W/sequence path"
proj "$S2" '- **Kind:** `product`' '- **Knowledge base:** `docs/kb/`'
kb "$S2" docs/kb
onenew "5.5 docs/kb created by kb docs/kb, config.json unfilled" "$S2" 'fill in' docs/kb/config.json

echo "== 6. Sequence, a product onboarded before this step (Unit B1's folder: sync-state.json, gmail keys)"
newrepo "onboarded before"; O="$W/onboarded before"; mkdir -p "$O/sub"
proj "$O" '- **Kind:** `product`' '- **Knowledge base:** `knowledge-base/`'
(cd "$O" && $SH "$B1B" kb) > "$W/b1kb.out" 2>&1
fill "$O/knowledge-base/config.json" 0 2>/dev/null
printf '%s\n' '{"gmail": {"lastSync": 1791296254, "items": {"1a09985d426e92a7": {"entry": "issues/example.md", "version": 1}}}, "asana": {"lastSync": "2026-10-06T14:17:34Z", "items": {}}}' > "$O/knowledge-base/sync-state.json"
t "setup: Unit B1's kb made the folder with sync-state.json, and config.json has gmail keys, all filled" sh -c "test -f '$O/knowledge-base/sync-state.json' && python3 -c 'import json,sys; d=json.load(open(sys.argv[1])); sys.exit(0 if d[\"gmail\"][\"mailbox\"]==\"filled-value\" and d[\"gmail\"][\"query\"]==\"filled-value\" and d[\"asana\"][\"projectGid\"]==\"filled-value\" else 1)' '$O/knowledge-base/config.json'"
gitc -C "$O" add -A; gitc -C "$O" commit -q -m "onboarded with Unit B1" >/dev/null 2>&1
cp "$O/knowledge-base/config.json" "$W/old-config.json"; cp "$O/knowledge-base/sync-state.json" "$W/old-sync-state.json"
same2 "6.1 onboarded before, filled" "$O"
info "6.1 check after: $(tr '\n' '|' < "$W/new.chk")"
t "6.1 check names neither sync-state.json nor Gmail (no error or warning on them)" not grep -Eqi 'sync-state|gmail' "$W/new.chk"
t "6.1 check reports nothing about the knowledge base (no missing or fill-in line for it)" not grep -Eqi 'knowledge-base|config\.json' "$W/new.chk"
same2 "6.2 the same, from a subfolder" "$O/sub"
kb "$O"
info "6.3 kb on it: exit $(rc): $(tr '\n' '|' < "$W/out")"
t "6.3 kb on it: exit 0" test "$(rc)" = 0
for f in $FILES; do t "6.3 kb on it: 'kept' line for knowledge-base/$f" kept knowledge-base "$f"; done
t "6.3 kb on it: no 'created' line" not grep -q '^created' "$W/out"
t "6.3 kb on it: no line names sync-state.json or Gmail" not grep -Eqi 'sync-state|gmail' "$W/out" "$W/err"
t "6.3 kb on it: sync-state.json and config.json (gmail keys too) left as they were" sh -c "cmp -s '$W/old-sync-state.json' '$O/knowledge-base/sync-state.json' && cmp -s '$W/old-config.json' '$O/knowledge-base/config.json'"
t "6.3 kb on it: nothing in the repo changed (git status clean)" test -z "$(git -C "$O" status --porcelain)"
same2 "6.4 check again after kb" "$O"

echo "== 7. The PROJECT.md template's bullets"
KB_LINE=$(bullet "$PJT" Kind); BB_LINE=$(bullet "$PJT" 'Knowledge base')
info "template Kind bullet: $KB_LINE"
info "template Knowledge base bullet: $BB_LINE"
t "template: the Kind bullet, in The project, worded as the brief" test "$KB_LINE" = "$K_WANT"
t "template: the Knowledge base bullet, in The project, worded as the brief" test "$BB_LINE" = "$B_WANT"
ln_ki=$(grep -n '^- \*\*Known issues live in:\*\*' "$PJT" | head -1 | cut -d: -f1)
ln_k=$(grep -n '^- \*\*Kind:\*\*' "$PJT" | head -1 | cut -d: -f1)
ln_b=$(grep -n '^- \*\*Knowledge base:\*\*' "$PJT" | head -1 | cut -d: -f1)
t "template: Known issues, then Kind, then Knowledge base" test "${ln_ki:-0}" -gt 0 -a "${ln_k:-0}" -gt "${ln_ki:-0}" -a "${ln_b:-0}" -gt "${ln_k:-0}"
P="$W/prompts"; newrepo prompts
proj "$P" "$(braw "$PJT" Kind)" ""
chk "$P" "$NEWB" "$W/p.chk"
t "the template's Kind bullet alone, as its lines stand: check flags PROJECT.md as a draft" grep -Eq '^draft +\.claude/team/PROJECT\.md' "$W/p.chk"
proj "$P" "" "$(braw "$PJT" 'Knowledge base')"
chk "$P" "$NEWB" "$W/p.chk"
t "the template's Knowledge base bullet alone, as its lines stand: check flags PROJECT.md as a draft" grep -Eq '^draft +\.claude/team/PROJECT\.md' "$W/p.chk"
proj "$P" '- **Kind:** `site`' '- **Knowledge base:** `none`'
chk "$P" "$NEWB" "$W/p.chk"
t "both filled (site, none): PROJECT.md ok" grep -Eq '^ok +\.claude/team/PROJECT\.md' "$W/p.chk"

echo "== 8. Regression: a fresh onboarding (files, check, refresh) as before"
newrepo fresh; newrepo freshold; newrepo freshb1
(cd "$W/fresh" && $SH "$NEWB" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/fresh.out" 2>&1
(cd "$W/freshold" && $SH "$OLDB" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/freshold.out" 2>&1
(cd "$W/freshb1" && $SH "$B1B" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/freshb1.out" 2>&1
paths() { grep -E '^(created|kept) ' "$1" | awk '{print $2}' | LC_ALL=C sort; }
t "files: the same files created as before" test "$(paths "$W/fresh.out")" = "$(paths "$W/freshold.out")"
t "files: the same files created as Unit B1 did" test "$(paths "$W/fresh.out")" = "$(paths "$W/freshb1.out")"
t "files: 'Placeholders left:' as before (the bullets add no {{KEY}})" test "$(grep '^Placeholders left' "$W/fresh.out")" = "$(grep '^Placeholders left' "$W/freshold.out")"
t "files: no knowledge-base folder, and nothing about one" sh -c "test ! -e '$W/fresh/knowledge-base' && ! grep -q 'knowledge-base' '$W/fresh.out'"
t "files: the fresh PROJECT.md has both bullets" sh -c "grep -q '^- \*\*Kind:\*\*' '$W/fresh/.claude/team/PROJECT.md' && grep -q '^- \*\*Knowledge base:\*\*' '$W/fresh/.claude/team/PROJECT.md'"
chk "$W/fresh" "$NEWB" "$W/fresh.chk"; chk "$W/freshold" "$OLDB" "$W/freshold.chk"; chk "$W/freshb1" "$B1B" "$W/freshb1.chk"
t "check on a fresh copy: PROJECT.md flagged, as before" grep -Eq '^(draft|fill in) +\.claude/team/PROJECT\.md' "$W/fresh.chk"
mask() { sed -E 's/^(draft +\.claude\/team\/PROJECT\.md: )[0-9]+/\1N/' "$1" | LC_ALL=C sort; }
npr() { grep -E '^draft +\.claude/team/PROJECT\.md: [0-9]+' "$1" | sed -E 's/^[^:]*: ([0-9]+).*/\1/'; }
info "check on a fresh copy: PROJECT.md prompt lines before $(npr "$W/freshold.chk"), Unit B1 $(npr "$W/freshb1.chk"), after $(npr "$W/fresh.chk")"
t "check on a fresh copy: the same lines as before (PROJECT.md's prompt count aside)" test "$(mask "$W/fresh.chk")" = "$(mask "$W/freshold.chk")"
t "check on a fresh copy: PROJECT.md's prompt lines up by at least 2, one per new bullet" test "$(npr "$W/fresh.chk")" -ge $(( $(npr "$W/freshold.chk") + 2 ))
t "check on a fresh copy: exactly what Unit B1's check printed" test "$(LC_ALL=C sort "$W/fresh.chk")" = "$(LC_ALL=C sort "$W/freshb1.chk")"
gitc -C "$W/fresh" add -A; gitc -C "$W/fresh" commit -q -m onboarded >/dev/null 2>&1
rpaths() { grep -E '^(current|refreshed|declined|edited|unknown) ' | awk '{print $2}' | LC_ALL=C sort; }
po=$(cd "$W/fresh" && $SH "$OLDB" refresh 2>/dev/null | rpaths)
pn=$(cd "$W/fresh" && $SH "$NEWB" refresh 2>/dev/null | rpaths)
t "refresh: lists the same templates as before ($(echo $po))" test "$po" = "$pn"
t "refresh: leaves the fresh copy as it was" test -z "$(git -C "$W/fresh" status --porcelain)"

echo "== 9. The onboard skill, API.md, CHANGELOG, versions"
awk '/^## 2\./{p=1;next} p&&/^## /{exit} p' "$SKILL" > "$W/s2"
grep -Ei '^\| *(\*\*)?Kind' "$W/s2" > "$W/kindrow"
t "skill step 2: a Kind row" test -s "$W/kindrow"
t "skill Kind row: product or site" sh -c "grep -qi 'product' '$W/kindrow' && grep -qi 'site' '$W/kindrow'"
t "skill Kind row: says to ask" grep -qi 'ask' "$W/kindrow"
t "skill Kind row: still asks, for a product, the Asana project for support tickets" sh -c "grep -qi 'asana' '$W/kindrow' && grep -Eqi 'support|ticket' '$W/kindrow'"
t "skill Kind row: its name and GID" sh -c "grep -qi 'name' '$W/kindrow' && grep -qi 'gid' '$W/kindrow'"
t "B2: skill Kind row: no mailbox, Gmail or search-query question" not grep -Eqi 'gmail|mailbox|query' "$W/kindrow"
t "B2: skill step 2 asks nothing about a mailbox or Gmail" nosync "$W/s2"
awk 'function done(){ if (h != "" && b ~ /ada-onboard kb/ && b ~ /config\.json/ && b ~ /Knowledge base:/) { print hn; found=1 } }
     /^## / { if (!found) done(); h=$0; hn=NR; b=""; next } { b = b "\n" $0 } END { if (!found) done() }' "$SKILL" > "$W/kbstep"
ln_kb=$(head -1 "$W/kbstep"); ln_s4=$(grep -n '^## 4\.' "$SKILL" | head -1 | cut -d: -f1)
ln_ho=$(grep -n '^## [0-9]*\. Hand over' "$SKILL" | head -1 | cut -d: -f1)
info "skill: step 4 at line ${ln_s4:-?}, knowledge-base step at ${ln_kb:-none}, Hand over at ${ln_ho:-?}: $(sed -n "${ln_kb:-1}p" "$SKILL")"
t "skill: a step naming ada-onboard kb, config.json and Knowledge base:" test -n "$ln_kb"
t "skill: that step comes after step 4 and before Hand over" test "${ln_kb:-0}" -gt "${ln_s4:-999999}" -a "${ln_kb:-0}" -lt "${ln_ho:-0}"
awk -v n="${ln_kb:-0}" 'NR==n{p=1;print;next} p&&/^## /{exit} p' "$SKILL" > "$W/kbsect"
t "skill: that step is for products only" sh -c "grep -qi 'product' '$W/kbsect' && grep -Eqi 'only|skip|site' '$W/kbsect'"
t "skill: that step fills config.json from the Asana answers" grep -qi 'asana' "$W/kbsect"
for k in $(grep -oE '\{\{[A-Z0-9_]+\}\}' "$KBT/config.json"); do
  t "skill: that step names $k, the template config.json's placeholder" grep -qF "$k" "$W/kbsect"
done
t "B2: skill: that step names no mailbox, query, Gmail, sync-state.json or /ada:kb-sync" sh -c "! grep -Eqi 'gmail|mailbox|query|sync-state|kb-sync|lastsync' '$W/kbsect'"
t "B2: the whole skill names no Gmail, mailbox, /ada:kb-sync, sync-state.json or lastSync" nosync "$SKILL"
t "B2: the whole skill has no {{GMAIL_" not grep -q '{{GMAIL_' "$SKILL"
t "skill: the clock step still runs ada-onboard clock (regression)" grep -q 'ada-onboard clock' "$SKILL"
n_clock=$(grep -E '^## [0-9]+\. The local clock' "$SKILL" | head -1 | sed 's/^## \([0-9]*\)\..*/\1/')
n_api=$(grep -E '^\| `clock ' "$API" | grep -oE 'SKILL\.md`?, [0-9]+' | head -1 | sed 's/.*, //')
t "API.md's clock row points at the skill's clock step by its number (${n_api:-?} = ${n_clock:-?})" test -n "$n_clock" -a "$n_clock" = "${n_api:-x}"
n_kbs=$(sed -n "${ln_kb:-1}p" "$SKILL" | sed 's/^## \([0-9]*\)\..*/\1/')
n_akb=$(grep -E '^\| `kb' "$API" | grep -oE 'SKILL\.md`?, [0-9]+' | head -1 | sed 's/.*, //')
t "API.md's kb row points at the skill's knowledge-base step by its number (${n_akb:-?} = ${n_kbs:-?})" test -n "$n_kbs" -a "$n_kbs" = "${n_akb:-x}"
awk '/^### `ada-onboard`/{p=1;next} p&&/^##/{exit} p' "$API" > "$W/api"
grep -E '^\| `kb' "$W/api" > "$W/apikb"
t "API.md: the ada-onboard table has a kb row" test -s "$W/apikb"
t "API.md: the kb row gives the created/kept output" sh -c "grep -q 'created' '$W/apikb' && grep -q 'kept' '$W/apikb'"
t "B2: API.md: the kb row lists README.md, INDEX.md, config.json, issues/.gitkeep, and not sync-state.json" sh -c "grep -q 'README\.md' '$W/apikb' && grep -q 'INDEX\.md' '$W/apikb' && grep -q 'config\.json' '$W/apikb' && grep -q 'issues/\.gitkeep' '$W/apikb' && ! grep -qi 'sync-state' '$W/apikb'"
t "API.md: the check row mentions the knowledge base" sh -c "grep -E '^\| \`check' '$W/api' | grep -Eqi 'knowledge|config\.json'"
awk '/^## Unreleased/{p=1;next} p&&/^## /{exit} p' "$CL" > "$W/unrel"
items "$W/unrel" > "$W/unrelitems"
info "CHANGELOG Unreleased: $(lines "$W/unrelitems") entr(ies)"
grep -i 'knowledge base' "$W/unrelitems" | grep 'ada-onboard kb' > "$W/b1entry"
t "CHANGELOG Unreleased: Unit B1's entry is still there, one entry (knowledge base, ada-onboard kb)" test "$(lines "$W/b1entry")" = 1
t "B2: CHANGELOG: Unit B1's entry corrected in place: no mailbox, no Gmail search" not grep -Eqi 'gmail|mailbox' "$W/b1entry"
t "B2: CHANGELOG: Unit B1's entry corrected in place: no sync (no word sync, syncs, syncing, synced, kb-sync, sync-state, lastSync)" sh -c "! grep -Eiqw 'sync|syncs|syncing|synced' '$W/b1entry' && ! grep -Eqi 'kb-sync|sync-state|lastsync' '$W/b1entry'"
t "B2: CHANGELOG Unreleased: nothing names /ada:kb-sync, sync-state.json or lastSync, which never shipped" not grep -Eqi 'kb-sync|sync-state|lastsync' "$W/unrel"
t "B2: CHANGELOG Unreleased: exactly one entry for the When to write an entry rule (S105)" test "$(grep -ci 'when to write' "$W/unrelitems")" = 1
t "CHANGELOG Unreleased: no 'Nothing yet.' left (S105)" not grep -q 'Nothing yet' "$W/unrel"
vers() { python3 - "$1" <<'EOF'
import json, sys
def walk(x, path):
    if isinstance(x, dict):
        for k in sorted(x):
            if k == "version": print(path + "/version=" + str(x[k]))
            walk(x[k], path + "/" + k)
    elif isinstance(x, list):
        for i, v in enumerate(x): walk(v, path + "/" + str(i))
walk(json.load(open(sys.argv[1], encoding='utf-8')), "")
EOF
}
info "versions: plugin.json $(vers "$PLUGIN/.claude-plugin/plugin.json" | tr '\n' ' '), marketplace.json $(vers "$REPO/.claude-plugin/marketplace.json" | tr '\n' ' ')"
t "P31: plugin.json's version as in Unit B1" test "$(vers "$PLUGIN/.claude-plugin/plugin.json")" = "$(vers "$B1_PLUGIN/.claude-plugin/plugin.json")"
t "P31: marketplace.json's versions as in Unit B1" test "$(vers "$REPO/.claude-plugin/marketplace.json")" = "$(vers "$W/b1-marketplace.json")"
git -C "$REPO" show "$B1:CHANGELOG.md" 2>/dev/null | grep -E '^## [0-9]' > "$W/b1rel"
t "setup: Unit B1's CHANGELOG has release headings" test -s "$W/b1rel"
t "P31: CHANGELOG has the same release headings as Unit B1 (no new version)" test "$(grep -E '^## [0-9]' "$CL")" = "$(cat "$W/b1rel")"

echo "== 10. What Unit B2 removes, and must not add"
PAT='kb-sync|sync-state|lastSync|gmail'
if [ "$PLUGIN" = "$REPO/plugins/ada" ]; then
  git -C "$REPO" grep --untracked -niE "$PAT" -- plugins .claude/team README.md DEVELOPING.md > "$W/grep" 2>&1
else
  { (cd "$PLUGIN/../.." && grep -rnIiE "$PAT" plugins); git -C "$REPO" grep --untracked -niE "$PAT" -- .claude/team README.md DEVELOPING.md; } > "$W/grep" 2>&1
fi
info "git grep -niE '$PAT' over plugins/, .claude/team/, README.md, DEVELOPING.md: $(lines "$W/grep") line(s): $(tr '\n' '|' < "$W/grep")"
t "B2: the grep finds exactly one line" test "$(lines "$W/grep")" = 1
t "B2: it is in plugins/ada/templates/knowledge-base/README.md" grep -q '^plugins/ada/templates/knowledge-base/README\.md:' "$W/grep"
t "B2: it is the legacy gmail:<thread id> source line" grep -Eqi 'gmail:<thread[ _-]?id>' "$W/grep"
t "B2: no skills/kb-sync" test ! -e "$PLUGIN/skills/kb-sync"
t "B2: no kb-writer agent and no kb-nudge.md, anywhere in the plugin" test -z "$(find "$PLUGIN" \( -iname '*kb-writer*' -o -iname '*kb-nudge*' \) -print)"
t "B2: nothing in the plugin names kb-writer or kb-nudge" not grep -rIEqi 'kb-writer|kb-nudge' "$PLUGIN"
t "B2: the same agents as Unit B1" test "$(ls "$PLUGIN/agents")" = "$(ls "$B1_PLUGIN/agents")"
t "B2: the same skills as Unit B1" test "$(ls "$PLUGIN/skills")" = "$(ls "$B1_PLUGIN/skills")"
t "B2: skills/begin/SKILL.md unchanged from Unit B1" cmp -s "$PLUGIN/skills/begin/SKILL.md" "$B1_PLUGIN/skills/begin/SKILL.md"
t "B2: skills/begin/pr-gate.md unchanged from Unit B1" cmp -s "$PLUGIN/skills/begin/pr-gate.md" "$B1_PLUGIN/skills/begin/pr-gate.md"

echo "$fails failed, $passes passed"
if [ "$fails" = 0 ]; then rm -rf "$T"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2

With Docker and the `bash:3.2` image (it has no git, so the container adds it; that needs the
network). With `S` the scratch folder holding `check.sh`:

```sh
mkdir -p "$S/old" "$S/b1"
git -C <this checkout> archive 11a2540 plugins/ada | tar -x -m -C "$S/old"
git -C <this checkout> archive 6f0dd66 plugins/ada | tar -x -m -C "$S/b1"
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && REPO=/repo OLD_PLUGIN=/s/old/plugins/ada B1_PLUGIN=/s/b1/plugins/ada bash /s/check.sh'
```

On a Mac, `REPO=<this checkout> /bin/bash check.sh` uses the system bash 3.2.

### Python 3.8

The same, in `python:3.8-slim` (its `python3` is 3.8, so section 0 compiles every heredoc with
3.8 and every `check`, `files` and `refresh` run uses it):

```sh
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
  sh -c 'apt-get update -qq >/dev/null && apt-get install -y -qq git >/dev/null && git config --global --add safe.directory "*" && python3 --version && REPO=/repo OLD_PLUGIN=/s/old/plugins/ada B1_PLUGIN=/s/b1/plugins/ada bash /s/check.sh'
```

## Expected

Every line `PASS`, last line `0 failed, <n> passed`, exit 0, under the local bash, bash 3.2 and
Python 3.8 (the `info:` line says which). In detail, from the Unit B1 and Unit B2 briefs
(Decisions; Definition of done), `API.md` (`ada-onboard`: `check`'s words `missing`, `fill in`,
`draft`, `ok`, `error`) and `STANDARDS.md` (S100 to S105). Checks named `B2:` are new with Unit B2.

- **`bin/ada-onboard`** parses, its Python compiles, its usage header has `kb` and
  `knowledge-base`, and nothing in it names `/ada:kb-sync`, `sync-state.json`, Gmail or `lastSync`.
- **The template folder** holds exactly `README.md`, `INDEX.md`, `config.json`, `issues/.gitkeep`
  (no `sync-state.json`), and isn't in `templates/repo/`. `config.json` parses and has only
  `asana.projectName` and `asana.projectGid`, as two different `{{KEY}}` placeholders; no
  `{{GMAIL_` anywhere in the folder. The README keeps the entry format (five front-matter fields,
  four sections), the eight categories, the five statuses, IDs-only sources and the no-customer-data
  rule, and has no "pasted into an AI tool" line. It has no Syncing section, never names
  `/ada:kb-sync`, `sync-state.json` or `lastSync`, never uses the word sync, and names Gmail on
  exactly one line, which gives `gmail:<thread id>` as a source form (entries written before this
  change). It has one `## When to write an entry` section in which the rule's four points are
  four different list items (other content in the section is allowed): only when the investigation produces technical information that could help resolve a
  future issue; check whether the knowledge already exists and update the existing entry rather
  than a duplicate; capture the problem, root cause, investigation steps, resolution or workaround
  and technical context; skip it if no reusable technical knowledge was gained. Neither README nor
  `INDEX.md` has `awaiting-customer` (Unit B4's). `INDEX.md`'s status key names the five statuses
  with five different icons.
- **`kb`** with no argument creates `knowledge-base/` with the four files, each a byte-for-byte
  copy, one `created` line each giving its path from the repo root, exit 0; no `sync-state.json`,
  no `{{GMAIL_`. Run again: one `kept` line each, no `created`, nothing changed. An edited file is
  kept as it is; a deleted one is created again. `kb docs/kb` creates `docs/kb/` and no
  `knowledge-base/`. `kb knowledge-base/` works as the default. From a subfolder the folder goes at
  the repo root. Two arguments, `../outside`, an absolute path outside the repo, a path where any
  existing part is a file, and a symlink resolving outside the repo (`link`, `link/sub`,
  `link/new/kb`, a dangling one) all exit 2 with `ada-onboard: ...` on stderr, no traceback, and
  nothing written anywhere; the two controls in the same repos create four files.
- **`check`** prints exactly what it printed before Unit B1 (`BASE`) and what Unit B1 printed
  (`B1`), with the same exit status, for: no Kind line; `site` with `none`; `site` with a path;
  `product` with `none`; Kind still the template's prompt. For `product` with a path and no folder
  it prints one more line than before Unit B1, starting `missing`, naming the path (code span or
  first word; LF or CRLF; root or subfolder).
- **A new product (section 5):** once `kb` has made the folder the `missing` line is gone and one
  `fill in` line names `config.json` (no Gmail placeholder in it) while either placeholder is left;
  with both filled, `check` prints exactly what it printed before Unit B1 and what Unit B1 printed,
  and nothing asks for `sync-state.json`.
- **A product onboarded before this step (section 6):** a folder made by Unit B1's `kb`, with
  `config.json`'s `gmail.mailbox`, `gmail.query` and Asana keys filled and a `sync-state.json` with
  Gmail items (as Waitlist's): `check`, from the root or a subfolder, prints exactly what it printed
  before Unit B1 and what Unit B1 printed, same exit status, and names neither `sync-state.json`
  nor Gmail nor the knowledge base. `kb` run on it prints `kept` for the four template files, no
  `created`, nothing about `sync-state.json` or Gmail, and leaves `sync-state.json` and
  `config.json` (its `gmail` keys too) byte for byte; `check` after it is still the same.
- **The template `PROJECT.md`** has both bullets in "The project", worded as Unit B1's brief, after
  Known issues and Kind before Knowledge base; either bullet as the template has it makes `check`
  report `draft`; filled in, `ok`.
- **Regression:** a fresh `files` creates the same files as before Unit B1 and as Unit B1, the
  same `Placeholders left:` line, and no knowledge-base folder; `check` on a fresh copy prints the
  same lines as before Unit B1 (bar `PROJECT.md`'s prompt count, up by at least two) and exactly
  what Unit B1 printed; `refresh` lists the same templates and changes nothing.
- **Text:** the onboard skill's step 2 Kind row says to ask, product or site, and for a product
  the Asana project for support tickets (name and GID), with no mailbox, Gmail or search-query
  question anywhere in step 2. A step after step 4 and before Hand over, for products only, names
  `ada-onboard kb`, `config.json`, `Knowledge base:`, Asana, and every placeholder the template
  `config.json` has, and names no mailbox, query, Gmail, `sync-state.json` or `/ada:kb-sync`; the
  whole skill names none of them. `API.md`'s clock and kb rows point at the skill's steps by their
  numbers; its kb row gives `created` and `kept` and lists the four files, not `sync-state.json`;
  its `check` row mentions the knowledge base. CHANGELOG Unreleased still has Unit B1's entry,
  has exactly one entry for the "When to write an
  entry" rule, and no `Nothing yet.`. Unit B1's entry (the one naming `ada-onboard kb`) names no
  mailbox, Gmail or sync; nothing in Unreleased names `/ada:kb-sync`, `sync-state.json` or
  `lastSync`. `plugin.json`'s and `marketplace.json`'s versions, and the CHANGELOG's release
  headings, are Unit B1's (P31).
- **What Unit B2 removes and must not add:** `git grep -niE 'kb-sync|sync-state|lastSync|gmail'`
  over `plugins/`, `.claude/team/`, `README.md` and `DEVELOPING.md` (untracked files included)
  finds exactly one line, the template README's `gmail:<thread id>` source line. There is no
  `skills/kb-sync`, no kb-writer agent, no `kb-nudge.md`, nothing naming them; the agents and
  skills are Unit B1's; `skills/begin/SKILL.md` and `skills/begin/pr-gate.md` are byte for byte
  Unit B1's.

The break in step 4 (Unit B1's plugin; the docs are this checkout's) prints `FAIL` for the `B2:`
lines about `bin/ada-onboard`, the template, `kb`'s output, the skill and the grep's line count
(32 on 13 Nov 2026), for the four-file lines in 1, 2 and 3 (Unit B1's template has five) and the
two controls, and for section 6's "no line names sync-state.json or Gmail". The `setup:` lines,
sections 4, 7 and 8, the refusals in 3, section 6's `check` comparisons, and the agent, skill,
`begin`, kb-writer, API.md, CHANGELOG and version lines (Unit B1 compared with itself, or this
checkout's docs) PASS. Put the `info:` lines in the Run record.

## Observe at

stdout and `echo $?`. On a `FAIL` the scratch folder is kept and named: one scratch repo per
section (`kb default/`, `kb path/`, `check/`, `sequence/`, `onboarded before/`, `fresh/`,
`freshold/`, `freshb1/` ...), and beside them `out`, `err`, `rc` (the last `kb` run), `old.chk`,
`new.chk` (the last `check` before and after), `when` (the README's When to write an entry
section) and `grep` (section 10's hits).

## Regression

Sections 4, 7 and 8 are the regression: onboarding a project that isn't a product, or one
onboarded before Unit B1, changes nothing (Unit B2 brief, Decisions: "No `Kind:` line, or
`Kind: site`: onboarding and `ada-onboard check` behave exactly as before"). Section 6 is the
regression for a product onboarded with Unit B1 (Waitlist). `check` from a subfolder by a
relative path is in `check-from-subfolder.md`; `refresh`'s five outcomes in
`refresh-adds-design-line.md`.
