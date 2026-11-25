# `ada-brief status` after the areas table changes: a dropped slug, and back to one area

Unit H of the plan "Brief approval by area" (the README's "Changing the areas", as amended 24 and 25
Nov 2026): on an open PR whose brief has an `Areas:` line, `ada-brief status` stops with exit 2 and
`ada-brief: the brief names area <slug>, which PROJECT.md doesn't list` (nothing on stdout) when
the brief names a slug the table doesn't list, whatever the Approvers line says; and when the table
is deleted (back to one area) a brief with an `Areas:` line stops the same way unless `Approvers:
none`, which prints `not-needed`. The README's wording is tested in
`tests/functional/08-session-flow/starter-note.md` (Part A, section 4); this script runs the real
`ada-brief status` to show the claims hold.

- Area: 01 Brief approval (`ada-brief status`); 12 Platforms (bash 3.2, Python 3.8)
- Asana: https://app.asana.com/0/0/1220198295391965
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 25 Nov 2026, 17 passed, 0 failed under bash 5.3, bash 3.2.57 (Docker) and Python 3.8.20 (Docker) (see the PR)

## Precondition

- This checkout (`REPO`), with the change in its working tree. The script runs this checkout's
  `ada-brief` by path, never the one on the PATH.
- `git`, `python3` (3.8+), `mktemp`, `bash`. No network and no GitHub login: a stand-in `gh` is put
  first on the PATH. It plays one open PR (#1 in `o/r`, opened by `tester`, no labels, no reviews,
  no comments) whose body names `.claude/handoff/BRIEF.md`, and fails anything else it is asked.
- Each case is a scratch git repo (in a folder whose name has a space) with a local bare `origin`,
  branch `add/x` pushed with its upstream set, and a `PROJECT.md` and `BRIEF.md` as the case says.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`. A few seconds.
3. Under bash 3.2 and Python 3.8, as `check-areas-table.md` does (`bash:3.2`, `python:3.8-slim`
   with `PLUGIN=<plugins/ada mounted in the container>`; the script needs `git` and `python3`).
4. Break it on purpose: copy `plugins/ada` to a scratch folder, change the `unlisted()` message in
   the copy's `bin/ada-brief` (for example `sed 's/which PROJECT.md doesn/which PROJECT.md does/'`),
   and run with `PLUGIN=<the copy>`: the message checks of 1a, 1b, 1d and 3a print `FAIL`.

```bash
#!/bin/bash
# ada-brief status on an open PR whose brief has an Areas: line, when the project's areas table was
# changed (a slug dropped; the table deleted). Real ada-brief, stand-in gh. PASS/FAIL per check;
# exit 1 if any FAIL, 2 if set-up fails.
# Env: REPO (this checkout, default: this repo), PLUGIN (default $REPO/plugins/ada).
# bash 3.2 compatible: no mapfile, no [[ =~ ]], no ${x,,}.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
B=$PLUGIN/bin/ada-brief
for tool in git python3 mktemp; do command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }; done
[ -f "$B" ] || { echo "SETUP FAIL: no $B"; exit 2; }
T=$(mktemp -d) || exit 2
[ "${KEEP:-}" = 1 ] || trap 'rm -rf "$T"' EXIT
W="$T/status scratch"; mkdir "$W" || exit 2
fails=0; passes=0
ok() { if [ "$1" = 0 ]; then echo "PASS $2"; passes=$((passes+1)); else echo "FAIL $2"; fails=$((fails+1)); fi; }
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$tn"; else ok 1 "$tn"; fi; }
echo "info: $(bash --version | head -1); $(python3 --version 2>&1)"

