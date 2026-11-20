# A template the project deleted stays deleted: `ada-onboard files` and `refresh` record it `removed`

- Area: 02 Onboarding and refresh (`ada-onboard`); 12 Platforms
- Asana: https://app.asana.com/1/100438094841948/project/1204538801343792/task/1220575393845404
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 20 Nov 2026, 0 failed (bash 5.x local, bash 3.2 in Docker by the developer); break-it against 59499fb: 46 failed as expected (see the PR)

## Precondition

- This checkout (`REPO`). The script onboards scratch repos with this checkout's `ada-onboard`,
  run by path, deletes templates from them, and runs `files` and `refresh` again.
- `git`, `cmp`, `diff`, `awk`, and `sha256sum` or `shasum`. Python 3.8+ (for `ada-onboard`).
- Nothing in this checkout is touched; scratch repos live under `$TMPDIR`.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. bash 3.2, with Docker and the `bash:3.2` image (it has no git, so the container adds it; that
   needs the network). With `S` the scratch folder holding `check.sh`:

   ```sh
   docker run --rm -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 diffutils >/dev/null && bash --version | head -1 && REPO=/plugin PLUGIN=/plugin bash /s/check.sh'
   ```

   The docs checks (section 12) need the whole checkout and are skipped there (an `info:` line);
   step 2 runs them. On a Mac, `REPO=<this checkout> /bin/bash check.sh` uses the system bash 3.2.
4. Break it on purpose, once: export the plugin as released, before this change, and run the
   script against it:

   ```sh
   mkdir -p "$S/old" && git -C <this checkout> archive 59499fb plugins/ada | tar -x -C "$S/old"
   REPO=<this checkout> PLUGIN="$S/old/plugins/ada" bash check.sh
   ```

   It must print `FAIL` lines (see Expected).

