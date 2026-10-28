# `ada-handoff-init` copies DESIGN.md, keeps an edited one, and archives it with the others

Section 7 (step 1 of 27 Oct 2026, "Iris keeps the project's DESIGN.md in the Stitch format"):
it copies the handoff templates and never the root template `templates/DESIGN.md`, and leaves a
project's own root `DESIGN.md` alone through `--archive`.

- Area: 05 Handoff files (`ada-handoff-init`); 12 Platforms (bash 3.2, `bash --posix`)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905;
  section 7: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219893199817979
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 28 Oct 2026 (after the rebase onto PR #39), 0 failed (41 PASS) under bash 5, BASE pinned to 05a5365^; see the PR

## Precondition

- This checkout (`REPO`). The script compares the changed `ada-handoff-init` with the one from
  `BASE`.
- Sections 5 and 6 compare with a plugin from before `DESIGN.md` existed. Since #30 (05a5365)
  merged, `origin/next-release` already has it, so `BASE` defaults to `05a5365^` (the commit before
  it, which must be in this clone's history); against `origin/next-release` they fail for that
  reason alone (KG-5). Section 7 doesn't use `BASE`.
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
4. Break section 7 on purpose, once: in another scratch copy of `plugins/ada`, copy
   `templates/DESIGN.md` to `templates/handoff/ROOT-DESIGN.md`, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` for "no file in the
   repo is the root template".

```bash
#!/bin/bash
# DESIGN.md: copied, kept, archived. Prints PASS/FAIL per check; exits 1 if any FAIL.
# Env: REPO (this checkout), BASE (default 05a5365^), PLUGIN (plugin under test,
# default $REPO/plugins/ada), OLD_PLUGIN (released plugin; default: exported from BASE),
# SH (how to run the script under test; default bash).
# BASE is pinned to the commit before #30 (05a5365) added DESIGN.md, not origin/next-release, which
# has had it since #30 merged: against it sections 5 and 6 fail for that reason alone (KG-5).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-05a5365^}
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

# 7. The root template is not a handoff file: never copied, and a project's root DESIGN.md is left
#    alone (step 1 of 27 Oct 2026, Definition of Done 2).
ROOT_TPL=$PLUGIN/templates/DESIGN.md
t "the plugin has templates/DESIGN.md (the root template)" test -f "$ROOT_TPL"
copies_of_root() { find "$1" -path "$1/.git" -prune -o -type f -print | while read -r f; do cmp -s "$ROOT_TPL" "$f" && echo "$f"; done; }
R=$W/r3; mkrepo "$R"
run "$R" "$NEW"
t "fresh repo: exits 0" test "$(rc)" = 0
t "fresh repo: no DESIGN.md at the repo root" test ! -e "$R/DESIGN.md"
t "fresh repo: every created line is under .claude/handoff/" not sh -c "grep '^created' '$W/out' | grep -vq '\.claude/handoff/'"
t "fresh repo: no file in the repo is the root template" test -z "$(copies_of_root "$R")"
t "fresh repo: .claude/handoff holds the handoff templates, no more" \
  test "$(cd "$R/$H" && ls *.md | sort)" = "$(cd "$PLUGIN/templates/handoff" && ls *.md | sort)"
t "fresh repo: git status shows nothing outside .claude/" not sh -c "git -C '$R' status --porcelain | grep -vq ' \.claude/'"
R=$W/r4; mkrepo "$R"
printf -- '---\nversion: alpha\nname: ROOT-MARKER\ncolors:\n  primary: "#1A5276"\n---\n\n## Overview\n\nThe project.\n' > "$R/DESIGN.md"
git -C "$R" add DESIGN.md && git -C "$R" -c user.name=t -c user.email=t@example.invalid commit -q -m "root DESIGN.md"
cp "$R/DESIGN.md" "$W/root.orig"
run "$R" "$NEW"
t "with a root DESIGN.md: exits 0" test "$(rc)" = 0
t "with a root DESIGN.md: it is left byte for byte" cmp -s "$W/root.orig" "$R/DESIGN.md"
t "with a root DESIGN.md: no output line names a DESIGN.md outside .claude/handoff/" \
  not sh -c "cat '$W/out' '$W/err' | grep 'DESIGN\.md' | grep -vq '\.claude/handoff/'"
t "with a root DESIGN.md: the handoff DESIGN.md is the handoff template, not the root one" cmp -s "$TPL" "$R/$H/DESIGN.md"
run "$R" "$NEW" --archive step-1
t "with a root DESIGN.md, --archive step-1: exits 0" test "$(rc)" = 0
t "--archive leaves the root DESIGN.md where it is, byte for byte" cmp -s "$W/root.orig" "$R/DESIGN.md"
t "--archive puts no copy of the root DESIGN.md in the archive" not grep -rq ROOT-MARKER "$R/$H/archive"
t "--archive: the archived DESIGN.md is the handoff one" test -f "$R/$H/archive/step-1/DESIGN.md"
t "with a root DESIGN.md: git status shows nothing outside .claude/" not sh -c "git -C '$R' status --porcelain | grep -vq ' \.claude/'"

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2

With Docker and the `bash:3.2` image (it has no git, so the container adds it; that needs the
network). From any folder, with `S` the scratch folder holding `check.sh`:

```sh
mkdir -p "$S/old"
git -C <this checkout> archive '05a5365^' plugins/ada | tar -x -C "$S/old"
docker run --rm -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 >/dev/null && bash --version | head -1 && REPO=/plugin PLUGIN=/plugin OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

On a Mac, `REPO=<this checkout> /bin/bash check.sh` does the same with the system bash 3.2.

## Expected

Every line `PASS`, last line `0 failed`, exit 0, in all three runs (section 7 fails on the released
plugin until `templates/DESIGN.md` exists); the bash version line in the
Docker run says 3.2. The broken copy in step 3 prints `FAIL` for the template and the created
lines. Sources: the brief's Definition of Done (copy, keep an edited one, `--archive` moves it with
the others and puts a fresh one in place, runs under `bash --posix` with no bash 4 features, S100);
`API.md`, `ada-handoff-init` (`created` / `kept`; `--archive` moves `.claude/handoff/*.md`, the
plan stays; `--archive-plan` moves only `plan/`).

Section 7's sources: the brief of 27 Oct 2026 (Definition of Done 2: `ada-handoff-init` in a
scratch repo copies the handoff templates and not the root template; Decisions: the root
`DESIGN.md` is a project file that lasts beyond a step; `ada-handoff-init` does not copy the
template). The step 4 break prints `FAIL` for the copy of the root template.

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`ls -R <scratch>/r1/.claude/handoff` and the `diff.txt` there.

## Regression

Check 6 is the regression: every other run and refusal of `ada-handoff-init` (no name, a bad name,
an archive name used twice, `--archive-plan` with no plan, an unknown flag) gives the same output,
exit code and files as the released script, once `DESIGN.md` lines are set aside and the
`archived N file(s)` count is checked to be the released count plus one (DESIGN.md is archived too).
