# `ada-onboard settings` allows git branch, git reflog and gh api user only in read-only forms, and takes the old wildcard rules out

Found by Copilot's review of an onboarding PR (pie/aok-theme#34, 28 Oct 2026): `Bash(git branch*)`
allowed `git branch -D` and `-f`, and `Bash(git reflog*)` allowed `reflog delete` and `expire`,
with no prompt. `Bash(sed -n *)` (allows `sed -n -i`) and `Bash(gh api user*)` (allows writes to
`/user`) had the same problem. `ada-onboard settings` reaches every onboarded repo and only ever
added rules, so it now also removes these four where it finds them.

- Area: 02 Onboarding and refresh (`ada-onboard settings`); 12 Platforms (bash 3.2)
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 28 Oct 2026, 0 failed (17 PASS) under bash 5.2 and Docker bash 3.2.57; step 3's break fails 3 checks as it should

## Precondition

- This checkout (`REPO`), with `origin/main` fetched: the script runs the released
  `ada-onboard settings` from there to make an old project's settings.
- `git` 2.22 or later (for `--show-current`), `tar`, `cmp`, Python 3.8+. The script makes its own
  scratch repos, in a folder whose name has a space, and only reads this checkout.
- Run the checkout's script by path, never the `ada-onboard` on the PATH (the installed plugin).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, and under bash 3.2 as in the `ada-worktree` check
   (`settings-allows-worktree-readonly.md`, "bash 3.2"), with `BASE` exported the same way.
3. Break it on purpose in a scratch copy of `plugins/ada`: put `'Bash(git branch*)'` back in the
   copy's allow list and run with `PLUGIN=<the copy>`. It must print `FAIL` for the fresh-repo
   "none of the four" and "no other" checks and the upgrade "are gone" check.

