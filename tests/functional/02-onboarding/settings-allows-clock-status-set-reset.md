# `ada-onboard settings` allows `ada-clock status`, `set` and `reset`, and nothing for `install`, `uninstall`, `wp` or `extension`

Brief of 10 Nov 2026, "Let onboarding allow the ada-clock commands Tess runs": so that Tess can
move a local site's clock without a permission prompt, `ada-onboard settings` adds exactly
`Bash(ada-clock status)`, `Bash(ada-clock set *)` and `Bash(ada-clock reset)`. `install` and
`uninstall` swap Local's php-fpm, `wp` runs any WP-CLI command (`db drop` included) and
`extension` writes outside the repo, so they still ask. `settings` reaches every onboarded repo,
so nothing else it writes may move.

- Area: 02 Onboarding and refresh (`ada-onboard settings`); 12 Platforms (bash 3.2, Python 3.8)
- Asana: https://app.asana.com/0/1206474484537567/1220047866729297
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 10 Nov 2026, 0 failed (51 PASS) under bash 5.3, Docker bash 3.2.57 and Docker Python 3.8.20; both step 3 breaks fail as they should (see the PR)

## Precondition

- This checkout (`REPO`), with `origin/next-release` fetched: the script compares the changed
  `ada-onboard settings` with the released one there.
