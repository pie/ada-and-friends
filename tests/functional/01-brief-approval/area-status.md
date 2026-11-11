# `ada-brief status` reads a brief with `Areas:` area by area

Unit D1 of the plan "Brief approval by area": a brief with an `Areas:` line is approved area by
area, each by an `approved:<slug>` label from a login that counts for it (its Approved by, or
Trusted), or needs none because the PR's opener is its `Author:` and approves it. A brief without
`Areas:` reads exactly as before. One line per touched area follows the first line.

- Area: 01 Brief approval (`ada-brief status`; `submit`'s base check); 03 Plans (`ada-plan
  status`); 12 Platforms (bash 3.2, Python 3.8, CRLF, a path with a space)
- Asana: task 1220198285902650
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-11, 285 passed, 0 failed on the host (bash 5.3, Python 3.14); the 5h and 5i author-null checks print FAIL against the pre-fix ada-brief; Docker and On GitHub not run; see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree, and the base commit `c8e1c5a`
  (`origin/add/brief-approval-by-area` with Units B and C, the script before this change;
  `BASE=<ref>` for another) in its git. In a container a worktree's git is not readable: export
  the base on the host and pass `BASE_PLUGIN` (Steps, 3).
- `git`, `python3` (3.8+), `mktemp`, `awk`, `cmp`, `diff`, `tar`. No network, no GitHub login.
- The script puts a stateful stand-in `gh` first on the PATH. It holds any number of PRs, each with
  labels (and their `labeled` / `unlabeled` events), PR comments, reviews and line comments; the
  PR's opener is whoever ran `submit` (`GH_USER`), given as `author.login` by `gh pr view` and
  `user.login` by `gh api …/pulls/<n>`. Labels, comments and reviews are put in by the script with
  fixed times, so the order of approval is the sequence, not the clock: the brief is submitted at
  10:00, a label at 11:00 is after it, a push at 12:00 makes it before. A call the stand-in
  doesn't handle ends the run with `SETUP FAIL` and the call, so a gap in the harness can't pass
  or fail a check.
- Logins: `tester` (`<me>` in the brief), `other`, `opener` and `someone` (on no list), `mate`,
  `neil` (the Approvers line). Table **T**: `content` and `design` approved by `@tester`,
  `System behaviour (catch-all)` (`system`) by `@other`; `Approvers: @neil`, `Trusted: none`.
- The `Author:` endings follow the brief's Amendments of 2026-11-11: a `waiting` area line ends
  `; Author: @<x> is not the PR's opener @<y>, so it isn't exempt` when `Author:` names someone
  other than the opener and the **opener** counts for that area (`; no Author: line` when there is
  none); `approved`, `changes-requested` and `not needed` lines never end that way. The
  `amended …` second line comes only under an `approved …` first line. `waiting (@a, @b)` lists the
  area's Approved by logins, not Trusted. A catch-all touched but not on `Areas:` gets its line
  last; a label for an area the brief doesn't touch is ignored, with no note.
- `HOLD` lines would be checks waiting on an answer from Ada; there are none at present (the
  helper stays for the next open question). A `HOLD` prints the line `status` gave and counts
  neither way.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`. Under a minute.
3. Under bash 3.2 and Python 3.8 (Docker). Export the base first, since a container can't read a
   worktree's git (run on the host; `S` is the scratch folder holding `check.sh`):

   ```sh
   git -C <this checkout> archive -o "$S/base.tar" c8e1c5a plugins/ada
   mkdir -p "$S/base" && tar -x -C "$S/base" -f "$S/base.tar"
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 diffutils >/dev/null && REPO=/repo BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
     sh -c 'apt-get -qq update >/dev/null && apt-get -qq install -y git >/dev/null && REPO=/repo BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   ```

   The `info:` lines at the top say `bash 3.2` and `Python 3.8`; the PASS and FAIL lines are
   the same as under the host's bash (`diff` the two outputs less their `info:` lines). (The
   `python:3.8-slim` image runs the script under its own bash 5; the Python is what it adds.)
4. Break it on purpose, twice, each with `PLUGIN=<a plugin folder>`:
   - `PLUGIN=$S/base/plugins/ada` (the base, which knows nothing of areas): `info: the script
     under test is identical to the base`, no `SETUP FAIL`, and `FAIL` lines in sections 1 to 10d,
     12 to 13, 15a and 15b (every check of an `area …` line, `not-needed` for an exempt author, the exit 2
     for `price`); every section 10e, 10f and 11 line prints `PASS` (the base has the early exit).
   - A copy of `plugins/ada` with the change built, its `bin/ada-brief` with `doesn't approve`
     changed to `does not approve`: the two "has the line 'the approved:design label was added
     by …'" checks of section 3 print `FAIL`.
5. On GitHub, below: the Definition of Done in the brief's own words, in a scratch GitHub repo.