# The stand-in gh: one open PR (#1) in o/r, opened by tester, no labels, no reviews, no comments.
# Its body names the brief the way submit does. Anything it doesn't know fails, so nothing reaches GitHub.
mkdir "$W/stub"
cat > "$W/stub/gh" <<'STUB'
#!/usr/bin/env python3
import json, sys
a = sys.argv[1:]
URL = 'https://github.com/o/r/pull/1'
if a[:2] == ['auth', 'status']: sys.exit(0)
if a[:2] == ['api', 'user']: print('tester'); sys.exit(0)
if a[:2] == ['repo', 'view']: print('o/r'); sys.exit(0)
if a[:2] == ['pr', 'view']:
    body = 'Brief\n\n<!-- ada-brief: .claude/handoff/BRIEF.md -->\n'
    if '-q' in a:
        print(1 if a[a.index('-q') + 1] == '.number' else body); sys.exit(0)
    print(json.dumps({'number': 1, 'url': URL, 'body': body, 'labels': [], 'author': {'login': 'tester'}})); sys.exit(0)
if a[0] == 'api' and len(a) == 2 and a[1].split('?')[0] in (
        'repos/o/r/pulls/1/reviews', 'repos/o/r/pulls/1/comments',
        'repos/o/r/issues/1/comments', 'repos/o/r/issues/1/events'):
    print('[]'); sys.exit(0)
sys.stderr.write('stand-in gh: not stubbed: ' + ' '.join(a) + '\n'); sys.exit(1)
STUB
chmod +x "$W/stub/gh"
PATH="$W/stub:$PATH"; export PATH
unset ADA_BRIEF_REQUIRE
export GIT_CONFIG_NOSYSTEM=1 HOME="$W"
git config --global user.name t; git config --global user.email t@example.com; git config --global init.defaultBranch main

TABLE='| Area | Slug | Approved by |
|---|---|---|
| Wording and content | content | @client |
| System behaviour (catch-all) | system | Approvers |'

# mk <name> <approvers> <table 0|1> <areas line or empty>: a scratch repo on branch add/x, pushed
# with its upstream set, whose PROJECT.md and BRIEF.md are as given. st runs status in it.
mk() {
  R="$W/$1"; git init -q --bare "$R.git"; git init -q "$R"; cd "$R" || exit 2
  git remote add origin "$R.git"; mkdir -p .claude/team .claude/handoff
  { printf '%s\n' '# P' '' '## Brief approval' '' 'Whose briefs need approving.' '' "- **Approvers:** $2" '- **Trusted:** none'
    if [ "$3" = 1 ]; then printf '\n%s\n' "$TABLE"; fi; } > .claude/team/PROJECT.md
  { printf '%s\n' '# Brief: T' '' 'Branch: add/x'; if [ -n "$4" ]; then printf '%s\n' "$4"; fi
    printf '%s\n' '' '## Outcome' '' 'It works.' '' '## Amendments' '' '| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |' '|---|---|---|---|---|' '| | | | | |'; } > .claude/handoff/BRIEF.md
  git add .; git commit -qm init; git checkout -qb add/x; git push -q -u origin add/x 2>/dev/null
}
# st: ada-brief status in the current scratch repo: stdout in $W/out, stderr in $W/err, exit in $rc
st() { bash "$B" status > "$W/out" 2> "$W/err"; rc=$?; echo "info: exit $rc; stdout: $(tr '\n' '|' < "$W/out"); stderr: $(tr '\n' '|' < "$W/err")"; }
first() { head -1 "$W/out"; }

echo "== 1. Table kept, the brief names a slug the table doesn't list (price)"
mk a1 none 1 'Areas: price'; st
t "1a Approvers: none: exit 2" test "$rc" = 2
t "1a Approvers: none: stdout empty" test ! -s "$W/out"
t "1a Approvers: none: says the brief names area price, which PROJECT.md doesn't list" grep -qxF "ada-brief: the brief names area price, which PROJECT.md doesn't list" "$W/err"
mk a2 @tester 1 'Areas: price'; st
t "1b Approvers: @tester: exit 2 and stdout empty" test "$rc" = 2 -a ! -s "$W/out"
t "1b Approvers: @tester: the same message" grep -qxF "ada-brief: the brief names area price, which PROJECT.md doesn't list" "$W/err"
mk a3 none 1 'Areas: content'; st
t "1c control, Approvers: none, a listed slug: exit 0 and waiting (the harness reaches the area path)" test "$rc" = 0 -a "$(first)" = waiting
mk a4 none 1 'Areas: content, price'; st
t "1d a listed and an unlisted slug: exit 2 and stdout empty" test "$rc" = 2 -a ! -s "$W/out"
t "1d message names price" grep -qxF "ada-brief: the brief names area price, which PROJECT.md doesn't list" "$W/err"