- `git`, `tar`, `cmp`, Python 3.8+ (`ada-onboard` and the script's own JSON checks). The script
  makes its own scratch repos, in a folder whose name has a space, and only reads this checkout.
- Run the checkout's script by path, never the `ada-onboard` on the PATH (the installed plugin).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, then under bash 3.2 and Python 3.8 as in the two
   sections after it.
3. Break it on purpose, twice, each in its own scratch copy of `plugins/ada`, run with
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`:
   - delete the three `ada-clock` rules from the copy's `bin/ada-onboard`. It must print `FAIL`
     for each "allows" check, "exactly the three", and the upgrade "are added" check.
   - in the copy, add `'Bash(ada-clock *)'` to the allow list (beside
     `'Bash(ada-onboard refresh)'`). It must print `FAIL` for "exactly the three", the wildcard
     check, and "nothing allows" for `install`, `uninstall`, each `wp` command and `extension`.

```bash
#!/bin/bash
# ada-onboard settings and the ada-clock allow rules. PASS/FAIL per check; exit 1 if any FAIL.
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
st()  { (cd "$1" && $SH "$2" settings) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()  { cat "$W/rc"; }
allow() { python3 -c 'import json,sys
for x in json.load(open(sys.argv[1]))["permissions"]["allow"]:
    print(x)' "$1" | tr -d '\r'; }
clock_rules() { grep '^Bash(ada-clock' "$1" | LC_ALL=C sort; }
printf 'Bash(ada-clock reset)\nBash(ada-clock set *)\nBash(ada-clock status)\n' | LC_ALL=C sort > "$W/want"
printf 'Bash(ada-worktree check)\nBash(ada-worktree list)\nBash(ada-worktree main)\n' | LC_ALL=C sort > "$W/want.wt"

# Does any allow rule in settings.json match the command? Claude Code's Bash rule forms: `Bash`
# or `Bash(*)` (everything), `Bash(x)` (exactly x), `*` anywhere (any text), a trailing ` *`
# (also the bare command), and the legacy `Bash(x:*)` (x as a prefix). Read generously, so a
# rule that might match counts as matching.
matches() {   # settings.json command: exit 0 if some allow rule matches the command
  python3 -c 'import json,re,sys
cmd=sys.argv[2]
def m(rule):
    if rule in ("Bash", "Bash(*)"): return True
    r=re.fullmatch(r"Bash\((.*)\)", rule, re.S)
    if not r: return False
    p=r.group(1)
    if p.endswith(":*"): return cmd.startswith(p[:-2])
    if p.endswith(" *") and cmd==p[:-2]: return True
    return re.fullmatch(".*".join(re.escape(s) for s in p.split("*")), cmd, re.S) is not None
sys.exit(0 if any(m(x) for x in json.load(open(sys.argv[1]))["permissions"]["allow"]) else 1)' "$1" "$2"; }
FORBIDDEN='ada-clock install
ada-clock uninstall
ada-clock wp
ada-clock wp db drop --yes
ada-clock wp eval '"'"'echo 1;'"'"'
ada-clock extension
ada-clock status --all
ada-clock reset now
ada-clock help'
ALLOWED='ada-clock status
ada-clock set "2026-11-12 09:00"
ada-clock set 2026-11-12T09:00:00+00:00
ada-clock reset'
match_checks() {   # label settings.json (here-documents, not pipes, so fails is counted here)
  while IFS= read -r c; do
    t "$1: an allow rule matches: $c" matches "$2" "$c"
  done <<EOF
$ALLOWED
EOF
  while IFS= read -r c; do
    t "$1: nothing allows: $c" not matches "$2" "$c"
  done <<EOF
$FORBIDDEN
EOF
}
same_rest() {   # old.json new.json: identical once every ada-clock rule is taken out of both allow lists
  python3 -c 'import json,sys
a=json.load(open(sys.argv[1])); b=json.load(open(sys.argv[2]))
for d in (a,b): d["permissions"]["allow"]=[x for x in d["permissions"]["allow"] if not x.startswith("Bash(ada-clock")]
sys.exit(0 if a==b else 1)' "$1" "$2"; }
kept() {        # the hand-written allow rules, deny rule and key survive
  python3 -c 'import json,sys
d=json.load(open(sys.argv[1])); a=d["permissions"]["allow"]
ok = "Bash(echo hi)" in a and "Bash(ls)" in a and "Bash(rm -rf /)" in d["permissions"]["deny"] and d.get("myKey")==1
sys.exit(0 if ok else 1)' "$1"; }

# 1. A fresh repo: the released script, then the changed one.
RO="$S/released"; mkrepo "$RO"; st "$RO" "$OLD"
t "baseline: the released settings exits 0 and writes .claude/settings.json" sh -c 'test "$(cat "$1")" = 0 && test -f "$2"' _ "$W/rc" "$RO/.claude/settings.json"
allow "$RO/.claude/settings.json" > "$W/allow.old"
echo "info: the released ($BASE) allow list has $(grep -c '^Bash(ada-clock' "$W/allow.old") ada-clock rule(s)"
RN="$S/changed"; mkrepo "$RN"; st "$RN" "$NEW"
echo "info: settings: exit $(rc): $(head -n 1 "$W/err")"
t "fresh: exits 0" test "$(rc)" = 0
t "fresh: writes .claude/settings.json" test -f "$RN/.claude/settings.json"
allow "$RN/.claude/settings.json" > "$W/allow.new"
for r in 'Bash(ada-clock status)' 'Bash(ada-clock set *)' 'Bash(ada-clock reset)'; do
  t "fresh: allows $r (that exact text)" grep -qxF -- "$r" "$W/allow.new"
done
t "fresh: the ada-clock rules are exactly the three, each once" sh -c 'grep "^Bash(ada-clock" "$1" | LC_ALL=C sort | cmp -s - "$2"' _ "$W/allow.new" "$W/want"
t "fresh: no wildcard rule for ada-clock (ada-clock*, ada-clock *, ada-clock:*)" not grep -Eq '^Bash\(ada-clock( |:)?\*' "$W/allow.new"
match_checks "fresh" "$RN/.claude/settings.json"
t "fresh: the ada-worktree rules are still exactly check, list and main" sh -c 'grep "^Bash(ada-worktree" "$1" | LC_ALL=C sort | cmp -s - "$2"' _ "$W/allow.new" "$W/want.wt"
LC_ALL=C sort "$W/allow.old" > "$W/o.s"; LC_ALL=C sort "$W/allow.new" > "$W/n.s"
t "fresh: the allow list loses no rule the released script writes" test -z "$(LC_ALL=C comm -23 "$W/o.s" "$W/n.s")"
t "fresh: everything else in settings.json is as released (other rules and their order, deny, marketplace, attribution, cache TTL)" same_rest "$RO/.claude/settings.json" "$RN/.claude/settings.json"

# 2. A second run changes nothing.
cp "$RN/.claude/settings.json" "$W/after1"
st "$RN" "$NEW"
t "second run: exits 0" test "$(rc)" = 0
t "second run: settings.json unchanged, byte for byte" cmp -s "$W/after1" "$RN/.claude/settings.json"
allow "$RN/.claude/settings.json" > "$W/allow.new2"
t "second run: each of the three rules is there once" sh -c 'clock() { grep "^Bash(ada-clock" "$1" | LC_ALL=C sort; }; clock "$1" | cmp -s - "$2"' _ "$W/allow.new2" "$W/want"
t "second run: the allow list has no duplicates" test -z "$(LC_ALL=C sort "$W/allow.new2" | uniq -d)"

# 3. A project with its own settings.json and allow rules: they survive, and the three are added.
RK="$S/has settings"; mkrepo "$RK"; mkdir -p "$RK/.claude"
printf '{"permissions": {"allow": ["Bash(echo hi)", "Bash(ls)"], "deny": ["Bash(rm -rf /)"]}, "myKey": 1}\n' > "$RK/.claude/settings.json"
st "$RK" "$NEW"
t "existing settings: exits 0" test "$(rc)" = 0
t "existing settings: its own allow rules, deny rule and key survive" kept "$RK/.claude/settings.json"
allow "$RK/.claude/settings.json" > "$W/allow.k"
t "existing settings: the three rules are added, and no other ada-clock rule" sh -c 'grep "^Bash(ada-clock" "$1" | LC_ALL=C sort | cmp -s - "$2"' _ "$W/allow.k" "$W/want"
match_checks "existing settings" "$RK/.claude/settings.json"

# 4. A project that already allows one of the three by hand: it is not added twice.
RH="$S/has one"; mkrepo "$RH"; mkdir -p "$RH/.claude"
printf '{"permissions": {"allow": ["Bash(ada-clock status)"]}}\n' > "$RH/.claude/settings.json"
st "$RH" "$NEW"
t "one already there: exits 0" test "$(rc)" = 0
allow "$RH/.claude/settings.json" > "$W/allow.h"
t "one already there: the three rules, each once" sh -c 'grep "^Bash(ada-clock" "$1" | LC_ALL=C sort | cmp -s - "$2"' _ "$W/allow.h" "$W/want"

# 5. A project onboarded with the released script, with a rule and key of its own: the next
# settings run adds the three and moves nothing else.
RU="$S/upgrade"; mkrepo "$RU"; mkdir -p "$RU/.claude"
printf '{"permissions": {"allow": ["Bash(echo hi)", "Bash(ls)"], "deny": ["Bash(rm -rf /)"]}, "myKey": 1}\n' > "$RU/.claude/settings.json"
st "$RU" "$OLD"
t "upgrade baseline: the released settings exits 0" test "$(rc)" = 0
cp "$RU/.claude/settings.json" "$W/up.old"
st "$RU" "$NEW"
t "upgrade: exits 0" test "$(rc)" = 0
allow "$RU/.claude/settings.json" > "$W/allow.u"
t "upgrade: the three rules are added, and no other ada-clock rule" sh -c 'grep "^Bash(ada-clock" "$1" | LC_ALL=C sort | cmp -s - "$2"' _ "$W/allow.u" "$W/want"
t "upgrade: the project's own rules and key survive" kept "$RU/.claude/settings.json"
t "upgrade: everything else is as the released script left it" same_rest "$W/up.old" "$RU/.claude/settings.json"

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2

With Docker and the `bash:3.2` image (it has no git or Python, so the container adds them; that
needs the network). With `S` the scratch folder holding `check.sh`:

```sh
mkdir -p "$S/old"
git -C <this checkout> archive origin/next-release plugins/ada | tar -x -m -C "$S/old"
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && REPO=/repo PLUGIN=/repo/plugins/ada OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

On a Mac, `REPO=<this checkout> /bin/bash check.sh` uses the system bash 3.2.

### Python 3.8

With the `python:3.8-slim` image (Debian, so bash is there; git is added, which needs the
network), and `S/old` exported as above:

```sh
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
  sh -c 'apt-get -qq update >/dev/null && apt-get -qq install -y git >/dev/null && git config --global --add safe.directory "*" && python3 --version && REPO=/repo PLUGIN=/repo/plugins/ada OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0, in all three runs; the Docker runs print
`GNU bash, version 3.2…` and `Python 3.8…` first. Step 3's two broken copies print `FAIL` for
what they broke.

Sources: the brief's Outcome ("Every other `ada-clock` subcommand still asks"), Decisions (the
three rules, `status` and `reset` exact, `set *`; `install`, `uninstall`, `wp`, `extension` keep
asking; already-onboarded repos get the rules the next time `settings` runs) and Definition of
Done 1 to 5; `SYSTEM.md`, "ada-onboard: templates, settings and refresh" (`settings` appends
allow rules not already present). The rule-matching reading follows Claude Code's Bash permission
rules (exact text; `*` matches any text; the legacy `:*` prefix form), read generously so that a
rule that might match a forbidden command is counted as matching.

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`cat "<scratch>/with space/changed/.claude/settings.json"`.

## Regression

Section 1's last three checks: the `ada-worktree` rules are unchanged, no rule the released
script writes is lost, and everything apart from the `ada-clock` rules is as released. Sections 3
and 5: a project's own rules and keys survive. Section 2: a second run is a no-op.
