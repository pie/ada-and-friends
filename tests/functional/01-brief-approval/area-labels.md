# `ada-brief submit` creates and resets the area labels of a brief with `Areas:`

Unit D2 of the plan "Brief approval by area": `submit` creates the `approved:<slug>` labels of
the areas a brief touches, lists them with their approvers in the draft PR's note, and on an
amendment removes only the labels of the areas it sends back for approval, saying which in
`updated  <url> (approval reset: <slug>, ...)`. A brief without `Areas:` is submitted exactly as
before.

- Area: 01 Brief approval (`ada-brief submit`, `body`; `status` after a submit); 12 Platforms
  (bash 3.2, Python 3.8, CRLF, a path with a space)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198552681681
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-23, 0 failed (148 PASS) on the host (bash 5.3, Python 3.14), bash 3.2 and Python 3.8, same PASS lines; On GitHub steps 1 to 8 and 10 passed (step 9 Not verified: no Asana link from the CLI); see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree, and the base commit `b1d68d8`
  (`origin/add/brief-approval-by-area` with Unit D1, the script before this change; `BASE=<ref>`
  for another) in its git. In a container a worktree's git is not readable: export the base on the
  host and pass `BASE_PLUGIN` (Steps, 3).
- `git`, `python3` (3.8+), `mktemp`, `awk`, `cmp`, `diff`, `tar`. No network, no GitHub login.
- The script puts a stateful stand-in `gh` first on the PATH: the one from `area-status.md`, with
  repo labels kept with their colour and description (`gh label create <name> --color/-c
  --description/-d [--force/-f]`, or `POST repos/o/r/labels`), refusing an existing name without
  `--force` as GitHub does. Changes to the PR are logged in `writes`, label creation in
  `labelwrites`, so "changes nothing on the PR" can be checked apart from label creation. Labels,
  comments and pushes are put in by the script with fixed times, so the order of approval is the
  sequence, not the clock: the brief is submitted at 10:00, labels added at 11:00, the amendment
  submitted at 12:00. A call the stand-in doesn't handle ends the run with `SETUP FAIL` and the
  call, so a gap in the harness can't pass or fail a check.
- Logins: `tester` (`<me>` in the brief, who runs `submit` and so opens the PR), `other`,
  `someone` and `opener` (on no list), `mate`, `neil` (the Approvers line). Table **T**:
  `Wording and content` (`content`) and `Design` (`design`) approved by `@tester`,
  `System behaviour (catch-all)` (`system`) by `@other`; `Approvers: @neil`, `Trusted: none`.
  "Both labels on" is `approved:content` (11:00) and `approved:design` (11:05), added by `tester`,
  on a brief with `Areas: content, design` and `Author: @other` (so neither area is exempt).
- The note's first line is taken to be the first line of the PR body that starts `> ` (in the
  base it is `> **Brief for approval.** Add the `brief-approved` label …`).
- The Asana block is what the Asana app for GitHub appends to a PR description: a `---` line, then
  a line holding `To see the specific tasks where the Asana app for GitHub is being used` (the
  sentence `SYSTEM.md` says `ada-brief` finds it by), then the task's link.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`. Under a minute.
3. Under bash 3.2 and Python 3.8 (Docker). Export the base first, since a container can't read a
   worktree's git (run on the host; `S` is the scratch folder holding `check.sh`):

   ```sh
   git -C <this checkout> archive -o "$S/base.tar" b1d68d8 plugins/ada
   mkdir -p "$S/base" && tar -x -C "$S/base" -f "$S/base.tar"
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 diffutils >/dev/null && REPO=/repo BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
     sh -c 'apt-get -qq update >/dev/null && apt-get -qq install -y git >/dev/null && REPO=/repo BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   ```

   The `info:` lines at the top say `bash 3.2` and `Python 3.8`; the PASS and FAIL lines are
   the same as under the host's bash (`diff` the two outputs less their `info:` lines).
4. Break it on purpose, twice, each with `PLUGIN=<a plugin folder>`:
   - `PLUGIN=$S/base/plugins/ada` (the base, which creates no area labels): `info: the script
     under test is identical to the base`, no `SETUP FAIL`, `FAIL` lines in sections 1 to 7, 9a
     to 9c, 9e, 10 and 12 to 14 (no `approved:<slug>` label is created, no `(approval reset: …)`
     line, no per-area note line); sections 8, 9d and 11 print only `PASS` (the base prints
     `(approval reset)` too).
   - A copy of `plugins/ada` with the change built, its `bin/ada-brief` with every
     `approval reset: ` changed to `approval reset - `: the `(approval reset: …)` checks of
     sections 5, 6, 9 and 13 print `FAIL`; sections 7, 8 and 11 still `PASS`.
   - The same, with the comment for an area that newly needs approval taken out (Bob's step 2
     trigger): section 6's comment checks and section 13's `3 PR comment(s)` print `FAIL`.
5. On GitHub, below: the Definition of Done in the brief's own words, in a scratch GitHub repo.