```bash
#!/bin/bash
# ada-brief status, area by area (Unit D1), offline: a stateful stand-in gh and scratch repos with
# a local bare origin, one per case. PASS/FAIL per check; HOLD (none at present) for a line whose
# expectation waits on Ada (TEST-PLAN.md, Escalate). Exit 1 if any FAIL, 2 on SETUP FAIL.
# Env: REPO (this checkout; default the repo of the current folder), PLUGIN (the plugin under
#      test; default $REPO/plugins/ada), BASE (the script before D1; default c8e1c5a),
#      BASE_PLUGIN (an exported base plugins/ada, for a container), CHANGELOG (default
#      $REPO/CHANGELOG.md), KEEP=1 (keep the scratch folder).
# bash 3.2 compatible: no mapfile, no [[ =~ ]], no ${x,,}, no associative arrays.
set -u
unset ADA_BRIEF_REQUIRE
exec 3>&1
BASE=${BASE:-c8e1c5a}
for tool in git python3 mktemp awk cmp diff tar sed grep wc; do
  command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }
done
W=$(mktemp -d)
if [ "${KEEP:-}" = 1 ]; then echo "info: scratch folder $W"; else trap 'rm -rf "$W"' EXIT; fi
BASH_BIN=$(command -v bash)
echo "info: $("$BASH_BIN" --version | head -1)"
echo "info: $(python3 --version 2>&1)"

REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
NP=${PLUGIN:-$REPO/plugins/ada}
if [ -n "${BASE_PLUGIN:-}" ]; then
  BP=$BASE_PLUGIN
else
  mkdir -p "$W/base"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/base" || { echo "SETUP FAIL: no $BASE in $REPO"; exit 2; }
  BP=$W/base/plugins/ada
fi
[ -f "$BP/bin/ada-brief" ] || { echo "SETUP FAIL: no base script at $BP/bin/ada-brief"; exit 2; }
[ -f "$NP/bin/ada-brief" ] || { echo "SETUP FAIL: no script under test at $NP/bin/ada-brief"; exit 2; }
cmp -s "$BP/bin/ada-brief" "$NP/bin/ada-brief" && echo "info: the script under test is identical to the base (section 11 proves nothing)"
CL=${CHANGELOG:-$REPO/CHANGELOG.md}

# git for the scratch repos only: its own HOME, fixed identity, main as the first branch.
mkdir -p "$W/home"
printf '[user]\n\tname = T\n\temail = t@example.invalid\n[init]\n\tdefaultBranch = main\n' > "$W/home/.gitconfig"
HOME=$W/home; GIT_CONFIG_NOSYSTEM=1; export HOME GIT_CONFIG_NOSYSTEM

fails=0; passes=0; holds=0; sruns=0; swrites=0; n=0
ok() { if [ "$1" = 0 ]; then echo "PASS $2"; passes=$((passes+1)); else echo "FAIL $2"; fails=$((fails+1)); fi; }
setup_fail() { echo "SETUP FAIL: $*" >&3; exit 2; }

# The stand-in gh. State in $GH_STATE/state.json; every call in log, every change in writes, every
# call it can't answer in unsupported.
mkdir -p "$W/stub"
cat > "$W/stub/gh" <<'STUB'
#!/usr/bin/env python3
import json, os, re, subprocess, sys
from urllib.parse import unquote
D = os.environ['GH_STATE']
P = os.path.join(D, 'state.json')
st = json.load(open(P)) if os.path.exists(P) else {'prs': [], 'labels': []}
a = sys.argv[1:]
USER = os.environ.get('GH_USER', 'tester')
NOW = os.environ.get('NOW', '2026-01-01T00:00:00Z')

def note(name, line):
    with open(os.path.join(D, name), 'a') as f:
        f.write(line + '\n')

note('log', 'gh ' + ' '.join(a))

def unsupported(why):
    note('unsupported', why + '   (gh ' + ' '.join(a) + ')')
    sys.stderr.write('stand-in gh: not handled: ' + why + '\n')
    sys.exit(1)

def save():
    note('writes', 'gh ' + ' '.join(a))
    json.dump(st, open(P, 'w'))

def parse(args, valued):
    pos, o, i = [], {}, 0
    while i < len(args):
        x = args[i]
        if x in valued:
            o.setdefault(x, []).append(args[i + 1] if i + 1 < len(args) else '')
            i += 2
            continue
        if x.startswith('--') and '=' in x and x.split('=', 1)[0] in valued:
            k, v = x.split('=', 1)
            o.setdefault(k, []).append(v)
        elif x.startswith('-') and len(x) > 1:
            o.setdefault(x, []).append(True)
        else:
            pos.append(x)
        i += 1
    return pos, o

def one(o, *names):
    for n in names:
        if n in o:
            return o[n][-1]
    return None

def jq(obj, expr):
    toks = re.findall(r'\.([A-Za-z_][A-Za-z0-9_]*)|\[(\d*)\]', expr)
    if expr != '.' and ''.join('.' + n if n else '[' + i + ']' for n, i in toks) != expr.replace('.[', '['):
        unsupported('--jq ' + expr)
    vals = [obj]
    for n, i in toks:
        out = []
        for v in vals:
            if n:
                out.append(v.get(n) if isinstance(v, dict) else None)
            elif i == '':
                out.extend(v if isinstance(v, list) else [])
            else:
                out.append(v[int(i)] if isinstance(v, list) and int(i) < len(v) else None)
        vals = out
    return vals

def emit(obj, o):
    q = one(o, '-q', '--jq')
    if q is not None:
        for v in jq(obj, q):
            print(v if isinstance(v, str) else ('' if v is None else json.dumps(v)))
    else:
        print(json.dumps(obj))
    sys.exit(0)

def pick(obj, o):
    spec = one(o, '--json')
    if spec is None or spec is True:
        return obj
    out = {}
    for f in [x.strip() for x in spec.split(',') if x.strip()]:
        if f not in obj:
            unsupported('--json field ' + f)
        out[f] = obj[f]
    return out

def branch():
    r = subprocess.run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], stdout=subprocess.PIPE,
                       stderr=subprocess.DEVNULL, universal_newlines=True)
    return r.stdout.strip()

def find(sel):
    sel = branch() if sel is None else sel
    m = re.search(r'(?:^|/pull/)(\d+)$', sel)
    for pr in st['prs']:
        if (m and pr['number'] == int(m.group(1))) or pr['head'] == sel:
            return pr
    return None

def url(pr):
    return 'https://github.com/o/r/pull/%d' % pr['number']

def view(pr):
    return {'number': pr['number'], 'url': url(pr), 'title': pr['title'], 'body': pr['body'],
            'labels': [{'name': l} for l in pr['labels']],
            'author': None if os.environ.get('GH_AUTHOR_NULL') == '1' else {'login': pr['opener']},
            'headRefName': pr['head'], 'baseRefName': pr['base'], 'state': 'OPEN',
            'isDraft': pr['draft'], 'mergeCommit': None}

def rest(pr):
    return {'number': pr['number'], 'html_url': url(pr), 'title': pr['title'], 'body': pr['body'],
            'labels': [{'name': l} for l in pr['labels']], 'user': {'login': pr['opener']},
            'head': {'ref': pr['head']}, 'base': {'ref': pr['base']}, 'state': 'open',
            'draft': pr['draft']}

cmd = a[:2]
if cmd == ['auth', 'status']:
    sys.exit(0)
if cmd == ['repo', 'view']:
    pos, o = parse(a[2:], {'--json', '-q', '--jq'})
    if one(o, '-q', '--jq') is None and one(o, '--json') is None:
        print('o/r'); sys.exit(0)
    emit(pick({'nameWithOwner': 'o/r', 'name': 'r', 'owner': {'login': 'o'},
               'url': 'https://github.com/o/r', 'defaultBranchRef': {'name': 'main'}}, o), o)
if cmd == ['label', 'create']:
    if len(a) > 2 and a[2] not in st['labels']:
        st['labels'].append(a[2])
    save(); sys.exit(0)
if cmd == ['pr', 'create']:
    pos, o = parse(a[2:], {'--head', '-H', '--base', '-B', '--title', '-t', '--body', '-b',
                           '--body-file', '-F', '--repo', '-R', '--label', '-l', '--assignee', '-a',
                           '--reviewer', '-r', '--milestone', '-m', '--project', '-p'})
    head = one(o, '--head', '-H') or branch()
    if find(head):
        sys.stderr.write('a pull request for branch "%s" already exists\n' % head); sys.exit(1)
    bf = one(o, '--body-file', '-F')
    body = (sys.stdin.read() if bf == '-' else open(bf, encoding='utf-8').read()) if bf else (one(o, '--body', '-b') or '')
    pr = {'number': len(st['prs']) + 1, 'head': head, 'base': one(o, '--base', '-B') or 'main',
          'title': one(o, '--title', '-t') or '', 'body': body, 'opener': USER,
          'draft': bool(one(o, '--draft', '-d')), 'labels': [], 'events': [], 'comments': [],
          'reviews': [], 'review_comments': []}
    st['prs'].append(pr); save(); print(url(pr)); sys.exit(0)
if cmd == ['pr', 'view']:
    pos, o = parse(a[2:], {'--json', '-q', '--jq', '--repo', '-R', '-t', '--template'})
    pr = find(pos[0] if pos else None)
    if pr is None:
        sys.stderr.write('no pull requests found for branch "%s"\n' % (pos[0] if pos else branch())); sys.exit(1)
    if one(o, '--json') is None:
        unsupported('pr view without --json')
    emit(pick(view(pr), o), o)
if cmd == ['pr', 'list']:
    pos, o = parse(a[2:], {'--head', '-H', '--base', '-B', '--state', '-s', '--limit', '-L', '--json',
                           '-q', '--jq', '--search', '-S', '--label', '-l', '--author', '-A', '--repo', '-R'})
    head, base = one(o, '--head', '-H'), one(o, '--base', '-B')
    prs = [p for p in st['prs'] if (head is None or p['head'] == head) and (base is None or p['base'] == base)]
    if (one(o, '--state', '-s') or 'open') in ('closed', 'merged'):
        prs = []
    if one(o, '--json') is None:
        unsupported('pr list without --json')
    emit([pick(view(p), o) for p in prs], o)
if cmd == ['pr', 'ready']:
    pos, o = parse(a[2:], {'--repo', '-R'})
    pr = find(pos[0] if pos else None)
    if pr is None:
        sys.exit(1)
    pr['draft'] = False; save(); sys.exit(0)
if a[:1] == ['api']:
    pos, o = parse(a[1:], {'-X', '--method', '-F', '-f', '--field', '--raw-field', '-H', '--header',
                           '-q', '--jq', '--input', '-t', '--template', '--hostname', '--cache',
                           '-p', '--preview'})
    if not pos:
        unsupported('api with no path')
    path = pos[0].lstrip('/').replace('{owner}/{repo}', 'o/r').replace(':owner/:repo', 'o/r').split('?')[0]
    fields = {}
    for k in ('-F', '-f', '--field', '--raw-field'):
        for v in o.get(k, []):
            if v is True or '=' not in v:
                unsupported('field ' + str(v))
            name, val = v.split('=', 1)
            if k in ('-F', '--field') and val.startswith('@'):
                val = sys.stdin.read() if val == '@-' else open(val[1:], encoding='utf-8').read()
            fields.setdefault(name, []).append(val)
    method = (one(o, '-X', '--method') or ('POST' if fields else 'GET')).upper()
    if path == 'user' and method == 'GET':
        emit({'login': USER}, o)
    if path == 'repos/o/r' and method == 'GET':
        emit({'full_name': 'o/r', 'name': 'r', 'owner': {'login': 'o'}, 'default_branch': 'main'}, o)
    m = re.match(r'repos/o/r/labels(?:/(.+))?$', path)
    if m:
        name = unquote(m.group(1) or '')
        if method == 'GET' and name:
            if name in st['labels']:
                emit({'name': name}, o)
            sys.stderr.write('HTTP 404: Not Found\n'); sys.exit(1)
        if method == 'POST':
            st['labels'].append(fields.get('name', [''])[-1]); save(); emit({}, o)
        unsupported('api ' + method + ' ' + path)
    m = re.match(r'repos/o/r/(pulls|issues)/(\d+)(/[^/]+)?(/.+)?$', path)
    if not m:
        unsupported('api ' + method + ' ' + path)
    kind, num, sub, tail = m.group(1), int(m.group(2)), m.group(3) or '', unquote((m.group(4) or '')[1:])
    pr = ([p for p in st['prs'] if p['number'] == num] or [None])[0]
    if pr is None:
        sys.stderr.write('HTTP 404: Not Found\n'); sys.exit(1)
    if method == 'GET' and not tail:
        if sub == '':
            emit(rest(pr), o)
        if sub == '/events' and kind == 'issues':
            emit(pr['events'], o)
        if sub == '/labels' and kind == 'issues':
            emit([{'name': l} for l in pr['labels']], o)
        if sub == '/comments':
            emit(pr['comments'] if kind == 'issues' else pr['review_comments'], o)
        if sub == '/reviews' and kind == 'pulls':
            emit(pr['reviews'], o)
    if method == 'PATCH' and sub == '':
        if 'body' in fields:
            pr['body'] = fields['body'][-1]
        if 'title' in fields:
            pr['title'] = fields['title'][-1]
        save(); emit(rest(pr), o)
    if method == 'POST' and kind == 'issues' and sub == '/comments' and not tail:
        c = {'user': {'login': USER}, 'created_at': NOW, 'updated_at': NOW, 'body': fields.get('body', [''])[-1]}
        pr['comments'].append(c); save(); emit(c, o)
    if method == 'POST' and kind == 'issues' and sub == '/labels' and not tail:
        for name in fields.get('labels[]', []) + fields.get('labels', []):
            if name not in pr['labels']:
                pr['labels'].append(name)
                pr['events'].append({'event': 'labeled', 'label': {'name': name}, 'actor': {'login': USER}, 'created_at': NOW})
        save(); emit([{'name': l} for l in pr['labels']], o)
    if method == 'DELETE' and kind == 'issues' and sub == '/labels' and tail:
        if tail not in pr['labels']:
            sys.stderr.write('HTTP 404: Label does not exist\n'); sys.exit(1)
        pr['labels'].remove(tail)
        pr['events'].append({'event': 'unlabeled', 'label': {'name': tail}, 'actor': {'login': USER}, 'created_at': NOW})
        save(); sys.exit(0)
    unsupported('api ' + method + ' ' + path)
unsupported('command')
STUB
chmod +x "$W/stub/gh"

# gha <op> <head> <args...>: change the stand-in's state as GitHub would after a person's action.
gha() { python3 - "$GH_STATE/state.json" "$@" <<'PY' || setup_fail "$CASE: stand-in state ($*)"
import json, sys
p, op, head = sys.argv[1:4]
x = sys.argv[4:]
st = json.load(open(p))
pr = [q for q in st['prs'] if q['head'] == head][0]
if op in ('label', 'unlabel'):
    name, who, when = x
    if op == 'label' and name not in pr['labels']:
        pr['labels'].append(name)
    if op == 'unlabel' and name in pr['labels']:
        pr['labels'].remove(name)
    pr['events'].append({'event': op + 'ed', 'label': {'name': name}, 'actor': {'login': who}, 'created_at': when})
elif op == 'comment':
    who, when, body = x
    pr['comments'].append({'user': {'login': who}, 'created_at': when, 'updated_at': when, 'body': body})
elif op == 'review':
    who, when, state, body = x
    pr['reviews'].append({'user': {'login': who}, 'submitted_at': when, 'state': state, 'body': body})
elif op == 'linecomment':
    who, when, body = x
    pr['review_comments'].append({'user': {'login': who}, 'created_at': when, 'updated_at': when,
                                  'body': body, 'path': 'README.md', 'line': 1})
elif op == 'body':
    pr['body'] = x[0]
json.dump(st, open(p, 'w'))
PY
}
PRH=add/x
label()       { gha label "$PRH" "$@"; }        # label <name> <actor> <time>
unlabel()     { gha unlabel "$PRH" "$@"; }      # unlabel <name> <actor> <time>
comment()     { gha comment "$PRH" "$@"; }      # comment <actor> <time> <body>
review()      { gha review "$PRH" "$@"; }       # review <actor> <time> <state> <body>
linecomment() { gha linecomment "$PRH" "$@"; }  # linecomment <actor> <time> <body>

T_STD=$(printf '| Content | content | @tester |\n| Design | design | @tester |\n| System behaviour (catch-all) | system | @other |')
T_DOTHER=$(printf '| Content | content | @tester |\n| Design | design | @other |\n| System behaviour (catch-all) | system | @other |')
T_MATE=$(printf '| Content | content | @tester, @mate |\n| Design | design | @tester |\n| System behaviour (catch-all) | system | @other |')
T_DNONE=$(printf '| Content | content | @tester |\n| Design | design | none |\n| System behaviour (catch-all) | system | @other |')
T_APPR=$(printf '| Content | content | @tester |\n| Design | design | @tester |\n| System behaviour (catch-all) | system | Approvers |')
URL1=https://github.com/o/r/pull/1

# proj <approvers> <trusted> [<rows>]: $R's PROJECT.md, with the areas table if <rows> is given.
proj() {
  { printf '# Scratch\n\n## Code and branches\n\n- **Branch from:** `main`. **PR into:** `main`.\n\n'
    printf '## Brief approval\n\n- **Approvers:** %s\n- **Trusted:** %s\n' "$1" "$2"
    if [ -n "${3:-}" ]; then printf '\n| Area | Slug | Approved by |\n|---|---|---|\n%s\n' "$3"; fi
    printf '\nApprovers never need approval.\n'
  } > "$R/.claude/team/PROJECT.md"
}
at() { GIT_COMMITTER_DATE=$1; GIT_AUTHOR_DATE=$1; NOW=$1; export GIT_COMMITTER_DATE GIT_AUTHOR_DATE NOW; }
crlf() { awk '{ printf "%s\r\n", $0 }' "$1" > "$1.x" && mv "$1.x" "$1"; }

# newcase <name> <approvers> <trusted> [<rows>]: a fresh scratch repo $R (its path has a space)
# with a bare origin and its own stand-in state, PROJECT.md committed on main, on branch add/x.
newcase() {
  CASE=$1; n=$((n+1)); CW="$W/case $n"; R="$CW/r"
  HL=''; DEC=''; ROWS=''; BR=add/x; PRH=add/x; REQ=''; RUNNER=tester
  mkdir -p "$CW/state" "$R/.claude/team" "$R/.claude/handoff"; GH_STATE=$CW/state; export GH_STATE
  printf '.claude/handoff/\n.claude/worktrees/\n' > "$R/.gitignore"
  proj "$2" "$3" "${4:-}"
  at 2026-01-01T09:00:00Z
  { git init -q --bare "$CW/origin.git" && git -C "$R" init -q && git -C "$R" remote add origin "$CW/origin.git" \
    && git -C "$R" add -A && git -C "$R" commit -q -m init && git -C "$R" push -q origin main \
    && git -C "$R" checkout -q -b add/x; } || setup_fail "$CASE: scratch repo"
  [ -n "${QUIET:-}" ] || echo "--- $CASE"
}
# brief: $R's BRIEF.md: header lines $HL, the Decision ending $DEC, Amendments rows $ROWS.
brief() {
  { printf '# Brief — T: Test thing\n\nBranch: %s\n' "$BR"
    if [ -n "$HL" ]; then printf '%s\n' "$HL"; fi
    printf '\n## Outcome\n\nIt works.\n\n## Decisions\n\n- Decision one%s.\n\n' "$DEC"
    printf '## Constraints\n\n- Platforms: any.\n- Out of scope: other things.\n\n'
    printf '## Definition of done\n\n- [ ] It works.\n\n## Amendments\n\n'
    printf '| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |\n|---|---|---|---|---|\n'
    if [ -n "$ROWS" ]; then printf '%s\n' "$ROWS"; else printf '| | | | | |\n'; fi
  } > "$R/.claude/handoff/BRIEF.md"
}

# abr <gh user> <args...>: the script under test ($AB) in $R as that gh user; stdout $W/o,
# stderr $W/e, exit code $W/c. REQ=1 sets ADA_BRIEF_REQUIRE=1.
AB=$NP/bin/ada-brief
abr() { local u=$1; shift
  : > "$GH_STATE/writes"
  ( cd "$R" || exit 99
    PATH="$W/stub:$PATH"; GH_USER=$u; export PATH GH_USER
    if [ -n "$REQ" ]; then ADA_BRIEF_REQUIRE=1; export ADA_BRIEF_REQUIRE; fi
    exec "$BASH_BIN" "$AB" "$@" ) > "$W/o" 2> "$W/e"
  echo $? > "$W/c"
}
show() { echo "      exit $(cat "$W/c"); stdout:"; sed 's/^/        | /' "$W/o"; echo "      stderr:"; sed 's/^/        | /' "$W/e"; }
# st [<branch>]: status as $RUNNER; a run that changed the PR is counted (section 14).
st() { abr "$RUNNER" status "$@"; sruns=$((sruns+1))
  if [ -s "$GH_STATE/writes" ]; then swrites=$((swrites+1)); echo "info: $CASE: status changed the PR:"; sed 's/^/info:   /' "$GH_STATE/writes"; fi; }
# sub <opener> [<task> <base>]: submit as <opener>, who becomes the PR's opener; it must open a PR.
sub() { abr "$1" submit "${2:-T}" "${3:-main}"
  case "$(sed -n 1p "$W/o")" in
    "opened   https://github.com/o/r/pull/"*) ;;
    *) show >&3; setup_fail "$CASE: submit did not open a PR" ;;
  esac
  [ -f "$R/.claude/briefs/${2:-T}.md" ] || setup_fail "$CASE: no .claude/briefs/${2:-T}.md after submit"
}
# push_brief: write the brief and push it as .claude/briefs/T.md without submit (an amendment pushed
# with the labels left on).
push_brief() { brief
  { cp "$R/.claude/handoff/BRIEF.md" "$R/.claude/briefs/T.md" && git -C "$R" add .claude/briefs/T.md \
    && git -C "$R" commit -q -m amend && git -C "$R" push -q origin HEAD; } || setup_fail "$CASE: push"
}

# Checks on the last run's output.
line() { sed -n "${1}p" "$W/o"; }
starts() { awk -v p="$1" 'index($0, p) == 1' "$W/o"; }
L() { local got; got=$(line "$1")
  if [ "$got" = "$2" ]; then ok 0 "$CASE: line $1 is '$2'"; else ok 1 "$CASE: line $1 is '$2'"; show; fi; }
P() { local got; got=$(line "$1")
  case "$got" in "$2"*) ok 0 "$CASE: line $1 starts '$2'" ;; *) ok 1 "$CASE: line $1 starts '$2'"; show ;; esac; }
HAS() { if grep -Fxq -- "$1" "$W/o"; then ok 0 "$CASE: has the line '$1'"; else ok 1 "$CASE: has the line '$1'"; show; fi; }
NONE() { if [ -z "$(starts "$1")" ]; then ok 0 "$CASE: no line starts '$1'"; else ok 1 "$CASE: no line starts '$1'"; show; fi; }
# AREA <slug> <line>: exactly one line starts 'area <slug>: ', and it is <line>.
AREA() { local got cnt; cnt=$(starts "area $1: " | wc -l | tr -d ' '); got=$(starts "area $1: " | head -1)
  if [ "$cnt" = 1 ] && [ "$got" = "$2" ]; then ok 0 "$CASE: one area $1 line, '$2'"; else ok 1 "$CASE: one area $1 line, '$2'"; show; fi; }
BEFORE() { local i j; i=$(awk -v p="$1" 'index($0, p) == 1 { print NR; exit }' "$W/o"); j=$(awk -v p="$2" 'index($0, p) == 1 { print NR; exit }' "$W/o")
  if [ -n "$i" ] && [ -n "$j" ] && [ "$i" -lt "$j" ]; then ok 0 "$CASE: '$1' before '$2'"; else ok 1 "$CASE: '$1' before '$2'"; show; fi; }
LAST() { if [ "$(tail -1 "$W/o")" = "$URL1" ]; then ok 0 "$CASE: the PR URL is the last line"; else ok 1 "$CASE: the PR URL is the last line"; show; fi; }
EXIT() { if [ "$(cat "$W/c")" = "$1" ]; then ok 0 "$CASE: exit $1"; else ok 1 "$CASE: exit $1"; show; fi; }
ERRIS() { if [ "$(cat "$W/e")" = "$1" ]; then ok 0 "$CASE: stderr is '$1'"; else ok 1 "$CASE: stderr is '$1'"; show; fi; }
# LACKS <text>: stdout has no line containing <text>.
LACKS() { if ! grep -Fq -- "$1" "$W/o"; then ok 0 "$CASE: stdout has no '$1'"; else ok 1 "$CASE: stdout has no '$1'"; show; fi; }
NOTRACE() { if ! grep -q Traceback "$W/e" && [ ! -s "$W/e" ]; then ok 0 "$CASE: no traceback, nothing on stderr"; else ok 1 "$CASE: no traceback, nothing on stderr"; show; fi; }
NOOUT() { if [ ! -s "$W/o" ]; then ok 0 "$CASE: nothing on stdout"; else ok 1 "$CASE: nothing on stdout"; show; fi; }
# BLOCKS <n>: exactly <n> lines start '--- ' (the approvers' words, one block per distinct comment).
BLOCKS() { local cnt; cnt=$(starts '--- ' | wc -l | tr -d ' ')
  if [ "$cnt" = "$1" ]; then ok 0 "$CASE: $1 '---' block(s)"; else ok 1 "$CASE: $1 '---' block(s)"; show; fi; }
# EXACT <text>: stdout is exactly <text> and nothing else, exit 0.
EXACT() { if [ "$(cat "$W/o")" = "$1" ] && [ "$(cat "$W/c")" = 0 ]; then ok 0 "$CASE: stdout is exactly '$1', exit 0"; else ok 1 "$CASE: stdout is exactly '$1', exit 0"; show; fi; }
HOLD() { echo "HOLD $CASE: $1"; echo "      got: '$2'"; holds=$((holds+1)); }
keep() { cp "$W/o" "$W/k_$1.o"; cp "$W/e" "$W/k_$1.e"; cp "$W/c" "$W/k_$1.c"; }
SAME() { if cmp -s "$W/o" "$W/k_$1.o" && cmp -s "$W/e" "$W/k_$1.e" && cmp -s "$W/c" "$W/k_$1.c"; then ok 0 "$CASE: $2: the same output"
  else ok 1 "$CASE: $2: the same output"; echo "      before:"; sed 's/^/        | /' "$W/k_$1.o"; show; fi; }
MISM="; Author: @other is not the PR's opener @tester, so it isn't exempt"
T10=2026-01-01T10:00:00Z

# 1. Static: bash -n; the usage header documents the area path and lines (DoD 12, 13).
echo "--- 1. static"
CASE=static
if "$BASH_BIN" -n "$NP/bin/ada-brief" 2> "$W/e"; then ok 0 "bash -n ada-brief"; else ok 1 "bash -n ada-brief"; cat "$W/e"; fi
awk 'NR == 1 { next } !/^#/ { exit } { print }' "$NP/bin/ada-brief" > "$W/header"
for s in 'approved:<slug>' 'area <slug>: ' 'not needed (no approvers)' 'approves it)' 'Author:'; do
  if grep -qF -- "$s" "$W/header"; then ok 0 "usage header names '$s'"; else ok 1 "usage header names '$s'"; fi
done

# 2. DoD 2, as amended 2026-11-11: Areas: design, Author: @other. The opener (tester) counts for
#    design, so the waiting line ends with the mismatch; an approved line never does. A label for
#    an area the brief doesn't touch is ignored, with no note (Amendments, 2026-11-11).
newcase "2a Areas: design, Author: @other, opened by tester" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @other'); brief; at $T10; sub tester
st; EXIT 0; L 1 waiting; L 2 "area design: waiting (@tester)$MISM"; LAST; keep w
label approved:system tester 2026-01-01T11:00:00Z
st; SAME w "approved:system by tester (system not touched) is ignored"; NONE 'the approved:system label'; NONE 'area system:'
label approved:design tester 2026-01-01T11:10:00Z
st; L 1 'approved @tester 2026-01-01T11:10:00Z'; L 2 'area design: approved @tester 2026-01-01T11:10:00Z'; LAST

newcase "2b Areas: design, Author: @other, opened by opener (no ending due under any reading)" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @other'); brief; at $T10; sub opener
st; EXIT 0; L 1 waiting; L 2 'area design: waiting (@tester)'; LAST; keep w
RUNNER=someone; st; SAME w "status run by someone"; RUNNER=tester
HL=$(printf 'Areas: content\nAuthor: @other'); brief
st; SAME w "Areas: content in the unpushed BRIEF.md (the pushed brief decides)"; NONE 'area content:'
label approved:design tester 2026-01-01T11:10:00Z
st; L 1 'approved @tester 2026-01-01T11:10:00Z'; L 2 'area design: approved @tester 2026-01-01T11:10:00Z'; LAST; keep a
git -C "$R" checkout -q main || setup_fail "$CASE: checkout main"
st add/x; SAME a "status add/x from main"
git -C "$R" checkout -q add/x
label approved:system other 2026-01-01T11:20:00Z
st; SAME a "a later approved:system by other (system not touched) is ignored"; NONE 'area system:'

# 3. DoD 3: a label added by a login that doesn't count; Trusted; the last adder decides.
newcase "3a design approved by @other; tester adds approved:design" @neil none "$T_DOTHER"
HL=$(printf 'Areas: design\nAuthor: @opener'); brief; at $T10; sub opener
label approved:design tester 2026-01-01T11:00:00Z
st; L 1 waiting; L 2 'area design: waiting (@other)'; HAS "the approved:design label was added by @tester, who doesn't approve design, so it does not count"; LAST
proj @neil @tester "$T_DOTHER"
st; L 1 'approved @tester 2026-01-01T11:00:00Z'; L 2 'area design: approved @tester 2026-01-01T11:00:00Z'; NONE 'the approved:design label'; LAST

newcase "3b approved:design removed, then re-added by someone" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @opener'); brief; at $T10; sub opener
label approved:design tester 2026-01-01T11:00:00Z
st; L 1 'approved @tester 2026-01-01T11:00:00Z'; AREA design 'area design: approved @tester 2026-01-01T11:00:00Z'
unlabel approved:design tester 2026-01-01T11:20:00Z
st; L 1 waiting; AREA design 'area design: waiting (@tester)'
label approved:design someone 2026-01-01T11:30:00Z
st; L 1 waiting; AREA design 'area design: waiting (@tester)'; HAS "the approved:design label was added by @someone, who doesn't approve design, so it does not count"

newcase "3c an area brief with only brief-approved, added by an approver" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @opener'); brief; at $T10; sub opener
label brief-approved neil 2026-01-01T11:00:00Z
st; L 1 waiting; AREA design 'area design: waiting (@tester)'

newcase "3d Trusted: @someone; waiting (...) lists Approved by only" @neil @someone "$T_MATE"
HL=$(printf 'Areas: content, design\nAuthor: @opener'); brief; at $T10; sub opener
st; L 1 waiting; L 2 'area content: waiting (@tester, @mate)'; L 3 'area design: waiting (@tester)'
label approved:design someone 2026-01-01T11:00:00Z
st; L 1 waiting; L 2 'area content: waiting (@tester, @mate)'; L 3 'area design: approved @someone 2026-01-01T11:00:00Z'; NONE 'the approved:design label'

# 4. DoD 4: the PR's opener is the Author: and approves every area.
newcase "4a Areas: content, design, Author: @tester, opened by tester" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @tester'); brief; at $T10; sub tester
st; EXIT 0; L 1 not-needed; L 2 'area content: not needed (@tester approves it)'; L 3 'area design: not needed (@tester approves it)'; LAST; keep nn
RUNNER=other; st; SAME nn "status run by other"
RUNNER=someone; st; SAME nn "status run by someone"; RUNNER=tester
REQ=1; st; L 1 waiting; L 2 'area content: waiting (@tester)'; L 3 'area design: waiting (@tester)'; REQ=''
comment tester 2026-01-01T11:00:00Z 'please change X'
st; L 1 not-needed; AREA content 'area content: not needed (@tester approves it)'

newcase "4b Author: @Tester (upper case), opened by tester" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @Tester'); brief; at $T10; sub tester
st; L 1 not-needed; L 2 'area content: not needed (@tester approves it)'

newcase "4c Author: @tester, opened by Tester (upper case)" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @tester'); brief; at $T10; sub Tester
st; L 1 not-needed

newcase "4d Author: @tester, opened by opener (not exempt)" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @tester'); brief; at $T10; sub opener
st; L 1 waiting; L 2 'area content: waiting (@tester)'; L 3 'area design: waiting (@tester)'

newcase "4e Areas: content, system, Author: @tester, opened by tester" @neil none "$T_STD"
HL=$(printf 'Areas: content, system\nAuthor: @tester'); brief; at $T10; sub tester
st; L 1 waiting; L 2 'area content: not needed (@tester approves it)'; L 3 'area system: waiting (@other)'
label approved:system other 2026-01-01T11:00:00Z
st; L 1 'approved @other 2026-01-01T11:00:00Z'; L 2 'area content: not needed (@tester approves it)'; L 3 'area system: approved @other 2026-01-01T11:00:00Z'

# 5. DoD 5: Author: isn't the opener, or is missing.
newcase "5a Author: @other, opened by tester" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at $T10; sub tester
st; L 1 waiting; L 2 "area content: waiting (@tester)$MISM"; L 3 "area design: waiting (@tester)$MISM"; LAST

newcase "5b no Author: line, opened by tester" @neil none "$T_STD"
HL='Areas: content, design'; brief; at $T10; sub tester
st; L 1 waiting; L 2 'area content: waiting (@tester); no Author: line'; L 3 'area design: waiting (@tester); no Author: line'; LAST
label approved:content tester 2026-01-01T11:00:00Z
st; L 1 waiting; L 2 'area content: approved @tester 2026-01-01T11:00:00Z'; L 3 'area design: waiting (@tester); no Author: line'
comment tester 2026-01-01T11:10:00Z 'please change X'
st; L 1 changes-requested; L 2 'area content: approved @tester 2026-01-01T11:00:00Z'; L 3 'area design: changes-requested'

newcase "5c no Author: line, opened by opener (who counts for nothing)" @neil none "$T_STD"
HL='Areas: content, design'; brief; at $T10; sub opener
st; L 1 waiting; L 2 'area content: waiting (@tester)'; L 3 'area design: waiting (@tester)'

newcase "5d Author: @mate, opened by tester, both count for content" @neil none "$T_MATE"
HL=$(printf 'Areas: content\nAuthor: @mate'); brief; at $T10; sub tester
st; L 1 waiting; L 2 "area content: waiting (@tester, @mate); Author: @mate is not the PR's opener @tester, so it isn't exempt"

newcase "5e Author: @other, opened by tester, who counts for content but not design" @neil none "$T_DOTHER"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at $T10; sub tester
st; L 1 waiting; L 2 "area content: waiting (@tester)$MISM"; L 3 'area design: waiting (@other)'

newcase "5f no Author: line, opened by tester, who counts for content but not design" @neil none "$T_DOTHER"
HL='Areas: content, design'; brief; at $T10; sub tester
st; L 1 waiting; L 2 'area content: waiting (@tester); no Author: line'; L 3 'area design: waiting (@other)'

newcase "5g Author: @other, opened by opener, who is Trusted" @neil @opener "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @other'); brief; at $T10; sub opener
st; L 1 waiting; L 2 "area design: waiting (@tester); Author: @other is not the PR's opener @opener, so it isn't exempt"

# 5h. `gh pr view` gives "author": null (the opener's account was deleted): no traceback, exit 0, no
# area is exempt, and no line names the opener (no '@None', no 'Author:' ending).
newcase "5h gh pr view gives author null, Author: @tester" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @tester'); brief; at $T10; sub tester
GH_AUTHOR_NULL=1; export GH_AUTHOR_NULL; st; unset GH_AUTHOR_NULL
EXIT 0; NOTRACE; L 1 waiting; L 2 'area content: waiting (@tester)'; L 3 'area design: waiting (@tester)'
LACKS 'None'; LACKS 'not needed'; LACKS 'Author:'; LAST
label approved:content tester 2026-01-01T11:00:00Z
GH_AUTHOR_NULL=1; export GH_AUTHOR_NULL; st; unset GH_AUTHOR_NULL
EXIT 0; NOTRACE; L 1 waiting; L 2 'area content: approved @tester 2026-01-01T11:00:00Z'; L 3 'area design: waiting (@tester)'; LACKS 'None'

newcase "5i gh pr view gives author null, no Author: line" @neil none "$T_STD"
HL='Areas: content, design'; brief; at $T10; sub tester
GH_AUTHOR_NULL=1; export GH_AUTHOR_NULL; st; unset GH_AUTHOR_NULL
EXIT 0; NOTRACE; L 1 waiting; L 2 'area content: waiting (@tester)'; L 3 'area design: waiting (@tester)'; LACKS 'None'; LACKS 'Author:'

# 6. DoD 6: a needed: content amendment pushed with approved:content left on.
newcase "6 needed: content amendment" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @opener'); brief; at $T10; sub opener
label approved:content tester 2026-01-01T11:00:00Z; label approved:design tester 2026-01-01T11:05:00Z
st; L 1 'approved @tester 2026-01-01T11:05:00Z'; L 2 'area content: approved @tester 2026-01-01T11:00:00Z'; L 3 'area design: approved @tester 2026-01-01T11:05:00Z'; LAST
at 2026-01-01T12:00:00Z; DEC=', for content'
ROWS='| 2026-01-01 | Decision one → Decision one, for content | the content changed | needed: content | Neil |'; push_brief
st; L 1 waiting; L 2 'area content: waiting (@tester)'; L 3 'area design: approved @tester 2026-01-01T11:05:00Z'; NONE 'amended since approval'
AREA content 'area content: waiting (@tester)'; AREA design 'area design: approved @tester 2026-01-01T11:05:00Z'
HAS '@tester approved an earlier version of content; the brief has changed since: needed: content'; LAST
unlabel approved:content tester 2026-01-01T12:20:00Z; label approved:content tester 2026-01-01T12:30:00Z
st; L 1 'approved @tester 2026-01-01T12:30:00Z'; L 2 'amended since approval; no re-approval needed'
AREA content 'area content: approved @tester 2026-01-01T12:30:00Z'; AREA design 'area design: approved @tester 2026-01-01T11:05:00Z'; NONE '@tester approved an earlier version'; LAST

# 7. DoD 7: an approved section changed with no row touches the catch-all; a not needed row doesn't.
newcase "7a a Decision changed with no row" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @opener'); brief; at $T10; sub opener
label approved:content tester 2026-01-01T11:00:00Z; label approved:design tester 2026-01-01T11:05:00Z
at 2026-01-01T12:00:00Z; DEC=' (changed)'; push_brief
st; L 1 waiting; L 2 'area content: approved @tester 2026-01-01T11:00:00Z'; L 3 'area design: approved @tester 2026-01-01T11:05:00Z'; L 4 'area system: waiting (@other)'; NONE 'amended since approval'
AREA system 'area system: waiting (@other)'; LAST
comment other 2026-01-01T12:10:00Z 'please change Z'
st; L 1 changes-requested; L 2 'area content: approved @tester 2026-01-01T11:00:00Z'; L 4 'area system: changes-requested'; NONE 'amended since approval'
label approved:system other 2026-01-01T12:30:00Z
st; L 1 'approved @other 2026-01-01T12:30:00Z'; L 2 'amended since approval; no re-approval needed'
L 3 'area content: approved @tester 2026-01-01T11:00:00Z'; L 4 'area design: approved @tester 2026-01-01T11:05:00Z'; L 5 'area system: approved @other 2026-01-01T12:30:00Z'
AREA system 'area system: approved @other 2026-01-01T12:30:00Z'; LAST

newcase "7b a Decision changed with a not needed row" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @opener'); brief; at $T10; sub opener
label approved:content tester 2026-01-01T11:00:00Z; label approved:design tester 2026-01-01T11:05:00Z
at 2026-01-01T12:00:00Z; DEC=' (changed)'
ROWS='| 2026-01-01 | Decision one → Decision one (changed) | clearer wording | not needed | Neil |'; push_brief
st; L 1 'approved @tester 2026-01-01T11:05:00Z'; L 2 'amended since approval; no re-approval needed'; NONE 'area system:'
AREA content 'area content: approved @tester 2026-01-01T11:00:00Z'; AREA design 'area design: approved @tester 2026-01-01T11:05:00Z'; LAST

newcase "7c Areas: system, content; a Decision changed with no row" @neil none "$T_STD"
HL=$(printf 'Areas: system, content\nAuthor: @opener'); brief; at $T10; sub opener
label approved:content tester 2026-01-01T11:00:00Z; label approved:system other 2026-01-01T11:05:00Z
at 2026-01-01T12:00:00Z; DEC=' (changed)'; push_brief
st; L 1 waiting; L 2 'area system: waiting (@other)'; L 3 'area content: approved @tester 2026-01-01T11:00:00Z'; NONE 'amended since approval'
AREA system 'area system: waiting (@other)'; AREA content 'area content: approved @tester 2026-01-01T11:00:00Z'
HAS '@other approved an earlier version of system; the brief has changed since: approved section changed with no amendment recorded'

# 8. DoD 8: changes requested by a login that counts for the area, since the last push.
newcase "8a a PR comment by tester, then approved:design" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @opener'); brief; at $T10; sub opener
comment tester 2026-01-01T11:00:00Z 'please change X'
st; L 1 changes-requested; AREA design 'area design: changes-requested'; HAS 'please change X'; LAST
HAS '--- @tester, comment, 2026-01-01T11:00:00Z'; BLOCKS 1; BEFORE '--- @tester, comment' 'please change X'
label approved:design tester 2026-01-01T11:30:00Z
st; L 1 'approved @tester 2026-01-01T11:30:00Z'; AREA design 'area design: approved @tester 2026-01-01T11:30:00Z'; BLOCKS 0

newcase "8b a PR comment by other, who doesn't count for design" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @opener'); brief; at $T10; sub opener
comment other 2026-01-01T11:00:00Z 'a thought'
st; L 1 waiting; AREA design 'area design: waiting (@tester)'; BLOCKS 0; NONE 'a thought'

newcase "8c a PR comment by tester before the last push" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @opener'); brief; at $T10; sub opener
comment tester 2026-01-01T10:30:00Z 'please change X'
at 2026-01-01T12:00:00Z; DEC=' (changed as asked)'; push_brief
st; L 1 waiting; AREA design 'area design: waiting (@tester)'

newcase "8d a review by tester" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @opener'); brief; at $T10; sub opener
review tester 2026-01-01T11:00:00Z CHANGES_REQUESTED 'fix Y'
st; L 1 changes-requested; AREA design 'area design: changes-requested'

newcase "8e a line comment by tester" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @opener'); brief; at $T10; sub opener
linecomment tester 2026-01-01T11:00:00Z 'this line'
st; L 1 changes-requested; AREA design 'area design: changes-requested'

newcase "8f Areas: design, system; a PR comment by tester" @neil none "$T_STD"
HL=$(printf 'Areas: design, system\nAuthor: @opener'); brief; at $T10; sub opener
comment tester 2026-01-01T11:00:00Z 'please change X'
st; L 1 changes-requested; AREA design 'area design: changes-requested'; AREA system 'area system: waiting (@other)'; HAS '--- @tester, comment, 2026-01-01T11:00:00Z'; BLOCKS 1

newcase "8g Areas: content, design; one PR comment by tester sends both back" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @opener'); brief; at $T10; sub opener
comment tester 2026-01-01T11:00:00Z 'please change X'
st; L 1 changes-requested; L 2 'area content: changes-requested'; L 3 'area design: changes-requested'
HAS '--- @tester, comment, 2026-01-01T11:00:00Z'; HAS 'please change X'; BLOCKS 1; LAST

# 9. DoD 9: an area PROJECT.md doesn't list.
for areas in price 'content, price'; do
  newcase "9 Areas: $areas" @neil none "$T_STD"
  HL=$(printf 'Areas: %s\nAuthor: @opener' "$areas"); brief; at $T10; sub opener
  st; EXIT 2; NOOUT; ERRIS "ada-brief: the brief names area price, which PROJECT.md doesn't list"
done

# 10. Approvers: none with a table decides by the areas; an area with no approvers needs none.
newcase "10a Approvers: none, Areas: content" none none "$T_APPR"
HL=$(printf 'Areas: content\nAuthor: @opener'); brief; at $T10; sub opener
st; L 1 waiting; L 2 'area content: waiting (@tester)'; LAST

newcase "10b Approvers: none, Areas: system (Approved by Approvers)" none none "$T_APPR"
HL=$(printf 'Areas: system\nAuthor: @opener'); brief; at $T10; sub opener
st; L 1 not-needed; L 2 'area system: not needed (no approvers)'; LAST

newcase "10c design approved by none" @neil none "$T_DNONE"
HL=$(printf 'Areas: content, design\nAuthor: @opener'); brief; at $T10; sub opener
st; L 1 waiting; AREA design 'area design: not needed (no approvers)'
label approved:content tester 2026-01-01T11:00:00Z
st; L 1 'approved @tester 2026-01-01T11:00:00Z'; AREA content 'area content: approved @tester 2026-01-01T11:00:00Z'; AREA design 'area design: not needed (no approvers)'

newcase "10d design approved by none; Author: @other, opened by tester" @neil none "$T_DNONE"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at $T10; sub tester
st; L 1 waiting; L 2 "area content: waiting (@tester)$MISM"; L 3 'area design: not needed (no approvers)'

# 10f. Approvers: none with a table: the answers before the area path (no PR, no marker, the brief not
#     on the pushed branch) are exactly `not-needed` (a known gap for Unit E: `not-submitted` may be
#     the safer word once area briefs are routed here). With the marker and the pushed brief, 10a
#     gives `waiting` for the same project and brief.
newcase "10f-nopr Approvers: none, a table, Areas: content, no PR" none none "$T_APPR"
HL=$(printf 'Areas: content\nAuthor: @opener'); brief; at $T10
st; EXACT not-needed

newcase "10f-nomarker Approvers: none, a table, Areas: content, a PR with no ada-brief marker" none none "$T_APPR"
HL=$(printf 'Areas: content\nAuthor: @opener'); brief; at $T10; sub opener
gha body "$PRH" 'a PR body with no ada-brief marker'
st; EXACT not-needed

newcase "10f-notpushed Approvers: none, a table, Areas: content, the marker names a brief not on the pushed branch" none none "$T_APPR"
HL=$(printf 'Areas: content\nAuthor: @opener'); brief; at $T10; sub opener
gha body "$PRH" '<!-- ada-brief: .claude/briefs/Missing.md -->'
st; EXACT not-needed

# 10e-g. Approvers: none or missing and no areas table (Amendments, 2026-11-11, "Approvers: none with a
#     table"): status keeps today's early `not-needed` exit for every brief, before any gh call, so an
#     Areas: brief naming an area the project doesn't list is `not-needed`, not exit 2.
# The login check (`gh auth status`) runs before the early exit, as in the base; no other gh call may.
onlyauth() { if [ "$(grep -vc '^gh auth status$' "$GH_STATE/log")" = 0 ]; then ok 0 "$CASE: no gh call but gh auth status"; else ok 1 "$CASE: no gh call but gh auth status"; sed 's/^/      /' "$GH_STATE/log"; fi; }
noprview() { if ! grep -q '^gh pr view' "$GH_STATE/log"; then ok 0 "$CASE: no gh pr view call"; else ok 1 "$CASE: no gh pr view call"; sed 's/^/      /' "$GH_STATE/log"; fi; }
for v in none nosection; do
  case $v in
    none)      d="Approvers: none, no table" ;;
    nosection) d="no Brief approval section (the approvers are missing)" ;;
  esac
  newcase "10e-$v $d, Areas: price" none none
  case $v in
    nosection) printf '# Scratch\n\n## Code and branches\n\n- **Branch from:** `main`. **PR into:** `main`.\n' > "$R/.claude/team/PROJECT.md" ;;
  esac
  HL=$(printf 'Areas: price\nAuthor: @opener'); brief; at $T10; sub opener
  : > "$GH_STATE/log"
  st; EXIT 0; L 1 not-needed; NONE 'area '; ERRIS ''; noprview; onlyauth
  if [ "$(cat "$W/o")" = not-needed ]; then ok 0 "$CASE: stdout is exactly 'not-needed'"; else ok 1 "$CASE: stdout is exactly 'not-needed'"; show; fi
  keep nn; AB=$BP/bin/ada-brief; st; AB=$NP/bin/ada-brief; SAME nn "the base's status"
done

# 11. Briefs without Areas: print what the base prints (DoD 10): the 14 steps of
#     submit-and-status-offline-stub-gh.md, with and without a table; Approvers: none; the
#     template's Areas: prompt. Every line and exit code, then the labels and comments left.
rec() { echo "\$ ada-brief $*"; abr Tester "$@"; cat "$W/o" "$W/e"; echo "exit $(cat "$W/c")"; }
recstate() { echo "### state"; python3 -c 'import json,sys; s=json.load(open(sys.argv[1])); [print(p["labels"], len(p["comments"])) or [print(c["body"]) for c in p["comments"]] for p in s["prs"]]' "$GH_STATE/state.json"; }
seq14() {
  QUIET=1
  if [ "$1" = table ]; then newcase "seq14 table" @tester none "$T_APPR"; else newcase "seq14 plain" @tester none; fi
  QUIET=''
  echo "### 1"; at $T10; brief; rec submit T main
  echo "### 2"; rec status
  echo "### 3"; label brief-approved someone 2026-01-01T10:30:00Z; rec status
  echo "### 4"; at 2026-01-01T10:40:00Z; rec submit T main; rec status
  echo "### 5"; label brief-approved Tester 2026-01-01T11:00:00Z; rec status; rec status add/x
  echo "### 6"; at 2026-01-01T11:10:00Z; rec submit T main
  echo "### 7"; gha body "$PRH" 'old body'; rec submit T main
  echo "### 8"; at 2026-01-01T12:00:00Z; ROWS='| 2026-01-01 | a → b | reasons | not needed | Neil |'; DEC=' (changed)'; brief; rec submit T main; rec status
  echo "### 9"; at 2026-01-01T12:10:00Z; rec submit T main
  echo "### 10"; comment Tester 2026-01-01T12:20:00Z hello; rec status
  echo "### 11"; at 2026-01-01T13:00:00Z
  ROWS=$(printf '| 2026-01-01 | a → b | reasons | not needed | Neil |\n| 2026-01-01 | c → d | why | needed: it reverses a Decision | Neil |'); push_brief; rec status
  comment Tester 2026-01-01T13:30:00Z 'please change X'; rec status
  echo "### 12"; at 2026-01-01T13:40:00Z; rec submit T main; rec status
  echo "### 13"; label brief-approved Tester 2026-01-01T14:00:00Z; rec status
  echo "### 14"; at 2026-01-01T15:00:00Z; DEC=' (changed again)'; brief; rec submit T main; rec status
  recstate
}
small() {
  QUIET=1
  case $1 in
    none) newcase "approvers none" none none "$T_APPR"; brief; at $T10; rec submit T main; rec status ;;
    prompt) newcase "prompt" @tester none "$T_APPR"; HL='Areas: <the areas it touches>'; brief; at $T10; rec submit T main; rec status
            label brief-approved Tester 2026-01-01T11:00:00Z; rec status ;;
  esac
  QUIET=''; recstate
}
for v in plain table none prompt; do
  case $v in plain|table) f=seq14 ;; *) f=small ;; esac
  AB=$BP/bin/ada-brief; $f $v > "$W/rec-b-$v" 2>&1
  AB=$NP/bin/ada-brief; $f $v > "$W/rec-n-$v" 2>&1
  CASE="11 no Areas: ($v)"; echo "--- $CASE"
  if cmp -s "$W/rec-b-$v" "$W/rec-n-$v"; then ok 0 "$CASE: every line, exit code, label and comment as the base's"
  else ok 1 "$CASE: every line, exit code, label and comment as the base's"; diff "$W/rec-b-$v" "$W/rec-n-$v" | head -20 | sed 's/^/      /'; fi
  case $v in
    plain|table) want="approved @tester 2026-01-01T11:00:00Z|amended since approval; no re-approval needed|@tester approved an earlier version; the brief has changed since: needed: it reverses a Decision|changes-requested|updated  $URL1 (approval reset)|updated  $URL1 (approval kept)" ;;
    none) want="not-needed" ;;
    prompt) want="waiting|approved @tester 2026-01-01T11:00:00Z" ;;
  esac
  echo "$want" | tr '|' '\n' > "$W/want"
  while IFS= read -r s; do
    if grep -Fxq -- "$s" "$W/rec-n-$v"; then ok 0 "$CASE: has the line '$s'"; else ok 1 "$CASE: has the line '$s'"; fi
  done < "$W/want"
  if grep -q '^area ' "$W/rec-n-$v"; then ok 1 "$CASE: no area lines"; else ok 0 "$CASE: no area lines"; fi
done
AB=$NP/bin/ada-brief

# 12. ada-plan status shows the unit's brief as status's first line (DoD 11).
newcase "12 ada-plan status" @neil none "$T_STD"
git -C "$R" checkout -q main
( cd "$R" && PATH="$W/stub:$PATH" && GH_USER=tester && export PATH GH_USER \
  && "$BASH_BIN" "$NP/bin/ada-handoff-init" && "$BASH_BIN" "$NP/bin/ada-plan" init ) > "$W/o" 2>&1 || { show; setup_fail "$CASE: ada-plan init"; }
cat > "$R/.claude/handoff/plan/PLAN.md" <<'EOF'
# Plan: Widgets

Epic: https://app.asana.com/0/1/1219000000000009
Status: agreed

## Outcome

Widgets go out.

## Units

| ID | Unit | Task | Branch | Parent | Ships |
|---|---|---|---|---|---|
| A | Widget export | 1219000000000001 | add/x | | independent |

## Amendments

| Date | What changed | Why | Agreed by |
|---|---|---|---|
| | | | |
EOF
git -C "$R" checkout -q add/x
HL=$(printf 'Areas: content, design\nAuthor: @opener'); brief; at $T10; sub opener
plan() { ( cd "$R" && PATH="$W/stub:$PATH" && GH_USER=tester && export PATH GH_USER && exec "$BASH_BIN" "$NP/bin/ada-plan" status ) > "$W/o" 2> "$W/e"; echo $? > "$W/c"; }
ENDS() { if awk -v s="$1" 'length($0) >= length(s) && substr($0, length($0) - length(s) + 1) == s { f = 1 } END { exit !f }' "$W/o"; then ok 0 "$CASE: a line ends '$1'"; else ok 1 "$CASE: a line ends '$1'"; show; fi; }
plan; EXIT 0; ENDS 'brief: waiting'
label approved:content tester 2026-01-01T11:00:00Z; label approved:design tester 2026-01-01T11:05:00Z
plan; EXIT 0; ENDS 'brief: approved @tester 2026-01-01T11:05:00Z'

# 13. submit's base check: a part into a feature whose brief is an area brief.
prs() { python3 -c 'import json,sys; print(len(json.load(open(sys.argv[1]))["prs"]))' "$GH_STATE/state.json"; }
newcase "13a a part's submit while the feature's area brief waits" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @opener'); brief; at $T10; sub opener
git -C "$R" checkout -q -b add/p; BR=add/p; HL=''; brief; at 2026-01-01T10:30:00Z
abr opener submit T2 add/x
if [ "$(cat "$W/c")" != 0 ] && ! grep -q '^opened' "$W/o" && [ "$(prs)" = 1 ]; then ok 0 "$CASE: submit refused, no PR opened (exit $(cat "$W/c"))"; else ok 1 "$CASE: submit refused, no PR opened"; show; fi
label approved:design tester 2026-01-01T11:00:00Z
at 2026-01-01T11:10:00Z; abr opener submit T2 add/x; L 1 'opened   https://github.com/o/r/pull/2'

newcase "13b a part's submit when the feature's area brief needs no approval" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @tester'); brief; at $T10; sub tester
git -C "$R" checkout -q -b add/p; BR=add/p; HL=''; brief; at 2026-01-01T10:30:00Z
abr tester submit T2 add/x; L 1 'opened   https://github.com/o/r/pull/2'

# 14. status changes nothing on the PR.
CASE=readonly; echo "--- 14. read-only"
if [ "$swrites" = 0 ]; then ok 0 "no status run changed the PR ($sruns runs)"; else ok 1 "no status run changed the PR ($swrites of $sruns did)"; fi

# 15. CRLF line ends, in the brief and in PROJECT.md (area 12).
newcase "15a BRIEF.md with CRLF" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @other'); brief; crlf "$R/.claude/handoff/BRIEF.md"; at $T10; sub opener
st; L 1 waiting; L 2 'area design: waiting (@tester)'
label approved:design tester 2026-01-01T11:10:00Z
st; L 1 'approved @tester 2026-01-01T11:10:00Z'; L 2 'area design: approved @tester 2026-01-01T11:10:00Z'
newcase "15b PROJECT.md with CRLF" @neil none "$T_STD"
crlf "$R/.claude/team/PROJECT.md"
HL=$(printf 'Areas: design\nAuthor: @other'); brief; at $T10; sub opener
st; L 1 waiting; L 2 'area design: waiting (@tester)'

# 16. CHANGELOG: the Brief approval by area. bullet says how an approver approves an area (DoD 14).
CASE=changelog; echo "--- 16. CHANGELOG"
if [ -f "$CL" ]; then
  awk '/^## Unreleased/ { u = 1; next } u && /^## / { exit } u' "$CL" > "$W/unrel"
  awk '/\*\*Brief approval by area\.\*\*/ { b = 1 } b && /^$/ { exit } b && /^- / && !/Brief approval by area/ { exit } b' "$W/unrel" > "$W/bullet"
  if [ -s "$W/bullet" ]; then ok 0 "CHANGELOG: **Brief approval by area.** under Unreleased"; else ok 1 "CHANGELOG: **Brief approval by area.** under Unreleased"; fi
  if grep -qF 'approved:' "$W/bullet"; then ok 0 "CHANGELOG: the bullet names the approved:<slug> label"; else ok 1 "CHANGELOG: the bullet names the approved:<slug> label"; fi
else
  ok 1 "CHANGELOG: no file at $CL"
fi

echo "$passes passed, $fails failed, $holds held"
cat "$W"/case*/state/unsupported > "$W/unsup" 2>/dev/null
if [ -s "$W/unsup" ]; then echo "SETUP FAIL: the stand-in gh was asked for what it doesn't handle (extend the stand-in, not the expectations):"; sort -u "$W/unsup" | sed 's/^/  /'; exit 2; fi
[ "$fails" = 0 ] || exit 1
exit 0
```

