# `ada-onboard settings` allows `ada-worktree check`, `list` and `main`, and nothing for `new` or `remove`

Step 1 of 25 Oct 2026, "One worktree per step", Amendment of 2026-10-25: `ada-onboard settings`
reaches every onboarded repo, so what it adds to `.claude/settings.json` is exactly the three
read-only `ada-worktree` rules (they take no arguments, so the rules are exact). `new` makes a
directory and `remove` deletes one, so they still ask.

- Area: 02 Onboarding and refresh (`ada-onboard settings`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219769422658693
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 28 Oct 2026 (after the rebase onto PR #39), 0 failed (21 PASS) under bash 5 and Docker bash 3.2; both step 3 breaks fail as they should (see the PR)

## Precondition

- This checkout (`REPO`), with `origin/next-release` fetched: the script compares the changed
  `ada-onboard settings` with the released one there.
- `git`, `tar`, `cmp`, `comm`, Python 3.8+ (`ada-onboard` and the script's own JSON checks). The
  script makes its own scratch repos, in a folder whose name has a space, and only reads this
  checkout.
- Run the checkout's script by path, never the `ada-onboard` on the PATH (the installed plugin).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, and under bash 3.2 as in "bash 3.2" below.
3. Break it on purpose, twice, each in its own scratch copy of `plugins/ada`, run with
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`:
   - delete the `ada-worktree` rules from the copy's `bin/ada-onboard`. It must print `FAIL` for
     the three rules.
   - in the copy, add `Bash(ada-worktree *)` to the allow list. It must print `FAIL` for the
     added-exactly-three, `new` and `remove` checks.

```bash
#!/bin/bash
# ada-onboard settings and the ada-worktree allow rules. PASS/FAIL per check; exit 1 if any FAIL.
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
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
G()   { git -c user.name=t -c user.email=t@example.invalid "$@"; }
mkrepo() { mkdir -p "$1" && git -C "$1" init -q && G -C "$1" commit -q --allow-empty -m init; }
st()  { d=$1; s=$2; (cd "$d" && $SH "$s" settings) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()  { cat "$W/rc"; }
allow() { python3 -c 'import json,sys
for x in json.load(open(sys.argv[1]))["permissions"]["allow"]:
    print(x)' "$1" | tr -d '\r' | LC_ALL=C sort; }
same_but_extra() {   # old.json new.json: identical once the three rules are taken out of the new allow list
  python3 -c 'import json,sys
a=json.load(open(sys.argv[1])); b=json.load(open(sys.argv[2]))
extra={"Bash(ada-worktree check)","Bash(ada-worktree list)","Bash(ada-worktree main)"}
b["permissions"]["allow"]=[x for x in b["permissions"]["allow"] if x not in extra]
sys.exit(0 if a==b else 1)' "$1" "$2"; }
kept() {             # a hand-written rule, deny rule and key survive
  python3 -c 'import json,sys
d=json.load(open(sys.argv[1]))
ok = "Bash(echo hi)" in d["permissions"]["allow"] and "Bash(rm -rf /)" in d["permissions"]["deny"] and d.get("myKey")==1
sys.exit(0 if ok else 1)' "$1"; }
printf 'Bash(ada-worktree check)\nBash(ada-worktree list)\nBash(ada-worktree main)\n' | LC_ALL=C sort > "$W/want"

# 1. A fresh repo: the released script, then the changed one.
RO="$S/released"; mkrepo "$RO"; st "$RO" "$OLD"
t "baseline: the released settings exits 0 and writes .claude/settings.json" sh -c 'test "$(cat "$1")" = 0 && test -f "$2"' _ "$W/rc" "$RO/.claude/settings.json"
allow "$RO/.claude/settings.json" > "$W/allow.old"
t "baseline: the released allow list has no ada-worktree rule" not grep -q ada-worktree "$W/allow.old"
RN="$S/changed"; mkrepo "$RN"; st "$RN" "$NEW"
echo "info: settings: exit $(rc): $(head -n 1 "$W/err")"
t "settings: exits 0" test "$(rc)" = 0
t "settings: writes .claude/settings.json" test -f "$RN/.claude/settings.json"
allow "$RN/.claude/settings.json" > "$W/allow.new"
for r in 'Bash(ada-worktree check)' 'Bash(ada-worktree list)' 'Bash(ada-worktree main)'; do
  t "allows $r (the exact rule)" grep -qxF -- "$r" "$W/allow.new"
done
t "allows nothing for ada-worktree new" not grep -q 'ada-worktree new' "$W/allow.new"
t "allows nothing for ada-worktree remove" not grep -q 'ada-worktree remove' "$W/allow.new"
t "no wildcard rule for ada-worktree (ada-worktree * or ada-worktree*)" not grep -Eq 'ada-worktree( )?\*' "$W/allow.new"
t "every ada-worktree rule is one of the three" sh -c 'grep ada-worktree "$1" | LC_ALL=C sort | cmp -s - "$2"' _ "$W/allow.new" "$W/want"
LC_ALL=C comm -13 "$W/allow.old" "$W/allow.new" > "$W/added"
LC_ALL=C comm -23 "$W/allow.old" "$W/allow.new" > "$W/lost"
t "the allow list gains exactly those three rules" cmp -s "$W/added" "$W/want"
t "the allow list loses no rule" test ! -s "$W/lost"
t "everything else in settings.json is as released (deny, marketplace, attribution, cache TTL)" same_but_extra "$RO/.claude/settings.json" "$RN/.claude/settings.json"

# 2. A second run changes nothing.
cp "$RN/.claude/settings.json" "$W/after1"
st "$RN" "$NEW"
t "second run: exits 0" test "$(rc)" = 0
t "second run: settings.json unchanged, byte for byte" cmp -s "$W/after1" "$RN/.claude/settings.json"
t "second run: each rule still there once" sh -c 'test "$(grep -c "^Bash(ada-worktree " "$1")" = 3' _ "$W/allow.new"
allow "$RN/.claude/settings.json" > "$W/allow.new2"
t "second run: the allow list has no duplicates" test -z "$(uniq -d "$W/allow.new2")"

# 3. A project that already has its own settings: its rules and keys survive.
RK="$S/has settings"; mkrepo "$RK"; mkdir -p "$RK/.claude"
printf '{"permissions": {"allow": ["Bash(echo hi)"], "deny": ["Bash(rm -rf /)"]}, "myKey": 1}\n' > "$RK/.claude/settings.json"
st "$RK" "$NEW"
t "existing settings: exits 0" test "$(rc)" = 0
t "existing settings: its own allow rule, deny rule and key survive" kept "$RK/.claude/settings.json"
allow "$RK/.claude/settings.json" > "$W/allow.k"
t "existing settings: the three rules are added" sh -c 'grep ada-worktree "$1" | LC_ALL=C sort | cmp -s - "$2"' _ "$W/allow.k" "$W/want"

echo "$fails failed"
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

On a Mac, `REPO=<this checkout> /bin/bash check.sh` uses the system bash 3.2.

## Expected

Every line `PASS`, last line `0 failed`, exit 0, in both runs; the bash version line in the Docker
run says 3.2. Step 3's two broken copies print `FAIL` for what they broke.

Sources: the brief's Amendments, 2026-10-25 ("`ada-onboard` scope: ... `settings` allows
`ada-worktree check`, `list` and `main` (exact rules: they take no arguments); `new` and `remove`
still prompt") and 2026-10-27 (the Definition of Done item); Definition of Done 13; `API.md`
(`settings` merges plugin, marketplace, permissions, attribution off and `promptCacheTtl` into
`.claude/settings.json`, appending rules it doesn't have). The rule text `Bash(ada-worktree
check)` follows the form of the rules already there (`Bash(ada-onboard refresh)`).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`cat "<scratch>/with space/changed/.claude/settings.json"`.

## Regression

Section 1's last check: everything in `settings.json` apart from the three rules is as the
released script writes it. Section 3: a project's own rules and keys survive the merge.