```bash
#!/bin/bash
# ada-brief submit with area labels (Unit D2), offline: a stateful stand-in gh and scratch repos with
# a local bare origin, one per case. PASS/FAIL per check. Exit 1 if any FAIL, 2 on SETUP FAIL.
# Env: REPO (this checkout; default the repo of the current folder), PLUGIN (the plugin under
#      test; default $REPO/plugins/ada), BASE (the script before D2; default b1d68d8),
#      BASE_PLUGIN (an exported base plugins/ada, for a container), CHANGELOG (default
#      $REPO/CHANGELOG.md), KEEP=1 (keep the scratch folder).
# bash 3.2 compatible: no mapfile, no [[ =~ ]], no ${x,,}, no associative arrays.
set -u
unset ADA_BRIEF_REQUIRE
exec 3>&1
BASE=${BASE:-b1d68d8}
for tool in git python3 mktemp awk cmp diff tar sed grep wc sort tr; do
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

fails=0; passes=0; n=0
ok() { if [ "$1" = 0 ]; then echo "PASS $2"; passes=$((passes+1)); else echo "FAIL $2"; fails=$((fails+1)); fi; }
setup_fail() { echo "SETUP FAIL: $*" >&3; exit 2; }

# The stand-in gh (area-status.md's, with repo labels' colour and description). State in
# $GH_STATE/state.json; every call in log, every PR change in writes, every label creation in
# labelwrites, every call it can't answer in unsupported.
mkdir -p "$W/stub"
cat > "$W/stub/gh" <<'STUB'
#!/usr/bin/env python3
import json, os, re, subprocess, sys
from urllib.parse import unquote
D = os.environ['GH_STATE']
P = os.path.join(D, 'state.json')
st = json.load(open(P)) if os.path.exists(P) else {'prs': [], 'labels': [], 'labelinfo': {}}
st.setdefault('labelinfo', {})
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

def save(kind='writes'):
    note(kind, 'gh ' + ' '.join(a))
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

def new_label(name, color, desc, force):
    if name in st['labels'] and not force:
        sys.stderr.write('label with name "%s" already exists; use `--force` to update its color and description\n' % name)
        sys.exit(1)
    if name not in st['labels']:
        st['labels'].append(name)
    st['labelinfo'][name] = {'color': (color or '').lstrip('#'), 'description': desc or ''}
    save('labelwrites')

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
    pos, o = parse(a[2:], {'--color', '-c', '--description', '-d', '--repo', '-R'})
    if not pos:
        unsupported('label create with no name')
    new_label(pos[0], one(o, '--color', '-c'), one(o, '--description', '-d'), one(o, '--force', '-f'))
    sys.exit(0)
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
                i = st['labelinfo'].get(name, {})
                emit({'name': name, 'color': i.get('color', ''), 'description': i.get('description', '')}, o)
            sys.stderr.write('HTTP 404: Not Found\n'); sys.exit(1)
        if method == 'GET':
            emit([{'name': l} for l in st['labels']], o)
        if method == 'POST' and not name:
            nm = fields.get('name', [''])[-1]
            if nm in st['labels']:
                sys.stderr.write('HTTP 422: Validation Failed (already_exists)\n'); sys.exit(1)
            new_label(nm, fields.get('color', [''])[-1], fields.get('description', [''])[-1], True)
            emit({'name': nm}, o)
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
elif op == 'body':
    pr['body'] = x[0]
elif op == 'append':
    pr['body'] = pr['body'].rstrip('\n') + '\n\n' + x[0] + '\n'
json.dump(st, open(p, 'w'))
PY
}
PRH=add/x
label()   { gha label "$PRH" "$@"; }     # label <name> <actor> <time>
unlabel() { gha unlabel "$PRH" "$@"; }   # unlabel <name> <actor> <time>

# q <op> [<args>]: read the stand-in's state. labels (the repo's), info <label> (colour|description),
# prlabels [<pr>] (sorted, space-separated), ncomments [<pr>], comment <k> (PR 1's k-th), body [<pr>],
# snap (PR 1 as JSON, for "changed nothing"), asana [<pr>] (how many lines hold the Asana sentence).
cat > "$W/q.py" <<'PY'
import json, sys
st = json.load(open(sys.argv[1]))
op, x = sys.argv[2], sys.argv[3:]
def pr(n=None):
    return [p for p in st['prs'] if p['number'] == int(n or 1)][0]
if op == 'labels':
    for l in st['labels']:
        print(l)
elif op == 'info':
    i = st.get('labelinfo', {}).get(x[0])
    print('' if i is None else i['color'] + '|' + i['description'])
elif op == 'prlabels':
    print(' '.join(sorted(pr(x[0] if x else None)['labels'])))
elif op == 'ncomments':
    print(len(pr(x[0] if x else None)['comments']))
elif op == 'comment':
    sys.stdout.write(pr()['comments'][int(x[0]) - 1]['body'] + '\n')
elif op == 'body':
    sys.stdout.write(pr(x[0] if x else None)['body'].replace('\r\n', '\n') + '\n')
elif op == 'snap':
    print(json.dumps(pr(), sort_keys=True))
elif op == 'asana':
    print(sum(1 for l in pr(x[0] if x else None)['body'].split('\n')
              if 'To see the specific tasks where the Asana app for GitHub is being used' in l))
PY
q() { python3 "$W/q.py" "$GH_STATE/state.json" "$@"; }

T_STD=$(printf '| Wording and content | content | @tester |\n| Design | design | @tester |\n| System behaviour (catch-all) | system | @other |')
T_MATE=$(printf '| Wording and content | content | @tester, @mate |\n| Design | design | @tester |\n| System behaviour (catch-all) | system | @other |')
T_DNONE=$(printf '| Wording and content | content | @tester |\n| Design | design | none |\n| System behaviour (catch-all) | system | @other |')
T_APPR=$(printf '| Wording and content | content | @tester |\n| Design | design | @tester |\n| System behaviour (catch-all) | system | Approvers |')
URL1=https://github.com/o/r/pull/1
L_CONTENT='> - `approved:content` (Wording and content): @tester'
L_DESIGN='> - `approved:design` (Design): @tester'
ASANA_SENT='To see the specific tasks where the Asana app for GitHub is being used'
ASANA=$(printf -- '---\n- %s, see below:\n  - https://app.asana.com/0/0/1220000000000001' "$ASANA_SENT")

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
# with a bare origin and its own stand-in state (no labels yet), PROJECT.md committed on main, on
# branch add/x.
newcase() {
  CASE=$1; n=$((n+1)); CW="$W/case $n"; R="$CW/r"
  HL=''; DEC=''; ROWS=''; BR=add/x; PRH=add/x; REQ=''
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
  : > "$GH_STATE/writes"; : > "$GH_STATE/labelwrites"
  ( cd "$R" || exit 99
    PATH="$W/stub:$PATH"; GH_USER=$u; export PATH GH_USER
    if [ -n "$REQ" ]; then ADA_BRIEF_REQUIRE=1; export ADA_BRIEF_REQUIRE; fi
    exec "$BASH_BIN" "$AB" "$@" ) > "$W/o" 2> "$W/e"
  echo $? > "$W/c"
}
show() { echo "      exit $(cat "$W/c"); stdout:"; sed 's/^/        | /' "$W/o"; echo "      stderr:"; sed 's/^/        | /' "$W/e"; }
# sub [<opener>] [<task> <base>]: the first submit; it must open a PR.
sub() { abr "${1:-tester}" submit "${2:-T}" "${3:-main}"
  case "$(sed -n 1p "$W/o")" in
    "opened   https://github.com/o/r/pull/"*) ;;
    *) show >&3; setup_fail "$CASE: submit did not open a PR" ;;
  esac
  [ -f "$R/.claude/briefs/${2:-T}.md" ] || setup_fail "$CASE: no .claude/briefs/${2:-T}.md after submit"
}
# resub: write the brief and submit it again as tester (the PR's opener).
resub() { brief; abr tester submit T main; }
st() { abr tester status "$@"; }
# both: "Both labels on" (DoD): approved:content at 11:00 and approved:design at 11:05, by tester.
both() { label approved:content tester 2026-01-01T11:00:00Z; label approved:design tester 2026-01-01T11:05:00Z; }

# Checks on the last run's output.
line() { sed -n "${1}p" "$W/o"; }
L() { local got; got=$(line "$1")
  if [ "$got" = "$2" ]; then ok 0 "$CASE: line $1 is '$2'"; else ok 1 "$CASE: line $1 is '$2'"; show; fi; }
OUT() { if [ "$(cat "$W/o")" = "$1" ] && [ "$(cat "$W/c")" = 0 ]; then ok 0 "$CASE: submit prints exactly '$1', exit 0"; else ok 1 "$CASE: submit prints exactly '$1', exit 0"; show; fi; }
HAS() { if grep -Fxq -- "$1" "$W/o"; then ok 0 "$CASE: has the line '$1'"; else ok 1 "$CASE: has the line '$1'"; show; fi; }
# Checks on the stand-in's state.
RL()  { if q labels | grep -Fxq -- "$1"; then ok 0 "$CASE: the repo has the label $1"; else ok 1 "$CASE: the repo has the label $1"; q labels | sed 's/^/      repo label: /'; fi; }
NRL() { if q labels | grep -Fxq -- "$1"; then ok 1 "$CASE: the repo has no label $1"; q labels | sed 's/^/      repo label: /'; else ok 0 "$CASE: the repo has no label $1"; fi; }
INFO() { local got; got=$(q info "$1")
  if [ "$got" = "$2" ]; then ok 0 "$CASE: $1 is '$2'"; else ok 1 "$CASE: $1 is '$2'"; echo "      got: '$got'"; fi; }
# PRL <labels, sorted, space-separated> [<pr>]: the PR's labels, exactly.
PRL() { local got; got=$(q prlabels ${2:-})
  if [ "$got" = "$1" ]; then ok 0 "$CASE: PR ${2:-1} labels are '$1'"; else ok 1 "$CASE: PR ${2:-1} labels are '$1'"; echo "      got: '$got'"; fi; }
NCOM() { local got; got=$(q ncomments)
  if [ "$got" = "$1" ]; then ok 0 "$CASE: $1 PR comment(s)"; else ok 1 "$CASE: $1 PR comment(s)"; echo "      got: $got"; fi; }
# COM <k> <text>: comment k contains <text>; COMNO: it doesn't.
COM()   { if q comment "$1" | grep -Fq -- "$2"; then ok 0 "$CASE: comment $1 has '$2'"; else ok 1 "$CASE: comment $1 has '$2'"; q comment "$1" | sed 's/^/      | /'; fi; }
COMNO() { if q comment "$1" | grep -Fq -- "$2"; then ok 1 "$CASE: comment $1 has no '$2'"; q comment "$1" | sed 's/^/      | /'; else ok 0 "$CASE: comment $1 has no '$2'"; fi; }
# BODY <line> [<pr>]: the PR body has exactly that line; BODYNO <text>: no line contains <text>.
BODY()   { if q body ${2:-} | grep -Fxq -- "$1"; then ok 0 "$CASE: PR ${2:-1} body has the line '$1'"; else ok 1 "$CASE: PR ${2:-1} body has the line '$1'"; q body ${2:-} | grep '^>' | sed 's/^/      | /'; fi; }
BODYNO() { if q body | grep -Fq -- "$1"; then ok 1 "$CASE: PR body has no '$1'"; q body | grep -F -- "$1" | sed 's/^/      | /'; else ok 0 "$CASE: PR body has no '$1'"; fi; }
# BODYPS <prefix> <suffix>: exactly one body line starts <prefix> and it ends <suffix>.
BODYPS() { local got cnt
  got=$(q body | awk -v p="$1" 'index($0, p) == 1'); cnt=$(printf '%s' "$got" | grep -c '^' | tr -d ' ')
  case "$got" in "$1"*"$2") r=0 ;; *) r=1 ;; esac
  [ "$cnt" = 1 ] || r=1
  if [ "$r" = 0 ]; then ok 0 "$CASE: one body line '$1…$2'"; else ok 1 "$CASE: one body line '$1…$2'"; echo "      got: '$got'"; fi; }
# NOTE1: the note's first line (the body's first '> ' line) speaks of labels, not brief-approved, and
# comes before the per-area line <line>.
NOTE1() { local first i j
  first=$(q body | awk 'index($0, "> ") == 1 { print; exit }')
  i=$(q body | awk 'index($0, "> ") == 1 { print NR; exit }'); j=$(q body | awk -v p="$1" '$0 == p { print NR; exit }')
  case "$first" in *brief-approved*) r=1 ;; *label*) r=0 ;; *) r=1 ;; esac
  if [ "$r" = 0 ] && [ -n "$j" ] && [ "$i" -lt "$j" ]; then ok 0 "$CASE: the note's first line names labels, not brief-approved, before the area lines"
  else ok 1 "$CASE: the note's first line names labels, not brief-approved, before the area lines"; echo "      first: '$first' (line $i; area line at '${j:-none}')"; fi; }
# NOPRW: the last run changed nothing on the PR (labels created in the repo don't count).
NOPRW() { if [ ! -s "$GH_STATE/writes" ]; then ok 0 "$CASE: nothing changed on the PR"; else ok 1 "$CASE: nothing changed on the PR"; sed 's/^/      /' "$GH_STATE/writes"; fi; }
SNAPSAME() { if [ "$(q snap)" = "$1" ]; then ok 0 "$CASE: PR body, labels, events and comments as before"; else ok 1 "$CASE: PR body, labels, events and comments as before"; fi; }
NOGOOD="0E8A16|Approves the brief's"

# 1. Static: bash -n; the usage header's submit text; the CHANGELOG bullet (DoD 11, 12, 13).
echo "--- 1. static"
CASE=static
if "$BASH_BIN" -n "$NP/bin/ada-brief" 2> "$W/e"; then ok 0 "bash -n ada-brief"; else ok 1 "bash -n ada-brief"; cat "$W/e"; fi
awk 'NR == 1 { next } !/^#/ { exit } { print }' "$NP/bin/ada-brief" > "$W/header"
awk '/^#   ada-brief submit/ { s = 1 } /^#   ada-brief status/ { s = 0 } s' "$W/header" > "$W/hsubmit"
[ -s "$W/hsubmit" ] || setup_fail "static: no '#   ada-brief submit' section in the usage header"
for s in 'approved:<slug>' '(approval reset: '; do
  if grep -qF -- "$s" "$W/hsubmit"; then ok 0 "usage header, submit: names '$s'"; else ok 1 "usage header, submit: names '$s'"; fi
done
CASE=changelog
if [ -f "$CL" ]; then
  awk '/^## Unreleased/ { u = 1; next } u && /^## / { exit } u' "$CL" > "$W/unrel"
  awk '/\*\*Brief approval by area\.\*\*/ { b = 1 } b && /^$/ { exit } b && /^- / && !/Brief approval by area/ { exit } b' "$W/unrel" > "$W/bullet"
  if [ -s "$W/bullet" ]; then ok 0 "CHANGELOG: **Brief approval by area.** under Unreleased"; else ok 1 "CHANGELOG: **Brief approval by area.** under Unreleased"; fi
  for s in submit creat reset; do
    if grep -qi -- "$s" "$W/bullet"; then ok 0 "CHANGELOG: the bullet says '$s…' (labels created on submit, only the touched areas reset)"
    else ok 1 "CHANGELOG: the bullet says '$s…' (labels created on submit, only the touched areas reset)"; fi
  done
else
  ok 1 "CHANGELOG: no file at $CL"
fi

# 2. DoD 2: first submit of Areas: content, design, Author: @other. Labels created (not brief-approved),
#    with Decisions' colour and description; none added to the PR; the body lists them.
newcase "2a first submit, Areas: content, design" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; abr tester submit T main
OUT "opened   $URL1"
RL approved:content; RL approved:design; NRL approved:system; NRL brief-approved
INFO approved:content "0E8A16|Approves the brief's Wording and content area"
INFO approved:design "0E8A16|Approves the brief's Design area"
PRL ''
BODY "$L_CONTENT"; BODY "$L_DESIGN"; BODYNO '`approved:system`'; BODYNO 'Trusted developers can add'
NOTE1 "$L_CONTENT"

newcase "2b two approvers for content" @neil none "$T_MATE"
HL=$(printf 'Areas: content\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub
BODY '> - `approved:content` (Wording and content): @tester, @mate'; RL approved:content

# 3. DoD 3: Author: @<me>, opened by <me>: content is exempt (Unit D1's rule); system is listed.
newcase "3a Areas: content, system, Author: @tester, submitted by tester" @neil none "$T_STD"
HL=$(printf 'Areas: content, system\nAuthor: @tester'); brief; at 2026-01-01T10:00:00Z; sub tester
BODY '> - Wording and content: not needed (@tester approves it)'
BODYPS '> - `approved:system` (' '): @other'; BODYNO '`approved:content`'; RL approved:system

newcase "3b the same with ADA_BRIEF_REQUIRE=1 (Unit D1 exempts no one)" @neil none "$T_STD"
HL=$(printf 'Areas: content, system\nAuthor: @tester'); brief; at 2026-01-01T10:00:00Z; REQ=1; sub tester; REQ=''
BODY "$L_CONTENT"; BODYNO 'not needed (@tester approves it)'

newcase "3c Author: @tester, submitted by opener (the opener isn't the Author)" @neil none "$T_STD"
HL=$(printf 'Areas: content, system\nAuthor: @tester'); brief; at 2026-01-01T10:00:00Z; sub opener
BODY "$L_CONTENT"; BODYNO 'not needed (@tester approves it)'

# 4. DoD 4: Trusted isn't empty.
newcase "4 Trusted: @tester" @neil @tester "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub
BODY '> Trusted developers can add any of these labels: @tester'; BODY "$L_CONTENT"

# 5. DoD 5: both labels on, amendment row needed: content.
newcase "5a needed: content" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub; both
at 2026-01-01T12:00:00Z; DEC=', for content'
ROWS='| 2026-01-01 | Decision one → Decision one, for content | the content changed | needed: content | Neil |'; resub
OUT "updated  $URL1 (approval reset: content)"
PRL 'approved:design'; NCOM 1; COM 1 'approved:content'; COM 1 'needed: content'; COMNO 1 'approved:design'
BODY '- Decision one, for content.'
st; HAS 'area design: approved @tester 2026-01-01T11:05:00Z'; HAS 'area content: waiting (@tester); Author: @other is not the PR'"'"'s opener @tester, so it isn'"'"'t exempt'

newcase "5b needed: design, content: both reset, named in Areas: order" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub; both
at 2026-01-01T12:00:00Z; DEC=', for both'
ROWS='| 2026-01-01 | Decision one → Decision one, for both | both changed | needed: design, content | Neil |'; resub
OUT "updated  $URL1 (approval reset: content, design)"
PRL ''; NCOM 1; COM 1 'approved:content'; COM 1 'approved:design'; COM 1 'needed: design, content'

# 6. DoD 6: both labels on, a Decision changed with no row: the catch-all is touched.
newcase "6 a Decision changed with no row" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub; both
NRL approved:system
at 2026-01-01T12:00:00Z; DEC=' (changed)'; resub
OUT "updated  $URL1 (approval reset: system)"
PRL 'approved:content approved:design'; RL approved:system
NCOM 1; COM 1 'approved:system'; COM 1 '@other'; COM 1 'approved section changed with no amendment recorded'
COMNO 1 'approved:content'; COMNO 1 'approved:design'
st; HAS 'area system: waiting (@other)'

# 7. DoD 7: both labels on, a not needed row: kept, one comment; again: nothing changes.
newcase "7 a not needed row, then the same again" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub; both
at 2026-01-01T12:00:00Z; DEC=' (clearer)'
ROWS='| 2026-01-01 | Decision one → Decision one (clearer) | clearer wording | not needed | Neil |'; resub
OUT "updated  $URL1 (approval kept)"
PRL 'approved:content approved:design'; NCOM 1; COM 1 'clearer wording'; BODY '- Decision one (clearer).'
snap=$(q snap)
at 2026-01-01T12:10:00Z; resub
OUT "updated  $URL1 (approval kept)"; NOPRW; SNAPSAME "$snap"; NCOM 1

# 8. DoD 8: no label counted before the submit: plain (approval reset).
newcase "8a no label on, the same brief again" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub
at 2026-01-01T11:00:00Z; resub
OUT "updated  $URL1 (approval reset)"

newcase "8b no label on, a Decision changed with no row (the catch-all touched, still plain)" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub
at 2026-01-01T12:00:00Z; DEC=' (changed)'; resub
OUT "updated  $URL1 (approval reset)"
NCOM 0

newcase "8c only a label that doesn't count (approved:design by someone)" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub
label approved:design someone 2026-01-01T11:00:00Z
at 2026-01-01T12:00:00Z; DEC=' (clearer)'
ROWS='| 2026-01-01 | Decision one → Decision one (clearer) | clearer wording | not needed | Neil |'; resub
OUT "updated  $URL1 (approval reset)"
PRL 'approved:design'

# 9. Decisions, Output: the other ways an area needs approval with no counting label afterwards.
newcase "9a an area added to Areas: (with a not needed row)" @neil none "$T_STD"
HL=$(printf 'Areas: content\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub
label approved:content tester 2026-01-01T11:00:00Z
NRL approved:design
at 2026-01-01T12:00:00Z; HL=$(printf 'Areas: content, design\nAuthor: @other')
ROWS='| 2026-01-01 | Areas: content → content, design | it touches design too | not needed | Neil |'; resub
OUT "updated  $URL1 (approval reset: design)"
PRL 'approved:content'; RL approved:design; BODY "$L_DESIGN"; NCOM 1; COM 1 'approved:design'

newcase "9b one of two areas never approved, a not needed row" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub
label approved:content tester 2026-01-01T11:00:00Z
at 2026-01-01T12:00:00Z; DEC=' (clearer)'
ROWS='| 2026-01-01 | Decision one → Decision one (clearer) | clearer wording | not needed | Neil |'; resub
OUT "updated  $URL1 (approval reset: design)"
PRL 'approved:content'

newcase "9c an exempt area sent back has no label to keep" @neil none "$T_STD"
HL=$(printf 'Areas: content, system\nAuthor: @tester'); brief; at 2026-01-01T10:00:00Z; sub tester
label approved:system other 2026-01-01T11:00:00Z
at 2026-01-01T12:00:00Z; DEC=', for content'
ROWS='| 2026-01-01 | Decision one → Decision one, for content | the content changed | needed: content | Neil |'; resub
OUT "updated  $URL1 (approval kept)"
PRL 'approved:system'

newcase "9d a label on an exempt area only: it doesn't count and stays" @neil none "$T_STD"
HL=$(printf 'Areas: content, system\nAuthor: @tester'); brief; at 2026-01-01T10:00:00Z; sub tester
label approved:content tester 2026-01-01T11:00:00Z
at 2026-01-01T12:00:00Z; DEC=' (clearer)'
ROWS='| 2026-01-01 | Decision one → Decision one (clearer) | clearer wording | not needed | Neil |'; resub
OUT "updated  $URL1 (approval reset)"
PRL 'approved:content'

newcase "9e an area with no approvers in the body" @neil none "$T_DNONE"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub
BODY '> - Design: not needed (no approvers)'; BODY "$L_CONTENT"; BODYNO '`approved:design`'

# 10. DoD 10: the Asana block survives an area brief's resubmit (kept, then reset).
newcase "10 the Asana block" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub; both
gha append "$PRH" "$ASANA"
at 2026-01-01T12:00:00Z; DEC=' (clearer)'
ROWS='| 2026-01-01 | Decision one → Decision one (clearer) | clearer wording | not needed | Neil |'; resub
OUT "updated  $URL1 (approval kept)"
asana_end() { local got; got=$(q body | awk 'NF' | tail -3)
  if [ "$got" = "$ASANA" ] && [ "$(q asana)" = 1 ]; then ok 0 "$CASE: $1: the body ends with the Asana block, once"; else ok 1 "$CASE: $1: the body ends with the Asana block, once"; q body | tail -6 | sed 's/^/      | /'; fi; }
BODY '- Decision one (clearer).'; asana_end kept
at 2026-01-01T13:00:00Z; DEC=', for content'
ROWS=$(printf '%s\n%s' "$ROWS" '| 2026-01-01 | Decision one (clearer) → Decision one, for content | the content changed | needed: content | Neil |'); resub
OUT "updated  $URL1 (approval reset: content)"
BODY '- Decision one, for content.'; asana_end reset

# 11. DoD 9: briefs without Areas: submit's lines, the brief-approved label (and its colour and
#     description), the PR body (with an Asana block) and comments as the base's, for the 14-step
#     sequence of area-status.md, with and without a table, and the template's Areas: prompt.
rec() { echo "\$ ada-brief $*"; abr Tester "$@"; cat "$W/o" "$W/e"; echo "exit $(cat "$W/c")"; }
recstate() { echo "### state"
  python3 -c 'import json,sys
s = json.load(open(sys.argv[1]))
print("repo labels:", s["labels"], s.get("labelinfo"))
for p in s["prs"]:
    print("pr", p["number"], p["labels"], len(p["comments"]))
    for c in p["comments"]:
        print(c["body"])
    print("body:"); print(p["body"])' "$GH_STATE/state.json"; }
seq14() {
  QUIET=1
  if [ "$1" = table ]; then newcase "seq14 table" @tester none "$T_APPR"; else newcase "seq14 plain" @tester none; fi
  QUIET=''
  echo "### 1"; at 2026-01-01T10:00:00Z; brief; rec submit T main
  echo "### 2"; rec status
  echo "### 3"; label brief-approved someone 2026-01-01T10:30:00Z; rec status
  echo "### 4"; at 2026-01-01T10:40:00Z; rec submit T main; rec status
  echo "### 5"; label brief-approved Tester 2026-01-01T11:00:00Z; rec status; rec status add/x
  echo "### 6"; at 2026-01-01T11:10:00Z; rec submit T main
  echo "### 7"; gha body "$PRH" 'old body'; rec submit T main
  echo "### 7a"; gha append "$PRH" "$ASANA"
  echo "### 8"; at 2026-01-01T12:00:00Z; ROWS='| 2026-01-01 | a → b | reasons | not needed | Neil |'; DEC=' (changed)'; brief; rec submit T main; rec status
  echo "### 9"; at 2026-01-01T12:10:00Z; rec submit T main
  echo "### 10"; gha comment "$PRH" Tester 2026-01-01T12:20:00Z hello; rec status
  echo "### 11"; at 2026-01-01T13:00:00Z
  ROWS=$(printf '| 2026-01-01 | a → b | reasons | not needed | Neil |\n| 2026-01-01 | c → d | why | needed: it reverses a Decision | Neil |'); brief
  { cp "$R/.claude/handoff/BRIEF.md" "$R/.claude/briefs/T.md" && git -C "$R" add .claude/briefs/T.md \
    && git -C "$R" commit -q -m amend && git -C "$R" push -q origin HEAD; } || setup_fail "$CASE: push"
  rec status
  echo "### 12"; at 2026-01-01T13:40:00Z; rec submit T main; rec status
  echo "### 13"; label brief-approved Tester 2026-01-01T14:00:00Z; rec status
  echo "### 14"; at 2026-01-01T15:00:00Z; DEC=' (changed again)'; brief; rec submit T main; rec status
  recstate
}
prompt() {
  QUIET=1; newcase "prompt" @tester none "$T_APPR"; QUIET=''
  HL='Areas: <the areas it touches>'; brief; at 2026-01-01T10:00:00Z; rec submit T main
  label brief-approved Tester 2026-01-01T11:00:00Z; at 2026-01-01T12:00:00Z; DEC=' (changed)'; brief; rec submit T main
  recstate
}
for v in plain table prompt; do
  case $v in plain|table) f=seq14 ;; *) f=prompt ;; esac
  AB=$BP/bin/ada-brief; $f $v > "$W/rec-b-$v" 2>&1
  AB=$NP/bin/ada-brief; $f $v > "$W/rec-n-$v" 2>&1
  CASE="11 no Areas: ($v)"; echo "--- $CASE"
  if cmp -s "$W/rec-b-$v" "$W/rec-n-$v"; then ok 0 "$CASE: every line, exit code, label, body and comment as the base's"
  else ok 1 "$CASE: every line, exit code, label, body and comment as the base's"; diff "$W/rec-b-$v" "$W/rec-n-$v" | head -20 | sed 's/^/      /'; fi
  case $v in
    plain|table) want="opened   $URL1|updated  $URL1 (approval reset)|updated  $URL1 (approval kept)" ;;
    prompt) want="opened   $URL1|updated  $URL1 (approval reset)" ;;
  esac
  echo "$want" | tr '|' '\n' > "$W/want"
  while IFS= read -r s; do
    if grep -Fxq -- "$s" "$W/rec-n-$v"; then ok 0 "$CASE: has the line '$s'"; else ok 1 "$CASE: has the line '$s'"; fi
  done < "$W/want"
  if grep -q '^repo labels: .*brief-approved' "$W/rec-n-$v"; then ok 0 "$CASE: brief-approved created"; else ok 1 "$CASE: brief-approved created"; fi
  if grep -q "approved:" "$W/rec-n-$v"; then ok 1 "$CASE: no approved:<slug> label or line"; else ok 0 "$CASE: no approved:<slug> label or line"; fi
  if grep -Fq "(approval reset:" "$W/rec-n-$v"; then ok 1 "$CASE: no '(approval reset:' line"; else ok 0 "$CASE: no '(approval reset:' line"; fi
  case $v in plain|table)
    if grep -Fq "$ASANA_SENT" "$W/rec-n-$v"; then ok 0 "$CASE: the Asana block is still in the body"; else ok 1 "$CASE: the Asana block is still in the body"; fi ;;
  esac
done
AB=$NP/bin/ada-brief

# 12. Surfaces: a part with Areas: submitted into a feature whose area brief is approved.
newcase "12 a part under an approved area feature brief" @neil none "$T_STD"
HL=$(printf 'Areas: design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub
label approved:design tester 2026-01-01T11:00:00Z
git -C "$R" checkout -q -b add/p || setup_fail "$CASE: branch add/p"
BR=add/p; HL=$(printf 'Areas: content\nAuthor: @other'); brief; at 2026-01-01T11:30:00Z; abr tester submit T2 add/x
OUT "opened   https://github.com/o/r/pull/2"
RL approved:content; PRL 'approved:design' 1; PRL '' 2; BODY "$L_CONTENT" 2

# 13. Sequences: one PR through kept, repeated, reset by row, reset by the catch-all, approved again.
newcase "13 a chain of amendments on one PR" @neil none "$T_STD"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; at 2026-01-01T10:00:00Z; sub; both
at 2026-01-01T12:00:00Z; DEC=' (clearer)'
R1='| 2026-01-01 | Decision one → Decision one (clearer) | clearer wording | not needed | Neil |'
ROWS=$R1; resub; OUT "updated  $URL1 (approval kept)"; NCOM 1; PRL 'approved:content approved:design'
at 2026-01-01T12:10:00Z; resub; OUT "updated  $URL1 (approval kept)"; NCOM 1; NOPRW
at 2026-01-01T13:00:00Z; DEC=', for content'
ROWS=$(printf '%s\n%s' "$R1" '| 2026-01-01 | Decision one (clearer) → Decision one, for content | the content changed | needed: content | Neil |')
resub; OUT "updated  $URL1 (approval reset: content)"; NCOM 2; COM 2 'approved:content'; COMNO 2 'approved:design'; PRL 'approved:design'
label approved:content tester 2026-01-01T13:30:00Z
st; L 1 'approved @tester 2026-01-01T13:30:00Z'; L 2 'amended since approval; no re-approval needed'
at 2026-01-01T14:00:00Z; DEC=', for content (reworded)'
resub; OUT "updated  $URL1 (approval reset: system)"; NCOM 3; COM 3 'approved:system'; PRL 'approved:content approved:design'; RL approved:system
label approved:system other 2026-01-01T14:30:00Z
st; L 1 'approved @other 2026-01-01T14:30:00Z'; HAS 'area system: approved @other 2026-01-01T14:30:00Z'
snap=$(q snap)
at 2026-01-01T15:00:00Z; resub; OUT "updated  $URL1 (approval kept)"; NOPRW; SNAPSAME "$snap"

# 14. CRLF line ends in the brief and PROJECT.md (area 12).
newcase "14 BRIEF.md and PROJECT.md with CRLF" @neil none "$T_STD"
crlf "$R/.claude/team/PROJECT.md"
HL=$(printf 'Areas: content, design\nAuthor: @other'); brief; crlf "$R/.claude/handoff/BRIEF.md"; at 2026-01-01T10:00:00Z; sub
RL approved:content; RL approved:design; BODY "$L_CONTENT"; BODY "$L_DESIGN"

echo "$passes passed, $fails failed"
cat "$W"/case*/state/unsupported > "$W/unsup" 2>/dev/null
if [ -s "$W/unsup" ]; then echo "SETUP FAIL: the stand-in gh was asked for what it doesn't handle (extend the stand-in, not the expectations):"; sort -u "$W/unsup" | sed 's/^/  /'; exit 2; fi
[ "$fails" = 0 ] || exit 1
exit 0
```

