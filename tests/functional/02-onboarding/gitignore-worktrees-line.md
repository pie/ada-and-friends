# `ada-onboard gitignore` adds `.claude/worktrees/` once, and git then ignores the worktrees

Step 1 of 25 Oct 2026, "One worktree per step": `.claude/worktrees/` is ignored by git, in this
repo and in every onboarded repo through `ada-onboard gitignore`, which adds it once however
often it runs.

- Area: 02 Onboarding and refresh (`ada-onboard gitignore`, `--list`); 12 Platforms (bash 3.2, CRLF)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219769422658693
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 28 Oct 2026 (after the rebase onto PR #39), 0 failed, 2 KNOWN (47 PASS) under bash 5; Docker bash 3.2 0 failed apart from section 0, which can't run in a container (46 PASS); the step 3 break fails as it should (see the PR)

## Precondition

- This checkout (`REPO`), with `origin/next-release` fetched: the script compares the changed
  `ada-onboard gitignore` with the released one there.
- `git`, `tar`, `cmp`, Python 3.8+ (for `ada-onboard`). The script makes its own scratch repos,
  in a folder whose name has a space, and only reads this checkout.
- Run the checkout's script by path, never the `ada-onboard` on the PATH (the installed plugin).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, and under bash 3.2 as in "bash 3.2" below.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, delete `.claude/worktrees/`
   from the copy's `bin/ada-onboard` (the gitignore list), and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` lines.

```bash
#!/bin/bash
# ada-onboard gitignore and .claude/worktrees/. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO (this checkout), BASE (default origin/next-release), PLUGIN (plugin under test,
# default $REPO/plugins/ada), OLD_PLUGIN (released plugin; default: exported from BASE),
# SH (how to run the script under test; default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
SH=${SH:-bash}
W=$(mktemp -d)
mkdir -p "$W/with space"
S="$W/with space"
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -m -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
NEW=$PLUGIN/bin/ada-onboard
OLD=$OLD_PLUGIN/bin/ada-onboard
LINE=.claude/worktrees/
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
G()   { git -c user.name=t -c user.email=t@example.invalid "$@"; }
mkrepo() { mkdir -p "$1" && git -C "$1" init -q && G -C "$1" commit -q --allow-empty -m init; }
gi()  { d=$1; s=$2; (cd "$d" && $SH "$s" gitignore) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()  { cat "$W/rc"; }
count() { tr -d '\r' < "$1/.gitignore" | grep -cxF -- "$LINE"; }
said() { tr -d '\r' < "$W/out" | grep -Eq "^$1[[:space:]]+\\.claude/worktrees/\$"; }

# 0. This repo ignores .claude/worktrees/ (Definition of Done 2, "here").
t "this checkout's .gitignore has the line .claude/worktrees/" grep -qxF -- "$LINE" "$REPO/.gitignore"
t "git in this checkout ignores a file under .claude/worktrees/" git -C "$REPO" check-ignore -q .claude/worktrees/some-step/file.txt

# 1. A fresh repo: added once; a second run keeps it.
R="$S/fresh"; mkrepo "$R"
gi "$R" "$NEW"
t "fresh: exits 0" test "$(rc)" = 0
t "fresh: prints 'added' for .claude/worktrees/" said added
t "fresh: .gitignore has the line exactly once" test "$(count "$R")" = 1
cp "$R/.gitignore" "$W/after1"
gi "$R" "$NEW"
t "second run: exits 0" test "$(rc)" = 0
t "second run: prints 'kept' for .claude/worktrees/" said kept
t "second run: prints no 'added' line at all" not grep -q '^added' "$W/out"
t "second run: .gitignore unchanged, byte for byte" cmp -s "$W/after1" "$R/.gitignore"
t "second run: the line is still there once" test "$(count "$R")" = 1

# 2. Git then ignores the worktrees: git status shows nothing for them.
G -C "$R" add .gitignore && G -C "$R" commit -q -m gitignore
mkdir -p "$R/.claude/worktrees/x"; printf 'x\n' > "$R/.claude/worktrees/x/file.txt"
t "after gitignore: git status shows nothing" test -z "$(git -C "$R" status --porcelain)"
t "after gitignore: git check-ignore matches .claude/worktrees/x/file.txt" git -C "$R" check-ignore -q .claude/worktrees/x/file.txt

# 3. A project that already has the line (Surfaces): kept, still once, nothing else moves.
R="$S/has line"; mkrepo "$R"
gi "$R" "$OLD"; printf '%s\n' "$LINE" >> "$R/.gitignore"; cp "$R/.gitignore" "$W/before3"
gi "$R" "$NEW"
t "already there: exits 0" test "$(rc)" = 0
t "already there: prints 'kept' for .claude/worktrees/" said kept
t "already there: prints no 'added' line" not grep -q '^added' "$W/out"
t "already there: .gitignore unchanged, byte for byte" cmp -s "$W/before3" "$R/.gitignore"

# 4. Upgrade: a repo set up by the released script gains only the new line.
R="$S/upgrade"; mkrepo "$R"
gi "$R" "$OLD"; cp "$R/.gitignore" "$W/before4"
t "baseline: the released gitignore doesn't add .claude/worktrees/" test "$(count "$R")" = 0
gi "$R" "$NEW"
t "upgrade: exits 0" test "$(rc)" = 0
t "upgrade: exactly one 'added' line, for .claude/worktrees/" test "$(grep -c '^added' "$W/out")" = 1
t "upgrade: the 'added' line is .claude/worktrees/" said added
t "upgrade: every earlier line is kept, in order, unchanged" sh -c 'head -n "$(wc -l < "$1")" "$2" | cmp -s "$1" -' _ "$W/before4" "$R/.gitignore"
t "upgrade: one line more than before" test "$(wc -l < "$R/.gitignore")" = "$(( $(wc -l < "$W/before4") + 1 ))"

# 5 and 6. Edge cases the released script already gets wrong (raised with Ada): checked on both
#    scripts. FAIL only if the released script passes and the changed one doesn't; KNOWN if both
#    fail; PASS if the changed one passes.
known=0
both() { name=$1; shift
  if "$@" "$NEW" >/dev/null 2>&1; then ok 0 "$name"
  elif "$@" "$OLD" >/dev/null 2>&1; then ok 1 "$name (the released script passes it)"
  else echo "KNOWN $name (the released script fails it too)"; known=$((known+1)); fi; }
nonl() {     # a .gitignore with no newline at its end: its last line survives
  R=$(mktemp -d "$S/no newline.XXXXXX"); mkrepo "$R"; printf 'node_modules/' > "$R/.gitignore"
  gi "$R" "$1"; grep -qxF 'node_modules/' "$R/.gitignore"; }
crlf_once() { # a CRLF .gitignore (Windows checkouts): a second run adds no line twice
  R=$(mktemp -d "$S/crlf.XXXXXX"); mkrepo "$R"; gi "$R" "$1"
  awk '{printf "%s\r\n", $0}' "$R/.gitignore" > "$W/crlf" && cp "$W/crlf" "$R/.gitignore"
  gi "$R" "$1"; test -z "$(tr -d '\r' < "$R/.gitignore" | LC_ALL=C sort | uniq -d)"; }
both "no final newline: the last line is still a line of its own" nonl
both "CRLF .gitignore: a second run adds no line twice" crlf_once
R="$S/no newline, new"; mkrepo "$R"; printf 'node_modules/' > "$R/.gitignore"
gi "$R" "$NEW"
t "no final newline: exits 0" test "$(rc)" = 0
t "no final newline: .claude/worktrees/ is a line of its own, once" test "$(count "$R")" = 1

# 7. Regression: apart from the new line, output and file are as released.
A="$S/regress old"; B="$S/regress new"; mkrepo "$A"; mkrepo "$B"
printf '*.log\n' > "$A/.gitignore"; cp "$A/.gitignore" "$B/.gitignore"
gi "$A" "$OLD"; tr -d '\r' < "$W/out" > "$W/old.out"
gi "$B" "$NEW"; tr -d '\r' < "$W/out" | grep -vF "$LINE" > "$W/new.out"
t "regression: output matches the released one, apart from the new line" cmp -s "$W/old.out" "$W/new.out"
grep -vxF -- "$LINE" "$B/.gitignore" > "$W/new.gi"
t "regression: .gitignore matches the released one, apart from the new line" cmp -s "$A/.gitignore" "$W/new.gi"

# 8. gitignore --list prints the lines and writes nothing (Builder plan, question 6, agreed by Ada
#    on 2026-10-25); any other argument exits 2 and writes nothing (accepted by Ada, 2026-10-27).
R="$S/list"; mkrepo "$R"
(cd "$R" && $SH "$NEW" gitignore --list) > "$W/list.out" 2> "$W/list.err"; lcode=$?
echo "info: gitignore --list in a fresh repo: exit $lcode; lines: $(tr '\n' ' ' < "$W/list.out")"
t "--list: exits 0" test "$lcode" = 0
t "--list in a fresh repo: writes no .gitignore" test ! -e "$R/.gitignore"
t "--list: the repo is untouched (git status shows nothing)" test -z "$(git -C "$R" status --porcelain)"
t "--list: no 'added' or 'kept' words (it prints lines, not outcomes)" not grep -Eq '^(added|kept)' "$W/list.out"
for l in .claude/handoff/ .claude/settings.local.json .claude/team/local.env .phpunit.cache/ tools/vendor/ "$LINE"; do
  t "--list prints $l as a line of its own" sh -c 'tr -d "\r" < "$2" | grep -qxF -- "$1"' _ "$l" "$W/list.out"
done
R2="$S/list vs run"; mkrepo "$R2"; gi "$R2" "$NEW"
t "--list prints exactly the lines a plain run writes" sh -c 'tr -d "\r" < "$1" | LC_ALL=C sort > "$3"; tr -d "\r" < "$2/.gitignore" | LC_ALL=C sort > "$4"; cmp -s "$3" "$4"' _ "$W/list.out" "$R2" "$W/l.s" "$W/g.s"
printf '*.log\n' > "$R/.gitignore"; cp "$R/.gitignore" "$W/before8"
(cd "$R" && $SH "$NEW" gitignore --list) > /dev/null 2>&1
t "--list with a .gitignore: it is unchanged, byte for byte" cmp -s "$W/before8" "$R/.gitignore"
for arg in --bogus -l extra; do
  R3=$(mktemp -d "$S/arg.XXXXXX"); mkrepo "$R3"
  (cd "$R3" && $SH "$NEW" gitignore "$arg") > "$W/a.out" 2> "$W/a.err"; acode=$?
  echo "info: gitignore $arg: exit $acode: $(head -n 1 "$W/a.err")"
  t "gitignore $arg: exit 2" test "$acode" = 2
  t "gitignore $arg: writes no .gitignore" test ! -e "$R3/.gitignore"
  t "gitignore $arg: prints no added or kept line" not grep -Eq '^(added|kept)' "$W/a.out"
done

echo "$fails failed, $known known"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2

With Docker and the `bash:3.2` image (it has no git, so the container adds it; that needs the
network). With `S` the scratch folder holding `check.sh`:

```sh
mkdir -p "$S/old"
git -C <this checkout> archive origin/next-release plugins/ada | tar -x -m -C "$S/old"
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && REPO=/repo PLUGIN=/repo/plugins/ada OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

Section 0 reads `/repo/.gitignore` and asks git in `/repo`; in a worktree checkout `/repo/.git`
points outside the mount, so section 0 fails there for that reason alone. Read section 0 from the
host run. On a Mac, `REPO=<this checkout> /bin/bash check.sh` uses the system bash 3.2.

## Expected

Every line `PASS` or `KNOWN`, last line `0 failed, <n> known`, exit 0, in both runs; the bash
version line in the Docker run says 3.2. A `KNOWN` line is a fault the released script already
has (a `.gitignore` with no final newline gets its last line joined to the first added one; a
CRLF `.gitignore` gets every line added again): not this step's, raised with Ada in
`TEST-PLAN.md`. Should the step fix them, they turn to `PASS`; record which in the Run record. The broken copy in step 3 prints `FAIL` for sections 0 (if run against a
checkout whose `.gitignore` lacks the line), 1, 2 and 4. Section 8 is the `--list` flag and the
refusal of other arguments.

Sources: the brief's Decisions (`.claude/worktrees/` is ignored: in this repo's `.gitignore`, and
appended by `ada-onboard gitignore`, idempotent like the other lines) and Definition of Done 2
(`git status` shows nothing for `.claude/worktrees/`, here and in a scratch repo after
`ada-onboard gitignore`; twice adds the line once); Surfaces (a project that already has the
line); `API.md`, `ada-onboard` (`kept` / `added` per line); S100 (CRLF, Git Bash).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`cat -A "<scratch>/with space/<case>/.gitignore"` and `git -C … status`.

## Regression

Section 7: the other lines `gitignore` adds, and what it prints for them, are as released. Section
3: a `.gitignore` that already has every line is left byte for byte.