## Expected

Every check line `PASS`; the last lines `<n> passed, 0 failed, 0 held` and no `SETUP FAIL`;
exit 0. The same PASS and FAIL lines under bash 3.2 and Python 3.8 (Steps 3).

Sources, all the brief (Unit D1) unless named:

- Section 1: DoD 12 and 13. The header must name the label (`approved:<slug>`), the area lines'
  form (`area <slug>: `, `not needed (no approvers)`, `… approves it)`) and `Author:`.
- Section 2: DoD 2 as amended (2026-11-11: the opener counts for design, so the `waiting` line
  ends with the mismatch; the `approved` line has no ending; a label for an untouched area is
  ignored with no note, checked as the same output before and after it). 2b, opened by a login on
  no list: no ending; plus Decisions: the author is the PR's opener "whoever runs `status`"; "the
  pushed brief's `Areas:` line" decides; `status <branch>`; a later `approved:system` by `@other`,
  who counts for system, still doesn't change the first line.
- Section 3: DoD 3; Decisions, state 2 ("last added by a login that counts") and the
  non-counting note; "Labels read: `approved:<slug>`" (so `brief-approved` alone doesn't approve
  an area brief). 3d: `waiting (@a, @b)` lists Approved by in table order, not Trusted
  (Amendments, 2026-11-11); a Trusted login's label counts.