## Expected

Every check line `PASS`; the last line `<n> passed, 0 failed` and no `SETUP FAIL`; exit 0. The
same PASS and FAIL lines under bash 3.2 and Python 3.8 (Steps 3).

Sources, all the brief (Unit D2) unless named:

- Section 1: DoD "The usage header documents the labels and `(approval reset: <slugs>)`" (in
  `submit`'s text: Build order 4), "`bash -n` clean", "CHANGELOG's bullet mentions the labels"
  with Constraints (S105: "labels created on submit, only the touched areas reset").
- Section 2: DoD 2; Decisions, Labels (`approved:<slug>`, colour `0E8A16`, description `Approves
  the brief's <Area> area`, not `brief-approved` for an area brief); `render_body()` (one line per
  area, `` > - `approved:<slug>` (<Area>): @a, @b ``; the note's first line says each area is
  approved by its own label; no Trusted line when Trusted is empty). Submit creates labels; it
  adds none to the PR (approval is a person's label, `PROJECT.md`).
- Section 3: DoD 3; `render_body()`: "`not needed (@<author> approves it)` when Unit D1 would
  exempt it", and "the opener is the `gh` login running `submit`". 3b and 3c: Unit D1 exempts no
  one under `ADA_BRIEF_REQUIRE=1`, nor when the opener isn't the `Author:` (`SYSTEM.md`,
  `status`). Whether an exempt area's label is created is not stated, so not checked.
- Section 4: DoD 4.
- Section 5: DoD 5; Decisions, On an existing PR (DELETE only the area sent back), Output
  ("in `Areas:` order", so 5b's row lists them the other way round) and The PR comment (the
  removed label and `compare`'s reason, `needed: <cell>`). The `status` lines after it are Unit
  D1's (`SYSTEM.md`): design approved through the amendment, content waiting with the opener's
  ending.
- Section 6: DoD 6 as amended (Amendments, 2026-11-20): exactly one comment, naming
  `approved:system` as now needed, its approvers (`@other`) and the reason, `compare`'s
  `approved section changed with no amendment recorded` (`SYSTEM.md`); it names no label that
  stays.
- Section 7: DoD 7; the repeat changes nothing on the PR (`SYSTEM.md`, `keep`), and is still
  `(approval kept)` since both labels count.
- Section 8: DoD 8 (8a) and Output, "When none counted: `(approval reset)`": also with the
  catch-all touched (8b) and with only a label from a login that doesn't count (8c, Unit D1's
  rule 2), which stays on the PR (Builder questions, Ada's answer 3). 8b posts no comment: with
  no label counted before nothing was compared, so the catch-all isn't touched (BRIEF.md, Builder
  questions, Ada's readings for Tess's Escalate, 2026-11-20, third bullet, the earlier reading
  withdrawn).
- Section 9: Output, "every touched area that needs approval and has no counting label
  afterwards": an area added to `Areas:` (9a, named in the brief; its label created as a touched
  area's), an area never approved (9b, by the same words; see TEST-PLAN Escalate), and an exempt
  area sent back, which needs no approval (9c; On an existing PR, "exempt areas have no label to
  keep"). 9a's one comment names `approved:design`, an area that newly needs approval (Amendments,
  2026-11-20; still one comment per submit). 9d: a label on an exempt area doesn't count for the
  output line and stays on the PR; 9e: an area with no approvers is `> - <Area>: not needed (no
  approvers)` in the body (Builder questions, Ada's answers 3 and 2).
- Section 10: DoD 10; Surfaces (the Asana block kept on a body replace), for `keep`/`comment`
  and for a reset.
- Section 11: DoD 9 and Outcome ("a brief without `Areas:` is submitted exactly as today"),
  against `b1d68d8`, with the expected lines checked too so two identical wrong records can't
  pass. An `Areas:` line holding the template's prompt is no `Areas:` (`SYSTEM.md`,
  `header_value()`).
- Section 12: Constraints, Surfaces ("a part submitted under an area feature brief"): its own
  label created, the feature PR's labels untouched.
- Section 13: `PROJECT.md` (ada-brief is a fragile area: test sequences). Each step's expectation
  follows from the rules above and Unit D1's (`SYSTEM.md`, `status`, `compare`): the catch-all
  comes back from content's compare after a Decision changes with no row of its own.
- Section 14: area 12 (CRLF).

## On GitHub

The Definition of Done in its own words, for a person. A scratch GitHub repo you own, never a
client's; `gh` logged in as you (`<me>`); `@other` any other real GitHub login (it is only named,
it never acts). `B=<this checkout>/plugins/ada/bin/ada-brief`. Add a label to PR `<n>` with
`gh api -X POST repos/{owner}/{repo}/issues/<n>/labels -f 'labels[]=approved:design'`. Wait a few
seconds between a push and a label, since times are compared to the second.

