# `ada-onboard refresh` gives an onboarded repo the PR template's per-area `Brief:` line

- Area: 02 Onboarding and refresh (`ada-onboard`); 11 Templates; 12 Platforms; 08 Session flow
  (`pr-gate.md`)
- Asana: https://app.asana.com/0/0/1220198400718618 (Unit G of
  `.claude/briefs/1220197416280547-plan.md`)
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 24 Nov 2026, 0 failed (bash 5.x local; bash 3.2 in Docker not run); break-it against baf0204: 21 failed as expected (see the PR)

## Precondition

- This checkout (`REPO`), with the change. The script onboards scratch repos with the
  `ada-onboard` of a pinned base commit, then refreshes them with this checkout's `ada-onboard`,
  run by path:
  - `BASE`, default `baf0204`: the tip of `add/brief-approval-by-area` that Unit G started from,
    whose PR template has the old line,
    ``Brief: <`.claude/briefs/<task id>.md`, approved by @login | approval not needed, and why>``.

  It is pinned, not `origin/add/brief-approval-by-area`, which gains the new line once Unit G
  merges and would then fail the baseline checks for that reason alone (KG-5). The commit must be
  in this clone's history, or exported beforehand as `OLD_PLUGIN` (step 3).
- `git`, `tar`, `cmp`, `diff`, `awk`, `sed`, and `sha256sum` or `shasum`. Python 3.8+ (for
  `ada-onboard`).
- Nothing in this checkout is touched; scratch repos live under `$TMPDIR`.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. bash 3.2, with Docker and the `bash:3.2` image (it has no git, so the container adds it; that
   needs the network). With `S` the scratch folder holding `check.sh`:

   ```sh
   mkdir -p "$S/old"
   git -C <this checkout> archive baf0204 plugins/ada | tar -x -C "$S/old"
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 diffutils >/dev/null && bash --version | head -1 && REPO=/repo OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
   ```

   The section 8 checks against `BASE` print one `SKIP` line there (a worktree's `.git` points
   outside the container); the local run in step 2 covers them. On a Mac,
   `REPO=<this checkout> /bin/bash check.sh` does the same with the system bash 3.2.
4. Break it on purpose, once: with `$S/old` from step 3, run
   `REPO=<this checkout> PLUGIN=$S/old/plugins/ada OLD_PLUGIN=$S/old/plugins/ada bash check.sh`
   (the plugin as it was before Unit G). It must print `FAIL` lines (Expected).

