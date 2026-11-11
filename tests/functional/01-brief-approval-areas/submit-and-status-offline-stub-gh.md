# submit and status do exactly what the base branch's script does, offline (stand-in `gh`)

The offline twin of `submit-and-status-same-as-base.md`: the same refactor claim (Unit A changes no
printed line, exit code, PR body, PR comment, label or `gh` call), checked with no network, no
GitHub login and no scratch repo. A stateful stand-in `gh` plays one PR; the harness adopted from
Bob's build (it ran it against the base and this branch, both before and after the refactor).

- Area: 01 Brief approval (`ada-brief submit`, `status`; the label); 12 Platforms (bash 3.2,
  Python 3.8)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198400793919
- Automate as: none (a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-11, 0 failed (66 PASS) with BASE=febfd7b on bash 5.3 and Docker bash 3.2; see the PR

It runs one 14-step sequence of `submit` and `status` twice, once with the base branch's
`ada-brief` and once with this checkout's (or `PLUGIN`), each on a fresh scratch git repo with a
local bare `origin`, and:

- compares the two records step by step (every printed line and exit code, then the stand-in's
  final state: its labels and PR comments, then every `gh` call made, in order); and
- checks the lines the brief's Definition of Done and `SYSTEM.md` give, on this checkout's run:
  `opened   <url>`, `waiting`, `approved @<approver> <time>`, `updated  <url> (approval kept)` and
  one comment, `updated  <url> (approval reset)` and the label deleted, the notes, and the URL as
  the last line of every `status`. So a harness that stopped working shows up as a failure of
  these checks, not as two identical wrong records.

The offline checks cannot reach what the real GitHub API returns; the GitHub script does. This one
is what runs under bash 3.2 and Python 3.8 in a container, where the GitHub one needs a token.

## Precondition

- This checkout (`REPO`) with the change in its working tree, and the base branch
  (`add/brief-approval-by-area`, or `BASE=<ref>`) in its git. In a container a worktree's git is not
  readable: export the base on the host and pass `BASE_PLUGIN` (Steps, 3).
- `git`, `python3` (3.8+), `mktemp`, `awk`, `diff`, `tar`. No network. The stand-in `gh` is put
  first on the PATH; the script never runs the `ada-brief` on the PATH, only the two it is given.
- The scratch repo's `PROJECT.md` says `Approvers: @tester`. The stand-in answers `api user` /
  `repo view` as `Tester` of `o/r`; its PR is `https://github.com/o/r/pull/1`. Labels and comments
  are added by the harness as the state file, with fixed dates, so the order of approval is the
  sequence, not the clock.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`. About a second.
3. Under bash 3.2 and Python 3.8 (Docker). Export the base first, since a container can't read a
   worktree's git (the first command needs `git` on the host, run in this checkout; `S` is the
   scratch folder holding `check.sh`):

   ```sh
   git archive -o "$S/base.tar" add/brief-approval-by-area plugins/ada
   mkdir -p "$S/base" && tar -x -C "$S/base" -f "$S/base.tar"
   docker run --rm -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 >/dev/null && PLUGIN=/plugin BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   docker run --rm -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s python:3.8-slim \
     sh -c 'apt-get -qq update >/dev/null && apt-get -qq install -y git >/dev/null && PLUGIN=/plugin BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   ```

   The `info:` lines at the top say `bash 3.2` and `Python 3.8`; the PASS/FAIL lines are the same as
   under the host's bash. (The `python:3.8-slim` image runs the script under its own bash, 5.x; the
   Python is what that run adds.)
4. Break it on purpose, twice. Copy `plugins/ada` to a scratch folder, change the copy's
   `bin/ada-brief`, and run with `PLUGIN=<the copy>`:
   - `sed 's/approval kept/approval  kept/' <copy>/bin/ada-brief > x && mv x <copy>/bin/ada-brief`
     (then `chmod +x`): the `(approval kept)` checks of steps 6 to 9 and their `same as base`
     lines print `FAIL`; the reset checks and the rest still print `PASS`.
   - On a fresh copy, `sed 's/brief-approved/brief-approvex/g'` the same way: the `gh` calls and
     the label checks (`same as base: gh`, `calls: label deleted three times`, step 5's approved
     line at least) print `FAIL`.

