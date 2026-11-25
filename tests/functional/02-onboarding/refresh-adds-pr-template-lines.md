# `ada-onboard refresh` gives an onboarded repo the PR template's new lines: `Design:` and `Knowledge base:`

- Area: 02 Onboarding and refresh (`ada-onboard`); 11 Templates; 12 Platforms
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
  (the `Design:` line); https://app.asana.com/0/0/1220058523035214/f (the `Knowledge base:` line,
  Unit B4); https://app.asana.com/1/100438094841948/project/1204538801343792/task/1220575393845404
  (a deleted template stays deleted: the `deleted` cases)
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 24 Nov 2026, 0 failed (bash 5.x local; bash 3.2 in Docker not run this time) (see the PR)

## Precondition

- This checkout (`REPO`). The script onboards scratch repos twice over, with the `ada-onboard` of
  two pinned commits, then refreshes them with this checkout's `ada-onboard`, run by path:
  - `BASE_D`, default `05a5365^`: before #30 added the `Design:` line. A repo onboarded then has
    neither line.
  - `BASE_K`, default `75554ce`: the PR template as released (0.14.1) and as `add/knowledge-base`
    has it, before Unit B4 added the `Knowledge base:` line. A repo onboarded then has the
    `Design:` line and not the `Knowledge base:` one.

  Both are pinned, not `origin/next-release`, which gains each line once its PR merges and would
  then fail the baseline checks for that reason alone (KG-5). Both commits must be in this clone's
  history.
- `git`, `tar`, `cmp`, `diff`, and `sha256sum` or `shasum`. Python 3.8+ (for `ada-onboard`).
- Nothing in this checkout is touched; scratch repos live under `$TMPDIR`.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. bash 3.2, with Docker and the `bash:3.2` image (it has no git, so the container adds it; that
   needs the network). With `S` the scratch folder holding `check.sh`:

   ```sh
   mkdir -p "$S/old-d" "$S/old-k"
   git -C <this checkout> archive '05a5365^' plugins/ada | tar -x -C "$S/old-d"
   git -C <this checkout> archive 75554ce plugins/ada | tar -x -C "$S/old-k"
   docker run --rm -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 diffutils >/dev/null && bash --version | head -1 && REPO=/plugin PLUGIN=/plugin OLD_PLUGIN_D=/s/old-d/plugins/ada OLD_PLUGIN_K=/s/old-k/plugins/ada bash /s/check.sh'
   ```

   On a Mac, `REPO=<this checkout> /bin/bash check.sh` does the same with the system bash 3.2.
4. Break it on purpose, once: copy `plugins/ada` to a scratch folder, delete the
   `Knowledge base:` line from the copy's `templates/repo/.github/pull_request_template.md`, and
   run `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` lines.