1. In the scratch repo, on `main`: `.claude/team/PROJECT.md` whose Brief approval section has
   `- **Approvers:** @other`, `- **Trusted:** none` and the table below; `.gitignore` with
   `.claude/handoff/`. Commit and push. Delete any `approved:*` labels left from an earlier run
   (`gh label list`, `gh label delete <name> --yes`).

   ```
   | Area | Slug | Approved by |
   |---|---|---|
   | Wording and content | content | @<me> |
   | Design | design | @<me> |
   | System behaviour (catch-all) | system | @other |
   ```

   For each step below: a new branch from `main` (`add/d2-<step>`), a filled-in
   `.claude/handoff/BRIEF.md` as `brief()` in the script above writes it (with that `Branch:`),
   its header lines as the step says, then `bash "$B" submit 12190000000002<step> main`.
2. DoD 2: `Areas: content, design`, `Author: @other`. `submit` prints `opened   <url>`.
   `gh label list`: `approved:content` and `approved:design`, colour `#0E8A16`, descriptions
   `Approves the brief's Wording and content area` and `Approves the brief's Design area`; no
   `approved:system`. The PR's description has the lines
   `` > - `approved:content` (Wording and content): @<me> `` and
   `` > - `approved:design` (Design): @<me> ``, and the note above them says each area is approved
   by its own label. The PR has no labels.
