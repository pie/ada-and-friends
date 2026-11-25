# `ada-onboard check` reports a project with neither an areas table nor a `defaults kept` line, and nothing else reads that line

Unit H of the plan "Brief approval by area": a project whose Brief approval section has no
`| Area | Slug | Approved by |` table and no `- **Areas:** defaults kept` line gets one new
`missing` line from `ada-onboard check`. Nothing else in `check`'s output moves, and the new bullet
changes nothing that `ada-brief who` or `ada-brief areas` print.

- Area: 02 Onboarding and refresh (`check`); 01 Brief approval (`who`, `areas` ignore the bullet);
  11 Templates (the template's commented starter table); 12 Platforms (bash 3.2, Python 3.8, a
  CRLF `PROJECT.md`)
- Asana: https://app.asana.com/0/0/1220198295391965
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 25 Nov 2026, 177 passed, 0 failed under bash 5.3, bash 3.2.57 (Docker) and Python 3.8.20 (Docker); break against 9af4c6b fails as expected (29 failed) (see the PR)

## Precondition

- This checkout (`REPO`), with the change in its working tree. The script runs this checkout's
  `ada-onboard` and `ada-brief` by path, never the ones on the PATH.
- `git`, `tar`, `awk`, `cmp`, `python3` (3.8+). No network and no GitHub login: a stand-in `gh`
  is put first on the PATH; it answers `gh api user` with `tester` and fails anything else.
- `BASE` (default `9af4c6b`, the tip of `add/brief-approval-by-area` before this step) in this
  checkout. `check` is compared line for line with `BASE`'s.
- The scratch repos sit in a folder whose name has a space.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Run it under bash 3.2 and Python 3.8 ("bash 3.2" and "Python 3.8" below).
4. Break it on purpose: export `BASE`'s plugin (`git -C <this checkout> archive 9af4c6b
   plugins/ada | tar -x -C <empty folder>`) and run `REPO=<this checkout> PLUGIN=<that
   folder>/plugins/ada bash check.sh`. See Expected for what FAILs.

```bash
#!/bin/bash
# Unit H: ada-onboard check's areas line; the "- **Areas:** defaults kept" bullet leaves check's
# other lines and ada-brief who/areas alone. PASS/FAIL per check; exit 1 if any FAIL, 2 if set-up fails.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada),
#      BASE (before this step, default 9af4c6b), OLD_PLUGIN (BASE's plugins/ada; exported if unset),
#      SH (how to run the scripts under test, default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE=${BASE:-9af4c6b}
SH=${SH:-bash}
for tool in git python3 tar awk cmp; do command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }; done
T=$(mktemp -d) || exit 2
W="$T/areas scratch"; mkdir "$W" || exit 2
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
NEWB=$PLUGIN/bin/ada-onboard; OLDB=$OLD_PLUGIN/bin/ada-onboard; BRIEF=$PLUGIN/bin/ada-brief
PJT=$PLUGIN/templates/repo/.claude/team/PROJECT.md
for f in "$NEWB" "$OLDB" "$BRIEF" "$PJT"; do [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }; done
# The line, word for word from the brief (Decisions, ada-onboard check).
LINE='missing  areas table in .claude/team/PROJECT.md (/ada:begin offers the starter set, or add "- **Areas:** defaults kept")'
CR=$(printf '\r')
fails=0; passes=0
pass() { echo "PASS $1"; passes=$((passes+1)); }
fail() { echo "FAIL $1"; fails=$((fails+1)); }
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then pass "$tn"; else fail "$tn"; fi; }
not() { ! "$@"; }
info() { echo "info: $*"; }
lines() { if [ -s "$1" ]; then grep -c . "$1"; else echo 0; fi; }
gitc() { git -c user.name=t -c user.email=t@example.com "$@"; }
newrepo() { mkdir -p "$1"; git -C "$1" init -q; gitc -C "$1" commit -q --allow-empty -m init; }

# The stand-in gh: "gh api user ..." prints tester; anything else fails, so nothing reaches GitHub.
mkdir "$W/stub"
cat > "$W/stub/gh" <<'STUB'
#!/bin/sh
if [ "${1:-}" = api ] && [ "${2:-}" = user ]; then echo tester; exit 0; fi
if [ "${1:-}" = auth ]; then exit 0; fi
echo "stand-in gh: not stubbed: $*" >&2; exit 1
STUB
chmod +x "$W/stub/gh"
PATH="$W/stub:$PATH"; export PATH
unset ADA_BRIEF_REQUIRE

