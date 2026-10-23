# The brief's `Overlaps:` line: in the template, and in the draft PR description

- Area: 01 Brief approval (`ada-brief body`, `render_body`); 11 Templates
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219790041247935
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 25 Oct 2026, 0 failed under bash 5 and Docker bash 3.2, step 3 FAILs as it should; see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree, and `origin/next-release` fetched:
  the script compares with the released plugin exported from there.
- `git`, `tar`, `python3` (3.8+), `mktemp`. No network and no GitHub login: `body` and `who`
  call `need_gh`, so a stub `gh` that is logged in and knows one user is put first on the PATH.
  The briefs are committed on a local branch, which `render_body` reads first.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`. For bash 3.2, with `S` the scratch folder holding
   `check.sh` (the container adds the tools, so it needs the network; `diffutils` is there because
   busybox `diff` in the image prints a unified diff, not the plain one the script compares):

   ```sh
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 jq diffutils >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && REPO=/repo bash /s/check.sh'
   ```
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, delete the `Overlaps:`
   line from the copy's `templates/handoff/BRIEF.md`, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL`.

```bash
#!/bin/bash
# Overlaps: in the brief template and in ada-brief body. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO, BASE (default origin/next-release), PLUGIN (default $REPO/plugins/ada), OLD_PLUGIN.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASH_BIN=$(command -v bash)
W=$(mktemp -d)
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
NEWT=$PLUGIN/templates/handoff/BRIEF.md
OLDT=$OLD_PLUGIN/templates/handoff/BRIEF.md
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
G() { git -c user.name=t -c user.email=t@example.invalid -c init.defaultBranch=init "$@"; }

# A stub gh: logged in as "tester"; nothing else.
mkdir -p "$W/stub"
cat > "$W/stub/gh" <<'STUB'
#!/bin/sh
case "$1 $2" in
  "auth status") exit 0 ;;
  "api user") echo tester ;;
  *) echo "STUB-GAP: gh $*" >&2; exit 3 ;;
esac
STUB
chmod +x "$W/stub/gh"
SP="$W/stub:$PATH"

# 1. The template (D9, S104).
LINE="Overlaps: <none | #N: <what we're doing about it>; ...>"
after_ships() { awk '/^Ships:/{getline; print; exit}' "$1"; }
t "template: the line after Ships: is the Overlaps: line" test "$(after_ships "$NEWT")" = "$LINE"
t "template: one Overlaps: line" test "$(grep -c '^Overlaps:' "$NEWT")" = 1
t "baseline: the released template has no Overlaps: line" not grep -q '^Overlaps:' "$OLDT"
diff "$OLDT" "$NEWT" > "$W/tdiff"
t "template: no released line removed or changed" not grep -q '^<' "$W/tdiff"
t "template: the Overlaps: line is among the added lines" grep -qxF "> $LINE" "$W/tdiff"
echo "info: lines added to the template: $(grep -c '^>' "$W/tdiff")"

# 2. ada-brief body (D9) and the regression next to it.
R=$W/r; mkdir -p "$R/.claude/team" "$R/.claude/briefs"; G init -q "$R"
printf '# Scratch\n\n## Brief approval\n\n- **Approvers:** none\n- **Trusted:** none\n' > "$R/.claude/team/PROJECT.md"
brief() {  # brief <task> <Overlaps line or ->
  { printf '# Brief — Step 1: Test the overlaps line\n\nAsana: https://app.asana.com/0/1/2\n'
    printf 'Branch: add/test\nPR into: next-release\nPlan: none\nShips: independent\n'
    [ "$2" != - ] && printf '%s\n' "$2"
    printf 'Status: agreed\nApproval: not needed (test)\n\n## Outcome\n\nSomething happens.\n\n'
    printf '## Decisions\n\n- A decision.\n\n## Constraints\n\n- Expected review: small.\n'
    printf -- '- Out of scope: everything else.\n\n## Build order\n\n1. Build it.\n\n'
    printf '## Definition of done\n\n- [ ] It happens.\n'; } > "$R/.claude/briefs/$1.md"
}
OV='Overlaps: #12: build on top of it, branch based on next-release; #21: going ahead independently, different screen'
brief T1 "$OV"; brief T2 -; brief T3 'Overlaps: none'
G -C "$R" checkout -q -b add/test; G -C "$R" add -A; G -C "$R" commit -q -m briefs
body() { (cd "$R" && PATH="$SP" "$BASH_BIN" "$1/bin/ada-brief" body "$2") > "$W/$3" 2> "$W/$3.err"; echo $? > "$W/$3.rc"; }
for k in T1 T2 T3; do body "$PLUGIN" $k new.$k; body "$OLD_PLUGIN" $k old.$k; done
t "body T1: exit 0" test "$(cat "$W/new.T1.rc")" = 0
t "body T1: starts with the ada-brief marker" grep -q '^<!-- ada-brief: .claude/briefs/T1.md -->' "$W/new.T1"
for s in Overlaps '#12' 'build on top of it, branch based on next-release' '#21' 'going ahead independently, different screen'; do
  t "body T1 shows: $s" grep -qF -- "$s" "$W/new.T1"
done
t "baseline: the released body for T1 doesn't show it" not grep -qF 'build on top of it' "$W/old.T1"
diff "$W/old.T1" "$W/new.T1" > "$W/bdiff"
carries() { grep '^>' "$W/bdiff" | grep -vE 'Overlaps|#12|#21|build on top of it|going ahead independently' | grep -q .; }
t "body T1: every added line carries the overlaps" not carries
t "body T1: at most one released line replaced" test "$(grep -c '^<' "$W/bdiff")" -le 1
t "regression: body T2 (no Overlaps:) is the released output, byte for byte" cmp -s "$W/old.T2" "$W/new.T2"
t "regression: body T2 exit codes match" test "$(cat "$W/old.T2.rc")" = "$(cat "$W/new.T2.rc")"
echo "info: body T3 (Overlaps: none) shows 'none' for overlaps: $(grep -ciF 'overlaps' "$W/new.T3") line(s) mention overlaps; exit $(cat "$W/new.T3.rc")"
(cd "$R" && PATH="$SP" "$BASH_BIN" "$PLUGIN/bin/ada-brief" who) > "$W/who.new" 2>&1
(cd "$R" && PATH="$SP" "$BASH_BIN" "$OLD_PLUGIN/bin/ada-brief" who) > "$W/who.old" 2>&1
t "regression: who prints what the released one does" cmp -s "$W/who.old" "$W/who.new"
t "stub: no STUB-GAP (else fix the stub)" not grep -q STUB-GAP "$W"/*.err

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0. Record the two `info:` lines in the Run record:
how many lines the template gained (1 if only the `Overlaps:` line), and whether `Overlaps: none`
shows in the description (the brief doesn't say; worth a look). Step 3's broken copy prints
`FAIL` for the template line.

Sources: the brief's Decisions (the template line, exactly; `render_body` carries it "like the
other header lines", S104) and Definition of Done ("`ada-brief body` on a brief with that line
filled shows it in the draft PR description"); its Constraints (no change to `who`, `submit`,
`status`, `body` or `ready` beyond what `render_body` needs, so a brief without the line renders
as before); `API.md` (`body` output starts with the `<!-- ada-brief: …-->` marker).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept: `old.T1` / `new.T1`
are the released and new descriptions, `bdiff` their diff, `tdiff` the template diff.

## Regression

The T2 comparison (a brief without the line renders exactly as before) and `who`. `submit`
writes the same `render_body` output to the PR; it pushes, so it is not run here.
