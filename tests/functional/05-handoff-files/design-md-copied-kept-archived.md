# `ada-handoff-init` copies DESIGN.md, keeps an edited one, and archives it with the others

- Area: 05 Handoff files (`ada-handoff-init`); 12 Platforms (bash 3.2, `bash --posix`)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 21 Oct 2026, 25 PASS, 0 failed under bash 5, bash --posix and Docker bash 3.2; broken copy fails as it should (see the PR)

## Precondition

- This checkout (`REPO`), with `origin/next-release` fetched: the script compares the changed
  `ada-handoff-init` with the released one there.
- `git`, `tar`, `cmp`, `mktemp`. Nothing else. The script makes its own scratch repos under a
  temporary folder and never touches this checkout.
- Run the checkout's script by path, never the `ada-handoff-init` on the PATH (that is the
  installed plugin).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run it three ways and keep each output:
   - `REPO=<this checkout> bash check.sh`
   - `REPO=<this checkout> SH="bash --posix" bash check.sh` (runs `ada-handoff-init` in POSIX mode)
   - under bash 3.2, as in "bash 3.2" below.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, delete
   `templates/handoff/DESIGN.md` from the copy, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` lines.

```bash
#!/bin/bash
# DESIGN.md: copied, kept, archived. Prints PASS/FAIL per check; exits 1 if any FAIL.
# Env: REPO (this checkout), BASE (default origin/next-release), PLUGIN (plugin under test,
# default $REPO/plugins/ada), OLD_PLUGIN (released plugin; default: exported from BASE),
# SH (how to run the script under test; default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
SH=${SH:-bash}
W=$(mktemp -d)
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
NEW=$PLUGIN/bin/ada-handoff-init
OLD=$OLD_PLUGIN/bin/ada-handoff-init
TPL=$PLUGIN/templates/handoff/DESIGN.md
H=.claude/handoff
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
mkrepo() { mkdir -p "$1" && git -C "$1" init -q && git -C "$1" -c user.name=t -c user.email=t@example.invalid commit -q --allow-empty -m init; }
run() { d=$1; shift; (cd "$d" && $SH "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc() { cat "$W/rc"; }

# 1. A fresh repo gets DESIGN.md, the template byte for byte.
t "the plugin has templates/handoff/DESIGN.md" test -f "$TPL"
R=$W/r1; mkrepo "$R"
run "$R" "$NEW"
t "first run exits 0" test "$(rc)" = 0
t "first run prints a created line for DESIGN.md" grep -Eq '^created[[:space:]].*DESIGN\.md$' "$W/out"
t "DESIGN.md is the template, byte for byte" cmp -s "$TPL" "$R/$H/DESIGN.md"
n_tpl=$(ls "$PLUGIN/templates/handoff" | grep -c '\.md$')
t "every handoff template is created ($n_tpl)" test "$(grep -c '^created' "$W/out")" = "$n_tpl"

# 2. A second run leaves an edited DESIGN.md alone.
printf 'EDITED-MARKER\n' >> "$R/$H/DESIGN.md"
run "$R" "$NEW"
t "second run exits 0" test "$(rc)" = 0
t "second run prints a kept line for DESIGN.md" grep -Eq '^kept[[:space:]].*DESIGN\.md$' "$W/out"
t "second run creates nothing" not grep -q '^created' "$W/out"
t "the edited DESIGN.md is left alone" grep -q EDITED-MARKER "$R/$H/DESIGN.md"

# 3. --archive moves DESIGN.md with the others and puts a fresh one in place; the plan stays.
mkdir -p "$R/$H/plan"; printf 'PLAN-MARKER\n' > "$R/$H/plan/PLAN.md"
run "$R" "$NEW" --archive step-1
t "--archive step-1 exits 0" test "$(rc)" = 0
t "the archived DESIGN.md is the edited one" grep -q EDITED-MARKER "$R/$H/archive/step-1/DESIGN.md"
t "a fresh DESIGN.md (the template) is in place" cmp -s "$TPL" "$R/$H/DESIGN.md"
t "the archive holds the same set of files as the templates" \
  test "$(cd "$R/$H/archive/step-1" && ls *.md | sort)" = "$(cd "$PLUGIN/templates/handoff" && ls *.md | sort)"
t "the plan stays where it was" grep -q PLAN-MARKER "$R/$H/plan/PLAN.md"

# 4. --archive-plan moves only the plan; DESIGN.md stays.
printf 'EDITED-AGAIN\n' >> "$R/$H/DESIGN.md"
run "$R" "$NEW" --archive-plan p1
t "--archive-plan p1 exits 0" test "$(rc)" = 0
t "--archive-plan moves the plan" grep -q PLAN-MARKER "$R/$H/archive/p1/plan/PLAN.md"
t "--archive-plan leaves DESIGN.md where it is" grep -q EDITED-AGAIN "$R/$H/DESIGN.md"
t "--archive-plan does not archive DESIGN.md" test ! -e "$R/$H/archive/p1/DESIGN.md"

# 5. Upgrade: a project set up by the released script gains only DESIGN.md.
R=$W/r2; mkrepo "$R"
run "$R" "$OLD"
t "baseline: the released script makes no DESIGN.md" test ! -e "$R/$H/DESIGN.md"
printf 'BRIEF-MARKER\n' >> "$R/$H/BRIEF.md"
run "$R" "$NEW"
t "upgrade exits 0" test "$(rc)" = 0
t "upgrade creates DESIGN.md" grep -Eq '^created[[:space:]].*DESIGN\.md$' "$W/out"
t "upgrade creates nothing else" test "$(grep -c '^created' "$W/out")" = 1
t "upgrade keeps the existing BRIEF.md" grep -q BRIEF-MARKER "$R/$H/BRIEF.md"

# 6. Regression: apart from DESIGN.md, the same runs and refusals behave exactly as released.
transcript() {
  D=$(mktemp -d "$W/d.XXXXXX"); mkrepo "$D"
  for args in "" "" "--archive" "--archive ../escape" "--archive step-1" "--archive step-1" "--archive-plan p2" "--nope"; do
    run "$D" "$1" $args
    echo "## args=[$args] rc=$(rc)"
    cat "$W/out" "$W/err"
    (cd "$D" && find .claude -print | sort)
  done | grep -v 'DESIGN\.md' | sed -e "s|$PLUGIN|PLUGIN|g" -e "s|$OLD_PLUGIN|PLUGIN|g" -e "s|$D|SCRATCH|g"
}
transcript "$OLD" > "$W/old.txt"
transcript "$NEW" > "$W/new.txt"
# "archived N file(s)": N is one higher than released, because DESIGN.md is archived too.
counts() { sed -n 's/^archived \([0-9][0-9]*\) file(s) .*/\1/p' "$1"; }
counts "$W/old.txt" > "$W/old.n"; counts "$W/new.txt" > "$W/new.n"
: > "$W/want.n"; while read -r n; do echo $((n+1)) >> "$W/want.n"; done < "$W/old.n"
t "every 'archived N file(s)' count is the released count plus one" \
  sh -c 'test -s "$1" && cmp -s "$1" "$2"' _ "$W/want.n" "$W/new.n"
norm() { sed 's/^archived [0-9][0-9]* file(s) /archived N file(s) /' "$1"; }
norm "$W/old.txt" > "$W/old.norm"; norm "$W/new.txt" > "$W/new.norm"
if diff "$W/old.norm" "$W/new.norm" > "$W/diff.txt"; then
  ok 0 "outputs, exit codes and files match $BASE apart from DESIGN.md and that count"
else
  ok 1 "outputs, exit codes and files match $BASE apart from DESIGN.md and that count"; cat "$W/diff.txt"
fi

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2

With Docker and the `bash:3.2` image (it has no git, so the container adds it; that needs the
network). From any folder, with `S` the scratch folder holding `check.sh`:

```sh
mkdir -p "$S/old"
git -C <this checkout> archive origin/next-release plugins/ada | tar -x -C "$S/old"
docker run --rm -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 >/dev/null && bash --version | head -1 && REPO=/plugin PLUGIN=/plugin OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

On a Mac, `REPO=<this checkout> /bin/bash check.sh` does the same with the system bash 3.2.

## Expected

Every line `PASS`, last line `0 failed`, exit 0, in all three runs; the bash version line in the
Docker run says 3.2. The broken copy in step 3 prints `FAIL` for the template and the created
lines. Sources: the brief's Definition of Done (copy, keep an edited one, `--archive` moves it with
the others and puts a fresh one in place, runs under `bash --posix` with no bash 4 features, S100);
`API.md`, `ada-handoff-init` (`created` / `kept`; `--archive` moves `.claude/handoff/*.md`, the
plan stays; `--archive-plan` moves only `plan/`).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`ls -R <scratch>/r1/.claude/handoff` and the `diff.txt` there.

## Regression

Check 6 is the regression: every other run and refusal of `ada-handoff-init` (no name, a bad name,
an archive name used twice, `--archive-plan` with no plan, an unknown flag) gives the same output,
exit code and files as the released script, once `DESIGN.md` lines are set aside and the
`archived N file(s)` count is checked to be the released count plus one (DESIGN.md is archived too).
