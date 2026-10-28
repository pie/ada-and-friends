# The brief's `Overlaps:` line: in the template, and in the draft PR description

- Area: 01 Brief approval (`ada-brief body`, `render_body`); 11 Templates
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219790041247935
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 28 Oct 2026 (after the test fix), 0 failed (20 PASS) under bash 5 and bash 3.2; breaks FAIL; see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree. The script compares with the plugin
  as it was before the `Overlaps:` line was added, exported from `BASE`, which defaults to
  `18e3c82^` (the commit before #35 added the line). It is pinned, not `origin/next-release`,
  because `next-release` has had the line since #35 merged, so against it the baseline checks
  fail for that reason alone. The commit must be in this clone's history (it is, on any branch
  of `next-release` or `main`).
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
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL`. Break it a second
   time: in another copy delete the `Worktree:` line instead. It must print `FAIL` for "the added
   lines are the Overlaps: line and this step's Worktree: line and comment, no more".

```bash
#!/bin/bash
# Overlaps: in the brief template and in ada-brief body. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO, BASE (default 18e3c82^), PLUGIN (default $REPO/plugins/ada), OLD_PLUGIN.
# BASE is pinned to the commit before #35 added Overlaps:, not origin/next-release, which has had
# the line since #35 merged (a moving baseline would fail the "released template has none" checks).
# Because BASE is pinned, a later legitimate edit to the BRIEF template will fail the exact-added-lines
# check until its allowed list is updated; update the list, don't loosen the check.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-18e3c82^}
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
# Step 1 of 25 Oct 2026 ("One worktree per step", DoD 9) agreed exactly one more edit to this template:
# a Worktree: line after Branch:, and the comment's "a plan is drafted ..." line rewritten to three
# lines. Nothing else released may be removed or changed, and nothing else may be added.
# Since BASE is pinned to before #35, the diff also holds PR #39's (fae9738) edits to the template: the
# Amendments comment rewritten (4 lines for 1) and the table made five-column (Re-approval). They are
# allowed here so that only the Overlaps: line (this script's subject) and this step's edits are judged.
cat > "$W/allowed.removed" <<'EOF'
< a plan is drafted as .claude/handoff/plan/<task id>.md and copied here when its build starts.
< entry without "Agreed by" is not part of the brief. -->
< | Date | What changed (old → new) | Why (evidence) | Agreed by |
< |---|---|---|---|
< | | | | |
EOF
printf '< \n' >> "$W/allowed.removed"   # the blank line between the Amendments comment and its table
cat > "$W/allowed.added" <<'EOF'
> Overlaps: <none | #N: <what we're doing about it>; ...>
> Worktree: <.claude/worktrees/<slug>: the step's worktree, from ada-worktree new>
> a plan is drafted as .claude/handoff/plan/<task id>.md in the main checkout and copied here, into
> the unit's worktree, when its build starts. The plan/ copy is then marked with this worktree and
> no longer edited: amendments go here.
> entry without "Agreed by" is not part of the brief. Decisions, and Constraints less its
> "- Out of scope:" bullet, are the approved sections: an approver approved them. Re-approval is
> "not needed" or "needed: <trigger>", from the triggers in /ada:begin and PROJECT.md. A change
> to an approved section with no row here needs approving again. -->
> | Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |
> |---|---|---|---|---|
> | | | | | |
EOF
printf '> \n' >> "$W/allowed.added"   # the blank line before the table
grep '^<' "$W/tdiff" | LC_ALL=C sort > "$W/got.removed"
grep '^>' "$W/tdiff" | LC_ALL=C sort > "$W/got.added"
LC_ALL=C sort "$W/allowed.removed" -o "$W/allowed.removed"; LC_ALL=C sort "$W/allowed.added" -o "$W/allowed.added"
t "template: no released line removed or changed, except the one comment line step 1 rewrote" cmp -s "$W/allowed.removed" "$W/got.removed"
t "template: the Overlaps: line is among the added lines" grep -qxF "> $LINE" "$W/tdiff"
t "template: the added lines are the Overlaps: line and this step's Worktree: line and comment, no more" cmp -s "$W/allowed.added" "$W/got.added"
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
how many lines the template gained (13: the `Overlaps:` line, the `Worktree:` line and the three comment lines of this step, plus PR #39's eight: four comment lines, a blank line, the five-column header, separator and empty row; 13 in all), and whether `Overlaps: none`
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