```bash
#!/bin/bash
# ada-brief submit/status, offline: the base branch's script and this checkout's, one 14-step
# sequence each on a scratch repo with a stand-in gh, compared step by step.
# PASS/FAIL; exit 1 if any FAIL, 2 on SETUP FAIL.
# Env: REPO (default: this repo), PLUGIN (default $REPO/plugins/ada), BASE (default
#      add/brief-approval-by-area), BASE_PLUGIN (an exported base plugins/ada; default: git archive
#      of BASE from REPO), KEEP=1 (keep the scratch folder).
# bash 3.2 compatible: no mapfile, no [[ =~ ]], no ${x,,}.
set -u
BASE=${BASE:-add/brief-approval-by-area}
for tool in git python3 mktemp awk diff tar; do
  command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }
done
W=$(mktemp -d)
[ "${KEEP:-}" = 1 ] || trap 'rm -rf "$W"' EXIT
BASH_BIN=$(command -v bash)
echo "info: $("$BASH_BIN" --version | head -1)"
echo "info: $(python3 --version 2>&1)"

if [ -n "${BASE_PLUGIN:-}" ]; then
  BP=$BASE_PLUGIN
else
  REPO=${REPO:-$(git rev-parse --show-toplevel)}
  mkdir -p "$W/base"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/base" || { echo "SETUP FAIL: no $BASE in $REPO"; exit 2; }
  BP=$W/base/plugins/ada
fi
if [ -n "${PLUGIN:-}" ]; then
  NP=$PLUGIN
else
  REPO=${REPO:-$(git rev-parse --show-toplevel)}
  NP=$REPO/plugins/ada
fi
[ -f "$BP/bin/ada-brief" ] || { echo "SETUP FAIL: no base script at $BP/bin/ada-brief"; exit 2; }
[ -f "$NP/bin/ada-brief" ] || { echo "SETUP FAIL: no script under test at $NP/bin/ada-brief"; exit 2; }
cmp -s "$BP/bin/ada-brief" "$NP/bin/ada-brief" && echo "info: the script under test is identical to the base (the comparison proves nothing)"

fails=0
ok() { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }

# The stand-in gh: one PR (#1) in o/r. State in $GH_STATE/state.json, calls in $GH_STATE/log.
mkdir -p "$W/stub"
cat > "$W/stub/gh" <<'STUB'
#!/usr/bin/env python3
import json, os, sys
D = os.environ['GH_STATE']
P = os.path.join(D, 'state.json')
st = json.load(open(P)) if os.path.exists(P) else {'pr': None, 'labels': [], 'events': [], 'comments': [], 'reviews': []}
a = sys.argv[1:]

def log(line):
    open(os.path.join(D, 'log'), 'a').write(line + '\n')

def save():
    json.dump(st, open(P, 'w'))

def body_arg(v):
    path = v.split('@', 1)[1]
    return open(path).read()

shown = []
for x in a:
    if x.startswith('body=@'):
        shown.append('body=@FILE[' + str(len(body_arg(x))) + ']')
    elif x.startswith('/') and os.path.exists(x):
        shown.append('FILE[' + str(len(open(x).read())) + ']')
    else:
        shown.append(x)
log('gh ' + ' '.join(shown))
USER = os.environ.get('GH_USER', 'Tester')
URL = 'https://github.com/o/r/pull/1'

if a[:2] == ['auth', 'status']:
    sys.exit(0)
if a[:2] == ['api', 'user']:
    print(USER); sys.exit(0)
if a[:2] == ['repo', 'view']:
    print('o/r'); sys.exit(0)
if a[:2] == ['label', 'create']:
    sys.exit(0)
if a[:2] == ['pr', 'create']:
    st['pr'] = {'head': a[a.index('--head') + 1], 'body': open(a[a.index('--body-file') + 1]).read()}
    save(); print(URL); sys.exit(0)
if a[:2] == ['pr', 'view']:
    rest = a[2:]
    branch = rest[0] if rest and not rest[0].startswith('-') else None
    if st['pr'] is None or (branch is not None and branch != st['pr']['head']):
        sys.stderr.write('no pull requests found\n'); sys.exit(1)
    if '-q' in rest:
        q = rest[rest.index('-q') + 1]
        if q == '.number':
            print(1)
        elif q == '.body':
            print(st['pr']['body'])
        else:
            sys.exit('stub: pr view -q ' + q)
        sys.exit(0)
    print(json.dumps({'number': 1, 'url': URL, 'body': st['pr']['body'], 'labels': [{'name': l} for l in st['labels']]}))
    sys.exit(0)
if a[:3] == ['api', '-X', 'PATCH']:
    st['pr']['body'] = body_arg(a[a.index('-F') + 1]); save(); print(URL); sys.exit(0)
if a[:3] == ['api', '-X', 'POST']:
    st['comments'].append({'user': {'login': USER}, 'created_at': os.environ['NOW'], 'body': body_arg(a[a.index('-F') + 1])})
    save(); sys.exit(0)
if a[:3] == ['api', '-X', 'DELETE']:
    name = a[3].rsplit('/', 1)[1]
    if name not in st['labels']:
        sys.exit(1)
    st['labels'].remove(name); save(); sys.exit(0)
if a[0] == 'api' and len(a) == 2:
    path = a[1].split('?')[0]
    out = {
        'repos/o/r/pulls/1': {'number': 1, 'html_url': URL, 'body': st['pr']['body'] if st['pr'] else None, 'labels': [{'name': l} for l in st['labels']]},
        'repos/o/r/issues/1/events': st['events'],
        'repos/o/r/pulls/1/reviews': st['reviews'],
        'repos/o/r/pulls/1/comments': [],
        'repos/o/r/issues/1/comments': st['comments'],
    }
    if path not in out:
        sys.exit('stub: api ' + path)
    print(json.dumps(out[path])); sys.exit(0)
sys.exit('stub: unknown gh ' + ' '.join(a))
STUB
chmod +x "$W/stub/gh"

# The sequence: seq.sh <plugin dir> <work dir>. Prints a record: "### <step>" headers, "$ ada-brief
# <args>", the script's stdout and stderr together, "exit <n>"; then the stand-in's state and calls.
cat > "$W/seq.sh" <<'SEQ'
#!/bin/bash
set -u
PLUGIN=$1; WD=$2; H=$3
rm -rf "$WD"; mkdir -p "$WD/state"
export GH_STATE="$WD/state" PATH="$H/stub:$PATH" GIT_CONFIG_NOSYSTEM=1 HOME="$WD"
git config --global user.name T; git config --global user.email t@example.com
git config --global init.defaultBranch main
git init -q --bare "$WD/origin.git"
git init -q "$WD/r"; cd "$WD/r"
git remote add origin "$WD/origin.git"
mkdir -p .claude/team .claude/handoff
printf '# P\n\n## Brief approval\n\n- **Approvers:** @tester\n- **Trusted:** none\n' > .claude/team/PROJECT.md
git add .; GIT_COMMITTER_DATE=2026-01-01T09:00:00Z GIT_AUTHOR_DATE=2026-01-01T09:00:00Z git commit -qm init; git push -q origin main
git checkout -qb add/x
brief() {
cat > .claude/handoff/BRIEF.md <<EOF
# Brief — T: Test thing

Branch: add/x

## Outcome

It works.

## Decisions

- Decision one${DEC:-}.

## Constraints

- Platforms: any.
- Out of scope: other things.

## Definition of done

- [ ] It works.

## Amendments

| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |
|---|---|---|---|---|
${ROWS:-| | | | | |}
EOF
}
B="$PLUGIN/bin/ada-brief"
at() { export GIT_COMMITTER_DATE=$1 GIT_AUTHOR_DATE=$1 NOW=$1; }
step() { echo "### $*"; }
run() { echo "\$ ada-brief $*"; "$B" "$@" 2>&1; echo "exit $?"; }
admin() { python3 - "$GH_STATE/state.json" "$@" <<'PY'
import json, sys
p, op = sys.argv[1], sys.argv[2]; st = json.load(open(p))
if op == 'label':
    name, who, when = sys.argv[3:6]
    if name not in st['labels']: st['labels'].append(name)
    st['events'].append({'event': 'labeled', 'label': {'name': name}, 'actor': {'login': who}, 'created_at': when})
elif op == 'body':
    st['pr']['body'] = sys.argv[3]
elif op == 'comment':
    st['comments'].append({'user': {'login': sys.argv[3]}, 'created_at': sys.argv[4], 'body': sys.argv[5]})
json.dump(st, open(p, 'w'))
PY
}

step 1 submit new; at 2026-01-01T10:00:00Z; brief; run submit T main
step 2 status waiting; run status
step 3 label by non-approver; admin label brief-approved someone 2026-01-01T10:30:00Z; run status
step 4 submit with non-approver label: reset; at 2026-01-01T10:40:00Z; run submit T main; run status
step 5 label by approver after since; admin label brief-approved Tester 2026-01-01T11:00:00Z; run status; run status add/x
step 6 resubmit unchanged: keep; at 2026-01-01T11:10:00Z; run submit T main
step 7 body differs: patch; admin body 'old body'; run submit T main
step 8 not-needed amendment: comment; at 2026-01-01T12:00:00Z
ROWS='| 2026-01-01 | a → b | reasons | not needed | Neil |'; DEC=' (changed)'; brief; run submit T main; run status
step 9 repeat submit: keep; at 2026-01-01T12:10:00Z; run submit T main
step 10 approver comment after since: still approved; admin comment Tester 2026-01-01T12:20:00Z 'hello'; run status
step 11 pushed needed amendment without submit
at 2026-01-01T13:00:00Z
ROWS='| 2026-01-01 | a → b | reasons | not needed | Neil |
| 2026-01-01 | c → d | why | needed: it reverses a Decision | Neil |'; brief
cp .claude/handoff/BRIEF.md .claude/briefs/T.md; git commit -qm amend -- .claude/briefs/T.md; git push -q
run status
admin comment Tester 2026-01-01T13:30:00Z 'please change X'; run status
step 12 submit: reset; at 2026-01-01T13:40:00Z; run submit T main; run status
step 13 approved again; admin label brief-approved Tester 2026-01-01T14:00:00Z; run status
step 14 approved section change with no row: reset; at 2026-01-01T15:00:00Z; DEC=' (changed again)'; brief; run submit T main; run status
echo "### state"; python3 -c "import json,sys; s=json.load(open(sys.argv[1])); print(s['labels']); print(len(s['comments'])); [print(c['body']) for c in s['comments']]" "$GH_STATE/state.json"
echo "### gh calls"; cat "$GH_STATE/log"
SEQ

# split <record> <dir>: one file per step, s_<key> (key: the number, "state", or "gh").
split_record() {
  mkdir -p "$2"
  awk -v d="$2" '/^### /{split($0,a," "); f=d "/s_" a[2]; next} {print > f}' "$1"
}
# cmdout <file> <k>: the k-th command's output lines in a step file; cmdexit: its exit code.
cmdout()  { awk -v k="$2" '/^\$ ada-brief/{n++; next} /^exit [0-9]+$/{next} n==k{print}' "$1"; }
cmdexit() { awk -v k="$2" '/^exit [0-9]+$/{n++; if(n==k) print $2}' "$1"; }

bash "$W/seq.sh" "$BP" "$W/wb" "$W" > "$W/rec-base" 2>&1; rb=$?
bash "$W/seq.sh" "$NP" "$W/wn" "$W" > "$W/rec-new" 2>&1; rn=$?
[ $rb = 0 ] || { echo "SETUP FAIL: the sequence did not run on the base (exit $rb)"; tail -5 "$W/rec-base"; exit 2; }
[ $rn = 0 ] || { echo "SETUP FAIL: the sequence did not run on the script under test (exit $rn)"; tail -5 "$W/rec-new"; exit 2; }
split_record "$W/rec-base" "$W/b"
split_record "$W/rec-new" "$W/n"

URL=https://github.com/o/r/pull/1
KEPT="updated  $URL (approval kept)"
RESET="updated  $URL (approval reset)"
APPR1='approved @tester 2026-01-01T11:00:00Z'
AMEND='amended since approval; no re-approval needed'
EARLIER='@tester approved an earlier version; the brief has changed since: needed: it reverses a Decision'

# is <step> <k> <n> <exact line>: line n of the k-th command's output in this checkout's record.
is() {
  got=$(cmdout "$W/n/s_$1" "$2" | sed -n "$3p")
  if [ "$got" = "$4" ]; then ok 0 "head s$1.$2: line $3 is '$4'"; else ok 1 "head s$1.$2: line $3 is '$4' (got '$got')"; fi
}
# exit0 <step> <k>
exit0() { c=$(cmdexit "$W/n/s_$1" "$2"); [ "$c" = 0 ] && ok 0 "exit s$1.$2 is 0" || ok 1 "exit s$1.$2 is 0 (got '$c')"; }
# lastis <step> <k> <exact last line>
lastis() {
  got=$(cmdout "$W/n/s_$1" "$2" | tail -1)
  if [ "$got" = "$3" ]; then ok 0 "url last s$1.$2"; else ok 1 "url last s$1.$2 (got '$got')"; fi
}
# hasline <step> <k> <exact line>
hasline() {
  if cmdout "$W/n/s_$1" "$2" | grep -Fxq -- "$3"; then ok 0 "s$1.$2 has '$3'"; else ok 1 "s$1.$2 has '$3'"; fi
}

echo "--- the lines the brief and SYSTEM.md give, on the script under test"
is 1 1 1 "opened   $URL"; exit0 1 1
is 2 1 1 waiting; lastis 2 1 "$URL"
is 3 1 1 waiting; hasline 3 1 'the brief-approved label was added by @someone, who is not an approver, so it does not count'; lastis 3 1 "$URL"
is 4 1 1 "$RESET"; is 4 2 1 waiting; lastis 4 2 "$URL"
is 5 1 1 "$APPR1"; lastis 5 1 "$URL"
is 5 2 1 "$APPR1"; lastis 5 2 "$URL"
for s in 6 7 9; do is $s 1 1 "$KEPT"; exit0 $s 1; done
is 8 1 1 "$KEPT"; exit0 8 1
is 8 2 1 "$APPR1"; is 8 2 2 "$AMEND"; lastis 8 2 "$URL"
is 10 1 1 "$APPR1"; is 10 1 2 "$AMEND"; lastis 10 1 "$URL"
is 11 1 1 waiting; is 11 1 2 "$EARLIER"; lastis 11 1 "$URL"
is 11 2 1 changes-requested; is 11 2 2 "$EARLIER"
hasline 11 2 '--- @Tester, comment, 2026-01-01T13:30:00Z'; hasline 11 2 'please change X'; lastis 11 2 "$URL"
is 12 1 1 "$RESET"
is 12 2 1 changes-requested; lastis 12 2 "$URL"
is 13 1 1 'approved @tester 2026-01-01T14:00:00Z'; lastis 13 1 "$URL"
is 14 1 1 "$RESET"; is 14 2 1 waiting; lastis 14 2 "$URL"

echo "--- the stand-in's state and calls, on the script under test"
[ "$(sed -n 1p "$W/n/s_state")" = '[]' ] && ok 0 "state: the label is gone at the end" || ok 1 "state: the label is gone at the end (got '$(sed -n 1p "$W/n/s_state")')"
[ "$(sed -n 2p "$W/n/s_state")" = 3 ] && ok 0 "state: three comments (hello, please change X, one amendment comment)" || ok 1 "state: three comments (got '$(sed -n 2p "$W/n/s_state")')"
c=$(grep -c '^\*\*Brief amended after approval; no re-approval needed\.\*\*' "$W/n/s_state")
[ "$c" = 1 ] && ok 0 "state: exactly one amendment comment" || ok 1 "state: exactly one amendment comment (got $c)"
c=$(grep -c '^gh api -X POST repos/o/r/issues/1/comments' "$W/n/s_gh")
[ "$c" = 1 ] && ok 0 "calls: one comment posted (step 8 only)" || ok 1 "calls: one comment posted (got $c)"
c=$(grep -c '^gh api -X DELETE repos/o/r/issues/1/labels/brief-approved$' "$W/n/s_gh")
[ "$c" = 3 ] && ok 0 "calls: label deleted three times (the resets of steps 4, 12, 14)" || ok 1 "calls: label deleted three times (got $c)"
subs=$(grep -c '^\$ ada-brief submit' "$W/rec-new")
c=$(grep -c '^gh label create brief-approved --color 0E8A16 --description An approver has approved the brief$' "$W/n/s_gh")
[ "$c" = "$subs" ] && ok 0 "calls: the label is created on each of the $subs submits" || ok 1 "calls: the label is created on each submit ($c creates, $subs submits)"

echo "--- the same as the base, step by step"
for f in "$W"/b/s_*; do
  k=${f##*/s_}
  if cmp -s "$f" "$W/n/s_$k"; then ok 0 "same as base: $k"; else ok 1 "same as base: $k"; diff "$f" "$W/n/s_$k" | head -10; fi
done
for f in "$W"/n/s_*; do
  k=${f##*/s_}
  [ -f "$W/b/s_$k" ] || ok 1 "same as base: $k (not in the base's record)"
done

echo
[ $fails = 0 ] && echo "0 failed" || echo "$fails failed"
[ $fails = 0 ]
