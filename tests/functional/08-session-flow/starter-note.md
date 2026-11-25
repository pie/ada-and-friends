# `/ada:begin` offers the starter set once, in a project with no areas table, and writes the answer into `PROJECT.md`

Unit H of the plan "Brief approval by area": in a project whose Brief approval section has no
areas table and no `- **Areas:** defaults kept` line, `/ada:begin` shows a one-time note at
Session start (step 11) offering the starter set. Ada works out whether to show it by running
`ada-onboard check` and looking for its `missing  areas table` line, and takes the starter rows
from the commented table in the section when it has one, otherwise from the Brief approval row of
`/ada:onboard` (nothing in `approval.md` retypes them). Yes: the developer edits the rows, Ada
writes the table (moving the commented one out of its comment) and shows `ada-brief areas`. No: Ada
writes the `defaults kept` line. Either is committed in the step's first commit, so the note
doesn't return. `/ada:onboard` writes the `defaults kept` line when the developer keeps one area,
so a new project never sees the note.

- Area: 08 Session flow (`begin`'s SKILL.md Session start and `approval.md`); 02 Onboarding (the
  onboard skill's `defaults kept` line); the README and CHANGELOG
- Asana: https://app.asana.com/0/0/1220198295391965
- Automate as: none (part A is a self-checking shell script; part B is a dry run for a person)
- Last run: 25 Nov 2026, Part A 95 passed, 0 failed under bash 5.3, bash 3.2.57 (Docker, with the documented `apk add` of GNU grep and diffutils) and Python 3.8.20 (Docker); break against 9af4c6b fails as expected (34 failed); Part B not run (needs a person; see the PR)

`ada-onboard check`'s line for the same projects is tested in
`tests/functional/02-onboarding/check-areas-table.md`.

## Precondition

- Part A: this checkout (`REPO`) with the change in its working tree, and `BASE` (default
  `9af4c6b`, the tip of `add/brief-approval-by-area` before this step) in it. `git`, `tar`, `awk`,
  `diff`, `cmp`, `head`.
- Part B: `claude` logged in, `gh` logged in as you (`<you>`). `<client>` is any GitHub login that
  isn't yours; they need do nothing. Four scratch GitHub repos you own (never a client's), A, B,
  C and D, each cloned, with a `README.md` holding one line, `Thanks for your order.`, committed and
  pushed to `main`, and a `next-release` branch pushed. In each, `.claude/settings.local.json` turns
  `ada@pie` off (`DEVELOPING.md`), and every session starts with `claude --plugin-dir <this
  checkout>/plugins/ada`, without `ADA_BRIEF_REQUIRE`.
  - A and B: onboard them from this checkout by hand: `bash <this checkout>/plugins/ada/bin/ada-onboard
    files PROJECT_NAME=Scratch`, `ada-onboard settings`, `ada-onboard gitignore`. In
    `.claude/team/PROJECT.md` set **Branch from:** to `main` and **PR into:** to `next-release`,
    and make the Brief approval section exactly as a project onboarded before this release has it:
    the heading, its first paragraph, the Approvers and Trusted lines and nothing else (delete the
    `Re-approval triggers` line, the commented starter table and the paragraph after it).
    A: `- **Approvers:** none`. B: `- **Approvers:** @<you>`. Both: `- **Trusted:** none`. Commit
    and push.
  - C: nothing more; `/ada:onboard` sets it up in step 5.
  - D: onboard it as A and B, but leave `PROJECT.md`'s Brief approval section exactly as the
    template gives it, with its `Re-approval triggers` line and the starter table inside its
    HTML comment. `- **Approvers:** @<you>`, `- **Trusted:** none`. Commit and push.

## Steps

