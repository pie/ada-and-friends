# The approval refactor keeps the usage, the subcommands and the file list

- Area: 01 Brief approval (`ada-brief`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198400793919
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-04, 0 failed on bash 5.2 and Docker bash 3.2 (bash -n); the break prints 2 FAIL; see the PR

Offline. Checks the parts of the Definition of Done that don't need GitHub: the two new function
signatures, the usage header and `die 'usage: …'` line unchanged, no new subcommand, `bash -n`,
the files the branch changes, and that `tests/functional/01-brief-approval/` is untouched.

## Precondition

- This checkout (`REPO`) with the change in its working tree, and the base branch
  (`add/brief-approval-by-area`, or `BASE=<ref>`) in its git.
- `git`, `python3` (3.8+), `mktemp`, `cmp`, `diff`, `tar`. No network: a stand-in `gh` that knows
  only `api user` is put first on the PATH, and every call to it is logged.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Under bash 3.2: `docker run --rm -v <this checkout>/plugins/ada:/plugin:ro bash:3.2 bash -n
   /plugin/bin/ada-brief`, exit 0 and nothing printed. (The script itself needs the worktree's git,
   which a container can't read.)
4. Break it on purpose, once: copy `plugins/ada` to a scratch folder; in the copy's
   `bin/ada-brief` add a word to the end of line 3, and change `def implicit_areas(approvers):` to
   `def implicit_areas(approvers, extra):`. Run `REPO=<this checkout> PLUGIN=<the copy> bash
   check.sh`: "usage header" and "the two defs" must print `FAIL`.

```bash
#!/bin/bash
# ada-brief refactor, offline: signatures, usage, subcommands, changed files. PASS/FAIL; exit 1 if any FAIL.
# Env: REPO (default: this repo), PLUGIN (default $REPO/plugins/ada), BASE (default add/brief-approval-by-area).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE=${BASE:-add/brief-approval-by-area}
AB=$PLUGIN/bin/ada-brief
BASH_BIN=$(command -v bash)
W=$(mktemp -d)
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
git -C "$REPO" rev-parse --verify -q "$BASE^{commit}" >/dev/null || { echo "SETUP FAIL: no $BASE in $REPO"; exit 2; }
[ -f "$AB" ] || { echo "SETUP FAIL: no $AB"; exit 2; }
mkdir -p "$W/base"
git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/base" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
OLD=$W/base/plugins/ada/bin/ada-brief
echo "info: $("$BASH_BIN" --version | head -n 1)"
cmp -s "$OLD" "$AB" && echo "info: ada-brief is byte-identical to $BASE's (nothing built yet?)"

# 1 bash -n (DoD 5)
t "bash -n ada-brief" "$BASH_BIN" -n "$AB"

# 2 the two signatures, and no other 'def approval' (DoD 3: grep -n 'def approval\|def implicit_areas')
grep -nE 'def approval|def implicit_areas' "$AB" > "$W/defs.n"
sed 's/^[0-9]*:[[:space:]]*//' "$W/defs.n" | sort > "$W/defs"
printf '%s\n' 'def approval(repo, n, area, labels):' 'def implicit_areas(approvers):' | sort > "$W/defs.want"
t "the two defs, exactly: $(tr '\n' ';' < "$W/defs")" cmp -s "$W/defs.want" "$W/defs"

# 3 usage header unchanged (DoD 4: plugins/ada/bin/ada-brief:1-96)
sed -n '1,96p' "$OLD" > "$W/h0"; sed -n '1,96p' "$AB" > "$W/h1"
t "usage header (lines 1-96) unchanged" cmp -s "$W/h0" "$W/h1"
grep -F "die 'usage:" "$OLD" | sed 's/^[[:space:]]*//' > "$W/u0"
grep -F "die 'usage:" "$AB"  | sed 's/^[[:space:]]*//' > "$W/u1"
t "the base has a die 'usage: …' line" test -s "$W/u0"
t "die 'usage: …' line(s) unchanged" cmp -s "$W/u0" "$W/u1"

# 4 no new subcommand: names a later unit adds, and other words, behave as in the base (DoD 4)
SR=$W/scratch; mkdir -p "$SR/.claude/team" "$W/bin"
git -C "$SR" init -q; git -C "$SR" config user.name tester; git -C "$SR" config user.email tester@example.invalid
printf '# Scratch\n\n## Brief approval\n\n- **Approvers:** @tester\n- **Trusted:** none\n' > "$SR/.claude/team/PROJECT.md"
git -C "$SR" add .; git -C "$SR" commit -qm init
cat > "$W/bin/gh" <<'GH'
#!/bin/sh
echo "$*" >> "$GH_LOG"
case "$*" in *"api user"*) echo tester; exit 0;; esac
echo "stand-in gh: no network" >&2; exit 1
GH
chmod +x "$W/bin/gh"
i=0
for args in '' nosuch areas area labels implicit_areas help -h --help 'who everything' 'status --area everything'; do
  i=$((i+1))
  for v in old new; do
    if [ $v = old ]; then s=$OLD; else s=$AB; fi
    # shellcheck disable=SC2086
    (cd "$SR" && PATH="$W/bin:$PATH" GH_LOG="$W/gh.$v.$i" "$BASH_BIN" "$s" $args) > "$W/$v.$i.out" 2> "$W/$v.$i.err"
    echo "rc $?" >> "$W/$v.$i.out"
    for x in out err; do sed -e "s#$s#<ada-brief>#g" -e "s#${s%/bin/ada-brief}#<plugin>#g" "$W/$v.$i.$x" > "$W/n" && mv "$W/n" "$W/$v.$i.$x"; done
  done
  t "'ada-brief $args': same stdout and exit code as $BASE" cmp -s "$W/old.$i.out" "$W/new.$i.out"
  t "'ada-brief $args': same stderr as $BASE" cmp -s "$W/old.$i.err" "$W/new.$i.err"
done

# 5 the files the branch changes (DoD 6) and the 01 tests untouched (DoD 1)
{ git -C "$REPO" diff --name-only "$BASE"; git -C "$REPO" ls-files --others --exclude-standard; } | sort -u > "$W/changed"
echo "info: changed since $BASE: $(tr '\n' ' ' < "$W/changed")"
t "plugins/ada/bin/ada-brief is changed" grep -qx 'plugins/ada/bin/ada-brief' "$W/changed"
t "nothing outside ada-brief and tests/functional/01-brief-approval-areas/ is changed" not grep -v -e '^plugins/ada/bin/ada-brief$' -e '^tests/functional/01-brief-approval-areas/' "$W/changed"
t "no CHANGELOG.md, plugin.json or marketplace.json" not grep -E '^(CHANGELOG\.md|plugins/ada/\.claude-plugin/plugin\.json|\.claude-plugin/marketplace\.json)$' "$W/changed"
t "nothing changed or added in tests/functional/01-brief-approval/" not grep '^tests/functional/01-brief-approval/' "$W/changed"
t "git diff $BASE -- tests/functional/01-brief-approval is empty" test -z "$(git -C "$REPO" diff "$BASE" -- tests/functional/01-brief-approval)"
grep '^tests/' "$W/changed" > "$W/tests" && echo "info: test files in the diff (DoD 6 as amended 2026-11-04 allows new files under tests/functional/01-brief-approval-areas/): $(tr '\n' ' ' < "$W/tests")"

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0; step 3 prints nothing and exits 0; step 4 prints
`FAIL` where it says.

Sources: the brief's Definition of Done items 1 (`git diff <base> -- tests/functional/01-brief-approval`
empty), 3 (`def approval(repo, n, area, labels):` and `def implicit_areas(approvers):`), 4 (the
usage header, lines 1-96, and the `die 'usage: …'` line unchanged; no new subcommand), 5 (`bash -n`
clean, bash 3.2) and 6 (only `plugins/ada/bin/ada-brief` and new files under
`tests/functional/01-brief-approval-areas/`, as amended by Neil on 2026-11-04; no CHANGELOG,
`plugin.json` or `marketplace.json`), and its Constraints (Out of scope: `ada-brief areas` and
`who <area>` are Unit B, so in this unit they must do what the base does). The `info:` line names
the test files in the diff.

## Observe at

The script's output and exit code. On a failure the scratch folder is kept: `defs`, `h0`/`h1`
(the old and new header), `u0`/`u1`, `old.<n>.out|err` and `new.<n>.out|err` per subcommand case,
`gh.<old|new>.<n>` the stand-in `gh`'s calls, and `changed`.

## Regression

`who`, `status` and an unknown word still answer as before without the network (section 4), so
`/ada:begin`'s first calls and `ada-onboard check`'s call to `who` see the same lines.