- Section 4: DoD 4; Authorship (case-insensitive, `ADA_BRIEF_REQUIRE=1`); state order (1 before
  3, so an exempt area stays `not needed` after a comment); Output (first line `waiting` if any
  area waits, `approved @<login> <time>` from the latest counting label). 4d: `Author:` counts
  but the opener doesn't, so no ending (Amendments, 2026-11-11).
- Section 5: DoD 5. 5a and 5b exactly as the item says; 5b then has an `approved` and a
  `changes-requested` line, neither with an ending (Amendments, 2026-11-11: `waiting` lines
  only). 5c (the opener counts for nothing: no ending); 5d (`Author:` and the opener both count;
  logins in table order); 5e and 5f (the ending only on the area the opener counts for, though
  `Author: @other` counts for design); 5g (a Trusted opener counts, and isn't listed in
  `waiting (…)`).
- Section 6: DoD 6 and Sequences; the earlier-version note's wording from Decisions, the reason
  `needed: content` as `compare` gives it (`SYSTEM.md`); no `amended` line under `waiting`, so
  line 2 is content's (Amendments, 2026-11-11). Then a fresh
  label gives `approved` with the `amended` line (design is approved through the amendment).
- Section 7: DoD 7; touched areas (the catch-all, mapped to its slug `system`, once; not on
  `Areas:`, its line last; on `Areas:` first (7c), in `Areas:` order); no `amended` line under
  `waiting` or `changes-requested` (Amendments, 2026-11-11); `approved section changed with no
  amendment recorded` is `compare`'s reason (`SYSTEM.md`).