echo "== 2. Table deleted (back to one area), Approvers: none"
mk b1 none 0 'Areas: content'; st
t "2a brief with an Areas: line: exit 0" test "$rc" = 0
t "2a stdout is exactly not-needed" test "$(cat "$W/out")" = not-needed
t "2a nothing on stderr" test ! -s "$W/err"
mk b2 none 0 ''; st
t "2b brief with no Areas: line: exit 0, not-needed" test "$rc" = 0 -a "$(cat "$W/out")" = not-needed

echo "== 3. Table deleted, with approvers"
mk c1 @tester 0 'Areas: content'; st
t "3a brief with an Areas: line: exit 2" test "$rc" = 2
t "3a stdout empty" test ! -s "$W/out"
t "3a says the brief names area content, which PROJECT.md doesn't list" grep -qxF "ada-brief: the brief names area content, which PROJECT.md doesn't list" "$W/err"
mk c2 @tester 0 ''; st
t "3b brief with no Areas: line (unaffected): exit 0 and waiting" test "$rc" = 0 -a "$(first)" = waiting
# The README's way out: remove the Areas: line from the brief, push, and status works again.
mk c3 @tester 0 'Areas: content'
grep -v '^Areas:' .claude/handoff/BRIEF.md > "$W/b.tmp"; cp "$W/b.tmp" .claude/handoff/BRIEF.md
git commit -qam 'drop Areas:'; git push -q 2>/dev/null; st
t "3c after the Areas: line is removed and pushed: exit 0 and waiting" test "$rc" = 0 -a "$(first)" = waiting

echo "$passes passed, $fails failed"
[ "$fails" = 0 ] || exit 1
exit 0
```

## Expected

Every line `PASS`, last line `17 passed, 0 failed`, exit 0.

| Case | `Approvers:` | Areas table | Brief's `Areas:` | `status` prints | Exit |
|---|---|---|---|---|---|
| 1a | none | kept | `price` (not in the table) | nothing on stdout; stderr `ada-brief: the brief names area price, which PROJECT.md doesn't list` | 2 |
| 1b | `@tester` | kept | `price` | the same | 2 |
| 1c (control) | none | kept | `content` (listed) | `waiting`, `area content: waiting (@client)`, the PR URL | 0 |
| 1d | none | kept | `content, price` | the 1a message (about `price`) | 2 |
| 2a | none | deleted | `content` | `not-needed` and nothing else | 0 |
| 2b | none | deleted | no line | `not-needed` | 0 |
| 3a | `@tester` | deleted | `content` | nothing on stdout; stderr `ada-brief: the brief names area content, which PROJECT.md doesn't list` | 2 |
| 3b | `@tester` | deleted | no line | `waiting` (a brief with no `Areas:` line is unaffected) | 0 |
| 3c | `@tester` | deleted | line removed, pushed | `waiting` (the README's way out) | 0 |

Sources: the README's "Changing the areas" (Renamed or dropped slugs; Back to one area); the
brief's Amendments rows of 24 and 25 Nov 2026; `ada-brief`'s usage header for `status` (`Approvers:
none` and no areas table print `not-needed`; a slug the project doesn't list stops with exit 2).
Expected values come from those, not from the Python.

## Observe at

The script's output: each case prints an `info:` line with the exit status, stdout and stderr, then
its PASS/FAIL lines.

## Regression

The control cases (1c, 3b, 3c) show the stand-in reaches the area path and the one-area path, so an
`exit 2` or a `not-needed` is `status`'s answer, not a harness failure.
