# `ada-worktree new`, `check` and `list`: one worktree per step, and which step a checkout holds

Step 1 of 25 Oct 2026, "One worktree per step": `new` makes a step's worktree with fresh handoff
files and the developer's local config; `check` says whether the checkpoint belongs to the branch
it sits on; `list` shows each worktree and its step. Several steps at once share no handoff file.

- Area: 15 Worktrees (`ada-worktree`); 02 Onboarding (the ignore line); 12 Platforms (bash 3.2, a
  path with a space)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219769422658693
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 28 Oct 2026 (after the rebase onto PR #39), 0 failed (107 PASS) under bash 5, bash --posix, Docker bash 3.2 and Docker Python 3.8; the three step 3 breaks fail as they should (see the PR)

## Precondition

- This checkout (`REPO`). Nothing else from it is used: the script makes its own scratch repo,
  `scratch repo` (a path with a space), with a bare `origin` beside it, and never touches this
  checkout.
- `git` (2.17 or later, for `git worktree remove`), `cmp`, `cksum`, Python 3.8+ (for
  `ada-onboard`). No `gh` is needed: section 5 runs `list` with `gh` hidden from the PATH.
- Run the checkout's scripts by path, never the `ada-*` on the PATH (the installed plugin).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run it three ways and keep each output:
   - `REPO=<this checkout> bash check.sh`
   - `REPO=<this checkout> SH="bash --posix" bash check.sh` (runs `ada-worktree` in POSIX mode)
   - under bash 3.2, as in "bash 3.2" below, and once under Python 3.8 as in "Python 3.8".
3. Break it on purpose, three times, each in its own scratch copy of `plugins/ada`, run with
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`:
   - in the copy's `bin/ada-worktree`, stop `new` copying `.claude/team/local.env` (delete it from
     whatever list or loop copies it). It must print `FAIL` for the copied `local.env`.
   - in the copy's `bin/ada-worktree`, make `check` print `ok` where it would print `other`. It
     must print `FAIL` for the `other` lines.
   - in the copy's `bin/ada-worktree`, make `new` skip its refusal when `.claude/worktrees/` isn't
     ignored. It must print `FAIL` for the section 2b lines (the refusal and "changes nothing").

```bash
#!/bin/bash
# ada-worktree new, check and list. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada),
# SH (how to run the script under test; default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
SH=${SH:-bash}
W=$(mktemp -d)
WT=$PLUGIN/bin/ada-worktree
H=.claude/handoff
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
G()   { git -c user.name=t -c user.email=t@example.invalid "$@"; }
run() { d=$1; shift; (cd "$d" && $SH "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()  { cat "$W/rc"; }
first() { head -n 1 "$W/out" | tr -d '\r'; }
last()  { tail -n 1 "$W/out" | tr -d '\r'; }
real()  { (cd "$1" 2>/dev/null && pwd -P); }
same_dir() { a=$(real "$1") && b=$(real "$2") && [ -n "$a" ] && [ "$a" = "$b" ]; }
abs()   { case "$1" in /*|[A-Za-z]:/*) return 0;; esac; return 1; }
is_wt() { git -C "$M" worktree list --porcelain | sed -n 's/^worktree //p' > "$W/wts"
          while IFS= read -r p; do same_dir "$p" "$1" && return 0; done < "$W/wts"; return 1; }
no_upstream() { git -C "$M" rev-parse -q --verify "refs/heads/$1" >/dev/null && ! git -C "$M" rev-parse -q --verify "$1@{upstream}" && ! git -C "$M" config --get "branch.$1.merge"; }
fresh() {   # every handoff template in $1, byte for byte, and no other .md
  for f in "$PLUGIN/templates/handoff/"*.md; do cmp -s "$f" "$1/$H/${f##*/}" || return 1; done
  test "$(cd "$1/$H" && ls *.md | LC_ALL=C sort)" = "$(cd "$PLUGIN/templates/handoff" && ls *.md | LC_ALL=C sort)"; }
snap() {    # worktrees, refs, local config, and every file under the main checkout but .git
  { git -C "$M" worktree list --porcelain; git -C "$M" for-each-ref --format='%(refname) %(objectname)'
    git -C "$M" config --local --list | LC_ALL=C sort
    (cd "$M" && find . -path ./.git -prune -o -print | LC_ALL=C sort)
    (cd "$M" && find . -path ./.git -prune -o -type f -print | LC_ALL=C sort | while IFS= read -r f; do cksum "$f"; done)
  } > "$1" 2>&1; }
cp_fill() { # dest branch step worktree: the plugin's CHECKPOINT template with those filled in
  mkdir -p "$(dirname "$1")"
  sed -e "s|^\(.*Branch:\).*|\1 $2|" -e "s|^\(.*Step:\).*|\1 $3|" -e "s|^\(.*Worktree:\).*|\1 $4|" \
    "$PLUGIN/templates/handoff/CHECKPOINT.md" > "$1"; }

# Setup: an onboarded scratch repo on main, pushed to a bare origin, handoff files in place.
O="$W/origin.git"; M="$W/scratch repo"
git init -q --bare "$O"
git init -q "$M" && git -C "$M" symbolic-ref HEAD refs/heads/main
printf 'scratch\n' > "$M/README.md"
(cd "$M" && bash "$PLUGIN/bin/ada-onboard" gitignore) > "$W/gi.out" 2>&1 || { echo "SETUP FAIL: ada-onboard gitignore"; cat "$W/gi.out"; exit 2; }
G -C "$M" add -A && G -C "$M" commit -q -m init
G -C "$M" remote add origin "$O" && G -C "$M" push -q origin main && G -C "$M" fetch -q origin
(cd "$M" && bash "$PLUGIN/bin/ada-handoff-init") > /dev/null 2>&1 || { echo "SETUP FAIL: ada-handoff-init"; exit 2; }
printf 'MAIN-BRIEF-MARKER\n' >> "$M/$H/BRIEF.md"
# The ignored local config files ada-onboard gitignore lists: lines under .claude/ that name a file.
CFG=$(tr -d '\r' < "$M/.gitignore" | grep -E '^\.claude/' | grep -v '/$' | grep -v '[*?[]')
echo "info: local config files from ada-onboard gitignore:" $CFG
t "setup: the ignore list names .claude/team/local.env" grep -qxF .claude/team/local.env "$M/.gitignore"
t "setup: the ignore list names .claude/settings.local.json" grep -qxF .claude/settings.local.json "$M/.gitignore"
t "setup: the ignore list names .claude/handoff/" grep -qxF .claude/handoff/ "$M/.gitignore"
t "setup: the ignore list names .claude/worktrees/" grep -qxF .claude/worktrees/ "$M/.gitignore"
for p in $CFG; do mkdir -p "$(dirname "$M/$p")"; printf 'CONFIG-MARKER %s\n' "$p" > "$M/$p"; done
t "setup: the main checkout is clean" test -z "$(git -C "$M" status --porcelain)"

# 1. new change/x main (Definition of Done 1 and 2).
run "$M" "$WT" new change/x main
X="$M/.claude/worktrees/x"
t "new change/x main: exits 0" test "$(rc)" = 0
t "new: creates .claude/worktrees/x" test -d "$X"
t "new: git lists it as a worktree" is_wt "$X"
t "new: it is on change/x" test "$(git -C "$X" symbolic-ref --short HEAD 2>/dev/null)" = change/x
t "new: change/x starts at main" test "$(git -C "$M" rev-parse change/x 2>/dev/null)" = "$(git -C "$M" rev-parse main)"
t "new: change/x has no upstream" no_upstream change/x
L=$(last)
echo "info: last line: $L"
t "new: the last line is an absolute path" abs "$L"
t "new: the last line is the worktree's path" same_dir "$L" "$X"
t "new: fresh handoff templates in x, byte for byte" fresh "$X"
t "new: x's BRIEF.md is not the main checkout's" sh -c 'test -f "$1" && ! grep -q MAIN-BRIEF-MARKER "$1"' _ "$X/$H/BRIEF.md"
t "new: x has no plan/" test ! -e "$X/$H/plan"
t "new: x has no .claude/worktrees/ of its own" test ! -e "$X/.claude/worktrees"
t "new: the main checkout's BRIEF.md is untouched" grep -q MAIN-BRIEF-MARKER "$M/$H/BRIEF.md"
for p in $CFG; do t "new: $p copied into x, byte for byte" cmp -s "$M/$p" "$X/$p"; done
t "new: git status in the main checkout shows nothing" test -z "$(git -C "$M" status --porcelain)"
# (Not `git status --ignored`: with nothing under .claude/ tracked, it lists the whole `.claude/`
# as ignored and never `.claude/worktrees/`. check-ignore asks git about the path itself.)
t "new: git ignores the new worktree's path (git check-ignore .claude/worktrees/x)" git -C "$M" check-ignore -q .claude/worktrees/x
t "new: git status in x shows nothing" test -z "$(git -C "$X" status --porcelain)"

# 2. Refusals change nothing (Definition of Done 1: "run a second time, it refuses").
snap "$W/s1"; run "$M" "$WT" new change/x main
echo "info: second run exit $(rc): $(head -n 1 "$W/err")"
t "second run: refuses with exit 2" test "$(rc)" = 2
snap "$W/s2"; t "second run: changes nothing" cmp -s "$W/s1" "$W/s2"

G -C "$M" branch change/b main
snap "$W/s1"; run "$M" "$WT" new change/b main
t "branch exists, path free: refuses with exit 2" test "$(rc)" = 2
t "branch exists: no .claude/worktrees/b" test ! -e "$M/.claude/worktrees/b"
snap "$W/s2"; t "branch exists: changes nothing" cmp -s "$W/s1" "$W/s2"

mkdir -p "$M/.claude/worktrees/p"; printf 'keep\n' > "$M/.claude/worktrees/p/KEEP"
snap "$W/s1"; run "$M" "$WT" new change/p main
t "path exists, branch free: refuses with exit 2" test "$(rc)" = 2
t "path exists: no branch change/p" not git -C "$M" rev-parse -q --verify refs/heads/change/p
t "path exists: what was there is left alone" test "$(cat "$M/.claude/worktrees/p/KEEP" 2>/dev/null)" = keep
snap "$W/s2"; t "path exists: changes nothing" cmp -s "$W/s1" "$W/s2"
rm -rf "$M/.claude/worktrees/p"

snap "$W/s1"; run "$M" "$WT" new change/q no-such-base
t "a base that doesn't exist: refuses (non-zero)" test "$(rc)" != 0
t "bad base: no branch change/q" not git -C "$M" rev-parse -q --verify refs/heads/change/q
t "bad base: no .claude/worktrees/q" test ! -e "$M/.claude/worktrees/q"
snap "$W/s2"; t "bad base: changes nothing" cmp -s "$W/s1" "$W/s2"

for args in "" "new" "new change/u" "frobnicate"; do
  snap "$W/s1"; run "$M" "$WT" $args
  t "usage [ada-worktree $args]: exit 2" test "$(rc)" = 2
  snap "$W/s2"; t "usage [ada-worktree $args]: changes nothing" cmp -s "$W/s1" "$W/s2"
done

# 2b. A repo that doesn't ignore .claude/worktrees/ (Definition of Done 14; Amendment 2026-10-28):
#     new refuses, says to run ada-onboard gitignore, and changes nothing. With the line it works.
snapr() {   # repo $1 into file $2: worktrees, refs, config, status with ignored files, every file but .git
  { git -C "$1" worktree list --porcelain; git -C "$1" for-each-ref --format='%(refname) %(objectname)'
    git -C "$1" config --local --list | LC_ALL=C sort; git -C "$1" status --porcelain --ignored
    (cd "$1" && find . -path ./.git -prune -o -print | LC_ALL=C sort)
  } > "$2" 2>&1; }
N="$W/no ignore line"
git init -q "$N" && git -C "$N" symbolic-ref HEAD refs/heads/main
printf 'scratch\n' > "$N/README.md"; printf '.claude/handoff/\n' > "$N/.gitignore"
G -C "$N" add -A && G -C "$N" commit -q -m init
t "setup n: git does not ignore .claude/worktrees/n" not git -C "$N" check-ignore -q .claude/worktrees/n
snapr "$N" "$W/n1"; run "$N" "$WT" new change/n main
echo "info: new without the ignore line: exit $(rc): $(head -n 1 "$W/err")"
t "no ignore line: new refuses with exit 2" test "$(rc)" = 2
t "no ignore line: the message says to run ada-onboard gitignore" eval 'cat "$W/err" "$W/out" | grep -q "ada-onboard gitignore"'
t "no ignore line: no branch change/n" not git -C "$N" rev-parse -q --verify refs/heads/change/n
t "no ignore line: no .claude/worktrees/n" test ! -e "$N/.claude/worktrees/n"
t "no ignore line: no .claude/worktrees at all" test ! -e "$N/.claude/worktrees"
snapr "$N" "$W/n2"; t "no ignore line: changes nothing" cmp -s "$W/n1" "$W/n2"
(cd "$N" && bash "$PLUGIN/bin/ada-onboard" gitignore) > /dev/null 2>&1
run "$N" "$WT" new change/n main
t "after ada-onboard gitignore: new change/n main exits 0" test "$(rc)" = 0
t "after ada-onboard gitignore: the worktree exists, on change/n" test "$(git -C "$N/.claude/worktrees/n" symbolic-ref --short HEAD 2>/dev/null)" = change/n
# The test is git's own (Ada, 2026-10-28: `git check-ignore -q`), not the text of .gitignore.
E="$W/exclude only"
git init -q "$E" && git -C "$E" symbolic-ref HEAD refs/heads/main
printf 'scratch\n' > "$E/README.md"; G -C "$E" add -A && G -C "$E" commit -q -m init
printf '.claude/worktrees/\n' >> "$E/.git/info/exclude"
run "$E" "$WT" new change/e main
t "ignored through .git/info/exclude only (git check-ignore passes): new exits 0" test "$(rc)" = 0

# 3. A remote-tracking base still gets no upstream; only the config files that exist are copied.
keep=.claude/team/local.env
for p in $CFG; do [ "$p" = "$keep" ] || rm -f "$M/$p"; done
run "$M" "$WT" new change/y origin/main
Y="$M/.claude/worktrees/y"
t "new change/y origin/main: exits 0" test "$(rc)" = 0
t "new from origin/main: the last line is y's path" same_dir "$(last)" "$Y"
t "new from origin/main: no upstream (git would track one by default)" no_upstream change/y
t "new from origin/main: fresh handoff templates in y" fresh "$Y"
t "only some config: $keep copied" cmp -s "$M/$keep" "$Y/$keep"
for p in $CFG; do [ "$p" = "$keep" ] || t "only some config: $p not created in y (none in main)" test ! -e "$Y/$p"; done

# 4. check (Definition of Done 3). First line: ok | none | other <branch> <path or ->.
run "$Y" "$WT" check; echo "info: check exit codes: fresh template $(rc)"
t "check, fresh worktree (template checkpoint, no branch recorded): none" test "$(first)" = none
rm -f "$Y/$H/CHECKPOINT.md"; run "$Y" "$WT" check
t "check, no CHECKPOINT.md: none" test "$(first)" = none
grep -v -e 'Branch:' -e 'Worktree:' "$PLUGIN/templates/handoff/CHECKPOINT.md" | \
  sed -e 's|^\(.*Step:\).*|\1 Step 2: Old checkpoint|' > "$Y/$H/CHECKPOINT.md"
run "$Y" "$WT" check
t "check, a checkpoint from before Branch: lines: none" test "$(first)" = none
cp_fill "$X/$H/CHECKPOINT.md" change/x "Step 3: Widget export" "$X"
run "$X" "$WT" check; echo "info: check exit code, ok: $(rc)"
t "check, x's own checkpoint in x: ok" test "$(first)" = ok
cp_fill "$M/$H/CHECKPOINT.md" main "Step 9: Main work" "$M"
run "$M" "$WT" check
t "check, the main checkout's own checkpoint on main: ok" test "$(first)" = ok
cp_fill "$Y/$H/CHECKPOINT.md" change/x "Step 3: Widget export" "$X"
run "$Y" "$WT" check; echo "info: check exit code, other: $(rc); first line: $(first)"
t "check, x's checkpoint in y: starts 'other change/x '" eval 'first | grep -q "^other change/x "'
t "check, x's checkpoint in y: the path is x's worktree" same_dir "$(first | sed 's|^other change/x ||')" "$X"
G -C "$X" checkout -q -b change/x2
run "$X" "$WT" check
t "check, x switched to another branch, change/x checked out nowhere: other change/x -" test "$(first)" = "other change/x -"
git -C "$M" worktree add -q "$W/elsewhere dir" change/x
run "$X" "$WT" check
t "check, change/x now checked out elsewhere: starts 'other change/x '" eval 'first | grep -q "^other change/x "'
t "check, change/x now checked out elsewhere: the path is that worktree" same_dir "$(first | sed 's|^other change/x ||')" "$W/elsewhere dir"
git -C "$M" worktree remove "$W/elsewhere dir" && G -C "$X" checkout -q change/x && git -C "$M" branch -q -D change/x2
run "$X" "$WT" check
t "check, x back on change/x: ok" test "$(first)" = ok

# 4b. Checkpoints written by the released plugin, where Branch: was free text (Claudia's Must Fix;
#     Decisions, Definition of Done 3 and 8). check can't read these as an existing branch, so it
#     gives its defined answer: exit 2, a message on stderr, and no ok / none / other line. begin
#     step 6 and pickup step 3 then have Ada show the developer the Branch: line (08 script).
legacy() {  # label, Branch: value: x's checkpoint with that value, checked in x (on change/x)
  cp_fill "$X/$H/CHECKPOINT.md" "$2" "Step 3: Widget export" "$X"
  run "$X" "$WT" check
  echo "info: check, Branch: $2: exit $(rc); stdout: $(head -n 1 "$W/out"); stderr: $(head -n 1 "$W/err")"
  t "legacy Branch: [$1]: exit 2" test "$(rc)" = 2
  t "legacy Branch: [$1]: stdout has no ok, none or other line" not grep -Eq '^(ok|none|other)( |$)' "$W/out"
  t "legacy Branch: [$1]: a message on stderr" test -s "$W/err"
}
legacy "a comma and a word after the name (this repo's own checkpoint)" 'change/x, pushed'
legacy "a name in backticks" '`feature/gone`'
legacy "an ellipsis in the name" 'fix/a/…123'
legacy "none yet, then more words" 'none yet; on `production`, clean'
legacy "a well-formed name that is no local branch" change/gone
cp_fill "$X/$H/CHECKPOINT.md" change/x "Step 3: Widget export" "$X"
run "$X" "$WT" check
t "legacy lines rewritten as the name alone: ok again" test "$(first)" = ok

# 5. list (Definition of Done 4), with gh hidden from the PATH: the PR field is '-'.
F=$W/nogh; mkdir -p "$F"
printf '%s\n' "$PATH" | tr ':' '\n' > "$W/pathdirs"
while IFS= read -r d; do
  [ -d "$d" ] || continue
  for f in "$d"/*; do n=${f##*/}; [ "$n" = gh ] && continue; [ -e "$F/$n" ] || [ ! -x "$f" ] || ln -s "$f" "$F/$n"; done
done < "$W/pathdirs"
cp_fill "$Y/$H/CHECKPOINT.md" change/y "Step 4: Gadget import" "$Y"
runp() { d=$1; shift; (cd "$d" && PATH="$F" $SH "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
runp "$M" "$WT" list; cp "$W/out" "$W/list.main"
echo "info: list from the main checkout:"; sed 's/^/info:   /' "$W/list.main"
n_wt=$(git -C "$M" worktree list --porcelain | grep -c '^worktree ')
t "list: exits 0" test "$(rc)" = 0
t "list: one line per worktree ($n_wt)" test "$(grep -c . "$W/list.main")" = "$n_wt"
lx=$(grep -F '/.claude/worktrees/x' "$W/list.main" | head -n 1)
ly=$(grep -F '/.claude/worktrees/y' "$W/list.main" | head -n 1)
lm=$(grep -vF '/.claude/worktrees/' "$W/list.main" | head -n 1)
has() { printf '%s\n' "$1" | grep -qF -- "$2"; }
has_path() { has "$1" "$2" || has "$1" "$(real "$2")"; }
pr_dash() { test "$(printf '%s\n' "$1" | awk '{print $NF}')" = -; }
t "list, x: its path" has_path "$lx" "$X"
t "list, x: its branch change/x" has "$lx" change/x
t "list, x: its checkpoint step" has "$lx" "Widget export"
t "list, x: PR '-' with no gh" pr_dash "$lx"
t "list, y: its path" has_path "$ly" "$Y"
t "list, y: its branch change/y" has "$ly" change/y
t "list, y: its checkpoint step" has "$ly" "Gadget import"
t "list, y: PR '-' with no gh" pr_dash "$ly"
t "list, main checkout: its path" has_path "$lm" "$M"
t "list, main checkout: its branch main" eval 'printf "%s\n" "$lm" | sed "s|$M||" | grep -qw main'
t "list, main checkout: its checkpoint step" has "$lm" "Main work"
t "list, main checkout: PR '-' with no gh" pr_dash "$lm"
runp "$X" "$WT" list
t "list from inside x: the same lines as from the main checkout" \
  sh -c 'LC_ALL=C sort "$1" > "$1.s"; LC_ALL=C sort "$2" > "$2.s"; cmp -s "$1.s" "$2.s"' _ "$W/list.main" "$W/out"

# 6. The outcome: two steps at once share no handoff file.
printf 'STEP-X-MARKER\n' >> "$X/$H/BRIEF.md"; printf 'STEP-X-BUILD\n' >> "$X/$H/BUILD-LOG.md"
t "parallel: x's brief edit is not in y's brief" not grep -q STEP-X-MARKER "$Y/$H/BRIEF.md"
t "parallel: x's brief edit is not in the main checkout's brief" not grep -q STEP-X-MARKER "$M/$H/BRIEF.md"
t "parallel: x's build log is not in y's" not grep -q STEP-X-BUILD "$Y/$H/BUILD-LOG.md"
run "$Y" "$WT" check
t "parallel: y's check still ok" test "$(first)" = ok
run "$X" "$WT" check
t "parallel: x's check still ok" test "$(first)" = ok

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2

With Docker and the `bash:3.2` image (it has no git, so the container adds it; that needs the
network). With `S` the scratch folder holding `check.sh`:

```sh
docker run --rm -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 >/dev/null && bash --version | head -1 && REPO=/s PLUGIN=/plugin bash /s/check.sh'
```

On a Mac, `REPO=<this checkout> /bin/bash check.sh` uses the system bash 3.2.

### Python 3.8

Only if `ada-worktree` has Python in it (S101). With `S` as above:

```sh
docker run --rm -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s python:3.8-slim \
  sh -c 'apt-get update -qq >/dev/null && apt-get install -y -qq git >/dev/null && python3 --version && REPO=/s PLUGIN=/plugin bash /s/check.sh'
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0, in every run; the version lines say bash 3.2 and
Python 3.8. Each break in step 3 prints `FAIL` for what it broke. (Section 4b: `check`'s defined
answer for a `Branch:` it can't read as an existing branch is exit 2 with a message, as Claudia's
Must Fix recommends. The brief says only "a defined answer"; if Bob chooses another, Ada says so
and the section changes with it.) The `info:` lines record the
exit codes of `check`, which the brief doesn't fix; put them in the Run record.

Sources: the brief's Decisions (`ada-worktree new <branch> <base>`: `.claude/worktrees/<slug>`,
the branch less its `<type>/`; no upstream; copies the ignored local config files
`ada-onboard gitignore` lists, only those that exist; runs `ada-handoff-init` there; the absolute
path on its last line; refuses if the branch or path exists. `check`: first line `ok`, `none` (no
checkpoint, or no branch recorded) or `other <branch> <worktree path or ->`. `list`: one line per
worktree with path, branch, the checkpoint's step and the PR state, `-` without `gh`) and
Definition of Done 1 to 4, 11 (a repo path with a space) and 14 (`new` refuses unless
`.claude/worktrees/` is ignored, says to run `ada-onboard gitignore`, changes nothing: Amendment
2026-10-28); `REVIEW-FEEDBACK.md` (Must Fix: `Branch:` as the released plugin wrote it). Exit 2 for a refusal or a usage error:
`SYSTEM.md`, Known gotchas ("exit code 2 is every script's usage or precondition failure").
Readings of the brief that it doesn't spell out, raised with Ada in `TEST-PLAN.md`: `other`
names the worktree the branch is checked out in, so `-` when it is checked out nowhere; the
"local config files" are the lines under `.claude/` that name a file.

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`git -C "<scratch>/scratch repo" worktree list`, `ls -la` in the worktree, and `cat` its
`.claude/handoff/CHECKPOINT.md`.

## Regression

Section 1's checks on the main checkout: its own handoff files and `git status` are untouched by
`new`. `ada-handoff-init` and `ada-onboard gitignore` are run as they are released; their own
regressions are `tests/functional/05-handoff-files/design-md-copied-kept-archived.md` and
`tests/functional/02-onboarding/gitignore-worktrees-line.md`.