- Section 8: DoD 8; state 3 ("a review, line comment or PR comment by a login that counts, since
  `since`"); state 2 before 3.
- Section 9: DoD 9 (exit 2, the message; nothing on stdout, as for every `ada-brief:` error).
- Section 10: Decisions, "Approvers: none with a table" and state 1 ("it has no approvers");
  10d: no ending on a `not needed` line (Amendments, 2026-11-11).
- Section 10e: Amendments, 2026-11-11, "Approvers: none with a table": no approvers (`Approvers: none`,
  or no Brief approval section) and no areas table, an `Areas: price` brief: `not-needed`, exit 0, empty
  stderr, no `gh pr view` call (`gh auth status` is the only gh call: the login check before the early
  exit, as in the base), and the output the base's `status` gives. A Brief approval section with
  no `Approvers:` line is not "missing": it exits 2 as in the base, so it is not a case.
- Section 11: DoD 10 and Regression, against `c8e1c5a`, with the expected lines (`SYSTEM.md`,
  `status`) checked too so two identical wrong records can't pass. An `Areas:` line holding the
  template's prompt is no `Areas:` (`SYSTEM.md`, `header_value()`).
- Section 12: DoD 11. `ada-plan status` calls `status` only when `who` says `required`, so
  `PROJECT.md`'s Approvers line names `neil`, not `tester`.
- Section 13: Constraints, Surfaces (`submit`'s base check accepts `approved` and `not-needed`).
- Section 14: Constraints, "nothing writes labels or bodies".
- Section 15: area 12 (CRLF).
- Section 16: DoD 14 and Constraints (S105).

## On GitHub

The Definition of Done in its own words, for a person. A scratch GitHub repo you own, never a
client's; `gh` logged in as you (`<me>`); `@other` any other real GitHub login (it is only named,
it never acts). `B=<this checkout>/plugins/ada/bin/ada-brief`. Add a label to PR `<n>` with
`gh api -X POST repos/{owner}/{repo}/issues/<n>/labels -f 'labels[]=approved:design'`, remove it
with `gh api -X DELETE repos/{owner}/{repo}/issues/<n>/labels/approved:design`. Wait a few
seconds between a push and a label, since times are compared to the second. Here the opener is
always you, so (the brief's Amendments, 2026-11-11) a `waiting` line for an area you approve ends
`; Author: @other is not the PR's opener @<me>, so it isn't exempt` with `Author: @other`, or
`; no Author: line` with none; `approved`, `changes-requested` and `not needed` lines never do.

1. In the scratch repo, on `main`: `.claude/team/PROJECT.md` whose Brief approval section has
   `- **Approvers:** @other`, `- **Trusted:** none` and the table below; `.gitignore` with
   `.claude/handoff/`. Commit and push. `gh label create approved:content`, `approved:design`,
   `approved:system`.

   ```
   | Area | Slug | Approved by |
   |---|---|---|
   | Content | content | @<me> |
   | Design | design | @<me> |
   | System behaviour (catch-all) | system | @other |
   ```

   For each step below: a new branch from `main` (`add/d1-<step>`), a filled-in
   `.claude/handoff/BRIEF.md` as `brief()` in the script above writes it (with that `Branch:`),
   its header lines as the step says, then `bash "$B" submit 12190000000001<step> main`
   (`opened   <url>`); `bash "$B" status` after each action.
2. DoD 2, as amended: `Areas: design`, `Author: @other`. `status`: `waiting`, then exactly
   `area design: waiting (@<me>); Author: @other is not the PR's opener @<me>, so it isn't
   exempt`. Add `approved:system`: the same output as before it, with no `area system:` line and
   no note about the label. Add `approved:design`: `approved @<me> <time>`, then exactly
   `area design: approved @<me> <time>`.
3. DoD 3: change the table's Design row to `@other` (don't commit). `Areas: design`, no
   `Author:` line (with `Author: @<me>`, Trusted would exempt you). Add `approved:design`:
   `waiting`, exactly `area design: waiting (@other)` (you don't count for design: no ending),
   and the line `the approved:design label was added by @<me>, who doesn't approve design, so it
   does not count`. Set `- **Trusted:** @<me>`: `approved @<me> <time>`, exactly
   `area design: approved @<me> <time>`, and no such note. `git checkout --
   .claude/team/PROJECT.md`.
4. DoD 4: `Areas: content, design`, `Author: @<me>`. `status`: `not-needed`, then
   `area content: not needed (@<me> approves it)` and `area design: not needed (@<me> approves
   it)`. `ADA_BRIEF_REQUIRE=1 bash "$B" status`: `waiting`. If you can, have someone else run
   `status <branch>` from a clone: the same lines.
5. DoD 5: the same brief with `Author: @other`, on its own branch: `waiting`, then exactly
   `area content: waiting (@<me>); Author: @other is not the PR's opener @<me>, so it isn't
   exempt` and the same for `design`. With no `Author:` line, on its own branch: each line is
   exactly `area …: waiting (@<me>); no Author: line`. On that branch add `approved:content`:
   `waiting`, exactly `area content: approved @<me> <time>` and `area design: waiting (@<me>); no
   Author: line`. Then `gh pr comment <n> --body 'please change X'`: `changes-requested`,
   `area content: approved @<me> <time>`, exactly `area design: changes-requested`.
6. DoD 6: `Areas: content, design`, no `Author:` line. Add `approved:content` and
   `approved:design`: `approved @<me> <time>`. Add an Amendments row `| <date> | <what> | <why> |
   needed: content | <you> |` and change a Decision; `cp .claude/handoff/BRIEF.md
   .claude/briefs/<id>.md`, commit, push (not `submit`; the labels stay on). `status`: `waiting`;
   line 2 exactly `area content: waiting (@<me>); no Author: line` (no `amended` line under
   `waiting`); line 3 exactly `area design: approved @<me> <time>`; the note `@<me> approved an
   earlier version of content; the brief has changed since: needed: content`.
7. DoD 7: as 6 up to the labels, then change a Decision with no row and push the same way:
   `waiting`, then exactly `area content: approved @<me> <time>`, `area design: approved @<me>
   <time>` and `area system: waiting (@other)`, in that order (system isn't on `Areas:`, so it is
   last; no `amended` line). On a second branch, the same with a row
   whose Re-approval is `not needed`: `approved @<me> <time>`, then `amended since approval; no
   re-approval needed`, and no `area system:` line.
8. DoD 8: `Areas: design`, no `Author:`. `gh pr comment <n> --body 'please change X'`:
   `changes-requested`, exactly `area design: changes-requested`, and the comment's text.
9. DoD 9: `Areas: price`: `bash "$B" status; echo "exit $?"`: nothing on stdout, stderr
   `ada-brief: the brief names area price, which PROJECT.md doesn't list`, `exit 2`.
10. DoD 10: set `- **Approvers:** @<me>` (don't commit). A brief with no `Areas:` line: `waiting`;
    add `brief-approved`: `approved @<me> <time>`, the same as the installed plugin's
    `ada-brief status` on the same branch prints (the installed one is the base). Any older PR in
    the repo labelled `brief-approved` by an approver: `bash "$B" status <its branch>` prints
    `approved …`. `git checkout -- .claude/team/PROJECT.md`.
11. DoD 11: on `main`, `<this checkout>/plugins/ada/bin/ada-plan init`, then
    `.claude/handoff/plan/PLAN.md` as in the script's section 12 with unit A's Branch the branch
    of step 2. `<this checkout>/plugins/ada/bin/ada-plan status`: unit A shows `brief: approved
    @<me> <time>`.
12. Close every PR (`gh pr close <n> --delete-branch`).

## Observe at

The script's output and exit code. A failing check prints the run's exit code, stdout and stderr
under it. `KEEP=1` keeps the scratch folder (named on the first line): `case <n>/r` is a case's
repo, `case <n>/state/state.json` the stand-in's PR, labels, events and comments, `log` every
`gh` call and `writes` the last run's changes. On GitHub: the PR's labels and conversation, and
`status`'s output.

## Regression

Section 11 (briefs without `Areas:`, the 14-step `submit`/`status` sequence, against the base) and
sections 12 and 13 (the readers of the first line). Also run, unedited, the other scripts in this
folder and `tests/functional/01-brief-approval-areas/submit-and-status-offline-stub-gh.md`.
