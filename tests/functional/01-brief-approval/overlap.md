# `ada-brief overlap` lists work in flight and marks what shares the brief's files

- Area: 01 Brief approval (`ada-brief`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219790041247935
- Automate as: none (part A is a self-checking shell script with a stub `gh`; part B needs GitHub)
- Last run: 26 Oct 2026, part A 0 failed under bash 5 and Docker bash 3.2 (156 PASS each); part B not run this round (needs an open PR on GitHub; last run about 23 Oct 2026, 11 PASS, 0 failed against PR #34, logged as 5 Nov by a clock set 13 days fast, before sections 10 and 11 existed); see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree. The script runs this checkout's
  `ada-brief` by path, never the one on the PATH.
- `git`, `python3` (3.8+), `jq` (the stub `gh` uses it for `--jq`), `mktemp`. Part A needs no
  network and no GitHub login: it makes scratch repos with a local bare repo as `origin` and puts
  a stub `gh` first on the PATH. The stub logs every call; a call it doesn't understand prints
  `STUB-GAP` (fix the stub, not the code). `--slurp` fails as it does on gh 2.45. Sections 10
  and 11 (`--gate`; 101 PRs, 100 files) need git 2.15 or later (`--is-shallow-repository`) and
  clone `file://` URLs with `--depth 1`.
- Part B: `gh` logged in, network, and a repo with an open PR (below).

The output format (the brief's Amendments, 22 Oct 2026, agreed by Neil): line 1 `touching <n>`
or `touching -`; `waiting to ship:`, then `open:`; each entry one line indented two spaces:
`  #N <subject without (#N)> [a, b]`, `  <short hash> <subject> [a, b]`, `  #N [draft ]<title> [a, b]`
(`draft ` only on drafts); the changed files in literal square brackets separated by `, `; a
marked entry ends ` touches: a, b`; an empty group `  none`; no gh `  unknown (<reason>)`. The
checks read each entry whole: the text before the brackets exactly, and the files inside them
as a set (the brief doesn't fix their order).

## Steps

### Part A: scratch repos and a stub gh

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, then under bash 3.2 (below).
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, and in the copy's
   `bin/ada-brief` make the overlap arm skip the first file argument (or print `touching 0`
   always), then run `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL`.

```bash
#!/bin/bash
# ada-brief overlap in scratch repos. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
AB=$PLUGIN/bin/ada-brief
BASH_BIN=$(command -v bash)
W=$(mktemp -d)
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
G() { git -c user.name=t -c user.email=t@example.invalid -c init.defaultBranch=init "$@"; }
for tool in git python3 jq; do command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }; done

# --- the stub gh ------------------------------------------------------------------------------
mkdir -p "$W/stub"
cat > "$W/stub/gh" <<'STUB'
#!/usr/bin/env python3
# Stub gh. Env: STUB_PRS (JSON list of {number,title,base,head,draft,state,files}),
# STUB_AUTH (ok | fail), STUB_LOG (file: one line per call). Like gh, `pr list --json files`
# returns at most 100 files per PR, and `pr list` at most --limit PRs.
import json, os, subprocess, sys
from urllib.parse import urlsplit, parse_qsl
args = sys.argv[1:]
LOG = os.environ.get('STUB_LOG')
def log(line):
    if LOG:
        with open(LOG, 'a') as f: f.write(line + '\n')
log('gh ' + ' '.join(args))
def gap(why):
    log('STUB-GAP ' + why); sys.stderr.write('STUB-GAP: %s: gh %s\n' % (why, ' '.join(args))); sys.exit(3)
if '--slurp' in args:
    sys.stderr.write('unknown flag: --slurp\n'); sys.exit(1)
if args[:2] == ['auth', 'status']:
    if os.environ.get('STUB_AUTH', 'ok') == 'ok':
        sys.stderr.write('github.com\n  Logged in to github.com account tester\n'); sys.exit(0)
    sys.stderr.write('You are not logged into any GitHub hosts. Run gh auth login to authenticate.\n'); sys.exit(1)
if os.environ.get('STUB_AUTH', 'ok') != 'ok':
    sys.stderr.write('To get started with GitHub CLI, please run:  gh auth login\n'); sys.exit(4)
PRS = json.load(open(os.environ['STUB_PRS'])) if os.environ.get('STUB_PRS') else []
VALUE = {'-B', '--base', '-H', '--head', '-s', '--state', '-L', '--limit', '--json', '-q', '--jq',
         '-R', '--repo', '-A', '--author', '-X', '--method', '-f', '--raw-field', '-F', '--field',
         '-H', '--header', '-l', '--label', '-a', '--assignee'}
BOOL = {'--paginate', '-d', '--draft', '--name-only', '-i', '--include', '--silent'}
def parse(a):
    pos, opt = [], {}
    i = 0
    while i < len(a):
        x = a[i]
        if x.startswith('--') and '=' in x:
            k, v = x.split('=', 1); opt.setdefault(k, []).append(v)
        elif x in VALUE:
            if i + 1 >= len(a): gap('flag without value')
            opt.setdefault(x, []).append(a[i + 1]); i += 1
        elif x in BOOL:
            opt.setdefault(x, []).append(True)
        elif x.startswith('-') and x != '-':
            gap('unknown flag ' + x)
        else:
            pos.append(x)
        i += 1
    return pos, opt
def one(opt, *names):
    for n in names:
        if n in opt: return opt[n][-1]
    return None
def emit(obj, opt):
    fields = one(opt, '--json')
    if fields:
        keep = [f.strip() for f in fields.split(',')]
        pick = lambda o: dict((k, o[k]) for k in keep if k in o)
        obj = [pick(o) for o in obj] if isinstance(obj, list) else pick(obj)
    q = one(opt, '-q', '--jq')
    text = json.dumps(obj)
    if q:
        r = subprocess.run(['jq', '-r', '-c', q], input=text, capture_output=True, text=True)
        sys.stdout.write(r.stdout); sys.stderr.write(r.stderr); sys.exit(r.returncode)
    print(text)
def cli(p):
    return {'number': p['number'], 'title': p['title'], 'isDraft': bool(p.get('draft')),
            'headRefName': p['head'], 'baseRefName': p['base'], 'state': p.get('state', 'OPEN'),
            'url': 'https://github.com/pie/scratch/pull/%d' % p['number'],
            'author': {'login': 'someone'}, 'changedFiles': len(p['files']),
            'files': [{'path': f, 'additions': 1, 'deletions': 0} for f in p['files'][:100]]}  # gh's cap
def rest(p):
    return {'number': p['number'], 'title': p['title'], 'draft': bool(p.get('draft')),
            'state': p.get('state', 'OPEN').lower(),
            'head': {'ref': p['head'], 'label': 'pie:' + p['head']}, 'base': {'ref': p['base']},
            'html_url': 'https://github.com/pie/scratch/pull/%d' % p['number'],
            'user': {'login': 'someone'}}
def state_ok(p, want):
    s = p.get('state', 'OPEN').upper()
    return want == 'all' or s == want.upper()
def current():
    return subprocess.run(['git', 'branch', '--show-current'], capture_output=True, text=True).stdout.strip()
pos, opt = parse(args)
if pos[:2] == ['repo', 'view']:
    emit({'nameWithOwner': 'pie/scratch', 'name': 'scratch', 'owner': {'login': 'pie'},
          'url': 'https://github.com/pie/scratch'}, opt)
elif pos[:2] == ['api', 'user']:
    emit({'login': 'tester'}, opt)
elif pos[:2] == ['pr', 'list']:
    if len(pos) > 2: gap('pr list with arguments')
    want = one(opt, '-s', '--state') or 'open'
    base, head = one(opt, '-B', '--base'), one(opt, '-H', '--head')
    out = [p for p in PRS if state_ok(p, want) and (base is None or p['base'] == base)
           and (head is None or p['head'] == head) and ('--draft' not in opt or p.get('draft'))]
    out = out[:int(one(opt, '-L', '--limit') or 30)]
    if one(opt, '--json'): emit([cli(p) for p in out], opt)
    else:
        for p in out: print('%d\t%s\t%s\tOPEN' % (p['number'], p['title'], p['head']))
elif pos[:2] == ['pr', 'view'] or pos[:2] == ['pr', 'diff']:
    sel = pos[2] if len(pos) > 2 else current()
    sel = sel.rstrip('/').split('/')[-1] if sel.startswith('http') else sel
    hits = [p for p in PRS if str(p['number']) == sel or p['head'] == sel]
    if sel == current(): hits = [p for p in hits if p.get('state', 'OPEN') == 'OPEN']
    if not hits:
        sys.stderr.write('no pull requests found for branch "%s"\n' % sel); sys.exit(1)
    if pos[1] == 'diff':
        if '--name-only' not in opt: gap('pr diff without --name-only')
        print('\n'.join(hits[0]['files']))
    elif one(opt, '--json'): emit(cli(hits[0]), opt)
    else: print('%s #%d' % (hits[0]['title'], hits[0]['number']))
elif pos[:1] == ['api'] and len(pos) == 2:
    if pos[1] == 'graphql': gap('graphql')
    u = urlsplit(pos[1].lstrip('/'))
    params = dict(parse_qsl(u.query))
    method = (one(opt, '-X', '--method') or ('POST' if ('-f' in opt or '-F' in opt or '--raw-field' in opt or '--field' in opt) else 'GET')).upper()
    for k in ('-f', '-F', '--raw-field', '--field'):
        for kv in opt.get(k, []): params[kv.split('=', 1)[0]] = kv.split('=', 1)[1] if '=' in kv else ''
    if method != 'GET':
        sys.stderr.write('gh: Validation Failed (HTTP 422)\n'); sys.exit(1)
    parts = u.path.replace('{owner}', 'pie').replace('{repo}', 'scratch').split('/')
    if len(parts) < 4 or parts[0] != 'repos' or parts[3] != 'pulls': gap('api path')
    page = int(params.get('page', '1'))
    if len(parts) == 4:
        want = params.get('state', 'open')
        base, head = params.get('base'), params.get('head')
        out = [rest(p) for p in PRS if state_ok(p, want) and (base is None or p['base'] == base)
               and (head is None or 'pie:' + p['head'] == head or p['head'] == head)]
        emit(out if page == 1 else [], opt)
    else:
        hits = [p for p in PRS if str(p['number']) == parts[4]]
        if not hits:
            sys.stderr.write('gh: Not Found (HTTP 404)\n'); sys.exit(1)
        if len(parts) == 5: emit(rest(hits[0]), opt)
        elif len(parts) == 6 and parts[5] == 'files':
            emit([{'filename': f, 'status': 'modified', 'additions': 1, 'deletions': 0, 'changes': 1}
                  for f in hits[0]['files']] if page == 1 else [], opt)
        else: gap('api path')
else:
    gap('command')
STUB
chmod +x "$W/stub/gh"
STUBPATH="$W/stub:$PATH"
export STUB_LOG="$W/gh.log"
# A PATH with every command on it except gh.
mkdir -p "$W/nogh"
oldifs=$IFS; IFS=:
for d in $PATH; do
  [ -d "$d" ] || continue
  for f in "$d"/*; do
    b=${f##*/}
    [ "$b" = gh ] && continue
    [ -x "$f" ] && [ ! -e "$W/nogh/$b" ] && [ ! -L "$W/nogh/$b" ] && ln -s "$f" "$W/nogh/$b"
  done
done
IFS=$oldifs
NOGH="$W/nogh"

# --- helpers ----------------------------------------------------------------------------------
# run <dir> <PATH> args... : ada-brief overlap args, output in $W/out, $W/err, $W/rc
run() { d=$1; p=$2; shift 2; (cd "$d" && PATH="$p" "$BASH_BIN" "$AB" overlap "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc() { cat "$W/rc"; }
first_is() { test "$(head -1 "$W/out")" = "$1"; }
grp() { awk -v h="$1" '$0==h{p=1;next} /^[^ ]/{p=0} p' "$W/out"; }
W8='waiting to ship:'; OP='open:'
line() { grp "$1" | grep -E -- "$2" | head -1; }                     # first entry matching
n_entries() { test "$(grp "$1" | grep -c '^  [^ ]')" = "$2"; }
has() { l=$(line "$1" "$2"); shift 2; test -n "$l" || return 1; for f; do case "$l" in *"$f"*) ;; *) return 1;; esac; done; }
ends() { l=$(line "$1" "$2"); case "$l" in *" touches: $3") return 0;; esac; return 1; }
unmarked() { l=$(line "$1" "$2"); test -n "$l" && case "$l" in *" touches: "*) false;; *) true;; esac; }
no_marks() { not grep -q ' touches: ' "$W/out"; }
order_ok() { a=$(grep -nx "$W8" "$W/out" | cut -d: -f1); b=$(grep -nx "$OP" "$W/out" | cut -d: -f1); test -n "$a" && test -n "$b" && test "$a" -lt "$b"; }
nowhere() { not grep -Eq -- "$1" "$W/out"; }
# exit 2, 'ada-brief: ' on stderr, nothing on stdout, and not the top-level usage (no overlap arm)
err_ok() { test "$(rc)" = 2 && head -1 "$W/err" | grep -q '^ada-brief: ' && test ! -s "$W/out" && not grep -q 'usage: ada-brief who' "$W/err"; }
touches_both() { l=$(line "$1" "$2"); case "$l" in *" touches: $3, $4"|*" touches: $4, $3") return 0;; esac; return 1; }
# entry_ok <group> <text before the brackets> <file>... : the entry is exactly
# '  <text> [f1, f2]' (an optional ' touches: ...' ending aside), the files inside the literal
# brackets joined by ', ' and equal, as a set, to the files given.
entry_ok() {
  g=$1; h=$2; shift 2
  l=$(grp "$g" | grep -F -- "  $h [" | head -1)
  test -n "$l" || return 1
  b=${l%% touches: *}
  case "$b" in "  $h ["*"]") ;; *) return 1;; esac
  inner=${b#"  $h ["}; inner=${inner%]}
  got=$(printf '%s\n' "$inner" | awk '{n=split($0,a,", "); for(i=1;i<=n;i++) print a[i]}' | sort | tr '\n' ' ')
  want=$(printf '%s\n' "$@" | sort | tr '\n' ' ')
  test "$got" = "$want"
}
state() { (cd "$1" && git status --porcelain && git rev-parse HEAD && git branch --show-current && git for-each-ref refs/heads); }
cm() { d=$1; msg=$2; shift 2; for f; do mkdir -p "$(dirname "$d/$f")"; echo "$msg" >> "$d/$f"; done; G -C "$d" add -A; G -C "$d" commit -q -m "$msg"; }

# history <dir> <from> <into> <PROJECT.md text|-> : origin.git, seed and clone (on add/my-step)
history() {
  D=$1; F=$2; I=$3
  mkdir -p "$D"; G init -q --bare "$D/origin.git"; G -C "$D/origin.git" symbolic-ref HEAD "refs/heads/$F"
  S=$D/seed; G init -q "$S"; G -C "$S" symbolic-ref HEAD "refs/heads/$F"
  G -C "$S" remote add origin "$D/origin.git"
  if [ "$4" != - ]; then mkdir -p "$S/.claude/team"; printf '%s\n' "$4" > "$S/.claude/team/PROJECT.md"; fi
  cm "$S" "Initial" src/cache.php src/shared.php src/reports.php src/checkout.php notes.txt
  cm "$S" "Shipped thing (#5)" src/shared.php
  G -C "$S" push -q origin "$F"
  G -C "$S" checkout -q -b "$I"
  cm "$S" "Add the widget cache (#11)" src/cache.php src/shared.php
  cm "$S" "Tidy the reports (#12)" src/reports.php
  cm "$S" "Quick fix without a PR" notes.txt
  G -C "$S" checkout -q "$F"; cm "$S" "Hotfix the checkout (#9)" src/checkout.php; G -C "$S" push -q origin "$F"
  G -C "$S" checkout -q "$I"; G -C "$S" merge -q --no-ff -m "Merge branch '$F' into $I" "$F"
  G -C "$S" push -q origin "$I"
  G clone -q "$D/origin.git" "$D/clone" 2>/dev/null
  G -C "$D/clone" checkout -q -b add/my-step "origin/$I"
}
PROJ='- **Branch from:** `main`. **PR into:** `develop`. **Test on:** a scratch repo.'

# 0. Static and usage.
t "bash -n ada-brief" bash -n "$AB"
awk 'NR>1 && !/^#/{exit} {print}' "$AB" > "$W/header"
t "header usage text names ada-brief overlap (S102)" grep -q 'ada-brief overlap' "$W/header"

# 1. D1-D3: waiting to ship, from git; stub gh with no open PRs.
history "$W/a" main develop "$PROJ"; C=$W/a/clone
state "$C" > "$W/before"
: > "$STUB_LOG"
run "$C" "$STUBPATH" src/shared.php
t "D1: exit 0" test "$(rc)" = 0
t "D1: first line is 'touching 1'" first_is 'touching 1'
t "D1: 'waiting to ship:' then 'open:' headings, in that order" order_ok
t "D1: #11 is '  #11 Add the widget cache [src/cache.php, src/shared.php]' (files in literal brackets)" entry_ok "$W8" '#11 Add the widget cache' src/cache.php src/shared.php
t "D1: #11's files are separated by ', ' inside square brackets" grep -Eq '^  #11 Add the widget cache \[[^],]+, [^],]+\]( touches: .*)?$' "$W/out"
t "D1: #11's subject has no (#11)" not has "$W8" '^  #11 ' '(#11)'
t "D1: #11 ends ' touches: src/shared.php'" ends "$W8" '^  #11 ' src/shared.php
t "D1: #12 is '  #12 Tidy the reports [src/reports.php]'" entry_ok "$W8" '#12 Tidy the reports' src/reports.php
t "D1: #12 not marked" unmarked "$W8" '^  #12 '
t "D1: three entries waiting (no shipped #5, no #9, no merge commit)" n_entries "$W8" 3
t "D1: #5, #9 and the merge commit appear nowhere" nowhere '#5|#9|Merge branch'
t "D1: open group says '  none'" test "$(grp "$OP")" = '  none'
t "D1: exactly one entry marked" test "$(grep -c ' touches: ' "$W/out")" = 1
qh=$(G -C "$C" log --format=%H --grep='^Quick fix without a PR$' origin/develop)
hl=$(line "$W8" ' Quick fix without a PR' | awk '{print $1}')
t "D2: no-PR commit is '  <hash> Quick fix without a PR [notes.txt]'" entry_ok "$W8" "$hl Quick fix without a PR" notes.txt
t "D2: the hash is 7 or more hex characters" grep -Eq '^  [0-9a-f]{7,} Quick fix without a PR \[' "$W/out"
t "D2: its hash is a short form of the commit's id" test -n "$hl" -a "${qh#$hl}" != "$qh"
t "D2: not marked" unmarked "$W8" 'Quick fix without a PR'
cp "$W/out" "$W/out.d1"

run "$C" "$STUBPATH"
t "D3: no arguments: exit 0" test "$(rc)" = 0
t "D3: first line is 'touching -'" first_is 'touching -'
t "D3: nothing marked" no_marks
t "D3: the three entries still listed" n_entries "$W8" 3

run "$C" "$STUBPATH" src/cache.php src/shared.php
t "two files, one entry: 'touching 1'" first_is 'touching 1'
t "two files: #11 marked with both" touches_both "$W8" '^  #11 ' src/cache.php src/shared.php
run "$C" "$STUBPATH" src/reports.php notes.txt
t "two files, two entries: 'touching 2'" first_is 'touching 2'
t "#12 marked with src/reports.php" ends "$W8" '^  #12 ' src/reports.php
t "no-PR commit marked with notes.txt" ends "$W8" ' Quick fix without a PR' notes.txt
for a in ./src/shared.php 'src\shared.php'; do
  run "$C" "$STUBPATH" "$a"
  t "normalised '$a': 'touching 1'" first_is 'touching 1'
  t "normalised '$a': #11 marked with src/shared.php" ends "$W8" '^  #11 ' src/shared.php
done
for a in src/shared shared.php src README.md; do
  run "$C" "$STUBPATH" "$a"
  t "exact paths only, '$a': 'touching 0'" first_is 'touching 0'
  t "exact paths only, '$a': nothing marked, entries listed" eval 'no_marks && n_entries "$W8" 3'
done
state "$C" > "$W/after"
t "working tree, HEAD and local branches unchanged" cmp -s "$W/before" "$W/after"

# 2. D4: open PRs from the stub gh.
cat > "$W/prs.json" <<'JSON'
[{"number": 21, "title": "Speed up the search", "base": "develop", "head": "add/search-speed", "draft": false, "files": ["src/search.php", "src/shared.php"]},
 {"number": 22, "title": "Rework the reports", "base": "main", "head": "fix/reports", "draft": true, "files": ["src/reports.php"]},
 {"number": 24, "title": "Brief: my step", "base": "develop", "head": "add/my-step", "draft": true, "files": [".claude/briefs/T1.md", "src/shared.php"]},
 {"number": 25, "title": "Other base work", "base": "feature/other", "head": "add/other", "draft": false, "files": ["src/shared.php"]},
 {"number": 26, "title": "Closed one", "base": "develop", "head": "add/closed", "draft": false, "state": "CLOSED", "files": ["src/shared.php"]}]
JSON
export STUB_PRS="$W/prs.json"
: > "$STUB_LOG"
run "$C" "$STUBPATH" src/shared.php
t "D4: exit 0" test "$(rc)" = 0
t "D4: 'touching 2' (#11 waiting, #21 open)" first_is 'touching 2'
t "D4: #21 is '  #21 Speed up the search [src/search.php, src/shared.php]' (no 'draft ')" entry_ok "$OP" '#21 Speed up the search' src/search.php src/shared.php
t "D4: #21 not draft" not grep -q '^  #21 draft ' "$W/out"
t "D4: #21 ends ' touches: src/shared.php'" ends "$OP" '^  #21 ' src/shared.php
t "D4: #22 is '  #22 draft Rework the reports [src/reports.php]'" entry_ok "$OP" '#22 draft Rework the reports' src/reports.php
t "D4: #22 not marked" unmarked "$OP" '^  #22 '
t "D4: exactly two open entries" n_entries "$OP" 2
t "D4: the current branch's PR (#24) is left out" nowhere '#24'
t "open PRs into another base (#25) left out" nowhere '#25'
t "closed PRs (#26) left out" nowhere '#26'
grp "$W8" > "$W/w8.stub"
run "$C" "$STUBPATH" src/reports.php
t "D4: 'touching 2' (#12, #22)" first_is 'touching 2'
t "D4: draft #22 marked with src/reports.php" ends "$OP" '^  #22 draft ' src/reports.php
run "$C" "$STUBPATH"
t "D4, no arguments: 'touching -', both open entries, nothing marked" eval 'first_is "touching -" && n_entries "$OP" 2 && no_marks'
t "gh: no --slurp" not grep -q -- '--slurp' "$STUB_LOG"
t "gh: no call about the waiting PRs #11, #12 (files from git)" not grep -Eq 'pulls/1[12]([^0-9]|$)|pr (view|diff) 1[12]([^0-9]|$)' "$STUB_LOG"
t "gh: every call understood by the stub (else fix the stub)" not grep -q '^STUB-GAP' "$STUB_LOG"
G -C "$C" checkout -q -b add/unrelated origin/develop
run "$C" "$STUBPATH"
t "on another branch, #24 is '  #24 draft Brief: my step [.claude/briefs/T1.md, src/shared.php]'" entry_ok "$OP" '#24 draft Brief: my step' .claude/briefs/T1.md src/shared.php
t "on another branch, three open entries" n_entries "$OP" 3
G -C "$C" checkout -q add/my-step

# 3. D5: no gh, and gh not logged in.
run "$C" "$NOGH" src/shared.php
t "D5 no gh: exit 0" test "$(rc)" = 0
t "D5 no gh: 'touching 1'" first_is 'touching 1'
t "D5 no gh: waiting group as with gh" test "$(grp "$W8")" = "$(cat "$W/w8.stub")"
t "D5 no gh: open group is one 'unknown (...)' line" eval 'n_entries "$OP" 1 && grp "$OP" | grep -Eq "^  unknown \(.+\)\$"'
t "D5 no gh: the reason says gh is needed" eval 'grp "$OP" | grep -qF "gh (GitHub CLI) is needed"'
: > "$STUB_LOG"
STUB_AUTH=fail run "$C" "$STUBPATH" src/shared.php
t "D5 not logged in: exit 0" test "$(rc)" = 0
t "D5 not logged in: 'touching 1'" first_is 'touching 1'
t "D5 not logged in: waiting group as with gh" test "$(grp "$W8")" = "$(cat "$W/w8.stub")"
t "D5 not logged in: open group is one 'unknown (...)' line" eval 'n_entries "$OP" 1 && grp "$OP" | grep -Eq "^  unknown \(.+\)\$"'
t "D5 not logged in: the reason says not logged in" eval 'grp "$OP" | grep -qF "not logged in"'

# 4. D6: argument validation.
for a in ../x src/../x .. -x --all /etc/passwd; do
  run "$C" "$STUBPATH" "$a"
  t "D6: '$a' exits 2 with 'ada-brief: ...' on stderr, nothing on stdout" err_ok
done
run "$C" "$STUBPATH" src/shared.php ../x
t "D6: a good and a bad argument together exit 2" err_ok

# 4b. KG-1, ruled by Neil on 25 Oct 2026: the arm refuses only what breaks its one-line entries or
#     what S102 names (a leading '-', an absolute path, '..', a newline). Commas, brackets, '#' and
#     non-ASCII are real file names (variable fonts in block themes) and are accepted and matched.
history "$W/k" main develop "$PROJ"; K=$W/k/clone; SK2=$W/k/seed
FONT1='assets/fonts/Inter-VariableFont_slnt,wght.ttf'; FONT2='assets/fonts/Font[wght].woff2'
HASHF='docs/#notes.txt'; UTF='src/caf'$'\303\251''.php'
cm "$SK2" "Fonts and notes (#41)" "$FONT1" "$FONT2" "$HASHF" "$UTF"; G -C "$SK2" push -q origin develop
for a in "$FONT1" "$FONT2" "$HASHF" "$UTF"; do
  run "$K" "$STUBPATH" "$a"
  t "KG-1: '$a' accepted: exit 0, 'touching 1'" eval 'test "$(rc)" = 0 && first_is "touching 1"'
  t "KG-1: '$a' matched: #41 ends ' touches: $a'" ends "$W8" '^  #41 ' "$a"
  t "KG-1: '$a' listed whole inside the brackets of #41" entry_ok "$W8" '#41 Fonts and notes' "$FONT1" "$FONT2" "$HASHF" "$UTF"
done
run "$K" "$STUBPATH" "./$FONT2"
t "KG-1: './$FONT2' normalised and matched" eval 'test "$(rc)" = 0 && first_is "touching 1" && ends "$W8" "^  #41 " "$FONT2"'
run "$K" "$STUBPATH" "$FONT1" "$FONT2" "$HASHF" "$UTF"
t "KG-1: all four together: exit 0, 'touching 1', #41 marked with all four" eval 'test "$(rc)" = 0 && first_is "touching 1" && has "$W8" "^  #41 " "touches: " "$FONT1" "$FONT2" "$HASHF" "$UTF"'
run "$K" "$STUBPATH" 'assets/fonts/Other,font.ttf'
t "KG-1: an unchanged path with a comma is accepted and matches nothing ('touching 0')" eval 'test "$(rc)" = 0 && first_is "touching 0" && no_marks'
NL=$(printf 'a/b\nc.txt')
run "$K" "$STUBPATH" "$NL"
t "KG-1: a path containing a newline exits 2 with 'ada-brief: ...' on stderr, nothing on stdout" err_ok
run "$K" "$STUBPATH" "$FONT1" "$NL"
t "KG-1: a good path and a newline path together exit 2" err_ok
run "$K" "$STUBPATH" '..,x'
t "KG-1: '..,x' is an ordinary file name, not a '..' segment: accepted, exit 0, 'touching 0'" eval 'test "$(rc)" = 0 && first_is "touching 0" && no_marks'
for a in '-fonts,x.ttf' '-Font[wght].woff2' '/assets/Font[wght].woff2' 'assets/../Font[wght].woff2' 'a,b/../c.ttf'; do
  run "$K" "$STUBPATH" "$a"
  t "KG-1: '$a' still refused (leading '-', absolute or '..'): exit 2, 'ada-brief: ...'" err_ok
done

# 5. Fetch failures die.
history "$W/f1" develop2 develop '- **Branch from:** `main`. **PR into:** `develop`.'
run "$W/f1/clone" "$STUBPATH"
t "Branch from missing on origin: exit 2, 'ada-brief: ...'" err_ok
history "$W/f2" main develop2 '- **Branch from:** `main`. **PR into:** `develop`.'
run "$W/f2/clone" "$STUBPATH"
t "PR into missing on origin: exit 2, 'ada-brief: ...'" err_ok
G -C "$C" remote rename origin upstream
run "$C" "$STUBPATH"
t "no origin remote: exit 2, 'ada-brief: ...'" err_ok
G -C "$C" remote rename upstream origin

# 6. It fetches; one entry per PR; the number is the trailing (#N).
S=$W/a/seed
cm "$S" "Late arrival (#13)" src/late.php; G -C "$S" push -q origin develop
run "$C" "$STUBPATH"
t "fetches first: '  #13 Late arrival [src/late.php]' listed without a manual fetch" entry_ok "$W8" '#13 Late arrival' src/late.php
cm "$S" "Part one (#31)" src/part1.php
cm "$S" "Part two (#31)" src/part2.php
cm "$S" 'Revert "Tidy the reports (#12)" (#14)' src/reports.php
G -C "$S" push -q origin develop
run "$C" "$STUBPATH"
t "one entry per PR: exactly one '#31' entry" test "$(grp "$W8" | grep -c '^  #31 ')" = 1
t "one entry per PR: '  #31 <subject> [src/part1.php, src/part2.php]' (either commit's subject, both files)" eval 'entry_ok "$W8" "#31 Part one" src/part1.php src/part2.php || entry_ok "$W8" "#31 Part two" src/part1.php src/part2.php'
t "trailing (#N): '#14 Revert ...' listed, its title keeping the inner (#12)" entry_ok "$W8" '#14 Revert "Tidy the reports (#12)"' src/reports.php
t "trailing (#N): still exactly one '#12' entry" test "$(grp "$W8" | grep -c '^  #12 ')" = 1

# 7. D7 and the Amendments: a PROJECT.md without the branch lines uses production / next-release;
#    no PROJECT.md at all dies; one line given, the other takes its default.
export STUB_PRS=""   # no open PRs for sections 7 to 9: only the waiting group is under test
history "$W/d" production next-release '# A project with no branch lines'
run "$W/d/clone" "$STUBPATH" src/shared.php
t "D7 no branch lines: exit 0 (defaults production / next-release)" test "$(rc)" = 0
t "D7 no branch lines: 'touching 1'" first_is 'touching 1'
t "D7 no branch lines: '  #11 Add the widget cache [...]' waiting and marked" eval 'entry_ok "$W8" "#11 Add the widget cache" src/cache.php src/shared.php && ends "$W8" "^  #11 " src/shared.php'
t "D7 no branch lines: three entries waiting" n_entries "$W8" 3
history "$W/d2" production develop '- **PR into:** `develop`.'
run "$W/d2/clone" "$STUBPATH" src/shared.php
t "D7 only PR into given: Branch from defaults to production (exit 0, 'touching 1', #11 waiting)" eval 'test "$(rc)" = 0 && first_is "touching 1" && n_entries "$W8" 3'
history "$W/d3" main next-release '- **Branch from:** `main`.'
run "$W/d3/clone" "$STUBPATH" src/shared.php
t "D7 only Branch from given: PR into defaults to next-release (exit 0, 'touching 1', #11 waiting)" eval 'test "$(rc)" = 0 && first_is "touching 1" && n_entries "$W8" 3'
history "$W/e" production next-release -
run "$W/e/clone" "$STUBPATH" src/shared.php
t "D7 no PROJECT.md at all: exit 2, 'ada-brief: ...' on stderr, nothing on stdout" err_ok
run "$W/e/clone" "$STUBPATH"
t "D7 no PROJECT.md at all, no arguments: exit 2 too" err_ok

# 8. D13 (Amendments): this repo's own PROJECT.md. Its Branch from is main and PR into next-release,
#    so ada-brief overlap run with it lists what next-release has that main hasn't.
PM=$REPO/.claude/team/PROJECT.md
t "D13: this repo's PROJECT.md says Branch from \`main\`" grep -Eq '\*\*Branch from:\*\* *`main`' "$PM"
t "D13: this repo's PROJECT.md says PR into \`next-release\`" grep -Eq '\*\*PR into:\*\* *`next-release`' "$PM"
history "$W/g" main next-release "$(cat "$PM")"
run "$W/g/clone" "$STUBPATH" src/shared.php
t "D13: with this repo's PROJECT.md: exit 0, 'touching 1'" eval 'test "$(rc)" = 0 && first_is "touching 1"'
t "D13: with this repo's PROJECT.md the waiting group is next-release not in main (3 entries, not empty)" n_entries "$W8" 3
t "D13: #11 marked, #5 and #9 (on main) absent" eval 'ends "$W8" "^  #11 " src/shared.php && nowhere "#5( |\$)|#9( |\$)"'

# 9. An empty waiting group: Branch from and PR into the same branch.
history "$W/h" main develop "$PROJ"
printf '%s\n' '- **Branch from:** `develop`. **PR into:** `develop`.' > "$W/h/clone/.claude/team/PROJECT.md"
run "$W/h/clone" "$STUBPATH" src/shared.php
t "same branch twice: exit 0, 'touching 0', waiting group is '  none'" eval 'test "$(rc)" = 0 && first_is "touching 0" && test "$(grp "$W8")" = "  none"'

# 10. PIE review (Anna, PR #35) and the Amendment of 26 Oct 2026: --gate builds the branch's own
#     file list: the diff from git merge-base origin/<PR into> HEAD (committed, staged and
#     unstaged) plus untracked files, paths whole. If merge-base fails it exits 2 and lists
#     nothing; --gate with file arguments exits 2; with no changed files it prints 'touching 0'.
code_has() { grep -v '^[[:space:]]*#' "$AB" | grep -Eq -- "$1"; }
# The command itself, as the first word of a command (line start, or after ; & | ( { then do else).
# The words in a trailing comment (`# no mapfile: macOS has bash 3.2`, on two older lines) don't count.
t "gate: no mapfile or readarray command in ada-brief (Amendment 26 Oct: bash 3.2 and zsh, S100)" not code_has '(^|[;&|({]|[^[:alnum:]_](then|do|else))[[:space:]]*(mapfile|readarray)([[:space:]]|$)'
history "$W/q" main develop "$PROJ"; Q=$W/q/clone; SQ=$W/q/seed
SP='docs/meeting notes.txt'; UQ='src/caf'$'\303\251''.php'
cm "$Q" "My step: the cache" src/cache.php                                      # committed
cm "$SQ" "Late arrival (#13)" src/late.php; G -C "$SQ" push -q origin develop    # lands after the branch
echo staged >> "$Q/src/reports.php"; G -C "$Q" add src/reports.php              # staged
echo unstaged >> "$Q/notes.txt"                                                  # unstaged
mkdir -p "$Q/docs"; echo new > "$Q/$SP"; echo new > "$Q/$UQ"                    # untracked, one in a new folder
cat > "$W/gate.json" <<'JSON'
[{"number": 51, "title": "Write up the meeting", "base": "develop", "head": "add/meeting", "draft": false, "files": ["docs/meeting notes.txt"]},
 {"number": 52, "title": "Cafe page", "base": "develop", "head": "add/cafe", "draft": false, "files": ["src/café.php"]},
 {"number": 53, "title": "Checkout tweak", "base": "main", "head": "add/checkout", "draft": false, "files": ["src/checkout.php"]}]
JSON
export STUB_PRS="$W/gate.json"
state "$Q" > "$W/before"
: > "$STUB_LOG"
run "$Q" "$STUBPATH" --gate
t "gate: exit 0" test "$(rc)" = 0
t "gate: 'touching 5' (#11, #12, the no-PR commit, #51, #52)" first_is 'touching 5'
t "gate, committed file: #11 ends ' touches: src/cache.php' (src/shared.php, unchanged, not in it)" ends "$W8" '^  #11 ' src/cache.php
t "gate, staged file: #12 ends ' touches: src/reports.php'" ends "$W8" '^  #12 ' src/reports.php
t "gate, unstaged file: the no-PR commit ends ' touches: notes.txt'" ends "$W8" ' Quick fix without a PR' notes.txt
t "gate, untracked file with a space, in a new folder: #51 ends ' touches: $SP'" ends "$OP" '^  #51 ' "$SP"
t "gate, untracked non-ASCII file: #52 ends ' touches: $UQ'" ends "$OP" '^  #52 ' "$UQ"
t "gate: diff from the merge base, not origin/develop's tip: #13 (landed after the branch) listed, not marked" unmarked "$W8" '^  #13 '
t "gate: #53 (an unchanged file) listed, not marked" unmarked "$OP" '^  #53 '
state "$Q" > "$W/after"
t "gate: working tree, index, HEAD and local branches unchanged" cmp -s "$W/before" "$W/after"
t "gate: every gh call understood by the stub (else fix the stub)" not grep -q '^STUB-GAP' "$STUB_LOG"
run "$Q" "$STUBPATH" --gate src/shared.php
t "gate: '--gate src/shared.php' exits 2 with 'ada-brief: ...', nothing on stdout" err_ok
run "$Q" "$STUBPATH" src/shared.php --gate
t "gate: 'src/shared.php --gate' exits 2 too" err_ok
G clone -q "$W/q/origin.git" "$W/q/clean" 2>/dev/null; QC=$W/q/clean
G -C "$QC" checkout -q -b add/clean origin/develop
run "$QC" "$STUBPATH" --gate
t "gate, no changed files: exit 0, 'touching 0' (not 'touching -')" eval 'test "$(rc)" = 0 && first_is "touching 0"'
t "gate, no changed files: nothing marked, both groups listed (4 waiting, 3 open)" eval 'no_marks && n_entries "$W8" 4 && n_entries "$OP" 3'
G -C "$QC" checkout -q --orphan add/orphan; G -C "$QC" commit -q -m "Unrelated root"
echo new > "$QC/src/orphan.php"
t "setup: the orphan branch has no merge base with origin/develop" not G -C "$QC" merge-base origin/develop HEAD
run "$QC" "$STUBPATH" --gate
t "gate, no merge base (unrelated history): exit 2, 'ada-brief: ...', nothing listed" err_ok
G clone -q --depth 1 --no-single-branch "file://$W/q/origin.git" "$W/q/shallow" 2>/dev/null; QS=$W/q/shallow
G -C "$QS" checkout -q -b add/shallow
echo new > "$QS/src/shallow.php"
t "setup: a shallow clone with origin/develop and no merge base with it" eval 'G -C "$QS" rev-parse -q --verify origin/develop && test "$(G -C "$QS" rev-parse --is-shallow-repository)" = true && not G -C "$QS" merge-base origin/develop HEAD'
run "$QS" "$STUBPATH" --gate
t "gate, shallow clone (git merge-base fails): exit 2, 'ada-brief: ...', nothing listed" err_ok
t "setup: still no merge base after the run (else the arm deepened the clone, and exit 0 was right)" not G -C "$QS" merge-base origin/develop HEAD
run "$W/f2/clone" "$STUBPATH" --gate
t "gate, PR into missing on origin: exit 2, 'ada-brief: ...', nothing listed" err_ok

# 11. PIE review (Anna, PR #35) and the Amendment of 26 Oct 2026: a full page from gh means
#     unknown, never a silent none. 101 open PRs into a base: the open group is exactly
#     '  unknown (more than 100 open PRs into <base>)'; exactly 100 are all listed. A PR that comes
#     back with 100 files (gh's cap per PR; the stub caps as gh does):
#     '  unknown (#N has 100 or more files)'; 99 files are listed. Exit 0 in every case.
bulk() { python3 -c 'import json, sys
out, base, n = sys.argv[1], sys.argv[2], int(sys.argv[3])
json.dump([{"number": 1001 + i, "title": "Bulk %d" % i, "base": base, "head": "add/bulk-%d" % i,
            "draft": False, "files": ["bulk/f%d.php" % (1001 + i)]} for i in range(n)], open(out, "w"))' "$@"; }
big() { python3 -c 'import json, sys
out, n = sys.argv[1], int(sys.argv[2])
json.dump([{"number": 61, "title": "Huge rename", "base": "develop", "head": "add/huge", "draft": False,
            "files": ["big/f%d.php" % i for i in range(n)]},
           {"number": 62, "title": "Small fix", "base": "develop", "head": "add/small", "draft": False,
            "files": ["src/search.php"]}], open(out, "w"))' "$@"; }
export STUB_PRS="$W/many.json"
bulk "$W/many.json" develop 101
run "$C" "$STUBPATH" src/shared.php
t "101 open PRs into develop: exit 0" test "$(rc)" = 0
t "101 open PRs into develop: the open group is exactly '  unknown (more than 100 open PRs into develop)'" test "$(grp "$OP")" = '  unknown (more than 100 open PRs into develop)'
t "101 open PRs: waiting group still listed, #11 marked, 'touching 1'" eval 'first_is "touching 1" && ends "$W8" "^  #11 " src/shared.php'
bulk "$W/many.json" main 101
run "$C" "$STUBPATH" src/shared.php
t "101 open PRs into main (Branch from): exit 0, open group exactly '  unknown (more than 100 open PRs into main)'" eval 'test "$(rc)" = 0 && test "$(grp "$OP")" = "  unknown (more than 100 open PRs into main)"'
bulk "$W/many.json" develop 100
run "$C" "$STUBPATH" src/shared.php
t "exactly 100 open PRs into develop: exit 0, all 100 listed, no 'unknown'" eval 'test "$(rc)" = 0 && n_entries "$OP" 100 && nowhere unknown'
t "100 open PRs: the last, '  #1100 Bulk 99 [bulk/f1100.php]', listed" entry_ok "$OP" '#1100 Bulk 99' bulk/f1100.php
export STUB_PRS="$W/big.json"
for n in 100 150; do
  big "$W/big.json" $n
  run "$C" "$STUBPATH" src/shared.php
  t "a PR with $n files (gh returns 100): exit 0, open group exactly '  unknown (#61 has 100 or more files)'" eval 'test "$(rc)" = 0 && test "$(grp "$OP")" = "  unknown (#61 has 100 or more files)"'
  t "a PR with $n files: waiting group still listed, #11 marked, 'touching 1'" eval 'first_is "touching 1" && ends "$W8" "^  #11 " src/shared.php'
done
big "$W/big.json" 99
run "$C" "$STUBPATH" big/f98.php
t "a PR with 99 files: exit 0, 'touching 1', #61 and #62 listed, #61 marked, no 'unknown'" eval 'test "$(rc)" = 0 && first_is "touching 1" && n_entries "$OP" 2 && ends "$OP" "^  #61 " big/f98.php && nowhere unknown'
t "gh: every call understood by the stub (else fix the stub)" not grep -q '^STUB-GAP' "$STUB_LOG"
export STUB_PRS=""

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2

With Docker and the `bash:3.2` image (no git, Python or jq inside, so the container adds them;
that needs the network). With `S` the scratch folder holding `check.sh`:

```sh
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 jq diffutils >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && REPO=/repo bash /s/check.sh'
```

The whole checkout is mounted (not only `plugins/ada`) because section 8 (D13) reads
`$REPO/.claude/team/PROJECT.md`; `safe.directory` is set because the mount is owned by another
user, and git would otherwise refuse to read it.

There is no `gh` in the container, so the "no gh" checks are real there too. On a Mac,
`REPO=<this checkout> /bin/bash check.sh` uses the system bash 3.2.

### Part B: a real GitHub repo (D8)

`overlap` only reads (`git fetch`, `gh pr list`, `gh api`), so it can run in this checkout. It
needs an open PR into `next-release` (or the `Branch from` branch) other than the current
branch's. If there is none (part B prints `SKIP`), use a scratch GitHub repo you own: onboard
it, push `production` and `next-release`, open a PR into `next-release` that changes two files,
and run part B there with `DIR=<that clone>`.

1. Save the block below as `real.sh` in a scratch folder.
2. Run `REPO=<this checkout> bash real.sh` (or with `DIR=<scratch clone>`).

```bash
#!/bin/bash
# ada-brief overlap against GitHub: the open group matches gh; the waiting group matches git. PASS/FAIL/SKIP.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
AB=${AB:-$REPO/plugins/ada/bin/ada-brief}
DIR=${DIR:-$REPO}
cd "$DIR" || exit 2
W=$(mktemp -d); fails=0
ok() { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
grp() { awk -v h="$1" '$0==h{p=1;next} /^[^ ]/{p=0} p' "$W/out"; }
gh auth status >/dev/null 2>&1 || { echo "SKIP gh is not logged in"; exit 3; }
b1=$(sed -n 's/.*\*\*Branch from:\*\* *`\([^`]*\)`.*/\1/p' .claude/team/PROJECT.md | head -1)
b2=$(sed -n 's/.*\*\*PR into:\*\* *`\([^`]*\)`.*/\1/p' .claude/team/PROJECT.md | head -1)
cur=$(git branch --show-current)
: > "$W/prs"
for b in $(printf '%s\n%s\n' "${b1:-production}" "${b2:-next-release}" | sort -u); do
  gh pr list --state open --base "$b" --limit 100 --json number,headRefName,isDraft \
    -q ".[] | select(.headRefName != \"$cur\") | \"\(.number) \(.isDraft)\"" >> "$W/prs"
done
# D13: the waiting group is what origin/<PR into> has that origin/<Branch from> hasn't, one entry
# per PR number (or short hash). In this repo that is next-release not in main.
if [ "$DIR" = "$REPO" ]; then
  ok "$(grep -Eq '\*\*Branch from:\*\* *`main`' .claude/team/PROJECT.md; echo $?)" "this repo's PROJECT.md: Branch from \`main\`"
  ok "$(grep -Eq '\*\*PR into:\*\* *`next-release`' .claude/team/PROJECT.md; echo $?)" "this repo's PROJECT.md: PR into \`next-release\`"
fi
bash "$AB" overlap > "$W/out" 2> "$W/err"; rc0=$?
ok "$([ $rc0 = 0 ]; echo $?)" "waiting group: exit 0"
w1=${b1:-production}; w2=${b2:-next-release}
git log --no-merges --format='%h %s' "origin/$w2" "^origin/$w1" \
  | awk '{ if (match($0, /\(#[0-9]+\)[ \t]*$/)) { k = substr($0, RSTART + 2); sub(/\).*/, "", k); print "#" k } else print $1 }' \
  | sort -u > "$W/want.w"
[ -s "$W/want.w" ] || echo none > "$W/want.w"
grp 'waiting to ship:' | grep '^  [^ ]' | awk '{print $1}' | sort -u > "$W/got.w"
ok "$(cmp -s "$W/want.w" "$W/got.w"; echo $?)" "waiting group lists exactly origin/$w2 not in origin/$w1 ($(wc -l < "$W/want.w" | tr -d ' ') entries: $(tr '\n' ' ' < "$W/want.w"))"
[ -s "$W/prs" ] || { echo "SKIP no open PR other than this branch's into ${b1:-production} or ${b2:-next-release}"; exit 3; }
bash "$AB" overlap > "$W/out" 2> "$W/err"; rc=$?
ok "$([ $rc = 0 ]; echo $?)" "exit 0"
ok "$([ "$(head -1 "$W/out")" = 'touching -' ]; echo $?)" "first line 'touching -'"
ok "$([ "$(grp 'open:' | grep -c '^  [^ ]')" = "$(wc -l < "$W/prs" | tr -d ' ')" ]; echo $?)" "one open entry per open PR ($(wc -l < "$W/prs" | tr -d ' '))"
first_file=
while read -r n d; do
  l=$(grp 'open:' | grep -E "^  #$n( |\$)" | head -1)
  ok "$([ -n "$l" ]; echo $?)" "#$n listed"
  if [ "$d" = true ]; then pre="  #$n draft "; else pre="  #$n "; fi
  case "$l" in "$pre"*) r=0;; *) r=1;; esac
  if [ "$d" != true ]; then case "$l" in "  #$n draft "*) r=1;; esac; fi
  ok $r "#$n draft marker matches GitHub (draft=$d)"
  gh api "repos/{owner}/{repo}/pulls/$n/files?per_page=100" -q '.[].filename' > "$W/files.$n"
  miss=0; while read -r f; do case "$l" in *"$f"*) ;; *) miss=1; echo "  missing: $f";; esac; done < "$W/files.$n"
  ok $miss "#$n lists every file GitHub has for it"
  [ -z "$first_file" ] && first_file=$(head -1 "$W/files.$n") && first_n=$n
done < "$W/prs"
bash "$AB" overlap "$first_file" > "$W/out" 2> "$W/err"
l=$(grp 'open:' | grep -E "^  #$first_n( |\$)" | head -1)
case "$l" in *" touches: "*"$first_file"*) r=0;; *) r=1;; esac
ok $r "with '$first_file' as argument, #$first_n is marked"
echo "$fails failed"; rm -rf "$W"; [ "$fails" = 0 ]
```

## Expected

Part A: every line `PASS`, last line `0 failed`, exit 0, under bash 5 and Docker bash 3.2 (its
version line says 3.2). Step 3's broken copy prints `FAIL`.
Part B: every line `PASS`, exit 0; `SKIP` (exit 3) means it found no open PR to check the open group
against (the waiting-group checks before it still print). Run from this checkout it also checks
that `.claude/team/PROJECT.md` says Branch from `main` and PR into `next-release`.

Sources: the brief's Decisions and Definition of Done (groups, `(#N)`, short hash, files from
git, current branch's PR left out, `draft`, exact path after normalising `./` and `\`,
`touching -`, `unknown (<need_gh's reason>)` with exit 0, `die` exit 2 on a fetch failure,
argument rules S102, as ruled for KG-1 on 25 Oct 2026: only a leading `-`, an absolute path, `..` and a newline are refused; commas, brackets, `#` and non-ASCII are accepted and matched, as git lists them with `core.quotePath=false`) and its Amendments of 22 Oct 2026, agreed by Neil: a PROJECT.md without the
branch lines uses `production` and `next-release`; no PROJECT.md at all exits 2 with
`ada-brief: ...`; the entry format with literal `[a, b]` file lists; in this repo `overlap` reads
`main` and `next-release` (D13, here). The Definition of Done items and Amendments of 26 Oct
2026 from the PIE review (Anna, PR #35), agreed by Neil: `--gate` lists the files itself, the diff
from `git merge-base origin/<PR into> HEAD` (committed, staged, unstaged) plus untracked files,
paths whole; a failing merge base exits 2 and lists nothing; `--gate` with files exits 2; no
changes prints `touching 0`; no `mapfile`; 101 open PRs into a base gives
`  unknown (more than 100 open PRs into <base>)`, a PR with 100 files
`  unknown (#N has 100 or more files)`, exit 0 (sections 10 and 11). `ada-brief`'s `need_gh` reasons at b83e15c ("gh (GitHub
CLI) is needed", "gh is not logged in"). Not from the brief, so not asserted: the order of files
inside the brackets, which commit's subject a multi-commit PR shows, the wording of the `die`
message.

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named:
`<scratch>/out`, `err`, `rc` hold the last run; `gh.log` every gh call; `a/clone` the main
scratch repo (`git log --oneline origin/develop`); `q/clone` the `--gate` repo (`git status`),
`q/shallow` the shallow clone.

## Regression

The checks in part A that are not tagged D1 to D7 or D13 (other bases and closed PRs left out, merge
and shipped commits left out, the working tree untouched, fetch failures die) guard behaviour
next to the change. Part B's `gh` calls are the oracle for real PRs. `ada-brief body` and `who`
are in `overlaps-line-in-body.md`.