```bash
#!/bin/bash
# ada-onboard settings: the git branch, git reflog and gh api user rules are read-only, sed is
# gone, and the old wildcard rules are removed from an existing project. PASS/FAIL per check.
# Env: REPO (this checkout), BASE (default origin/main), PLUGIN (plugin under test, default
# $REPO/plugins/ada), OLD_PLUGIN (released plugin; default: exported from BASE).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/main}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
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
st()  { (cd "$1" && bash "$2" settings) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()  { cat "$W/rc"; }
allow() { python3 -c 'import json,sys
for x in json.load(open(sys.argv[1]))["permissions"]["allow"]:
    print(x)' "$1" | tr -d '\r'; }
RETIRED='Bash(git branch*)
Bash(git reflog*)
Bash(sed -n *)
Bash(gh api user*)'
WANT='Bash(git branch)
Bash(git branch --show-current)
Bash(git branch -a)
Bash(git branch -r)
Bash(git branch -vv)
Bash(git branch -avv)
Bash(git branch --list*)
Bash(git branch --contains *)
Bash(git branch --merged*)
Bash(git branch --no-merged*)
Bash(git reflog)
Bash(git reflog -*)
Bash(git reflog show*)
Bash(gh api user)
Bash(gh api user -q .login)
Bash(gh api user --jq .login)'
has_all()  { echo "$WANT" | while IFS= read -r r; do grep -qxF -- "$r" "$1" || exit 1; done; }
has_none() { echo "$RETIRED" | while IFS= read -r r; do if grep -qxF -- "$r" "$1"; then exit 1; fi; done; }
same_rest() {  # old.json new.json: identical once the retired and wanted rules are taken out
  RET="$RETIRED" WAN="$WANT" python3 -c 'import json,os,sys
drop=set(os.environ["RET"].split("\n"))|set(os.environ["WAN"].split("\n"))
a=json.load(open(sys.argv[1])); b=json.load(open(sys.argv[2]))
for d in (a,b): d["permissions"]["allow"]=[x for x in d["permissions"]["allow"] if x not in drop]
sys.exit(0 if a==b else 1)' "$1" "$2"; }

# 1. A fresh repo.
RN="$S/fresh"; mkrepo "$RN"; st "$RN" "$NEW"
echo "info: settings: exit $(rc): $(head -n 1 "$W/err")"
t "fresh: exits 0" test "$(rc)" = 0
allow "$RN/.claude/settings.json" > "$W/a.fresh"
t "fresh: every read-only rule is there" has_all "$W/a.fresh"
t "fresh: none of the four wildcard rules" has_none "$W/a.fresh"
t "fresh: no sed rule at all" not grep -q '^Bash(sed' "$W/a.fresh"
t "fresh: no other git branch, git reflog or gh api user rule" sh -c 'grep -E "^Bash\((git branch|git reflog|gh api user)" "$1" | while IFS= read -r r; do echo "$2" | grep -qxF -- "$r" || exit 1; done' _ "$W/a.fresh" "$WANT"

# 2. A project onboarded with the released script, with a rule and key of its own: upgrade.
RU="$S/upgrade"; mkrepo "$RU"; mkdir -p "$RU/.claude"
printf '{"permissions": {"allow": ["Bash(echo hi)"], "deny": ["Bash(rm -rf /)"]}, "myKey": 1}\n' > "$RU/.claude/settings.json"
st "$RU" "$OLD"
t "baseline: the released settings exits 0" test "$(rc)" = 0
allow "$RU/.claude/settings.json" > "$W/a.old"
t "baseline: the released settings has all four wildcard rules" sh -c 'echo "$2" | while IFS= read -r r; do grep -qxF -- "$r" "$1" || exit 1; done' _ "$W/a.old" "$RETIRED"
cp "$RU/.claude/settings.json" "$W/old.json"
st "$RU" "$NEW"
t "upgrade: exits 0" test "$(rc)" = 0
allow "$RU/.claude/settings.json" > "$W/a.up"
t "upgrade: the four wildcard rules are gone" has_none "$W/a.up"
t "upgrade: every read-only rule is added" has_all "$W/a.up"
t "upgrade: the project's own allow rule, deny rule and key survive" python3 -c 'import json,sys
d=json.load(open(sys.argv[1]))
sys.exit(0 if "Bash(echo hi)" in d["permissions"]["allow"] and "Bash(rm -rf /)" in d["permissions"]["deny"] and d.get("myKey")==1 else 1)' "$RU/.claude/settings.json"
t "upgrade: everything else is as the released script left it" same_rest "$W/old.json" "$RU/.claude/settings.json"

# 3. A second run changes nothing.
cp "$RU/.claude/settings.json" "$W/after1"
st "$RU" "$NEW"
t "second run: exits 0" test "$(rc)" = 0
t "second run: settings.json unchanged, byte for byte" cmp -s "$W/after1" "$RU/.claude/settings.json"

# 4. No command an allowed rule matches deletes, moves or renames a branch, or deletes or expires
# the reflog. Each line is a command a rule allows (a wildcard rule followed by destructive flags).
GR="$S/git"; mkrepo "$GR"; G -C "$GR" commit -q --allow-empty -m second
for b in d1 d2 d3 d4 d5 d6 d7 d8 d9; do git -C "$GR" branch "$b" HEAD~1; done
snap() { git -C "$GR" for-each-ref --format='%(refname) %(objectname) %(upstream)' refs/heads; git -C "$GR" reflog | wc -l; }
snap > "$W/before"
while IFS= read -r cmd; do
  (cd "$GR" && eval "$cmd") >/dev/null 2>&1 < /dev/null
done <<'CMDS'
git branch --list -D d1
git branch --list -d d1
git branch --list -m d2 x2
git branch --list -c d3 x3
git branch --list -f d4 HEAD
git branch --list --set-upstream-to=HEAD d5
git branch --contains HEAD~1 -D d1
git branch --contains HEAD~1 -f d6 HEAD
git branch --merged HEAD -D d7
git branch --merged -D d7
git branch --no-merged HEAD -D d8
git branch --no-merged -D d8
git reflog -5 delete HEAD@{0}
git reflog -n 1 expire --expire=now --all
git reflog show --expire=now --all
CMDS
snap > "$W/after"
t "no allowed rule deletes, moves or renames a branch, or touches the reflog" cmp -s "$W/before" "$W/after"
# And the reason -v, -a and -r have exact rules: git refuses nothing after them.
(cd "$GR" && git branch -v -D d9) >/dev/null 2>&1
t "control: git branch -v -D does delete (so no -v* rule)" not git -C "$GR" rev-parse -q --verify refs/heads/d9

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0. Step 3's broken copy prints `FAIL` for what it
broke.

Sources: Copilot's review comments on pie/aok-theme#34 (`settings.json:34`, `:38`); git's own
refusal to combine `--list`, `--contains`, `--merged` and `--no-merged` with `-D`, `-m`, `-c` and
`--set-upstream-to` (checked by section 4, not assumed).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`<scratch>/with space/upgrade/.claude/settings.json`.

## Regression

Section 2: a project's own rules and keys survive, and nothing else the released script wrote
changes. Section 3: a second run is a no-op.
