# `ada-onboard refresh` gives an onboarded repo the PR template's `Design:` line

- Area: 02 Onboarding and refresh (`ada-onboard`); 11 Templates; 12 Platforms
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 21 Oct 2026, 28 PASS, 0 failed under bash 5 and Docker bash 3.2; broken copy fails as it should (see the PR)

## Precondition

- This checkout (`REPO`), with `origin/next-release` fetched. The script onboards scratch repos
  with the **released** `ada-onboard` exported from there (the old, unedited PR template), then
  refreshes them with this checkout's `ada-onboard`, run by path.
- `git`, `tar`, `cmp`, and `sha256sum` or `shasum`. Python 3.8+ (for `ada-onboard`).
- Nothing in this checkout is touched; scratch repos live under `$TMPDIR`.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`. For bash 3.2, use the Docker command in
   `tests/functional/05-handoff-files/design-md-copied-kept-archived.md` with this `check.sh`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, delete the `Design:` line from
   the copy's `templates/repo/.github/pull_request_template.md`, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` lines.

```bash
#!/bin/bash
# refresh brings the Design: line to onboarded repos. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO, BASE (default origin/next-release), PLUGIN (default $REPO/plugins/ada), OLD_PLUGIN.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
W=$(mktemp -d)
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
NEWB=$PLUGIN/bin/ada-onboard
OLDB=$OLD_PLUGIN/bin/ada-onboard
NEWT=$PLUGIN/templates/repo/.github/pull_request_template.md
OLDT=$OLD_PLUGIN/templates/repo/.github/pull_request_template.md
P=.github/pull_request_template.md
HF=.claude/team/.template-hashes
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
sha() { if command -v sha256sum >/dev/null 2>&1; then tr -d '\r' < "$1" | sha256sum | cut -d' ' -f1; else tr -d '\r' < "$1" | shasum -a 256 | cut -d' ' -f1; fi; }
commit() { git -C "$1" add -A && git -C "$1" -c user.name=t -c user.email=t@example.invalid commit -q -m "$2"; }
onboard() {   # a scratch repo onboarded by the released plugin, committed
  mkdir -p "$1" && git -C "$1" init -q
  (cd "$1" && bash "$OLDB" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/onboard.out" 2>&1
  commit "$1" onboarded
  if ! grep -Fxq "copied $(sha "$OLDT") $P" "$1/$HF"; then
    echo "SETUP FAIL: $1 has no untouched-copy hash for $P"; cat "$W/onboard.out"; exit 2
  fi
}
refresh() { d=$1; shift; (cd "$d" && bash "$NEWB" refresh "$@") > "$W/out" 2>&1; echo $? > "$W/rc"; }
rc() { cat "$W/rc"; }
first() { head -1 "$W/out"; }
line_is() { first | grep -Eq "^$1[[:space:]]+\\.github/pull_request_template\\.md\$"; }
has_hash() { grep -Fxq "$2 $(sha "$3") $P" "$1/$HF"; }
one_hash() { test "$(grep -c " $P\$" "$1/$HF")" = 1; }
untouched() { test -z "$(git -C "$1" status --porcelain -- .github)"; }
design_line() { grep -q 'Design:' "$1"; }
design_values() { grep -qF 'Design: <the Design artifact link | text only, in the brief | none>' "$1"; }

# 0. The templates under test.
t "plugin PR template has a Design: line" design_line "$NEWT"
t "its Design: line offers the three values (amended, Q5)" design_values "$NEWT"
t "baseline: the released PR template has no Design: line" not design_line "$OLDT"

# 1. Old, unedited template: refreshed, and the hash follows.
R=$W/unedited; onboard "$R"
refresh "$R"
t "unedited: exit 0" test "$(rc)" = 0
t "unedited: prints refreshed for the PR template" line_is refreshed
t "unedited: the template is now the plugin's, byte for byte" cmp -s "$NEWT" "$R/$P"
t "unedited: the template has the Design: line" design_line "$R/$P"
t "unedited: hash rewritten to the new copy" has_hash "$R" copied "$NEWT"
t "unedited: one hash line for the template" one_hash "$R"
t "unedited: only the template and the hashes changed" \
  test "$(git -C "$R" status --porcelain | sort)" = "$(printf ' M %s\n M %s\n' "$HF" "$P")"
refresh "$R"
t "unedited, second refresh: prints current" line_is current
t "unedited, second refresh: template unchanged" cmp -s "$NEWT" "$R/$P"

# 2. Old template, edited by the project: edited, with a diff; file left alone.
R=$W/edited; onboard "$R"
printf '\nProject note.\n' >> "$R/$P"; commit "$R" "edit the template"
refresh "$R"; echo "info: edited exit code $(rc)"
t "edited: prints edited for the PR template" line_is edited
t "edited: a unified diff follows" grep -q '^@@' "$W/out"
t "edited: the diff shows the Design: line arriving" grep -q '^+.*Design:' "$W/out"
t "edited: the template is left alone" untouched "$R"
t "edited: still no Design: line" not design_line "$R/$P"
# Regression: --force refreshes an edited copy.
refresh "$R" --force
t "edited, --force: prints refreshed" line_is refreshed
t "edited, --force: template is the plugin's" cmp -s "$NEWT" "$R/$P"

# 3. Regression: --decline, then a plain refresh, both say declined.
R=$W/declined; onboard "$R"
printf '\nProject note.\n' >> "$R/$P"; commit "$R" "edit the template"
refresh "$R" --decline
t "decline: prints declined" line_is declined
t "decline: records declined with the plugin copy's hash" has_hash "$R" declined "$NEWT"
t "decline: the template is left alone" untouched "$R"
refresh "$R"
t "declined, plain refresh: prints declined" line_is declined

# 4. Regression: no hash for the template: unknown, with a diff; file left alone.
R=$W/unknown; onboard "$R"
printf '\nProject note.\n' >> "$R/$P"
grep -v " $P\$" "$R/$HF" > "$W/h" && cp "$W/h" "$R/$HF"; commit "$R" "edit, forget the hash"
refresh "$R"; echo "info: unknown exit code $(rc)"
t "no hash: prints unknown" line_is unknown
t "no hash: a unified diff follows" grep -q '^@@' "$W/out"
t "no hash: the template is left alone" untouched "$R"

# 5. Regression: a deleted template comes back as the plugin's.
R=$W/deleted; onboard "$R"
rm "$R/$P"; commit "$R" "delete the template"
refresh "$R"
t "deleted: prints refreshed" line_is refreshed
t "deleted: template recreated as the plugin's" cmp -s "$NEWT" "$R/$P"

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0. The `info:` lines record the exit codes for
`edited` and `unknown`, which the docs don't fix; put them in the Run record. The broken copy in
step 3 prints `FAIL` for the Design: line and for the refreshed template.

Sources: the brief's Definition of Done (an old, unedited template prints `refreshed` and gains
the `Design:` line; an edited one prints `edited`; the line, as amended (Q5), is
`Design: <the Design artifact link | text only, in the brief | none>`);
`SYSTEM.md`, ada-onboard (the order of `current`, `refreshed`, `declined`, `edited`, `unknown`; the
hash rewritten on `refreshed`; `declined <sha of plugin copy>`; a missing template recreated);
`API.md` (one line per template, the word padded to 9, a unified diff after `edited` and
`unknown`).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`git -C <scratch>/<case> status`, `git diff`, and `.claude/team/.template-hashes` there.

## Regression

Sections 3 to 5 and the `--force` lines are the refresh outcomes this change must not move. Also
check, once by eye, that `git diff origin/next-release -- plugins/ada/templates/repo/.github/pull_request_template.md`
only adds lines: every other line of the PR template that onboarded repos already have stays as it
was.