```bash
#!/bin/bash
# Unit G: the PR template's Brief: line becomes the per-area form; refresh brings it to onboarded
# repos in every outcome, files to new ones, and pr-gate.md fills it from the brief's Approval:
# line area by area. PASS/FAIL per check; exit 1 if any FAIL, 2 on a setup error.
# Env: REPO (this checkout), PLUGIN (default $REPO/plugins/ada: the plugin under test),
#   BASE (default baf0204: add/brief-approval-by-area when Unit G started), or OLD_PLUGIN: BASE's
#   plugins/ada already exported (for a run with no git history, such as bash 3.2 in Docker).
# BASE is pinned, not origin/add/brief-approval-by-area, which gains the new line once Unit G
# merges (KG-5).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE=${BASE:-baf0204}
W=$(mktemp -d "${TMPDIR:-/tmp}/refresh-brief.XXXXXX") || exit 2
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
NEWB=$PLUGIN/bin/ada-onboard
OLDB=$OLD_PLUGIN/bin/ada-onboard
P=.github/pull_request_template.md
NEWT=$PLUGIN/templates/repo/$P
OLDT=$OLD_PLUGIN/templates/repo/$P
GATE=$PLUGIN/skills/begin/pr-gate.md
CL=$REPO/CHANGELOG.md
HF=.claude/team/.template-hashes
for f in "$NEWB" "$NEWT" "$OLDB" "$OLDT" "$GATE" "$CL"; do
  [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }
done
# The line before Unit G, and the line as Unit G's brief words it (Decisions, as amended on 2026-11-24:
# three area states: by @login on <date>, not needed (@login approves it), not needed (no approvers)).
OLD_BRIEF='Brief: <`.claude/briefs/<task id>.md`, approved by @login | approval not needed, and why>'
NEW_BRIEF='Brief: <`.claude/briefs/<task id>.md`; approved by area: <slug> by @login on <date>, <slug> not needed (@login approves it), <slug> not needed (no approvers) | approved by @login | approval not needed, and why>'
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
sha() { if command -v sha256sum >/dev/null 2>&1; then tr -d '\r' < "$1" | sha256sum | cut -d' ' -f1; else tr -d '\r' < "$1" | shasum -a 256 | cut -d' ' -f1; fi; }
commit() { git -C "$1" add -A && git -C "$1" -c user.name=t -c user.email=t@example.invalid commit -q -m "$2" >/dev/null 2>&1; }
onboard() {   # onboard <dir> <ada-onboard>: a scratch repo onboarded by that script's files, committed
  mkdir -p "$1" && git -C "$1" init -q && git -C "$1" config core.autocrlf false
  (cd "$1" && bash "$2" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/onboard.out" 2>&1
  commit "$1" onboarded
}
base_onboard() {   # onboarded by BASE, which must leave the old template, untouched, hashed
  onboard "$1" "$OLDB"
  if ! grep -Fxq "copied $(sha "$OLDT") $P" "$1/$HF" || ! grep -Fxq "$OLD_BRIEF" "$1/$P"; then
    echo "SETUP FAIL: $1 has no untouched old template with its hash"; cat "$W/onboard.out"; exit 2
  fi
}
run_refresh() { d=$1; b=$2; shift 2; (cd "$d" && bash "$b" refresh "$@") > "$W/out" 2>&1; echo $? > "$W/rc"; }
refresh() { d=$1; shift; run_refresh "$d" "$NEWB" "$@"; }        # this branch's script
base_refresh() { d=$1; shift; run_refresh "$d" "$OLDB" "$@"; }   # BASE's script
rc() { cat "$W/rc"; }
first() { head -1 "$W/out"; }
says() { test "$(first)" = "$(printf '%-9s %s' "$1" "$P")"; }   # API.md: the word padded to 9
has_hash() { grep -Fxq "$2 $(sha "$3") $P" "$1/$HF"; }
one_hash() { test "$(grep -c " $P\$" "$1/$HF")" = 1; }
untouched() { test -z "$(git -C "$1" status --porcelain -- .github)"; }
clean() { test -z "$(git -C "$1" status --porcelain)"; }
new_line() { grep -Fxq "$NEW_BRIEF" "$1"; }
old_line() { grep -Fxq "$OLD_BRIEF" "$1"; }
plus_lines() { grep '^+' "$W/out" | grep -v '^+++'; }
only_new_plus() { test "$(plus_lines | wc -l | tr -d ' ')" = 1 && test "$(plus_lines)" = "+$NEW_BRIEF"; }
minus_old() { grep -Fxq -- "-$OLD_BRIEF" "$W/out"; }
lineno() { grep -n '^Brief:' "$1" | cut -d: -f1; }

# 0. The template under test, and the baseline (DoD 1, 7; Constraints: "the Brief: line only").
t "template: the Brief: line is the brief's per-area line, whole" new_line "$NEWT"
t "template: exactly one Brief: line" test "$(grep -c '^Brief:' "$NEWT")" = 1
t "template: the old Brief: line is gone" not old_line "$NEWT"
t "template: the Brief: line keeps its place (line $(lineno "$OLDT") at $BASE)" test "$(lineno "$NEWT")" = "$(lineno "$OLDT")"
t "template: no {{KEY}} anywhere, so refresh can offer it" not grep -q '{{' "$NEWT"
t "template: against $BASE, the only line removed is the old Brief: line" test "$(diff "$OLDT" "$NEWT" | grep '^< ')" = "< $OLD_BRIEF"
t "template: against $BASE, the only line added is the new Brief: line" test "$(diff "$OLDT" "$NEWT" | grep '^> ')" = "> $NEW_BRIEF"
t "baseline $BASE: has the old Brief: line" old_line "$OLDT"
t "baseline $BASE: no per-area Brief: line" not grep -q 'approved by area' "$OLDT"

# 1. Untouched copy (DoD 1): refreshed, the new line, the hash follows; then current.
R=$W/untouched; base_onboard "$R"
refresh "$R"
t "untouched: exit 0" test "$(rc)" = 0
t "untouched: prints refreshed .github/pull_request_template.md" says refreshed
t "untouched: the template is now the plugin's, byte for byte" cmp -s "$NEWT" "$R/$P"
t "untouched: the template's Brief: line is the new one" new_line "$R/$P"
t "untouched: .template-hashes has copied <new sha> for the template" has_hash "$R" copied "$NEWT"
t "untouched: the old copy's hash is gone" not has_hash "$R" copied "$OLDT"
t "untouched: one hash line for the template" one_hash "$R"
t "untouched: only the template and .template-hashes changed" \
  test "$(git -C "$R" status --porcelain | sort)" = "$(printf ' M %s\n M %s\n' "$HF" "$P")"
commit "$R" refreshed
refresh "$R"
t "untouched, second refresh: prints current" says current
t "untouched, second refresh: nothing changed" clean "$R"

# 2. The same with CRLF line endings (DoD 2): hashes are of LF-normalised text, so refreshed.
R=$W/crlf; base_onboard "$R"
awk '{ printf "%s\r\n", $0 }' "$R/$P" > "$W/crlf.tmp" && cp "$W/crlf.tmp" "$R/$P"
commit "$R" "CRLF line endings"
grep -q "$(printf '\r')" "$R/$P" || { echo "SETUP FAIL: $R/$P has no CRLF"; exit 2; }
refresh "$R"
t "CRLF: exit 0" test "$(rc)" = 0
t "CRLF: prints refreshed .github/pull_request_template.md" says refreshed
t "CRLF: the template is now the plugin's, byte for byte" cmp -s "$NEWT" "$R/$P"
t "CRLF: the template's Brief: line is the new one" new_line "$R/$P"
t "CRLF: .template-hashes has copied <new sha>" has_hash "$R" copied "$NEWT"
t "CRLF: one hash line for the template" one_hash "$R"

# 3. Edited copy (DoD 3): edited, a diff whose + line is the new Brief: line; nothing changed.
R=$W/edited; base_onboard "$R"
printf '\nProject note.\n' >> "$R/$P"; commit "$R" "edit the template"
refresh "$R"; echo "info: edited exit code $(rc)"
t "edited: prints edited    .github/pull_request_template.md" says edited
t "edited: a unified diff follows" grep -q '^@@' "$W/out"
t "edited: the diff's only + line is the new Brief: line" only_new_plus
t "edited: the diff takes out the old Brief: line" minus_old
t "edited: the template still has the old Brief: line" old_line "$R/$P"
t "edited: nothing in the repo changed (template and .template-hashes as committed)" clean "$R"

# 4. --force on the edited copy (DoD 5): refreshed.
refresh "$R" --force
t "edited, --force: exit 0" test "$(rc)" = 0
t "edited, --force: prints refreshed" says refreshed
t "edited, --force: the template is the plugin's" cmp -s "$NEWT" "$R/$P"
t "edited, --force: the template's Brief: line is the new one" new_line "$R/$P"
t "edited, --force: .template-hashes has copied <new sha>" has_hash "$R" copied "$NEWT"
t "edited, --force: one hash line for the template" one_hash "$R"

# 5. Declined at the base's template (DoD 4): the plugin's copy changed, so edited with the diff;
#    then --decline: declined; a further refresh: declined.
R=$W/declined; base_onboard "$R"
printf '\nProject note.\n' >> "$R/$P"; commit "$R" "edit the template"
base_refresh "$R" --decline
if ! says declined || ! has_hash "$R" declined "$OLDT"; then
  echo "SETUP FAIL: $BASE's refresh --decline did not record declined"; cat "$W/out"; exit 2
fi
commit "$R" "declined at the base"
refresh "$R"; echo "info: declined at $BASE, edited exit code $(rc)"
t "declined at $BASE: prints edited, since the plugin's copy changed" says edited
t "declined at $BASE: a unified diff follows" grep -q '^@@' "$W/out"
t "declined at $BASE: the diff's only + line is the new Brief: line" only_new_plus
t "declined at $BASE: the template still has the old Brief: line" old_line "$R/$P"
t "declined at $BASE: nothing in the repo changed" clean "$R"
refresh "$R" --decline
t "declined again: prints declined" says declined
t "declined again: records declined with the new plugin copy's sha" has_hash "$R" declined "$NEWT"
t "declined again: one hash line for the template" one_hash "$R"
t "declined again: the template is left alone" untouched "$R"
commit "$R" "declined again"
refresh "$R"
t "declined, a further refresh: prints declined" says declined
t "declined, a further refresh: nothing changed" clean "$R"

# 6. Deleted template (DoD 6). With its hash line kept, as files leaves it: removed, not
#    recreated (SYSTEM.md, API.md); --force recreates it. With no hash line: refreshed, recreated.
R=$W/deleted; base_onboard "$R"
rm "$R/$P"; commit "$R" "delete the template"
refresh "$R"
t "deleted, hash line kept: prints removed" says removed
t "deleted, hash line kept: the template is not recreated" test ! -e "$R/$P"
t "deleted, hash line kept: recorded removed with the old copy's sha" has_hash "$R" removed "$OLDT"
t "deleted, hash line kept: one hash line for the template" one_hash "$R"
commit "$R" "refresh records removed"
refresh "$R" --force
t "deleted, --force: exit 0" test "$(rc)" = 0
t "deleted, --force: prints refreshed" says refreshed
t "deleted, --force: the file recreated as the plugin's" cmp -s "$NEWT" "$R/$P"
t "deleted, --force: the template's Brief: line is the new one" new_line "$R/$P"
t "deleted, --force: .template-hashes has copied <new sha>" has_hash "$R" copied "$NEWT"
t "deleted, --force: one hash line for the template" one_hash "$R"

R=$W/deleted-unlisted; base_onboard "$R"
rm "$R/$P"
grep -v " $P\$" "$R/$HF" > "$W/h" && cp "$W/h" "$R/$HF"; commit "$R" "delete the template and its hash"
refresh "$R"
t "deleted, no hash line: exit 0" test "$(rc)" = 0
t "deleted, no hash line: prints refreshed" says refreshed
t "deleted, no hash line: the file recreated as the plugin's" cmp -s "$NEWT" "$R/$P"
t "deleted, no hash line: the template's Brief: line is the new one" new_line "$R/$P"
t "deleted, no hash line: .template-hashes has copied <new sha>" has_hash "$R" copied "$NEWT"

# 7. A new repo onboarded with this branch's files (DoD 7).
R=$W/new; onboard "$R" "$NEWB"
t "new repo: the template's Brief: line is the new one" new_line "$R/$P"
t "new repo: the template is the plugin's" cmp -s "$NEWT" "$R/$P"
t "new repo: .template-hashes has copied <new sha>" has_hash "$R" copied "$NEWT"
refresh "$R"
t "new repo, refresh: prints current" says current

# 8. The text: pr-gate.md (DoD 8), CHANGELOG (DoD 10), and what must not change.
SEP=$(printf '\001')
sentences() { tr '\n' ' ' < "$1" | tr -s ' ' | sed "s/\\([.?!]\\) \\([A-Z]\\)/\\1$SEP\\2/g" | tr "$SEP" '\n'; }
gate_brief() { sentences "$GATE" | grep -F '`Brief:`' | grep -F '`Approval:`' | grep -Eiq 'area by area|per area|each area|every area'; }
gate_design() { sentences "$GATE" | grep -F '`Design:`' | grep -Fq 'DESIGN.md'; }
bullet() { awk '/^## /{ u = ($0 == "## Unreleased") } u && /^- \*\*Brief approval by area\.\*\*/{ p = 1; print; next } p && (/^- / || /^## /){ p = 0 } p' "$1" | tr '\n' ' ' | tr -s ' '; }
t "pr-gate.md: a sentence fills the PR's Brief: line from the brief's Approval: line, area by area" gate_brief
t "regression: pr-gate.md still fills the Design: line from DESIGN.md" gate_design
t "CHANGELOG: the Brief approval by area. bullet is under Unreleased" test -n "$(bullet "$CL")"
t "CHANGELOG: the bullet mentions the PR template" sh -c 'echo "$1" | grep -qi "PR template"' - "$(bullet "$CL")"
t "CHANGELOG: the bullet says an edited copy shows the diff" sh -c 'echo "$1" | grep -qi "diff"' - "$(bullet "$CL")"
if git -C "$REPO" rev-parse -q --verify "$BASE^{commit}" >/dev/null 2>&1; then
  git -C "$REPO" show "$BASE:CHANGELOG.md" > "$W/base-changelog.md"
  tmpl_changes() { { git -C "$REPO" diff --name-only "$BASE" -- plugins/ada/templates; git -C "$REPO" ls-files --others --exclude-standard -- plugins/ada/templates; } | sort -u; }
  bin_new() { git -C "$REPO" ls-files --others --exclude-standard -- plugins/ada/bin; }
  t "baseline $BASE: the CHANGELOG bullet didn't mention the PR template" not sh -c 'echo "$1" | grep -qi "PR template"' - "$(bullet "$W/base-changelog.md")"
  t "skills/begin/SKILL.md unchanged since $BASE (its size limit)" git -C "$REPO" diff --quiet "$BASE" -- plugins/ada/skills/begin/SKILL.md
  t "plugins/ada/bin unchanged since $BASE (no change to ada-onboard)" git -C "$REPO" diff --quiet "$BASE" -- plugins/ada/bin
  t "plugins/ada/bin: no new file" test -z "$(bin_new)"
  t "templates: only the PR template changed since $BASE (others out of scope)" test "$(tmpl_changes)" = "plugins/ada/templates/repo/$P"
  t "no version change since $BASE (P31)" git -C "$REPO" diff --quiet "$BASE" -- plugins/ada/.claude-plugin/plugin.json .claude-plugin/marketplace.json
  t "CHANGELOG: no new version heading since $BASE" not sh -c 'git -C "$1" diff "$2" -- CHANGELOG.md | grep -Eq "^\+## +v?[0-9]"' - "$REPO" "$BASE"
else
  echo "SKIP the checks against $BASE: $REPO has no git history with it (run them with the local bash)"
fi

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0, under the local bash (no `SKIP` line) and bash
3.2 (one `SKIP` line, for the checks against `BASE`). The `info:` lines record the exit codes for
`edited`, which the docs don't fix; put them in the Run record.

The break-it run in step 4 (this checkout's text, the plugin from before Unit G) exits 1 with
`FAIL` for:

- section 0: "the Brief: line is the brief's per-area line", "the old Brief: line is gone", and
  both "against `BASE`" checks;
- every "the template's Brief: line is the new one" check (`untouched`, `CRLF`, `edited,
  --force`, `deleted, --force`, `deleted, no hash line`, `new repo`);
- `untouched`: "prints refreshed", "the old copy's hash is gone" and "only the template and
  .template-hashes changed" (the copy is already the plugin's: `current`, nothing changed);
- `CRLF`: "prints refreshed" and "byte for byte" (the same text with CRLF endings: `current`,
  the file left with CRLF);
- `edited`: "the diff's only + line" and "the diff takes out the old Brief: line";
- `declined at`: "prints edited", "a unified diff follows" and "the diff's only + line" (the
  declined sha is the plugin's, so it prints `declined`);
- the `pr-gate.md` per-area check.

21 `FAIL` lines in all with this branch's `CHANGELOG.md` (tried on 24 Nov 2026). The `removed` checks, the
`--force` and no-hash-line recreations, the `declined again` checks, the `Design:` regression and
the checks against `BASE` PASS, so a `FAIL` in them on this branch is about refresh or the diff
itself, not the new line. The CHANGELOG checks read this checkout's `CHANGELOG.md`, not `PLUGIN`.

Sources: Unit G's brief: Decisions (the line's exact wording; filled from the brief's `Approval:`
line; no change to `ada-onboard`), Constraints (the `Brief:` line only; `SKILL.md` untouched; no
version change; the CHANGELOG bullet; the surfaces: untouched, edited, declined, CRLF and deleted),
Definition of done 1 to 10. `SYSTEM.md`, "ada-onboard: templates, settings and refresh": the
order of `current`, `removed`, `refreshed`, `declined`, `edited`; `--force`; `declined <sha of
plugin copy>`; hashes of LF-normalised text; a missing template with a hash line is `removed`
until `--force`, one with no line is `refreshed`. `API.md`, `ada-onboard refresh`: one line per
template, the word padded to 9, a unified diff after `edited`.

The deleted case differs from the brief's Definition of done 6 ("Deleted template: `refreshed`,
the file recreated"): a template deleted with its `.template-hashes` line kept prints `removed`
and is recreated only by `--force`, as `SYSTEM.md` and `API.md` say and the brief's "no change to
`ada-onboard`" keeps. The brief's `refreshed` holds for a deleted template with no hash line and
after `--force`; both are checked.

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`git -C <scratch>/<case> status`, `git diff`, and `.claude/team/.template-hashes` there.

## Regression

- Refresh's outcomes don't move: `current` on a second refresh, `declined` kept, `removed` kept
  until `--force`, a missing template with no hash line `refreshed`.
- The PR template changes in its `Brief:` line only, in the same place: every other line is as
  at `baf0204` (section 0).
- `pr-gate.md` still fills `Design:` from `DESIGN.md`; `SKILL.md`, `plugins/ada/bin` and the
  other templates unchanged; no version change (section 8).
- Also run `refresh-adds-pr-template-lines.md` (older repos, before #30 and at 0.14.1),
  `files-keeps-deleted-templates-deleted.md` and `08-session-flow/begin-split-and-models.md`.