3. DoD 3: `Areas: content, system`, `Author: @<me>`: the description has
   `> - Wording and content: not needed (@<me> approves it)` and a line for `` `approved:system` ``
   ending `: @other`.
4. DoD 4: set `- **Trusted:** @<me>` (commit and push on the step's branch only), `Areas:
   content, design`, `Author: @other`: the description has `> Trusted developers can add any of
   these labels: @<me>`.
5. DoD 5: as step 2, then add `approved:content` and `approved:design`. Add the row `| <date> |
   <what> | <why> | needed: content | <you> |`, change a Decision, `submit` again: `updated  <url>
   (approval reset: content)`. The PR keeps `approved:design` and has lost `approved:content`;
   one new comment names `approved:content` and `needed: content`.
6. DoD 6: as step 2 with both labels, then change a Decision with no row and `submit`:
   `updated  <url> (approval reset: system)`. Both labels stay; `gh label list` now shows
   `approved:system`; exactly one new comment names `approved:system`, `@other` and the reason
   (`approved section changed with no amendment recorded`); `bash "$B" status` shows the line
   `area system: waiting (@other)`.
7. DoD 7: as step 2 with both labels, then a row with Re-approval `not needed` and a changed
   Decision, `submit`: `updated  <url> (approval kept)`, both labels stay, one comment showing the
   row. `submit` again: `updated  <url> (approval kept)`, no new comment, the PR's timeline shows
   no change.
8. DoD 8: as step 2 with no label, `submit` again: `updated  <url> (approval reset)`.
9. DoD 10: on step 7's PR, link an Asana task with the Asana app for GitHub (the description gains
   the block ending `To see the specific tasks where the Asana app for GitHub is being used…`).
   Add a `not needed` row with a changed Decision and `submit`: the block is still at the end of
   the description, once.
10. DoD 9: a brief with no `Areas:` line, Approvers set to `@<me>` (on the step's branch): run the
    steps of `approval-kept-after-amendment.md` Sequence A with this checkout's script; the lines
    are as that script expects, and `brief-approved` is the only label created.
11. Close every PR (`gh pr close <n> --delete-branch`) and delete the `approved:*` labels.

## Observe at

The script's output and exit code. A failing check prints the run's output, the PR's `>` lines,
or the state it read under it. `KEEP=1` keeps the scratch folder (named on the first line):
`case <n>/r` is a case's repo, `case <n>/state/state.json` the stand-in's PRs, repo labels (with
`labelinfo`), events and comments, `log` every `gh` call, `writes` the last run's changes to the
PR and `labelwrites` its label creations. On GitHub: `gh label list`, the PR's labels,
description and conversation, and `submit`'s output.

## Regression

Section 11 (briefs without `Areas:`, against the base: lines, `brief-approved`, body, comments,
Asana block). Also run, unedited, the other scripts in this folder (`area-status.md` above all:
Unit D1's `status` with `submit` opening every PR), and
`tests/functional/01-brief-approval-areas/submit-and-status-offline-stub-gh.md` with
`BASE=b1d68d8`.