### Part A: the wording, checked

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: export `BASE`'s plugin (`git -C <this checkout> archive 9af4c6b
   plugins/ada | tar -x -C <empty folder>`) and run `REPO=<this checkout> PLUGIN=<that
   folder>/plugins/ada bash check.sh`. Sections 1 to 3 FAIL (no step 11, no The areas note, no
   `defaults kept` in onboard); section 2b (the template's commented table, onboard's Brief
   approval row) still PASSes, since those are unchanged.

```bash
#!/bin/bash
# Unit H: the one-time areas note. begin's Session start step 11 and approval.md's The areas note;
# the onboard skill's defaults kept line; README's Changing the areas; CHANGELOG; no version change.
# PASS/FAIL per check; exit 1 if any FAIL, 2 on SETUP FAIL.
# Env: REPO (this checkout), PLUGIN (default $REPO/plugins/ada), BASE (before this step, default
#      9af4c6b), OLD_PLUGIN (BASE's plugins/ada; exported if unset).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
BASE=${BASE:-9af4c6b}
W=$(mktemp -d); trap 'rm -rf "$W"' EXIT
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
git -C "$REPO" show "$BASE:CHANGELOG.md" > "$W/old.cl" 2>/dev/null || { echo "SETUP FAIL: cannot read $BASE:CHANGELOG.md"; exit 2; }
git -C "$REPO" show "$BASE:.claude-plugin/marketplace.json" > "$W/old.mp" 2>/dev/null || { echo "SETUP FAIL: cannot read $BASE:.claude-plugin/marketplace.json"; exit 2; }
B=$PLUGIN/skills/begin/SKILL.md; A=$PLUGIN/skills/begin/approval.md; O=$PLUGIN/skills/onboard/SKILL.md
OA=$OLD_PLUGIN/skills/begin/approval.md
RD=$REPO/README.md; CL=$REPO/CHANGELOG.md
for f in "$B" "$A" "$O" "$OA" "$RD" "$CL"; do [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }; done
fails=0; passes=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; passes=$((passes+1)); else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { local name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
info() { echo "info: $*"; }
flat() { tr '\n' ' ' | tr -s ' \t' '  '; }
has()  { grep -qiE -- "$2" "$1"; }
hasF() { grep -qiF -- "$2" "$1"; }
allw() { af=$1; shift; for w in "$@"; do grep -qiE -- "$w" "$af" || return 1; done; }
# items <file>: one line per top-level list item (-, * or 1.), continuation lines joined
items() { awk '/^([-*]|[0-9]+\.)[[:space:]]/{ if (b != "") print b; b=$0; next } /^[[:space:]]+[^[:space:]]/ && b != "" { sub(/^[[:space:]]+/," "); b=b $0; next } { if (b != "") print b; b="" } END { if (b != "") print b }' "$1"; }
# added <file under plugins/ada>: the lines this step adds to it, against the base (one line)
added() { diff "$OLD_PLUGIN/$1" "$PLUGIN/$1" | sed -n 's/^> //p' | flat; }

echo "== 1. begin/SKILL.md, Session start (DoD 6; Decisions: When, Where the text lives)"
t "begin/SKILL.md: under 26000 bytes" test "$(wc -c < "$B")" -lt 26000
info "begin/SKILL.md: $(wc -c < "$B") bytes (base $(wc -c < "$OLD_PLUGIN/skills/begin/SKILL.md"))"
awk '/^## /{p=($0 ~ /^## Session start/); next} p' "$B" > "$W/ss"
items "$W/ss" > "$W/ssi"
grep -E '^10\. ' "$W/ssi" > "$W/s10"; grep -E '^11\. ' "$W/ssi" > "$W/s11"
info "step 11: $(cat "$W/s11")"
t "Session start: step 10 is still ada-onboard refresh" hasF "$W/s10" 'ada-onboard refresh'
t "Session start: has a step 11" test -s "$W/s11"
t "Session start: step 11 is the last step" test "$(grep -E '^[0-9]+\. ' "$W/ssi" | tail -1 | cut -d. -f1)" = 11
t "Session start: step 11 points at approval.md" hasF "$W/s11" '`approval.md`'
t "Session start: step 11 names The areas note" hasF "$W/s11" 'areas note'
t "Session start: step 11 is one short line (at most 200 characters; the rest is in approval.md)" test "$(wc -c < "$W/s11")" -le 201

echo "== 2. approval.md, The areas note (Decisions: When, The note, On yes, On no, the first commit)"
t "approval.md: one '## The areas note' heading" test "$(grep -c '^## The areas note[[:space:]]*$' "$A")" = 1
t "approval.md: it is the last ## section" test "$(grep '^## ' "$A" | tail -1 | sed 's/[[:space:]]*$//')" = '## The areas note'
t "regression: approval.md up to the new section is as it was (only a section is added)" sh -c "head -c \"\$(wc -c < '$OA')\" '$A' | cmp -s - '$OA'"
awk '/^## The areas note/{p=1} p' "$A" | flat > "$W/note"
cat "$W/s11" "$W/note" | flat > "$W/when"
t "When: no | Area | Slug | Approved by | table" has "$W/when" '\| Area \| Slug \| Approved by \||areas table|no table'
t "When: and no - **Areas:** defaults kept line" hasF "$W/when" '**Areas:** defaults kept'
t "When: whatever the project's Approvers" has "$W/when" "whatever (its|the|their)( project.s)? Approvers|(any|every) Approvers|even (when|if|with)[^.]{0,60}Approvers|regardless[^.]{0,40}Approvers|Approvers[^.]{0,60}(do(es)?n.t matter|either way)"
t "The note is shown once; answered, it doesn't come back" has "$W/note" "(show|shown|offer|offered|ask|asked|appear)s?[^.]{0,30}once|once[^.]{0,30}(only|per project)|(doesn.t|does not|won.t|will not|never)[^.]{0,30}(again|return)|not (come back|return)"
t "The note: what areas are (each with its approvers)" has "$W/note" 'area[^.]{0,120}approv|approv[^.]{0,120}area'
t "The note: says the starter set is seven" has "$W/note" 'seven'
# Fix round 1 (Amendments): the slugs are not retyped; the rows come from the project's commented
# table, else from /ada:onboard's Brief approval row.
for s in content design price system fragile security dependencies; do
  t "The note: does not retype the slug $s" not has "$W/note" "\`$s\`|\\| *$s *\\|"
done
t "The note: the rows come from the commented table in the Brief approval section" has "$W/note" 'commented table[^.]{0,80}Brief approval|Brief approval[^.]{0,120}commented table'
t "The note: otherwise from /ada:onboard's Brief approval row (skills/onboard/SKILL.md)" allw "$W/note" 'otherwise' 'skills/onboard/SKILL\.md' 'Brief approval row'
t "The note: runs ada-onboard check and keys on its missing areas table line" allw "$W/note" 'ada-onboard check' 'missing +areas table'
t "The note: without that line, says nothing" has "$W/note" 'say nothing'
t "The note: does not work the rule out a second time (no 'everything:' test of ada-brief areas)" not hasF "$W/note" 'everything:'
t "The note: keeping one area changes nothing" has "$W/note" 'one area[^.]{0,100}(changes nothing|nothing changes|(no|nothing) (change|different)|as (it is|it was|before|today|now)|same)'
t "The note: asks: set them up, or keep the defaults" allw "$W/note" 'set (them|the areas|it) up' 'keep (the )?defaults'
t "Yes: each row kept, renamed or dropped" allw "$W/note" 'keep' 'renam' 'drop'
t "Yes: who approves each row, @logins or Approvers" allw "$W/note" '@login|@<' '`?Approvers`?'
t "Yes: which row is the catch-all" has "$W/note" 'which[^.]{0,60}catch-all'
t "Yes: writes the table into the Brief approval section" has "$W/note" 'table[^.]{0,120}Brief approval|Brief approval[^.]{0,120}table'
t "Yes: moves the commented table out of its comment" has "$W/note" 'move[^.]{0,40}out of (its|the) comment'
t "Yes: writes a new table only when the section has none" has "$W/note" 'otherwise write a new table'
t "Yes: one table, outside any comment" has "$W/note" 'one table[^.]{0,40}outside (any|a|the) comment'
t "Yes: the table goes below the Re-approval triggers line" has "$W/note" 'below the Re-approval triggers line'
t "Yes: or below the Trusted line when the project has no Re-approval triggers line" has "$W/note" 'below the Trusted line[^.]{0,80}(before|without|no)'
t "Yes: then runs ada-brief areas" has "$W/note" '(writ|table)[^#]{0,400}ada-brief areas'
t "Yes: shows its output" has "$W/note" '(show|print|paste)[^.]{0,60}(its |the )?output|output[^.]{0,60}(show|developer)'
t "Yes: fixes and reruns until it exits 0" has "$W/note" '(until|till)[^.]{0,60}exits? (with )?0|(rerun|re-run|run it again|run again)[^.]{0,80}exits? (with )?0'
t "No: adds - **Areas:** defaults kept (<name>, <date>)" has "$W/note" '\*\*Areas:\*\* defaults kept \(<[^>]*name[^>]*>, <[^>]*date[^>]*>\)'
t "Either answer is committed in the step's first commit" has "$W/note" 'first commit'

echo "== 2b. The two places the rows come from hold the starter set (system the catch-all)"
PJT=$PLUGIN/templates/repo/.claude/team/PROJECT.md
awk '/^## Brief approval/{s=1; next} /^## /{s=0} s && /^<!--/{p=1} s && p {print} s && p && /-->/{exit}' "$PJT" > "$W/cmt"
awk '/^## Brief approval/{s=1; next} /^## /{s=0} s' "$PJT" > "$W/tsec"
t "template: its Brief approval section has a commented table (| Area | Slug | Approved by |)" grep -q '^| Area | Slug | Approved by |' "$W/cmt"
t "template: that table is the section's only one (none outside the comment)" test "$(grep -c '^| Area | Slug | Approved by |' "$W/tsec")" = 1
t "template: the comment is below the Re-approval triggers line" test "$(grep -n 'Re-approval triggers' "$W/tsec" | head -1 | cut -d: -f1)" -lt "$(grep -n '^<!--' "$W/tsec" | head -1 | cut -d: -f1)"
for s in content design price system fragile security dependencies; do
  t "template: the commented table has the row $s" has "$W/cmt" "^\\|[^|]*\\| $s \\|"
done
t "template: the system row is the catch-all" has "$W/cmt" '^\|[^|]*\(catch-all\)[^|]*\| system \|'
grep -E '^\| Brief approval \|' "$O" | flat > "$W/obrow"
t "onboard: the Brief approval row exists" test -s "$W/obrow"
for s in content design price system fragile security dependencies; do
  t "onboard: its Brief approval row offers $s" has "$W/obrow" "\`$s\`"
done
t "onboard: its Brief approval row has system as the catch-all" has "$W/obrow" 'System behaviour \(catch-all\) `system`'

echo "== 3. /ada:onboard (Decisions: /ada:onboard; DoD 7)"
added skills/onboard/SKILL.md > "$W/ob"
info "onboard, added: $(cut -c1-300 "$W/ob")"
t "onboard: the text this step adds writes the defaults kept line" hasF "$W/ob" '**Areas:** defaults kept'
t "onboard: when the developer keeps one area" has "$W/ob" 'one area|keeps? (the )?defaults|no (areas )?table|without (a|an|the) (areas )?table'
flat < "$O" > "$W/o"
t "regression: onboard still offers the starter set, system the catch-all" has "$W/o" 'System behaviour.{0,60}catch-all'

echo "== 4. README, Brief approval: Changing the areas (DoD 8)"
awk '/^## /{p=($0 ~ /^## Brief approval[[:space:]]*$/); next} p' "$RD" > "$W/rba"
t "README: a ## Brief approval section" test -s "$W/rba"
t "README: it has a 'Changing the areas' part (heading or bold lead)" grep -Eq '^###+ Changing the areas|^\*\*Changing the areas' "$W/rba"
awk '/^###+ Changing the areas|^\*\*Changing the areas/{p=1; print; next} p && (/^#/ || /^\*\*[A-Z]/){exit} p' "$W/rba" | flat > "$W/chg"
awk '/^###+ Changing the areas|^\*\*Changing the areas/{p=1; next} p && (/^#/ || /^\*\*[A-Z]/){exit} p' "$W/rba" > "$W/chg.raw"
items "$W/chg.raw" | grep -iE '^- \*\*Renamed or dropped' > "$W/chg.rd"
items "$W/chg.raw" | grep -iE '^- \*\*Back to one area' > "$W/chg.one"
info "Changing the areas: $(wc -c < "$W/chg") characters; Renamed or dropped bullet $(wc -c < "$W/chg.rd"), Back to one area bullet $(wc -c < "$W/chg.one")"
t "Changing the areas: edit (change) the table in PROJECT.md" has "$W/chg" '(edit|change)[^.]{0,80}PROJECT\.md|PROJECT\.md[^.]{0,80}edit'
t "Changing the areas: new labels are created on the next submit" has "$W/chg" 'label[^.]{0,120}(next|following)[^.]{0,40}submit|(next|following)[^.]{0,40}submit[^.]{0,120}(creat|label)'
t "Changing the areas: renaming or dropping a slug" allw "$W/chg" 'renam' 'drop|remov|delet' 'slug'
t "Changing the areas: speaks of the approved:<slug> labels" hasF "$W/chg" 'approved:'
t "Changing the areas: on open PRs" has "$W/chg" 'open (PR|pull request|draft)'
# Amendments row, 24 Nov 2026: renaming or dropping a slug makes ada-brief status stop (exit 2, area
# not listed) on open PRs whose brief names it, until Areas: is updated and the brief resubmitted;
# (Replaces "labels uncounted, so their briefs wait again".) Amendments row, 25 Nov 2026: that stop
# holds whatever the Approvers line says; only once the table is deleted (back to one area) does
# Approvers: none print not-needed, so that sentence belongs in the "Back to one area" bullet.
t "Changing the areas: ada-brief status stops with exit 2 on an open PR whose brief names a renamed or dropped area" allw "$W/chg" 'ada-brief status' 'exit 2' 'renamed or dropped'
t "Changing the areas: says the area is not listed in PROJECT.md" has "$W/chg" "names area <slug>, which PROJECT\.md doesn.t list|not listed"
t "Changing the areas: until the Areas: line is updated" has "$W/chg" 'until[^.]{0,60}`?Areas:`? line'
t "Changing the areas: and the brief is submitted again" has "$W/chg" 'submitted again|resubmit'
t "Changing the areas: a Renamed or dropped slugs bullet, and a Back to one area bullet" test -s "$W/chg.rd" -a -s "$W/chg.one"
t "Renamed or dropped slugs: the stop holds whatever the Approvers line says" has "$W/chg.rd" 'whatever (the )?Approvers'
t "Renamed or dropped slugs: says exit 2 and the not-listed message" allw "$W/chg.rd" 'exit 2' "names area <slug>, which PROJECT\.md doesn.t list"
t "Renamed or dropped slugs: does not say Approvers: none prints not-needed" not has "$W/chg.rd" 'not-needed'
t "Back to one area: with Approvers: none it prints not-needed" has "$W/chg.one" 'Approvers: none`?[^.]{0,40}not-needed'
t "Back to one area: an open PR whose brief has an Areas: line stops the same way" allw "$W/chg.one" '`?Areas:`? line' 'ada-brief status' 'same way'
t "Back to one area: until the line is removed and the brief submitted again" has "$W/chg.one" 'until[^.]{0,60}(removed|deleted)[^.]{0,60}(submitted again|resubmit)'
t "Back to one area: then approved as a whole with brief-approved; a brief with no Areas: line is unaffected" allw "$W/chg.one" 'brief-approved' 'no `?Areas:`? line[^.]{0,40}unaffected'
t "Changing the areas: the not-needed sentence is in Back to one area only (no other bullet has it)" test "$(items "$W/chg.raw" | grep -ci 'not-needed')" = 1
t "Changing the areas: no longer says a renamed or dropped slug makes the open PRs' briefs wait again" not has "$W/chg" 'briefs? wait again|so (their|the) briefs? wait'
t "Changing the areas: back to one area by deleting the table" has "$W/chg" 'one area.{0,40}(delet|remov)[^.]{0,40}table|(delet|remov)[^.]{0,40}table[^.]{0,120}one area'

echo "== 5. CHANGELOG (S105, DoD 10) and no version change (P31)"
bul() { awk '/^## /{u=($0 ~ /^## Unreleased/); p=0; next} u && /^- \*\*Brief approval by area\.\*\*/{p=1; print; next} p && (/^- / || /^$/){p=0} p' "$1" | flat; }
bul "$CL" > "$W/b.new"
t "CHANGELOG: the Brief approval by area bullet is still under Unreleased" test -s "$W/b.new"
t "CHANGELOG: one such bullet under Unreleased" test "$(awk '/^## /{u=($0 ~ /^## Unreleased/)} u' "$CL" | grep -c '^- \*\*Brief approval by area\.\*\*')" = 1
# Fix round 1: the bullet says "a one-time note offering the starter set".
t "CHANGELOG: the bullet now says a one-time note offering the starter set" has "$W/b.new" 'one-time note offering the starter set'
t "P31: CHANGELOG's ## headings as before (no release heading added)" test "$(grep '^## ' "$CL")" = "$(grep '^## ' "$W/old.cl")"
t "P31: plugin.json as before" cmp -s "$PLUGIN/.claude-plugin/plugin.json" "$OLD_PLUGIN/.claude-plugin/plugin.json"
t "P31: marketplace.json as before" cmp -s "$REPO/.claude-plugin/marketplace.json" "$W/old.mp"

echo "$passes passed, $fails failed"
[ "$fails" = 0 ] || exit 1
exit 0
```

#### bash 3.2 and Python 3.8

Part A also has to pass under bash 3.2 and Python 3.8. With Docker and the `bash:3.2` image (it is
Alpine: no git or Python, and its `grep` and `diff` are busybox's, which fail three checks, so the
container adds GNU `grep` and `diffutils` as well; that needs the network). `S` is the scratch
folder holding `check.sh`; `<main>` is the main checkout and `<worktree>` this one (the same path
when you aren't in a worktree). `<main>` is mounted at its own path because a worktree's `.git`
points into it.

```sh
mkdir -p "$S/old"
git -C <this checkout> archive 9af4c6b plugins/ada | tar -x -m -C "$S/old"
docker run --rm -v <main>:<main>:ro -v "$S":/s -e REPO=<worktree> -e OLD_PLUGIN=/s/old/plugins/ada bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 grep diffutils >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && bash /s/check.sh'
```

The same in `python:3.8-slim` (Debian, so GNU tools already):

```sh
docker run --rm -v <main>:<main>:ro -v "$S":/s -e REPO=<worktree> -e OLD_PLUGIN=/s/old/plugins/ada python:3.8-slim \
  sh -c 'apt-get update -qq >/dev/null && apt-get install -y -qq git >/dev/null && git config --global --add safe.directory "*" && python3 --version && bash /s/check.sh'
```

Both: every line `PASS`, last line `95 passed, 0 failed`, exit 0.

### Part B: a dry run (a person, 60 to 75 minutes)

1. **Yes, in repo A** (`Approvers: none`, no table). Start a session and run `/ada:begin`, then:
   "Change the thank-you line in README.md to 'Thanks, your order is in.' Nothing else." When Ada
   shows the areas note (she should run `ada-onboard check` first), answer: "Yes, set them up. Drop Price and New dependency. `@<client>`
   approves content; Approvers approve the rest; System behaviour is the catch-all." Agree the
   brief when Ada shows it. Let her go on until the step's branch has its first commit; if she
   stops for approval, stop there. Then, in repo A's main checkout and in the step's worktree, run
   `bash <this checkout>/plugins/ada/bin/ada-brief areas` and `git log --reverse --format=%h
   main..<the step's branch> | head -1`, and `git show --stat` that commit. Close any draft PR and
   delete its branch afterwards.
2. **A table: no note, repo A.** Start a fresh session in repo A and run `/ada:begin` with
   another small request ("Add a full stop to the end of the README line."). Stop once Ada has
   finished Session start. Run `bash <this checkout>/plugins/ada/bin/ada-onboard check`.
3. **No, in repo B** (`Approvers: @<you>`, no table). `/ada:begin`, the request from step 1. When
   Ada shows the note, answer: "No, keep the defaults." Agree the brief. Let her go on until the
   step's branch has its first commit. Look at `PROJECT.md` and the first commit as in step 1.
   Run `bash <this checkout>/plugins/ada/bin/ada-onboard check` and `bash <this
   checkout>/plugins/ada/bin/ada-brief who`.
4. **No note the second time, repo B.** A fresh session, `/ada:begin`, the request from step 2.
   Stop once Ada has finished Session start.
5. **Onboarding keeps one area, repo C.** `/ada:onboard`. When it asks about brief approval, say:
   "Only PIE writes code. PIE (`@<you>`) decides everything; one area is fine." When it offers the
   starter set, keep one area. Let it finish, and commit and push what it wrote if it doesn't.
   Run `bash <this checkout>/plugins/ada/bin/ada-onboard check`. Then a fresh session,
   `/ada:begin`, the request from step 2; stop once Ada has finished Session start.
6. **Yes, with the commented table, repo D** (`Approvers: @<you>`, the template's commented
   table). `/ada:begin`, the request from step 1. When Ada shows the note, answer: "Yes, set them
   up. Keep all seven rows. `@<client>` approves content; Approvers approve the rest; System
   behaviour is the catch-all." Agree the brief. Let her go on until the step's branch has its
   first commit. Look at `PROJECT.md`'s Brief approval section (`sed -n '/^## Brief approval/,/^## /p'`),
   run `grep -n '^| Area | Slug | Approved by |\|^<!--\|-->\|Re-approval triggers'` on it, and
   run `bash <this checkout>/plugins/ada/bin/ada-brief areas` and `bash <this
   checkout>/plugins/ada/bin/ada-onboard check`. Then a fresh session, `/ada:begin`, the request
   from step 2; stop once Ada has finished Session start.

## Expected

Part A: every line `PASS`, last line `<n> passed, 0 failed`, exit 0.

Part B:

1. After Session start's step 10 and before any work on the brief, Ada runs `ada-onboard check` and,
   because it prints the `missing  areas table` line, shows the note once: one short paragraph
   saying what areas are, naming the starter set (seven rows, `system` the catch-all; there is no
   commented table in repo A, so the rows come from `/ada:onboard`'s Brief approval row), and that
   keeping one area changes nothing, then asking whether to set them up or keep the defaults. On
   your yes she goes through the rows with you (keep, rename or drop; who
   approves; which is the catch-all), writes the table into `PROJECT.md`'s Brief approval section,
   runs `ada-brief areas` and shows you its output (exit 0). The table has a `| Area | Slug |
   Approved by |` header and five rows: `content` approved by `@<client>`, `design`, `system`
   (its Area ending `(catch-all)`), `fragile` and `security`; no `price` or `dependencies` row. It
   is written below the `Trusted` line (the project has no `Re-approval triggers` line).
   Your `ada-brief areas` prints those five slugs in table order, `system`'s line ending
   ` (catch-all)`, then `trusted: none`. The step branch's first commit lists
   `.claude/team/PROJECT.md`. The note appears once in the session.
2. No note: `ada-onboard check` prints no `areas table` line, and Ada says nothing about areas.
3. Ada shows the note (Approvers being `@<you>` doesn't stop it). On your no, `PROJECT.md`'s Brief
   approval section gains `- **Areas:** defaults kept (<your name>, <today's date>)` and nothing
   else changes in the section; no table. The step branch's first commit lists
   `.claude/team/PROJECT.md`. `ada-onboard check` prints no `areas table` line; `ada-brief who`
   prints `approver <you>`, as it did before.
4. No note.
5. `PROJECT.md`'s Brief approval section has `- **Areas:** defaults kept (…)` and no table outside
   an HTML comment. `ada-onboard check` prints no `areas table` line. `/ada:begin` shows no note.
6. The note is shown once, naming the seven starter rows taken from the commented table in
   `PROJECT.md`. After your yes the section has exactly one `| Area | Slug | Approved by |` line,
   and it is not inside an HTML comment (the `grep` shows no `<!--` still open at that line; the
   table is outside any `<!-- ... -->`), and it comes after the `Re-approval triggers` line. The
   table has seven rows, `content` approved by `@<client>`, `system` ending `(catch-all)`. Ada ran
   `ada-brief areas` and showed its output (exit 0); yours prints the seven slugs in table order,
   `system`'s line ending ` (catch-all)`. The step branch's first commit lists
   `.claude/team/PROJECT.md`. `ada-onboard check` prints no `areas table` line. The fresh session
   shows no note.

Sources, all Unit H's brief (task 1220198295391965) as amended: Outcome; Decisions (When: Session
start after step 10, `ada-onboard check`'s `missing  areas table` line (no table and no `defaults
kept` line), whatever the Approvers; the rows from the commented table, else `/ada:onboard`'s row; Where the text lives: a
one-line step 11 in `SKILL.md` pointing at The areas note at the end of `approval.md`; The note;
On yes; On no; the step's first commit; `/ada:onboard`; README); Constraints (`SKILL.md` under
26000 bytes; S105 the bullet extended; P31 no version change); Definition of Done 3 to 8 and 10;
Amendments (24 Nov 2026: `ada-brief status` stops on a renamed or dropped slug, README) and Fix
round 1 (`approval.md` no longer retypes the slugs; the Yes path moves the commented table out of
its comment; the CHANGELOG bullet says "a one-time note offering the starter set").

## Observe at

Part A: the script's output. Part B: the session transcript (where the note appears and how many
times, the Bash calls to `ada-brief areas`, what Ada tells you), each repo's
`.claude/team/PROJECT.md`, `git show --stat` of the step branch's first commit, and the commands'
output.

## Regression

`approval.md`'s existing sections are unchanged (part A). Session start step 10 is still
`ada-onboard refresh`. `begin/SKILL.md` stays under 26000 bytes and
`tests/functional/08-session-flow/begin-split-and-models.md` still passes; so do the other text
checks on `begin` (`08-session-flow/*`, `13-design/*`), since the step trims `SKILL.md` to fit.
The onboard skill still offers the starter set (`02-onboarding/onboard-asks-for-areas.md`).