```bash
#!/bin/bash
# refresh brings the PR template's new lines to onboarded repos: Design: (#30) and
# Knowledge base: (Unit B4). PASS/FAIL per check; exit 1 if any FAIL, 2 on a setup error.
# Env: REPO, PLUGIN (default $REPO/plugins/ada),
#   BASE_D (default 05a5365^: before the Design: line), BASE_K (default 75554ce: the template as
#   released, before the Knowledge base: line), or OLD_PLUGIN_D / OLD_PLUGIN_K: those commits'
#   plugins/ada already exported (for a run with no git history, such as bash 3.2 in Docker).
#   OLD_PLUGIN is still read as OLD_PLUGIN_D.
# Both bases are pinned, not origin/next-release, which gains each line once its PR merges (KG-5).
# Section 5: a template the project deleted stays deleted (`removed`, keeping the old copy's sha);
# only a missing template with no hash line comes back.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE_D=${BASE_D:-05a5365^}
BASE_K=${BASE_K:-75554ce}
W=$(mktemp -d "${TMPDIR:-/tmp}/refresh-lines.XXXXXX") || exit 2
export_old() { # export_old <commit> <dir>: prints the exported plugins/ada
  mkdir -p "$W/$2"
  git -C "$REPO" archive "$1" plugins/ada | tar -x -C "$W/$2" || { echo "SETUP FAIL: cannot export $1" >&2; return 1; }
  echo "$W/$2/plugins/ada"
}
OLD_PLUGIN_D=${OLD_PLUGIN_D:-${OLD_PLUGIN:-}}
[ -n "$OLD_PLUGIN_D" ] || OLD_PLUGIN_D=$(export_old "$BASE_D" old-d) || exit 2
[ -n "${OLD_PLUGIN_K:-}" ] || OLD_PLUGIN_K=$(export_old "$BASE_K" old-k) || exit 2
NEWB=$PLUGIN/bin/ada-onboard
P=.github/pull_request_template.md
NEWT=$PLUGIN/templates/repo/$P
OLDT_D=$OLD_PLUGIN_D/templates/repo/$P
OLDT_K=$OLD_PLUGIN_K/templates/repo/$P
HF=.claude/team/.template-hashes
for f in "$NEWB" "$NEWT" "$OLD_PLUGIN_D/bin/ada-onboard" "$OLDT_D" "$OLD_PLUGIN_K/bin/ada-onboard" "$OLDT_K"; do
  [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }
done
# The two lines, as the briefs word them (#30 as amended, Q5; Unit B4, Decisions, and its
# 2026-11-18 amendments: "the entry updated" -> "the entry written or updated"; "Products only; delete for a site" -> "Only for a product with a knowledge base (...); delete it otherwise", amendment 9).
DESIGN='Design: <the Design artifact link | text only, in the brief | none>'
KBL='Knowledge base: the entry written or updated (`issues/<file>`), or why none was needed. Only for a product with a knowledge base (`PROJECT.md` `Kind:` `product`, `Knowledge base:` a folder); delete it otherwise.'
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
sha() { if command -v sha256sum >/dev/null 2>&1; then tr -d '\r' < "$1" | sha256sum | cut -d' ' -f1; else tr -d '\r' < "$1" | shasum -a 256 | cut -d' ' -f1; fi; }
commit() { git -C "$1" add -A && git -C "$1" -c user.name=t -c user.email=t@example.invalid commit -q -m "$2"; }
onboard() {   # onboard <dir>: a scratch repo onboarded by $OLDB, committed
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
clean() { test -z "$(git -C "$1" status --porcelain)"; }
built() { awk '/^## /{ p = ($0 == "## Built") } p' "$1"; }
in_built() { built "$NEWT" | grep -qF "$KBL"; }
bare_angle() { grep -F 'Knowledge base:' "$NEWT" | sed 's/`[^`]*`//g' | grep -q '<[^>]*>'; }
# The Brief: line is left out: Unit G changes it on purpose, and refresh-brief-line-per-area.md
# checks it exactly. Every other line is still held to 75554ce.
removed() { diff "$OLDT_K" "$NEWT" | grep '^<' | grep -v '^< Brief:' | grep -q .; }
added() { diff "$OLDT_K" "$NEWT" | grep '^> ' | sed 's/^> //' | grep -v '^[[:space:]]*$' | grep -v '^Brief:'; }
only_kb_added() { test "$(added | wc -l | tr -d ' ')" = 1 && added | grep -qF "$KBL"; }

# 0. The template under test (Unit B4, DoD 1) and the baselines.
t "template: has the Knowledge base: line, worded as the brief's Decisions" grep -qF "$KBL" "$NEWT"
t "template: exactly one Knowledge base: line" test "$(grep -c 'Knowledge base:' "$NEWT")" = 1
t "template: the Knowledge base: line is under ## Built" in_built
t "template: no {{KEY}} anywhere, so refresh can offer it" not grep -q '{{' "$NEWT"
t "template: the Knowledge base: line has no <...> outside a code span" not bare_angle
t "template: against the released template ($BASE_K), no line removed or changed but the Brief: line (Unit G)" not removed
t "template: against the released template, the one non-blank line added, but the Brief: line, is the Knowledge base: line" only_kb_added
t "regression: template keeps the Design: line with its three values (#30, Q5)" grep -qF "$DESIGN" "$NEWT"
t "regression: ## Built keeps its prompt" grep -qF "Files touched, and any decision that wasn't in the brief." "$NEWT"
t "baseline $BASE_D: no Design: line" not grep -q 'Design:' "$OLDT_D"
t "baseline $BASE_D: no Knowledge base: line" not grep -q 'Knowledge base:' "$OLDT_D"
t "baseline $BASE_K: has the Design: line" grep -qF "$DESIGN" "$OLDT_K"
t "baseline $BASE_K: no Knowledge base: line" not grep -q 'Knowledge base:' "$OLDT_K"

# cases <tag> <old plugins/ada> <line>...: the five refresh outcomes for a repo onboarded by that
# plugin; each <line> (a grep pattern) is absent from its template and must arrive.
cases() {
  tag=$1; OLDB=$2/bin/ada-onboard; OLDT=$2/templates/repo/$P; shift 2
  # 1. Old, unedited template: refreshed, and the hash follows.
  R=$W/$tag-unedited; onboard "$R"
  refresh "$R"
  t "$tag unedited: exit 0" test "$(rc)" = 0
  t "$tag unedited: prints refreshed for the PR template" line_is refreshed
  t "$tag unedited: the template is now the plugin's, byte for byte" cmp -s "$NEWT" "$R/$P"
  for l in "$@"; do t "$tag unedited: the template now has the $l line" grep -q "$l" "$R/$P"; done
  t "$tag unedited: hash rewritten to the new copy (.template-hashes updates)" has_hash "$R" copied "$NEWT"
  t "$tag unedited: one hash line for the template" one_hash "$R"
  t "$tag unedited: only the template and the hashes changed" \
    test "$(git -C "$R" status --porcelain | sort)" = "$(printf ' M %s\n M %s\n' "$HF" "$P")"
  commit "$R" refreshed
  refresh "$R"
  t "$tag unedited, second refresh: prints current" line_is current
  t "$tag unedited, second refresh: nothing changed" clean "$R"

  # 2. Old template, edited by the project: edited, with a diff; nothing changed.
  R=$W/$tag-edited; onboard "$R"
  printf '\nProject note.\n' >> "$R/$P"; commit "$R" "edit the template"
  refresh "$R"; echo "info: $tag edited exit code $(rc)"
  t "$tag edited: prints edited for the PR template" line_is edited
  t "$tag edited: a unified diff follows" grep -q '^@@' "$W/out"
  for l in "$@"; do
    t "$tag edited: the diff shows the $l line arriving" grep -q "^+.*$l" "$W/out"
    t "$tag edited: the template still has no $l line" not grep -q "$l" "$R/$P"
  done
  t "$tag edited: nothing in the repo changed (template and .template-hashes as committed)" clean "$R"
  # Regression: --force refreshes an edited copy.
  refresh "$R" --force
  t "$tag edited, --force: prints refreshed" line_is refreshed
  t "$tag edited, --force: template is the plugin's" cmp -s "$NEWT" "$R/$P"
  t "$tag edited, --force: hash rewritten to the new copy" has_hash "$R" copied "$NEWT"

  # 3. Regression: --decline, then a plain refresh, both say declined.
  R=$W/$tag-declined; onboard "$R"
  printf '\nProject note.\n' >> "$R/$P"; commit "$R" "edit the template"
  refresh "$R" --decline
  t "$tag decline: prints declined" line_is declined
  t "$tag decline: records declined with the plugin copy's hash" has_hash "$R" declined "$NEWT"
  t "$tag decline: the template is left alone" untouched "$R"
  refresh "$R"
  t "$tag declined, plain refresh: prints declined" line_is declined
  t "$tag declined, plain refresh: the template is left alone" untouched "$R"

  # 4. Regression: no hash for the template: unknown, with a diff; file left alone.
  R=$W/$tag-unknown; onboard "$R"
  printf '\nProject note.\n' >> "$R/$P"
  grep -v " $P\$" "$R/$HF" > "$W/h" && cp "$W/h" "$R/$HF"; commit "$R" "edit, forget the hash"
  refresh "$R"; echo "info: $tag unknown exit code $(rc)"
  t "$tag no hash: prints unknown" line_is unknown
  t "$tag no hash: a unified diff follows" grep -q '^@@' "$W/out"
  t "$tag no hash: the template is left alone" untouched "$R"

  # 5. A deleted template that .template-hashes lists stays deleted: removed, with the sha the
  #    entry held (the old copy's), not the plugin's.
  R=$W/$tag-deleted; onboard "$R"
  rm "$R/$P"; commit "$R" "delete the template"
  refresh "$R"; echo "info: $tag deleted exit code $(rc)"
  t "$tag deleted: prints removed" line_is removed
  t "$tag deleted: the template is not recreated" test ! -e "$R/$P"
  t "$tag deleted: hash is removed with the old copy's sha" has_hash "$R" removed "$OLDT"
  t "$tag deleted: one hash line for the template" one_hash "$R"
  t "$tag deleted: only .template-hashes changed" test "$(git -C "$R" status --porcelain)" = " M $HF"
  commit "$R" "refresh records removed"
  refresh "$R"
  t "$tag deleted, second refresh: prints removed" line_is removed
  t "$tag deleted, second refresh: nothing changed" clean "$R"
  # --force is the way back: the plugin's copy, recorded copied.
  refresh "$R" --force
  t "$tag deleted, --force: prints refreshed" line_is refreshed
  t "$tag deleted, --force: template is the plugin's" cmp -s "$NEWT" "$R/$P"
  t "$tag deleted, --force: hash is copied, the new copy's" has_hash "$R" copied "$NEWT"
  t "$tag deleted, --force: one hash line for the template" one_hash "$R"

  # 6. Regression: a deleted template with no hash line comes back as the plugin's.
  R=$W/$tag-deleted-unlisted; onboard "$R"
  rm "$R/$P"
  grep -v " $P\$" "$R/$HF" > "$W/h" && cp "$W/h" "$R/$HF"; commit "$R" "delete the template and its hash"
  refresh "$R"
  t "$tag deleted, no hash: prints refreshed" line_is refreshed
  t "$tag deleted, no hash: template recreated as the plugin's" cmp -s "$NEWT" "$R/$P"
  t "$tag deleted, no hash: hash is copied, the new copy's" has_hash "$R" copied "$NEWT"
}

cases "before-design" "$OLD_PLUGIN_D" 'Design:' 'Knowledge base:'
cases "released" "$OLD_PLUGIN_K" 'Knowledge base:'

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0, under the local bash and bash 3.2. The `info:`
lines record the exit codes for `edited` and `unknown`, which the docs don't fix; put them in the
Run record. The broken copy in step 4 prints `FAIL` for the template's `Knowledge base:` checks,
for each `unedited`, `--force` and `deleted, no hash` case's `Knowledge base:` line, and for
each `edited` case's "the diff shows the Knowledge base: line arriving".

On a plugin from before a deleted template stayed deleted (e.g. `59499fb`, 0.15.0), each set's
`deleted` checks FAIL (that plugin prints `refreshed` and recreates the template), and so does
`deleted, --force: prints refreshed` (the template is already back, so it prints `current`); the
other `deleted, --force` checks and `deleted, no hash` PASS (tried on 20 Nov 2026 with
`PLUGIN=` an export of `59499fb`: 12 failed, all of them `deleted` checks).

On the commit Unit B4 started from (a376b09, with no `Knowledge base:` line yet) the template's
first, second, third and seventh checks and every `Knowledge base:` line check in both case sets
must FAIL. There the `released` set's unedited repo already has the plugin's template, so it
prints `current`, and its `refreshed` and "only the template and the hashes changed" checks FAIL
too. The `before-design` set's `Design:` checks and the regression checks PASS.

Sources: Unit B4's brief: Decisions (the line's wording, under `## Built`, placeholder-free so
`refresh` can offer it; no change to `refresh` itself), Definition of Done 1 and 2 (`refreshed` in
a repo with the old template untouched, `edited` with a diff where it was edited,
`.template-hashes` updates), Constraints, Surfaces (untouched `refreshed`, edited `edited` + diff,
declined `declined`). #30's brief (the `Design:` line, as amended, Q5). `SYSTEM.md`, ada-onboard
(the order of `current`, `refreshed`, `declined`, `edited`, `unknown`; the hash rewritten on
`refreshed` and `--force`; `declined <sha of plugin copy>`). The brief "A template the project deleted stays deleted": a
missing template that `.template-hashes` lists prints `removed`, is not recreated, and is
recorded `removed <the sha the entry held>`; `--force` brings it back as `copied`; one with no
hash line is still `refreshed`.
`API.md` (one line per template, the word padded to 9, a unified diff after `edited` and
`unknown`).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`git -C <scratch>/<case> status`, `git diff`, and `.claude/team/.template-hashes` there.

## Regression

- Sections 3 and 4, the `--force` lines, the second refresh's `current` and section 6 (a missing
  template with no hash line still `refreshed`) are the refresh outcomes this change must not
  move, for a repo onboarded before #30 and one onboarded with the released template. Section 5
  is the one case that changed: a deleted, listed template is now `removed`.
- The PR template only gains the one line: every other line onboarded repos already have stays
  as it was (the template's "no line removed or changed" and "one non-blank line added" checks
  against `75554ce`), and the `Design:` line keeps its three values. The `Brief:` line is left
  out of those two checks since Unit G changed it on purpose;
  `refresh-brief-line-per-area.md` checks it, and refresh's outcomes for it.