```bash
#!/bin/bash
# A template the project deleted stays deleted: neither `ada-onboard files` nor `refresh` copies
# it back, and .claude/team/.template-hashes records it as `removed <sha> <path>`, keeping the sha
# the entry held. PASS/FAIL per check; exit 1 if any FAIL, 2 on a setup error.
# Env: REPO (this checkout), PLUGIN (default $REPO/plugins/ada).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
B=$PLUGIN/bin/ada-onboard
T=$PLUGIN/templates/repo
HF=.claude/team/.template-hashes
P=.github/pull_request_template.md   # the one template refresh handles; no placeholders
S=tests/presstest/site_test.js       # a template with no placeholders: its sha is the template's
C=CLAUDE.md                          # a template with {{PROJECT_NAME}}: its sha is of filled text
W=$(mktemp -d "${TMPDIR:-/tmp}/keep-deleted.XXXXXX") || exit 2
for f in "$B" "$T/$P" "$T/$S" "$T/$C"; do
  [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }
done
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
sha() { if command -v sha256sum >/dev/null 2>&1; then tr -d '\r' < "$1" | sha256sum | cut -d' ' -f1; else tr -d '\r' < "$1" | shasum -a 256 | cut -d' ' -f1; fi; }
commit() { git -C "$1" add -A && git -C "$1" -c user.name=t -c user.email=t@example.invalid commit -q -m "$2" >/dev/null; }
run_files()   { (cd "$1" && bash "$B" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/out" 2>&1; echo $? > "$W/rc"; }
run_refresh() { d=$1; shift; (cd "$d" && bash "$B" refresh "$@") > "$W/out" 2>&1; echo $? > "$W/rc"; }
rc()       { cat "$W/rc"; }
re()       { printf '%s' "$1" | sed 's/\./\\./g'; }
said()     { grep -Fxq "$1" "$W/out"; }                                  # this exact output line
said_word(){ grep -Eq "^$1[[:space:]]+$(re "$2")\$" "$W/out"; }          # <word> <spaces> <path>
first_is() { head -1 "$W/out" | grep -Eq "^$1[[:space:]]+$(re "$2")\$"; } # refresh's first line
has_diff() { grep -q '^@@' "$W/out"; }
no_trace() { not grep -q 'Traceback' "$W/out"; }
entry()    { awk -v p="$2" '$3 == p' "$1/$HF"; }                         # the hash line(s) for a path
entry_is() { test "$(entry "$1" "$2")" = "$3"; }                         # exactly one line, this text
hsha()     { awk -v p="$2" '$3 == p { print $2 }' "$1/$HF"; }
others()   { awk -v a="$2" -v b="${3:-}" '$3 != a && $3 != b' "$1"; }    # a hash file less one or two paths
pos()      { awk -v p="$2" '$3 == p { print NR }' "$1"; }
missing()  { test ! -e "$1"; }
clean()    { test -z "$(git -C "$1" status --porcelain)"; }
only_hf()  { test "$(git -C "$1" status --porcelain)" = " M $HF"; }
drop()     { others "$1/$HF" "$2" > "$W/h" && cp "$W/h" "$1/$HF"; }      # delete a path's line by hand
set_entry(){ awk -v p="$2" -v l="$3" '$3 == p { $0 = l } { print }' "$1/$HF" > "$W/h" && cp "$W/h" "$1/$HF"; }
onboard() {   # onboard <dir>: a scratch repo onboarded by this checkout's files, committed
  mkdir -p "$1" && git -C "$1" init -q
  run_files "$1"; commit "$1" onboarded
  for p in "$P" "$S" "$C"; do
    if [ "$(entry "$1" "$p" | cut -d' ' -f1)" != copied ]; then
      echo "SETUP FAIL: $1 has no copied entry for $p"; cat "$W/out"; exit 2
    fi
  done
}

# 0. Control: files run again in an untouched onboarded repo.
R=$W/control; onboard "$R"
run_files "$R"; RC0=$(rc)
echo "info: files exit code with nothing to copy: $RC0"
t "regression, control, files again: kept for $S" said "kept     $S"
t "regression, control, files again: nothing changed" clean "$R"
t "regression: an untouched copy's entry is copied <the template's sha>" entry_is "$R" "$S" "copied $(sha "$T/$S") $S"

# 1. files, a listed template deleted and committed (DoD 1, 5).
R=$W/deleted; onboard "$R"
SHA_S=$(hsha "$R" "$S")
rm "$R/$S"; commit "$R" "delete $S"
cp "$R/$HF" "$W/h0"
run_files "$R"
t "deleted: prints 'removed  $S'" said "removed  $S"
t "deleted: no created or kept line for it" not grep -Eq "^(created|kept)[[:space:]]+$(re "$S")\$" "$W/out"
t "deleted: same exit code as a run with nothing to copy ($RC0)" test "$(rc)" = "$RC0"
t "deleted: no Python traceback" no_trace
t "deleted: the file is still missing" missing "$R/$S"
t "deleted: entry is 'removed <the sha it had> $S'" entry_is "$R" "$S" "removed $SHA_S $S"
t "deleted: every other hash line keeps its text and order" test "$(others "$W/h0" "$S")" = "$(others "$R/$HF" "$S")"
t "deleted: the rewritten entry stays on its line" test "$(pos "$W/h0" "$S")" = "$(pos "$R/$HF" "$S")"
t "deleted: only .template-hashes changed" only_hf "$R"
t "regression, deleted: the PR template still prints kept" said "kept     $P"
t "regression, deleted: CLAUDE.md still prints kept" said "kept     $C"
commit "$R" "files records removed"

# 2. files a second time (DoD 2), and the other readers of a removed line.
run_files "$R"
t "deleted, files again: prints 'removed  $S'" said "removed  $S"
t "deleted, files again: the file is still missing" missing "$R/$S"
t "deleted, files again: git status clean (hash file unchanged)" clean "$R"
run_refresh "$R"
t "removed line for another path, refresh: exit 0" test "$(rc)" = 0
t "removed line for another path, refresh: the PR template is current" first_is current "$P"
t "removed line for another path, refresh: no Python traceback" no_trace
t "removed line for another path, refresh: nothing changed" clean "$R"
(cd "$R" && bash "$B" check) > "$W/check-a" 2>&1; echo "exit $?" >> "$W/check-a"
drop "$R" "$S"
(cd "$R" && bash "$B" check) > "$W/check-b" 2>&1; echo "exit $?" >> "$W/check-b"
git -C "$R" checkout -q -- "$HF"
t "removed line, check: same output and exit code as without the line" cmp -s "$W/check-a" "$W/check-b"
t "removed line, check: no Python traceback" not grep -q 'Traceback' "$W/check-a"

# 3. files: the sha the entry held is kept, not recomputed: a template with filled placeholders,
#    and a declined entry. Two entries rewritten in one run.
R=$W/two; onboard "$R"
SHA_C=$(hsha "$R" "$C")
t "setup: CLAUDE.md's entry is not the plugin template's sha (placeholders filled)" not test "$SHA_C" = "$(sha "$T/$C")"
printf 'a plugin copy the project declined\n' > "$W/dec"; SHA_D=$(sha "$W/dec")
set_entry "$R" "$P" "declined $SHA_D $P"
rm "$R/$C" "$R/$P"; commit "$R" "decline the PR template; delete it and CLAUDE.md"
cp "$R/$HF" "$W/h0"
run_files "$R"
t "two deleted: prints 'removed  $C'" said "removed  $C"
t "two deleted: prints 'removed  $P'" said "removed  $P"
t "two deleted: CLAUDE.md's entry keeps the sha it held" entry_is "$R" "$C" "removed $SHA_C $C"
t "two deleted: a declined entry becomes removed with the declined sha" entry_is "$R" "$P" "removed $SHA_D $P"
t "two deleted: CLAUDE.md not written" missing "$R/$C"
t "two deleted: PR template not written" missing "$R/$P"
t "two deleted: every other hash line keeps its text and order" test "$(others "$W/h0" "$C" "$P")" = "$(others "$R/$HF" "$C" "$P")"
t "two deleted: only .template-hashes changed" only_hf "$R"
commit "$R" "files records removed"
run_refresh "$R"
t "already removed by files, refresh: prints removed" first_is removed "$P"
t "already removed by files, refresh: no file written" missing "$R/$P"
t "already removed by files, refresh: entry unchanged" entry_is "$R" "$P" "removed $SHA_D $P"
t "already removed by files, refresh: git status clean" clean "$R"

# 4. files: no entry and no file is a new template, copied as before (DoD 3).
R=$W/unlisted; onboard "$R"
rm "$R/$S"; drop "$R" "$S"; commit "$R" "delete $S and its hash line"
cp "$R/$HF" "$W/h0"
run_files "$R"
t "no entry: prints 'created  $S'" said "created  $S"
t "no entry: no removed line" not said_word removed "$S"
t "no entry: copied byte for byte" cmp -s "$T/$S" "$R/$S"
t "no entry: entry is 'copied <sha> $S'" entry_is "$R" "$S" "copied $(sha "$T/$S") $S"
t "no entry: every other hash line keeps its text and order" test "$(cat "$W/h0")" = "$(others "$R/$HF" "$S")"

# 5. files: a removed entry whose file was put back by hand (DoD 4).
R=$W/deleted
cp "$T/$S" "$R/$S"; commit "$R" "put $S back"
run_files "$R"
t "put back: prints 'kept     $S'" said "kept     $S"
t "put back: no removed line" not said_word removed "$S"
t "put back: entry unchanged, still removed" entry_is "$R" "$S" "removed $SHA_S $S"
t "put back: git status clean" clean "$R"
printf '// project edit\n' >> "$R/$S"; commit "$R" "edit $S"
run_files "$R"
t "put back, edited: prints 'kept     $S'" said "kept     $S"
t "put back, edited: entry unchanged, still removed" entry_is "$R" "$S" "removed $SHA_S $S"
t "put back, edited: git status clean" clean "$R"

# 6. refresh: the PR template deleted and listed (DoD 6, 7, 8).
R=$W/r-deleted; onboard "$R"
SHA_P=$(hsha "$R" "$P")
rm "$R/$P"; commit "$R" "delete the PR template"
cp "$R/$HF" "$W/h0"
run_refresh "$R"
echo "info: refresh exit code for removed: $(rc)"
t "refresh, deleted: prints removed for the PR template" first_is removed "$P"
t "refresh, deleted: the line is exactly 'removed   $P' (three spaces)" said "removed   $P"
t "refresh, deleted: no Python traceback" no_trace
t "refresh, deleted: no file written" missing "$R/$P"
t "refresh, deleted: entry is 'removed <the sha it had> $P'" entry_is "$R" "$P" "removed $SHA_P $P"
t "refresh, deleted: every other hash line keeps its text and order" test "$(others "$W/h0" "$P")" = "$(others "$R/$HF" "$P")"
t "refresh, deleted: only .template-hashes changed" only_hf "$R"
commit "$R" "refresh records removed"
run_refresh "$R"
t "refresh, deleted, second run: prints removed" first_is removed "$P"
t "refresh, deleted, second run: the line is exactly 'removed   $P' (three spaces)" said "removed   $P"
t "refresh, deleted, second run: no file written" missing "$R/$P"
t "refresh, deleted, second run: git status clean" clean "$R"
run_refresh "$R" --decline
t "refresh --decline, removed: prints removed" first_is removed "$P"
t "refresh --decline, removed: the line is exactly 'removed   $P' (three spaces)" said "removed   $P"
t "refresh --decline, removed: entry stays removed" entry_is "$R" "$P" "removed $SHA_P $P"
t "refresh --decline, removed: no file written" missing "$R/$P"
t "refresh --decline, removed: git status clean" clean "$R"
run_refresh "$R" --force
t "refresh --force, removed: prints refreshed" first_is refreshed "$P"
t "refresh --force, removed: writes the plugin's template" cmp -s "$T/$P" "$R/$P"
t "refresh --force, removed: entry is 'copied <sha of what it wrote>'" entry_is "$R" "$P" "copied $(sha "$T/$P") $P"
commit "$R" "bring the PR template back"
run_refresh "$R"
t "refresh after --force: prints current" first_is current "$P"
t "refresh after --force: git status clean" clean "$R"

# 7. refresh --decline on a copied entry whose file is missing: removed comes first.
R=$W/r-decline; onboard "$R"
SHA_P=$(hsha "$R" "$P")
rm "$R/$P"; commit "$R" "delete the PR template"
run_refresh "$R" --decline
t "refresh --decline, deleted and listed copied: prints removed" first_is removed "$P"
t "refresh --decline, deleted and listed copied: the line is exactly 'removed   $P' (three spaces)" said "removed   $P"
t "refresh --decline, deleted and listed copied: entry is removed <the sha it had>" entry_is "$R" "$P" "removed $SHA_P $P"
t "refresh --decline, deleted and listed copied: no file written" missing "$R/$P"

# 8. refresh --force on a copied entry whose file is missing: --force comes first.
R=$W/r-force; onboard "$R"
rm "$R/$P"; commit "$R" "delete the PR template"
run_refresh "$R" --force
t "refresh --force, deleted and listed copied: prints refreshed" first_is refreshed "$P"
t "refresh --force, deleted and listed copied: writes the plugin's template" cmp -s "$T/$P" "$R/$P"
t "refresh --force, deleted and listed copied: entry is copied <sha>" entry_is "$R" "$P" "copied $(sha "$T/$P") $P"

# 9. refresh on a declined entry whose file is missing: removed, with the declined sha.
R=$W/r-declined; onboard "$R"
set_entry "$R" "$P" "declined $SHA_D $P"
rm "$R/$P"; commit "$R" "decline and delete the PR template"
run_refresh "$R"
t "refresh, deleted and declined: prints removed" first_is removed "$P"
t "refresh, deleted and declined: the line is exactly 'removed   $P' (three spaces)" said "removed   $P"
t "refresh, deleted and declined: entry is removed <the declined sha>" entry_is "$R" "$P" "removed $SHA_D $P"
t "refresh, deleted and declined: no file written" missing "$R/$P"

# 10. refresh, the PR template missing with no entry: refreshed, as before (DoD 9).
R=$W/r-unlisted; onboard "$R"
rm "$R/$P"; drop "$R" "$P"; commit "$R" "delete the PR template and its hash line"
run_refresh "$R"
t "regression, refresh, missing and unlisted: prints refreshed" first_is refreshed "$P"
t "regression, refresh, missing and unlisted: writes the plugin's template" cmp -s "$T/$P" "$R/$P"
t "regression, refresh, missing and unlisted: entry is copied <sha>" entry_is "$R" "$P" "copied $(sha "$T/$P") $P"

# 11. refresh, a removed entry whose file was put back: read as no entry, entry left as it is.
R=$W/r-back; onboard "$R"
SHA_P=$(hsha "$R" "$P")
rm "$R/$P"; commit "$R" "delete the PR template"
run_refresh "$R"; commit "$R" "refresh records removed"
t "setup: the entry is removed" entry_is "$R" "$P" "removed $SHA_P $P"
cp "$T/$P" "$R/$P"; commit "$R" "put the PR template back"
run_refresh "$R"
t "put back, same text, refresh: prints current" first_is current "$P"
t "put back, same text, refresh: entry unchanged, still removed" entry_is "$R" "$P" "removed $SHA_P $P"
t "put back, same text, refresh: git status clean" clean "$R"
printf '\nProject note.\n' >> "$R/$P"; commit "$R" "edit the PR template"
run_refresh "$R"
t "put back, edited, refresh: prints unknown" first_is unknown "$P"
t "put back, edited, refresh: a unified diff follows" has_diff
t "put back, edited, refresh: file and entry left alone (git status clean)" clean "$R"
t "put back, edited, refresh: entry still removed" entry_is "$R" "$P" "removed $SHA_P $P"
run_files "$R"
t "put back, edited, files: prints 'kept     $P'" said "kept     $P"
t "put back, edited, files: git status clean" clean "$R"

# 12. Docs (DoD 12): only with the whole checkout.
if [ -f "$REPO/.claude/team/SYSTEM.md" ]; then
  FMT='<copied|declined|removed> <sha256> <path>'
  FMT_T='<copied\|declined\|removed> <sha256> <path>'
  sys_section() { awk '/^### /{ p = ($0 ~ /^### ada-onboard: templates, settings and refresh/) } p' "$REPO/.claude/team/SYSTEM.md"; }
  step10() { awk '/^10\. Run `ada-onboard refresh`/{ p = 1 } /^(#|[0-9]+\. )/ && !/^10\. /{ p = 0 } p' "$PLUGIN/skills/begin/SKILL.md"; }
  row02() { grep '^| 02 |' "$REPO/.claude/team/TEST-AREAS.md"; }
  t "docs: script header gives $FMT" grep -Fq "$FMT" "$B"
  t "docs: SYSTEM.md's data table gives the format with removed" grep -Fq "$FMT_T" "$REPO/.claude/team/SYSTEM.md"
  t "docs: SYSTEM.md's ada-onboard section names removed" eval 'sys_section | grep -q "removed"'
  t "docs: SYSTEM.md no longer says refresh recreates a deleted PR template" not grep -Fq 'recreates the PR template if the project deleted it' "$REPO/.claude/team/SYSTEM.md"
  t "docs: TEST-AREAS.md row 02 names removed" eval 'row02 | grep -q "removed"'
  t "docs: begin skill, Session start 10, names removed" eval 'step10 | grep -q "removed"'
  t "docs: API.md's files and refresh rows name removed (S103)" eval 'test "$(grep -E "^\| \`(files|refresh)" "$REPO/.claude/team/API.md" | grep -c removed)" = 2'
else
  echo "info: docs checks skipped (no $REPO/.claude/team/SYSTEM.md)"
fi

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0, under the local bash and bash 3.2 (where the docs
checks print `info: docs checks skipped`). The `info:` lines record exit codes the brief doesn't
fix: `files` with nothing to copy, and `refresh` printing `removed`; put them in the Run record.

`files`' `removed` line is exactly `removed  <path>`, two spaces, lining up with `kept     ` and
`created  ` (brief, Decisions). `refresh`'s is exactly `removed   <path>`, three spaces, lining
up with its 10-wide column (the brief's amended DoD 6): sections 6, 7 and 9 check the whole line
with `said`, as well as `first_is removed`, which accepts any run of spaces.

Against the released plugin (step 4, `59499fb`; tried on 20 Nov 2026: 46 failed) these must
FAIL: every `removed`, "still missing", "not written", "no file written" and "only
.template-hashes changed" check in sections 1 to 3, 6, 7 and 9 (it prints `created` and copies the
template back, or `refreshed` from `refresh`); "refresh --force, removed: prints refreshed" (the
template is already back, so it is `current`); the "entry unchanged, still removed" checks in
sections 5 and 11, and section 11's setup check (the entry is never `removed`); "put back, edited,
refresh: prints unknown" (it prints `edited`); every docs check. These must still PASS: section 0,
section 4 (no entry: `created`), section 8 (`--force` on a listed, missing template), section 10
(missing and unlisted: `refreshed`), and the section 2 checks of `refresh` and `check`.

Sources: the brief's Decisions (the `removed <sha> <path>` kind, the sha the entry held, copied or
declined, not recomputed; "deleted by the project" means listed, any kind, and missing; `files`
prints `removed  <path>` and rewrites only that entry, keeping the other lines and their order,
and leaves an already removed entry unchanged; `refresh`'s order: same text, `--force`, missing and
listed → `removed` before `--decline`, missing and unlisted → `refreshed`; a removed entry whose
file is back: the file wins, `kept` from `files`, `current` or `unknown` with the diff from
`refresh`, no rewrite; every other reader accepts a `removed` line; the docs in step 10, the script
header, SYSTEM.md and TEST-AREAS.md row 02) and its Definition of Done 1 to 9, 11 and 12. `API.md`
for the refresh output (one line, a unified diff after `unknown`) and S103 for its rows naming
`removed` (the brief's Builder questions, Q2).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`git -C <scratch>/<case> status`, `git diff`, and `.claude/team/.template-hashes` there.

## Regression

- Section 0: a second `files` run in an untouched repo prints `kept` and changes nothing; an
  untouched copy's entry is `copied <the template's sha>`.
- Section 4: a template with no entry and no file (one a plugin update adds) is still `created`
  and recorded `copied`.
- Sections 8 and 10: `refresh --force` and a missing, unlisted PR template still give
  `refreshed` and the plugin's copy.
- A `removed` entry for one path doesn't change `refresh` or `check` for anything else (section 2).
- `refresh-adds-pr-template-lines.md` covers `current`, `refreshed`, `edited`, `declined` and
  `unknown`, and the `removed` case for a repo onboarded by an older plugin.