# PROJECT.md pieces. head_ and tail_ surround the Brief approval section; sect <approvers> <part>...
# prints the section, the Approvers line first, then each part (a line or a block of lines).
head_() { printf '%s\n' '# Scratch' '' '## The project' '' '- **What it is:** a scratch repo.' '' '## Code and branches' '' '- **Branch from:** `main`. **PR into:** `main`.' ''; }
tail_() { printf '%s\n' '' '## Environments' '' 'None.'; }
sect() { a=$1; shift; printf '%s\n' '## Brief approval' '' 'Whose briefs need approving.' '' "- **Approvers:** $a"; for p in "$@"; do printf '%s\n' "$p"; done; }
TR='- **Trusted:** none'
KEPT='- **Areas:** defaults kept (Neil, 4 Nov 2026)'
KEPT2='- **Areas:** defaults kept (Ada Lovelace, 24 Nov 2026)'
KEPT0='- **Areas:** defaults kept'
TABLE='| Area | Slug | Approved by |
|---|---|---|
| Wording and content | content | @client |
| Design and UI | design | Approvers |
| System behaviour (catch-all) | system | Approvers |'
# The template's own commented starter table (the block from "<!--" to "-->" in its Brief approval section).
COMMENTED=$(awk '/^## Brief approval/{s=1; next} /^## /{s=0} s && /^<!--/{p=1} s && p {print} s && p && /-->/{exit}' "$PJT")
# mkp <repo> <crlf 0|1>: PROJECT.md from head_, stdin, tail_; CRLF line endings when asked.
mkp() {
  mkdir -p "$1/.claude/team"
  { head_; cat; tail_; } > "$W/p.tmp"
  if [ "$2" = 1 ]; then awk '{printf "%s\r\n", $0}' "$W/p.tmp" > "$1/.claude/team/PROJECT.md"; else cp "$W/p.tmp" "$1/.claude/team/PROJECT.md"; fi
}
# chk <dir> <script> <outfile>: check's stdout and stderr in <outfile>, its exit code in <outfile>.rc
chk() { (cd "$1" && $SH "$2" check) > "$3" 2>&1; echo $? > "$3.rc"; }
cmpchk() { chk "$2" "$OLDB" "$W/old.chk"; chk "$2" "$NEWB" "$W/new.chk"; info "$1: check exit before $(cat "$W/old.chk.rc"), after $(cat "$W/new.chk.rc")"; }
extra() { grep -vxFf "$W/old.chk" "$W/new.chk"; }   # lines check prints now that it didn't before
gone()  { grep -vxFf "$W/new.chk" "$W/old.chk"; }   # lines it printed before and doesn't now
# areas_line <label> <dir>: check prints the areas line, once, word for word, and nothing else changes
areas_line() {
  cmpchk "$1" "$2"; extra > "$W/x"
  info "$1: new line(s): $(tr '\n' '|' < "$W/x"), at line $(grep -nF 'areas table' "$W/new.chk" | cut -d: -f1 | tr '\n' ' ')of $(lines "$W/new.chk")"
  t "$1: exactly one new line" test "$(lines "$W/x")" = 1
  t "$1: it is the areas line, word for word" grep -qxF -- "$LINE" "$W/new.chk"
  t "$1: it starts with check's word 'missing' (S103)" grep -Eq '^missing +areas table' "$W/x"
  t "$1: printed once" test "$(grep -cF 'areas table' "$W/new.chk")" = 1
  t "$1: no carriage return in it" not grep -q "$CR" "$W/x"
  t "$1: check drops nothing" test -z "$(gone)"
  t "$1: check's exit status as before" test "$(cat "$W/old.chk.rc")" = "$(cat "$W/new.chk.rc")"
}
# noline <label> <dir>: no areas line, and check prints exactly what it printed before, same exit
noline() {
  cmpchk "$1" "$2"
  t "$1: no areas line" not grep -qi 'areas table' "$W/new.chk"
  t "$1: check prints nothing new" test -z "$(extra)"
  t "$1: check drops nothing" test -z "$(gone)"
  t "$1: check's exit status as before" test "$(cat "$W/old.chk.rc")" = "$(cat "$W/new.chk.rc")"
}
pyver=$(python3 -c 'import sys; print("%d.%d" % sys.version_info[:2])')
info "bash $BASH_VERSION, python3 $pyver, base $BASE, plugin $PLUGIN"

