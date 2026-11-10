# `ada-onboard kb` creates a product's knowledge-base folder without overwriting; `check` flags a product's missing folder and unfilled `config.json`, and nothing new for a site

Unit B1 of the knowledge-base plan (Asana 1219987986280083): onboarding records whether a project
is a `product` or a `site`, and for a product creates its knowledge-base folder from
`plugins/ada/templates/knowledge-base/`. A project with no `Kind:` line, or `Kind: site`, must
behave exactly as before.

- Area: 02 Onboarding and refresh (`kb`, `check`, `files`, `refresh`, the onboard skill's text);
  11 Templates (the `PROJECT.md` template's two bullets, the knowledge-base template folder);
  12 Platforms (bash 3.2, Python 3.8, a CRLF `PROJECT.md`)
- Asana: https://app.asana.com/0/0/1220058587596437/f
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 9 Nov 2026, 0 failed (195 PASS) under bash 5.2; the 11a2540 break prints 104 FAIL as expected. Bash 3.2 and Python 3.8 last run 30 Oct 2026 (before the rebase onto main)

The skill's behaviour in a real onboarding (what it asks, what it writes) is the dry run in
`onboard-asks-kind-and-creates-knowledge-base.md`.

## Precondition

- This checkout (`REPO`), with the change in its working tree. The script runs this checkout's
  `ada-onboard` by path, never the one on the PATH.
- `git`, `tar`, `cmp`, `python3` (3.8+). No network, no GitHub login: every scratch
  `PROJECT.md` says `Approvers: none`, so `ada-brief who` needs no `gh`.
- `BASE` (default `11a2540`, the 0.13.0 release on next-release, the commit before
  this step) must be in this checkout: the script exports its plugin as "before", and compares
  `check`, `files` and `refresh` with it.
- The scratch repos sit in a folder whose name has a space.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Run it under bash 3.2 and Python 3.8 ("bash 3.2" and "Python 3.8" below).
4. Break it on purpose: `REPO=<this checkout> PLUGIN=<an export of 11a2540>/plugins/ada bash check.sh`
   (`git -C <this checkout> archive 11a2540 plugins/ada | tar -x -C <empty folder>`). Every
   `kb`, knowledge-base `check`, template-bullet and skill check FAILs; the `setup:` checks, the
   "nothing new" comparisons (the old script compared with itself) and the regressions PASS.

```bash
#!/bin/bash
# ada-onboard kb; check's knowledge-base lines; the Kind and Knowledge base bullets; the onboard
# skill, API.md and CHANGELOG text. PASS/FAIL per check; exit 1 if any FAIL, 2 if set-up fails.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada),
#      BASE (the commit before this step, default 11a2540), OLD_PLUGIN (BASE's plugins/ada;
#      exported from BASE if unset), SH (how to run the script under test, default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE=${BASE:-11a2540}
SH=${SH:-bash}
for tool in git python3 tar cmp; do command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }; done
T=$(mktemp -d) || exit 2
W="$T/kb scratch"; mkdir "$W" || exit 2
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
NEWB=$PLUGIN/bin/ada-onboard; OLDB=$OLD_PLUGIN/bin/ada-onboard
KBT=$PLUGIN/templates/knowledge-base
PJT=$PLUGIN/templates/repo/.claude/team/PROJECT.md
SKILL=$PLUGIN/skills/onboard/SKILL.md
API=$REPO/.claude/team/API.md
CL=$REPO/CHANGELOG.md
FILES="README.md INDEX.md config.json sync-state.json issues/.gitkeep"
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
# cmpchk <label> <dir>: check before (BASE) and after (under test) from <dir>
cmpchk() { chk "$2" "$OLDB" "$W/old.chk"; chk "$2" "$NEWB" "$W/new.chk"; info "$1: check exit before $(cat "$W/old.chk.rc"), after $(cat "$W/new.chk.rc")"; }
extra() { grep -vxFf "$W/old.chk" "$W/new.chk"; }   # lines check prints now that it didn't before
gone()  { grep -vxFf "$W/new.chk" "$W/old.chk"; }   # lines it printed before and doesn't now
same() {
  cmpchk "$1" "$2"
  t "$1: check prints nothing new" test -z "$(extra)"
  t "$1: check drops nothing" test -z "$(gone)"
  t "$1: check's exit status as before" test "$(cat "$W/old.chk.rc")" = "$(cat "$W/new.chk.rc")"
}
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
pyver=$(python3 -c 'import sys; print("%d.%d" % sys.version_info[:2])')
info "bash $BASH_VERSION, python3 $pyver, before = $BASE, plugin = $PLUGIN"

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

echo "== 1. The template folder"
t "templates/knowledge-base/ exists" test -d "$KBT"
t "it holds exactly README.md, INDEX.md, config.json, sync-state.json, issues/.gitkeep" test "$(tree "$KBT")" = "$WANT"
t "not under templates/repo/, which every project gets" test -z "$(cd "$PLUGIN/templates/repo" && find . \( -name 'knowledge-base' -o -name sync-state.json -o -name '.gitkeep' -path '*issues*' \) -print)"
t "sync-state.json is exactly {gmail: {lastSync: 0, items: {}}, asana: {lastSync: null, items: {}}}" python3 - "$KBT/sync-state.json" <<'EOF'
import json, sys
d = json.load(open(sys.argv[1], encoding='utf-8'))
want = {"gmail": {"lastSync": 0, "items": {}}, "asana": {"lastSync": None, "items": {}}}
ok = d == want and type(d["gmail"]["lastSync"]) is int and d["asana"]["lastSync"] is None
sys.exit(0 if ok else 1)
EOF
t "config.json parses; gmail.mailbox, gmail.query, asana.projectName, asana.projectGid are four different {{KEY}} placeholders" python3 - "$KBT/config.json" <<'EOF'
import json, re, sys
d = json.load(open(sys.argv[1], encoding='utf-8'))
v = [d["gmail"]["mailbox"], d["gmail"]["query"], d["asana"]["projectName"], d["asana"]["projectGid"]]
ok = all(isinstance(x, str) and re.match(r'^\{\{[A-Z0-9_]+\}\}$', x) for x in v) and len(set(v)) == 4
sys.exit(0 if ok else 1)
EOF
R=$KBT/README.md
t "README: customer emails stay in Gmail and Asana; /ada:kb-sync's subagent reads them there (one paragraph)" para "$R" 'stay in Gmail' 'kb-sync' 'subagent' 'connectors'
t "README: nothing from them reaches this folder except the anonymised entry" para "$R" 'thread' 'anonymi[sz]ed'
t "README: no 'pasted into an AI tool' line (replaced, per the brief)" not grep -Eqi 'pasted into an? AI' "$R"
hasall() { hf=$1; shift; for w in "$@"; do grep -Fqi -- "$w" "$hf" || return 1; done; }
t "README: Entry format section" grep -Eq '^## Entry format' "$R"
t "README: the entry's front-matter fields title, category, status, tags, sources, each as a 'field:' line" sh -c "for f in title category status tags sources; do grep -Eq \"^\$f:\" '$R' || exit 1; done"
t "README: the entry's four sections Symptoms, Cause, Solution, How to diagnose" sh -c "for h in Symptoms Cause Solution 'How to diagnose'; do grep -Eq \"^## \$h\\\$\" '$R' || exit 1; done"
t "README: sources are IDs only, and code references carry no line numbers" hasall "$R" 'IDs only' 'no line numbers'
t "README: a no-customer-data rule, under its own heading" sh -c "grep -Eq '^## No customer data' '$R' && grep -Eqi 'customer (data|names?|e-?mails?|details)|personal data|no customer' '$R'"
t "README: the category list: all eight, in one paragraph (bug, conflict, configuration, how-to, feature, licensing, support-process, development)" para "$R" 'categories' '`bug`' '`conflict`' '`configuration`' '`how-to`' '`feature`' '`licensing`' '`support-process`' '`development`'
t "README: the status list: all five, in one paragraph (resolved, workaround, fixed-in-release, open-bug, unresolved)" para "$R" 'statuses' '`resolved`' '`workaround`' '`fixed-in-release`' '`open-bug`' '`unresolved`'
t "README: says how syncing works (names /ada:kb-sync)" grep -q 'kb-sync' "$R"
t "README: a Syncing section naming config.json and sync-state.json" sh -c "grep -Eq '^## Syncing' '$R' && grep -q 'config\.json' '$R' && grep -q 'sync-state\.json' '$R'"
t "INDEX: an example area heading" grep -Eq '^##+ ' "$KBT/INDEX.md"
t "INDEX: the status key names all five statuses (resolved, workaround, fixed in release, open bug, unresolved)" para "$KBT/INDEX.md" 'status' 'resolved' 'workaround' 'fixed.in.release' 'open.bug' 'unresolved'
t "INDEX: the status key has five different icons (symbols, not letters)" python3 - "$KBT/INDEX.md" <<'EOF'
import sys, unicodedata
s = open(sys.argv[1], encoding='utf-8').read()
icons = {c for c in s if unicodedata.category(c) == 'So'}
sys.exit(0 if len(icons) >= 5 else 1)
EOF
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
t "kb: one 'created' line per file, five in all" test "$(grep -c '^created' "$W/out")" = 5
t "kb: no 'kept' line on a first run" not grep -q '^kept' "$W/out"
t "kb: knowledge-base/ holds exactly the five files" test "$(tree "$A/knowledge-base")" = "$WANT"
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
t "kb after edit/delete: 'kept' for the other three" sh -c "grep -Eq '^kept +knowledge-base/config\.json\$' '$W/out' && grep -Eq '^kept +knowledge-base/sync-state\.json\$' '$W/out' && grep -Eq '^kept +knowledge-base/issues/\.gitkeep\$' '$W/out'"

echo "== 3. kb <path>, a trailing slash, a subfolder, bad arguments"
newrepo "kb path"; B="$W/kb path"
kb "$B" docs/kb
info "kb docs/kb: exit $(rc): $(tr '\n' '|' < "$W/out")"
t "kb docs/kb: exit 0" test "$(rc)" = 0
for f in $FILES; do t "kb docs/kb: 'created' and the template's copy for docs/kb/$f" sh -c "grep -Eq '^created +docs/kb/$(esc "$f")\$' '$W/out' && cmp -s '$KBT/$f' '$B/docs/kb/$f'"; done
t "kb docs/kb: docs/kb/ holds exactly the five files" test "$(tree "$B/docs/kb")" = "$WANT"
t "kb docs/kb: no knowledge-base/ at the root" test ! -e "$B/knowledge-base"
newrepo "kb slash"; C="$W/kb slash"
kb "$C" knowledge-base/
t "kb knowledge-base/: exit 0" test "$(rc)" = 0
t "kb knowledge-base/: the five files in knowledge-base/" test "$(tree "$C/knowledge-base")" = "$WANT"
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
t "control: kb other/kb in the repo with the file: exit 0, five files" sh -c "test \"\$(cat '$W/rc')\" = 0 && test \"\$(grep -c '^created' '$W/out')\" = 5 && test -f '$F/other/kb/config.json'"
mkdir "$L/real"; kb "$L" real/kb
t "control: kb real/kb in the repo with the symlinks: exit 0, five files, inside the repo" sh -c "test \"\$(cat '$W/rc')\" = 0 && test \"\$(grep -c '^created' '$W/out')\" = 5 && test -f '$L/real/kb/config.json'"

echo "== 4. check: nothing new unless Kind is product with a Knowledge base path"
K_WANT="- **Kind:** <one of \`product\` (a plugin or theme PIE sells and supports) or \`site\` (a client's website)>"
B_WANT="- **Knowledge base:** \`none\` <or, for a product, the folder's path, e.g. \`knowledge-base/\`>"
newrepo "check"; D="$W/check"; mkdir -p "$D/sub"
proj "$D" "" ""
chk "$D" "$NEWB" "$W/setup.chk"
t "setup: a filled-in PROJECT.md checks ok" grep -Eq '^ok +\.claude/team/PROJECT\.md' "$W/setup.chk"
t "setup: no brief-approval error" not grep -qi 'brief approval' "$W/setup.chk"
same "a. no Kind or Knowledge base line (a repo onboarded before)" "$D"
proj "$D" '- **Kind:** `site`' '- **Knowledge base:** `none`'
same "b. Kind site, Knowledge base none" "$D"
proj "$D" '- **Kind:** `site`' '- **Knowledge base:** `knowledge-base/`'
same "c. Kind site with a path, no folder" "$D"
proj "$D" '- **Kind:** `product`' '- **Knowledge base:** `none`'
same "d. Kind product, Knowledge base none" "$D"
proj "$D" "$K_WANT" '- **Knowledge base:** `knowledge-base/`'
same "e. Kind still the template prompt (unset), a path, no folder" "$D"
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

echo "== 5. Sequence: product, no folder -> kb -> fill config.json"
newrepo "sequence"; S="$W/sequence"
proj "$S" '- **Kind:** `product`' '- **Knowledge base:** `knowledge-base/`'
onenew "5.1 product, no folder yet" "$S" missing knowledge-base
kb "$S"
t "5.2 kb: exit 0" test "$(rc)" = 0
onenew "5.2 after kb, config.json unfilled" "$S" 'fill in' config.json
t "5.2 the missing-folder line is gone" not grep -q '^missing.*knowledge-base' "$W/new.chk"
fill "$S/knowledge-base/config.json" 1 2>/dev/null
onenew "5.3 one placeholder filled, three left" "$S" 'fill in' config.json
fill "$S/knowledge-base/config.json" 0 2>/dev/null
t "setup: config.json is there and has no {{KEY}} left" sh -c "test -f '$S/knowledge-base/config.json' && ! grep -q '{{' '$S/knowledge-base/config.json'"
same "5.4 every placeholder filled" "$S"
newrepo "sequence path"; S2="$W/sequence path"
proj "$S2" '- **Kind:** `product`' '- **Knowledge base:** `docs/kb/`'
kb "$S2" docs/kb
onenew "5.5 docs/kb created by kb docs/kb, config.json unfilled" "$S2" 'fill in' docs/kb/config.json

echo "== 6. The PROJECT.md template's bullets"
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

echo "== 7. Regression: a fresh onboarding (files, check, refresh) as before"
newrepo fresh; newrepo freshold
(cd "$W/fresh" && $SH "$NEWB" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/fresh.out" 2>&1
(cd "$W/freshold" && $SH "$OLDB" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/freshold.out" 2>&1
paths() { grep -E '^(created|kept) ' "$1" | awk '{print $2}' | LC_ALL=C sort; }
t "files: the same files created as before" test "$(paths "$W/fresh.out")" = "$(paths "$W/freshold.out")"
t "files: 'Placeholders left:' as before (the bullets add no {{KEY}})" test "$(grep '^Placeholders left' "$W/fresh.out")" = "$(grep '^Placeholders left' "$W/freshold.out")"
t "files: no knowledge-base folder, and nothing about one" sh -c "test ! -e '$W/fresh/knowledge-base' && ! grep -q 'knowledge-base' '$W/fresh.out'"
t "files: the fresh PROJECT.md has both bullets" sh -c "grep -q '^- \*\*Kind:\*\*' '$W/fresh/.claude/team/PROJECT.md' && grep -q '^- \*\*Knowledge base:\*\*' '$W/fresh/.claude/team/PROJECT.md'"
chk "$W/fresh" "$NEWB" "$W/fresh.chk"; chk "$W/freshold" "$OLDB" "$W/freshold.chk"
t "check on a fresh copy: PROJECT.md flagged, as before" grep -Eq '^(draft|fill in) +\.claude/team/PROJECT\.md' "$W/fresh.chk"
mask() { sed -E 's/^(draft +\.claude\/team\/PROJECT\.md: )[0-9]+/\1N/' "$1" | LC_ALL=C sort; }
npr() { grep -E '^draft +\.claude/team/PROJECT\.md: [0-9]+' "$1" | sed -E 's/^[^:]*: ([0-9]+).*/\1/'; }
info "check on a fresh copy: PROJECT.md prompt lines before $(npr "$W/freshold.chk"), after $(npr "$W/fresh.chk")"
t "check on a fresh copy: the same lines as before (PROJECT.md's prompt count aside)" test "$(mask "$W/fresh.chk")" = "$(mask "$W/freshold.chk")"
t "check on a fresh copy: PROJECT.md's prompt lines up by at least 2, one per new bullet" test "$(npr "$W/fresh.chk")" -ge $(( $(npr "$W/freshold.chk") + 2 ))
gitc -C "$W/fresh" add -A; gitc -C "$W/fresh" commit -q -m onboarded >/dev/null 2>&1
rpaths() { grep -E '^(current|refreshed|declined|edited|unknown) ' | awk '{print $2}' | LC_ALL=C sort; }
po=$(cd "$W/fresh" && $SH "$OLDB" refresh 2>/dev/null | rpaths)
pn=$(cd "$W/fresh" && $SH "$NEWB" refresh 2>/dev/null | rpaths)
t "refresh: lists the same templates as before ($(echo $po))" test "$po" = "$pn"
t "refresh: leaves the fresh copy as it was" test -z "$(git -C "$W/fresh" status --porcelain)"

echo "== 8. The onboard skill, API.md, CHANGELOG"
awk '/^## 2\./{p=1;next} p&&/^## /{exit} p' "$SKILL" > "$W/s2"
grep -Ei '^\| *(\*\*)?Kind' "$W/s2" > "$W/kindrow"
t "skill step 2: a Kind row" test -s "$W/kindrow"
t "skill Kind row: product or site" sh -c "grep -qi 'product' '$W/kindrow' && grep -qi 'site' '$W/kindrow'"
t "skill Kind row: says to ask" grep -qi 'ask' "$W/kindrow"
t "skill Kind row: for a product, the Gmail mailbox" sh -c "grep -qi 'gmail' '$W/kindrow' && grep -qi 'mailbox' '$W/kindrow'"
t "skill Kind row: and the search query" grep -qi 'query' "$W/kindrow"
t "skill Kind row: and the Asana project for support tickets" sh -c "grep -qi 'asana' '$W/kindrow' && grep -Eqi 'support|ticket' '$W/kindrow'"
awk 'function done(){ if (h != "" && b ~ /ada-onboard kb/ && b ~ /config\.json/ && b ~ /Knowledge base:/) { print hn; found=1 } }
     /^## / { if (!found) done(); h=$0; hn=NR; b=""; next } { b = b "\n" $0 } END { if (!found) done() }' "$SKILL" > "$W/kbstep"
ln_kb=$(head -1 "$W/kbstep"); ln_s4=$(grep -n '^## 4\.' "$SKILL" | head -1 | cut -d: -f1)
ln_ho=$(grep -n '^## [0-9]*\. Hand over' "$SKILL" | head -1 | cut -d: -f1)
info "skill: step 4 at line ${ln_s4:-?}, knowledge-base step at ${ln_kb:-none}, Hand over at ${ln_ho:-?}: $(sed -n "${ln_kb:-1}p" "$SKILL")"
t "skill: a step naming ada-onboard kb, config.json and Knowledge base:" test -n "$ln_kb"
t "skill: that step comes after step 4 and before Hand over" test "${ln_kb:-0}" -gt "${ln_s4:-999999}" -a "${ln_kb:-0}" -lt "${ln_ho:-0}"
awk -v n="${ln_kb:-0}" 'NR==n{p=1;print;next} p&&/^## /{exit} p' "$SKILL" > "$W/kbsect"
t "skill: that step is for products only" sh -c "grep -qi 'product' '$W/kbsect' && grep -Eqi 'only|skip|site' '$W/kbsect'"
t "skill: that step fills config.json from the step 2 answers (mailbox, query, Asana)" sh -c "grep -qi 'mailbox' '$W/kbsect' && grep -qi 'query' '$W/kbsect' && grep -qi 'asana' '$W/kbsect'"
t "skill: the clock step still runs ada-onboard clock (regression)" grep -q 'ada-onboard clock' "$SKILL"
n_clock=$(grep -E '^## [0-9]+\. The local clock' "$SKILL" | head -1 | sed 's/^## \([0-9]*\)\..*/\1/')
n_api=$(grep -E '^\| `clock ' "$API" | grep -oE 'SKILL\.md`?, [0-9]+' | head -1 | sed 's/.*, //')
t "API.md's clock row points at the skill's clock step by its number (${n_api:-?} = ${n_clock:-?})" test -n "$n_clock" -a "$n_clock" = "${n_api:-x}"
awk '/^### `ada-onboard`/{p=1;next} p&&/^##/{exit} p' "$API" > "$W/api"
t "API.md: the ada-onboard table has a kb row" grep -Eq '^\| `kb' "$W/api"
t "API.md: the kb row gives the created/kept output" sh -c "grep -E '^\| \`kb' '$W/api' | grep -q 'created' && grep -E '^\| \`kb' '$W/api' | grep -q 'kept'"
t "API.md: the check row mentions the knowledge base" sh -c "grep -E '^\| \`check' '$W/api' | grep -Eqi 'knowledge|config\.json'"
awk '/^## Unreleased/{p=1;next} p&&/^## /{exit} p' "$CL" > "$W/unrel"
t "CHANGELOG Unreleased: an entry for the knowledge base / ada-onboard kb" grep -Eqi 'knowledge.base|ada-onboard kb' "$W/unrel"
t "CHANGELOG Unreleased: no 'Nothing yet.' left (S105)" not grep -q 'Nothing yet' "$W/unrel"

echo "$fails failed, $passes passed"
if [ "$fails" = 0 ]; then rm -rf "$T"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2

With Docker and the `bash:3.2` image (it has no git, so the container adds it; that needs the
network). With `S` the scratch folder holding `check.sh`:

```sh
mkdir -p "$S/old"
git -C <this checkout> archive 11a2540 plugins/ada | tar -x -m -C "$S/old"
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && REPO=/repo OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

On a Mac, `REPO=<this checkout> /bin/bash check.sh` uses the system bash 3.2.

### Python 3.8

The same, in `python:3.8-slim` (its `python3` is 3.8, so section 0 compiles every heredoc with
3.8 and every `check`, `files` and `refresh` run uses it):

```sh
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
  sh -c 'apt-get update -qq >/dev/null && apt-get install -y -qq git >/dev/null && git config --global --add safe.directory "*" && python3 --version && REPO=/repo OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

## Expected

Every line `PASS`, last line `0 failed, <n> passed`, exit 0, under the local bash, bash 3.2 and
Python 3.8 (the `info:` line says which). In detail, from the brief (Decisions; Definition of
done), `API.md` (`ada-onboard`: `check`'s words `missing`, `fill in`, `draft`, `ok`, `error`) and
`STANDARDS.md` (S100 to S105):

- **The template folder** holds exactly `README.md`, `INDEX.md`, `config.json`,
  `sync-state.json`, `issues/.gitkeep`, and isn't in `templates/repo/`. `sync-state.json` is
  exactly `{"gmail": {"lastSync": 0, "items": {}}, "asana": {"lastSync": null, "items": {}}}`.
  `config.json` parses, with `gmail.mailbox`, `gmail.query`, `asana.projectName` and
  `asana.projectGid` as four `{{KEY}}` placeholders. The README says customer threads are read
  only by `/ada:kb-sync`'s subagent and only the anonymised entry reaches the folder, and has no
  "pasted into an AI tool" line. It lists the five front-matter fields and four sections of an
  entry, all eight categories (`bug`, `conflict`, `configuration`, `how-to`, `feature`,
  `licensing`, `support-process`, `development`) and all five statuses (`resolved`, `workaround`,
  `fixed-in-release`, `open-bug`, `unresolved`); `INDEX.md`'s status key names the five statuses
  with five different icons. Nothing here asserts the README's sentence about releases: its
  wording is Bob's (the folder is for the team, never part of a release), and the old "nothing
  here ships with it" is not asserted either.
- **`kb`** with no argument creates `knowledge-base/` with the five files, each a byte-for-byte
  copy, one `created` line each giving its path from the repo root (`knowledge-base/config.json`,
  as `files` prints paths), exit 0.
  Run again: one `kept` line each, no `created`, nothing changed. An edited file is kept as it
  is; a deleted one is created again. `kb docs/kb` creates `docs/kb/` and no `knowledge-base/`.
  `kb knowledge-base/` works as the default. From a subfolder the folder goes at the repo root.
  Two arguments, a path out of the repo (`../outside`) and an absolute path outside it exit 2,
  with nothing written anywhere. So does a path where any existing part is a file (`kb afile/kb`,
  `kb afile/deeper/kb`, `kb afile`): `ada-onboard: ...` on stderr, exit 2, no Python traceback,
  nothing written, the file untouched. So does a symlink in the repo that resolves outside it
  (`kb link`, `kb link/sub`, `kb link/new/kb`, and a dangling one): exit 2, nothing written
  outside the repo or in it.
- **`check`** prints exactly what it printed before this step, with the same exit status, for: no
  Kind line; `site` with `none`; `site` with a path; `product` with `none`; Kind still the
  template's prompt. For `product` with a path and no folder it prints one more line, starting
  `missing`, naming the path (as a code span or a first word; LF or CRLF; from the root or a
  subfolder). Once `kb` has made the folder that line is gone and one `fill in` line names
  `config.json` while any `{{KEY}}` is left in it; with all filled, `check` prints what it did
  before.
- **The template `PROJECT.md`** has both bullets in "The project", worded as the brief (the Kind
  bullet as amended on 2026-10-30, agreed by Anna: `<one of `product` (…) or `site` (…)>`), after
  Known issues and Kind before Knowledge base. Either bullet, line for line as the template has
  it, makes `check` report `draft` for `PROJECT.md`; filled in, `ok`. (`check` matches a
  `<prompt>` within one line, so a bullet whose `<` and `>` are on different lines isn't
  flagged: these checks and the prompt count in section 7 fail for it.)
- **Regression:** a fresh `files` creates the same files and the same `Placeholders left:` line as
  before and no knowledge-base folder; `check` on a fresh copy prints the same lines as before,
  except that `PROJECT.md`'s `draft` line counts at least two more prompt lines (the bullets);
  `refresh` lists the same templates and changes nothing.
- **Text:** the onboard skill's step 2 has a Kind row (ask; product or site; for a product the
  Gmail mailbox, the search query and the Asana project for support tickets); a step after step 4
  and before Hand over, for products only, names `ada-onboard kb`, `config.json` and
  `Knowledge base:`; the clock step's number in `API.md` still matches the skill. `API.md` has a
  `kb` row with `created` and `kept`, and its `check` row mentions the knowledge base; CHANGELOG
  Unreleased has the entry.

The break in step 4 (the plugin before this step) prints `FAIL` for sections 0 (usage header), 1,
2, 3 (bar the refusal lines, "nothing written" and "no traceback" ones, which pass because the old script has no `kb` and exits 2 as for any unknown subcommand; the two `control:` lines FAIL, which is what shows the refusals mean something), the
product cases in 4 and 5, 6, the bullets and prompt count in 7, and the skill lines of 8; `setup:`
lines and the rest of 7 PASS. Put the
`info:` lines in the Run record.

## Observe at

stdout and `echo $?`. On a `FAIL` the scratch folder is kept and named: one scratch repo per
section (`kb default/`, `kb path/`, `check/`, `sequence/`, `fresh/` and `freshold/` ...), and
beside them `out`, `err`, `rc` (the last `kb` run) and `old.chk`, `new.chk` (the last `check`
before and after).

## Regression

Section 7 is the regression: onboarding a project that isn't a product, or one onboarded before
this step, changes nothing (brief, Decisions: "behaves exactly as today"). `check` from a
subfolder by a relative path is in `check-from-subfolder.md`; `refresh`'s five outcomes in
`refresh-adds-design-line.md`.