echo "== 0. Static (S100, S101, S102)"
t "bash -n bin/ada-onboard" bash -n "$NEWB"
t "bin/ada-onboard has no carriage return" not grep -q "$CR" "$NEWB"
t "S102: bin/ada-onboard still sets -euo pipefail" grep -q '^set -euo pipefail' "$NEWB"
mkdir "$W/py"
awk -v d="$W/py" -v m1="<<'PY'" -v m2="<<-'PY'" '(index($0,m1)||index($0,m2)) && !p {n++; f=d "/heredoc" n ".py"; p=1; next} p && /^[[:space:]]*PY[[:space:]]*$/ {p=0; close(f); next} p {print > f}' "$NEWB"
nh=$(ls "$W/py" | grep -c '\.py$')
t "setup: found the Python heredocs in bin/ada-onboard ($nh)" test "$nh" -ge 1
for f in "$W/py"/*.py; do
  [ -f "$f" ] || continue
  t "$(basename "$f") compiles under Python $pyver" python3 -c 'import sys; compile(open(sys.argv[1], encoding="utf-8").read(), sys.argv[1], "exec")' "$f"
done
awk 'NR==1{next} /^#/{print; next} {exit}' "$NEWB" > "$W/hdr.new"
awk 'NR==1{next} /^#/{print; next} {exit}' "$OLDB" > "$W/hdr.old"
t "S102: the usage header, kept current, now speaks of the areas (check's new line)" test "$(grep -ci 'area' "$W/hdr.new")" -gt "$(grep -ci 'area' "$W/hdr.old")"
t "setup: the template's commented starter table was found" sh -c "printf '%s\n' \"\$1\" | grep -q '^| Area | Slug | Approved by |'" _ "$COMMENTED"

echo "== 1. check's areas line (DoD 1)"
D="$W/r1"; newrepo "$D"; mkdir -p "$D/sub"
sect none "$TR" | mkp "$D" 0
areas_line "1.1 section with neither (Approvers none)" "$D"
sect @tester "$TR" | mkp "$D" 0
areas_line "1.2 section with neither (Approvers @tester: whatever its Approvers)" "$D"
sect none "$TR" '' "$COMMENTED" | mkp "$D" 0
areas_line "1.3 the starter table only inside the template's HTML comment (ignored, so no table)" "$D"
sect @tester "$TR" '' "$TABLE" | mkp "$D" 0
noline "1.4 a table" "$D"
sect none "$TR" "$KEPT" | mkp "$D" 0
noline "1.5 defaults kept (Neil, 4 Nov 2026), after Trusted" "$D"
sect none "$KEPT" "$TR" | mkp "$D" 0
noline "1.6 defaults kept, between Approvers and Trusted" "$D"
sect none "$TR" "$KEPT0" | mkp "$D" 0
noline "1.7 '- **Areas:** defaults kept' alone, as check's line says to add it" "$D"
sect @tester "$TR" "$KEPT2" '' "$TABLE" | mkp "$D" 0
noline "1.8 a table and a defaults kept line" "$D"
printf '' | mkp "$D" 0
noline "1.9 no Brief approval section" "$D"
t "1.9 no Brief approval section: the existing missing \"Brief approval\" section line is there" grep -Eq '^missing +"Brief approval" section' "$W/new.chk"
rm -f "$D/.claude/team/PROJECT.md"
noline "1.10 no PROJECT.md" "$D"
sect none "$TR" '' '## Notes' '' "$TABLE" | mkp "$D" 0
areas_line "1.11 a table under another heading, none in the section" "$D"
{ printf '%s\n' '## Notes' '' "$KEPT" ''; sect none "$TR"; } | mkp "$D" 0
areas_line "1.12 a defaults kept line under another heading, none in the section" "$D"
sect none "$TR" | mkp "$D" 0
areas_line "1.13 section with neither, check run from a subfolder" "$D/sub"
echo "-- CRLF PROJECT.md (S100)"
sect none "$TR" | mkp "$D" 1
areas_line "1.14 CRLF, section with neither" "$D"
sect @tester "$TR" '' "$TABLE" | mkp "$D" 1
noline "1.15 CRLF, a table" "$D"
sect none "$TR" "$KEPT" | mkp "$D" 1
noline "1.16 CRLF, defaults kept (Neil, 4 Nov 2026)" "$D"
sect none "$TR" "$KEPT0" | mkp "$D" 1
noline "1.17 CRLF, '- **Areas:** defaults kept' alone" "$D"
printf '' | mkp "$D" 1
noline "1.18 CRLF, no Brief approval section" "$D"
t "1.18 CRLF, no section: the missing \"Brief approval\" section line is there" grep -Eq '^missing +"Brief approval" section' "$W/new.chk"

echo "== 2. ada-brief who and areas, with and without the defaults kept line (DoD 2)"
P="$W/plain"; K="$W/kept"; newrepo "$P"; newrepo "$K"
# brun <dir> <out> <require 0|1> <args...>: the ada-brief under test; stdout and stderr, then "exit <n>"
brun() {
  d=$1; o=$2; r=$3; shift 3
  if [ "$r" = 1 ]; then (cd "$d" && ADA_BRIEF_REQUIRE=1 $SH "$BRIEF" "$@") > "$o" 2>&1; else (cd "$d" && $SH "$BRIEF" "$@") > "$o" 2>&1; fi
  echo "exit $?" >> "$o"
}
# sb <label> <require 0|1> <args...>: ada-brief prints the same, byte for byte, with the bullet as without
sb() {
  l=$1; r=$2; shift 2
  brun "$P" "$W/p.out" "$r" "$@"; brun "$K" "$W/k.out" "$r" "$@"
  if cmp -s "$W/p.out" "$W/k.out"; then pass "$l: '$*'$([ "$r" = 1 ] && echo ' (ADA_BRIEF_REQUIRE=1)') the same with the bullet"
  else fail "$l: '$*'$([ "$r" = 1 ] && echo ' (ADA_BRIEF_REQUIRE=1)') the same with the bullet"; info "without: $(tr '\n' '|' < "$W/p.out")"; info "with: $(tr '\n' '|' < "$W/k.out")"; fi
}
# first <args...>: the first line ada-brief prints in the plain repo (sanity: the comparison isn't of two failures)
first() { brun "$P" "$W/f.out" 0 "$@"; head -1 "$W/f.out"; }
for eol in 0 1; do
  e=LF; [ "$eol" = 1 ] && e=CRLF
  for a in none @tester; do
    sect "$a" "$TR" | mkp "$P" "$eol"
    if [ "$a" = none ]; then want_who=not-needed; want_areas='everything: none (catch-all)'; else want_who='approver tester'; want_areas='everything: @tester (catch-all)'; fi
    t "2. $e, Approvers $a, no table: sanity: who prints $want_who" test "$(first who)" = "$want_who"
    t "2. $e, Approvers $a, no table: sanity: areas prints $want_areas" test "$(first areas)" = "$want_areas"
    for pos in after between end; do
      case $pos in
        after)   sect "$a" "$TR" "$KEPT" | mkp "$K" "$eol" ;;
        between) sect "$a" "$KEPT" "$TR" | mkp "$K" "$eol" ;;
        end)     sect "$a" "$TR" '' 'Anyone else needs approval.' '' "$KEPT" | mkp "$K" "$eol" ;;
      esac
      lb="2. $e, Approvers $a, no table, bullet $pos"
      sb "$lb" 0 who
      sb "$lb" 1 who
      sb "$lb" 0 areas
    done
  done
  sect @tester "$TR" '' "$TABLE" | mkp "$P" "$eol"
  t "2. $e, with a table: sanity: areas' first line is content: @client" test "$(first areas)" = 'content: @client'
  t "2. $e, with a table: sanity: who content prints required tester" test "$(first who content)" = 'required tester'
  for pos in before-table after-table; do
    case $pos in
      before-table) sect @tester "$TR" "$KEPT" '' "$TABLE" | mkp "$K" "$eol" ;;
      after-table)  sect @tester "$TR" '' "$TABLE" '' "$KEPT" | mkp "$K" "$eol" ;;
    esac
    lb="2. $e, a table, bullet $pos"
    sb "$lb" 0 areas
    sb "$lb" 0 who
    sb "$lb" 0 who content
    sb "$lb" 0 who system
    sb "$lb" 0 who content design system
    sb "$lb" 1 who content
  done
done

echo "$passes passed, $fails failed"
if [ "$fails" = 0 ]; then rm -rf "$T"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2

With Docker and the `bash:3.2` image (it has no git or Python, so the container adds them; that
needs the network). With `S` the scratch folder holding `check.sh`:

```sh
mkdir -p "$S/old"
git -C <this checkout> archive 9af4c6b plugins/ada | tar -x -m -C "$S/old"
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && REPO=/repo OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

From a worktree, mount the main checkout at its own path and pass `REPO` as the worktree's path
(`-v "<main>":"<main>":ro -e REPO="<worktree>"`, and drop `REPO=/repo` from the `sh -c`): the
worktree's `.git` points into the main checkout. On a Mac, `REPO=<this checkout> /bin/bash
check.sh` uses the system bash 3.2.

### Python 3.8

The same, in `python:3.8-slim` (its `python3` is 3.8, so section 0 compiles every heredoc with 3.8
and every `check` and `ada-brief` run uses it):

```sh
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
  sh -c 'apt-get update -qq >/dev/null && apt-get install -y -qq git >/dev/null && git config --global --add safe.directory "*" && python3 --version && REPO=/repo OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

## Expected

Every line `PASS`, last line `<n> passed, 0 failed`, exit 0, under the local bash, bash 3.2 and
Python 3.8 (the first `info:` line says which).

- Section 1: with a Brief approval section that has neither a table nor a `- **Areas:** defaults
  kept` line (1.1, 1.2, 1.3, 1.11 to 1.14), `check` prints one new line, exactly
  `missing  areas table in .claude/team/PROJECT.md (/ada:begin offers the starter set, or add "- **Areas:** defaults kept")`,
  whatever the Approvers, from a subfolder too, and with CRLF. A table only inside an HTML comment
  (1.3) or under another heading (1.11) is no table, and a bullet under another heading (1.12) is
  no bullet. With a table, a
  `defaults kept` line (with a name and date, or bare as the line itself suggests), both, no
  section (only the existing `missing  "Brief approval" section` line) or no `PROJECT.md`,
  `check` prints exactly what `9af4c6b`'s printed. Every other line and the exit status are
  unchanged in every case.
- Section 2: `ada-brief who`, `who` under `ADA_BRIEF_REQUIRE=1`, `who <area>...` and `areas` print
  the same, byte for byte with the exit status, whether or not the section has the bullet, wherever
  it sits, LF or CRLF.

Break on purpose (step 4): every `areas_line` case (1.1 to 1.3, 1.11 to 1.14) FAILs `exactly one
new line`, `it is the areas line, word for word`, `it starts with check's word 'missing'` and
`printed once`, and the usage-header check FAILs; the `noline` cases and section 2 PASS.

Sources: Unit H's brief (task 1220198295391965): Outcome; Decisions (When; `ada-onboard check`,
the line word for word; On no, the bullet); Constraints (`check`'s words, S103; `policy()` and
`read_areas()` read only the Approvers and Trusted bullets and the table; bash 3.2, Git Bash, CRLF,
S100); Definition of Done 1, 2 and 9. `SYSTEM.md`, ada-brief (a table inside an HTML comment is
ignored) and the template's own comment ("Inside this comment the table is ignored"). `API.md`:
`check`'s words; `who`'s first line and `needs` lines; `areas`' output.

## Observe at

The script's output and exit code. On a FAIL, the scratch folder it names (`check` before and
after in `old.chk` and `new.chk`).

## Regression

`check`'s other lines and exit status are compared with `9af4c6b`'s in every case (section 1).
`who`, `who <area>...` and `areas` are compared with and without the bullet (section 2).
`02-onboarding/kb-folder-and-check.md` carries a `defaults kept` line in its scratch `PROJECT.md`
from this step on, so its comparisons stay about the knowledge base.
