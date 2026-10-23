# `/ada:begin` checks the brief for overlap with work in flight, and again at the PR gate

- Area: 08 Session flow (`begin`); 01 Brief approval (the docs for `ada-brief overlap`)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219790041247935
- Automate as: none (part A is a self-checking shell script; part B is a dry run with a person)
- Last run: 25 Oct 2026, part A 0 failed, step 3 FAILs only when "Narrow" is deleted in any case; part B not run (a person); see the PR

## Precondition

- Part A: this checkout with the change in its working tree, and `origin/next-release` fetched.
  It only reads the checkout.
- Part B: a practice project (a PIE project or a scratch GitHub repo the tester owns, never a
  client's) onboarded, with `ada@pie` off in its `.claude/settings.local.json` and this
  checkout's plugin loaded with `claude --plugin-dir <this checkout>/plugins/ada`
  (`DEVELOPING.md`, Try a local checkout). Its PROJECT.md names `Branch from` and `PR into`, and
  it has **one open PR into `PR into`** that changes a file `X` (note its number `#N`, its title
  and `X`). `gh` logged in. Put `.claude/handoff/` back after each run (`git status` clean, or
  `ada-handoff-init --archive <name>` from this checkout).

## Steps

### Part A: the mechanical checks

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: in a scratch copy of the repo (`cp -R`), delete the word "narrow"
   in any case (the skill spells it `Narrow`) from the copy's begin skill:
   `sed -i 's/[Nn]arrow//g' <the copy>/plugins/ada/skills/begin/SKILL.md` (on macOS,
   `sed -i ''`), then run `REPO=<the copy> bash check.sh`. It must print exactly one `FAIL`
   (`... mentions: narrow`) and exit 1. If it prints `0 failed`, nothing was deleted: check the
   spelling in the copy with `grep -ci narrow`.

```bash
#!/bin/bash
# Text checks: the overlap check in the begin skill and the docs. PASS/FAIL; exit 1 if any FAIL.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
cd "$REPO" || exit 2
SK=plugins/ada/skills/begin/SKILL.md
W=$(mktemp -d)
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
ln_of() { grep -nF -- "$1" "$SK" | head -1 | cut -d: -f1; }       # first line with the text
section() { awk -v h="$1" 'index($0,h)==1{p=1;next} p&&/^#+ /{exit} p' "$2"; }
in_win() { test "$1" -gt 0 && sed -n "$1,$2p" "$SK" | grep -Eqi -- "$3"; }
# Like in_win, but joins the lines first, for a phrase the skill may wrap across two lines.
in_flat() { test "$1" -gt 0 && sed -n "$1,$2p" "$SK" | tr '\n' ' ' | tr -s ' ' | grep -Eqi -- "$3"; }

# D10, the brief step: before "Confirm the brief with the developer".
conf=$(ln_of 'Confirm the brief with the developer')
o1=$(ln_of 'ada-brief overlap')
t "begin: 'Confirm the brief with the developer' still there" test -n "$conf"
t "begin: runs 'ada-brief overlap'" test -n "$o1"
t "begin: the first overlap check comes before confirming the brief" test -n "$o1" -a -n "$conf" -a "${o1:-0}" -lt "${conf:-0}"
# A missing anchor gives the empty window 0-0, so every check in it fails.
if [ -n "$o1" ] && [ -n "$conf" ]; then s=$(( o1 > 3 ? o1 - 3 : 1 )); e=$conf; else s=0; e=0; fi
for re in 'expected files|files (it|the brief) expect' 'title' 'Overlaps:' 'stop|ask the developer' \
          'build on top' 'Ships: after #' 'recreat' 'fold' 'narrow' 'independent' 'reason'; do
  t "begin, brief step (lines $s-$e) mentions: $re" in_win "$s" "$e" "$re"
done
# D10, the PR gate: a second run, inside "Own the PR gate", before the summary.
gate=$(ln_of '### 3. Own the PR gate')
summ=$(ln_of 'Summarise for the developer')
o2=$(awk -v g="${gate:-0}" 'NR>g && index($0,"ada-brief overlap"){print NR; exit}' "$SK")
t "begin: a second 'ada-brief overlap' inside the PR gate" test -n "$o2"
t "begin: the PR gate check comes before the summary" test -n "$o2" -a -n "$summ" -a "${o2:-0}" -lt "${summ:-0}"
# The window starts at the PR gate heading, since the file-listing command may sit above the line
# that runs ada-brief overlap.
if [ -n "$o2" ] && [ -n "$summ" ] && [ -n "$gate" ]; then gs=$gate; ge=$summ; else gs=0; ge=0; fi
# The Amendment of 25 Oct 2026 (agreed by Neil): the files are the working tree against the merge
# base plus untracked files, NUL-separated into xargs -0 (paths with spaces or non-ASCII stay whole).
for re in 'git merge-base origin/' 'git -c core\.quotePath=false diff -z --name-only' \
          'ls-files -z --others --exclude-standard' 'xargs -0 ada-brief overlap' \
          'BUILD-LOG' 'For the reviewer' 'developer'; do
  t "begin, PR gate (lines $gs-$ge) mentions: $re" in_win "$gs" "$ge" "$re"
done
t "begin, PR gate: no longer lists files with origin/<PR into>...HEAD (committed files only)" not in_win "$gs" "$ge" 'name-only origin/[^ ]*\.\.\.HEAD'
# The Amendment of 25 Oct 2026: what Ada does when overlap exits 2, in both places. Never read as no overlap.
for re in 'exit(s|ed)? (with )?(code )?2' 'ada-brief:' "(never|not|n.t) .*(no overlap|Overlaps: none)|(no overlap|Overlaps: none).*(until|never|not)"; do
  t "begin, PR gate (lines $gs-$ge) on exit 2 mentions: $re" in_flat "$gs" "$ge" "$re"
  t "begin, brief step (lines $s-$e) on exit 2 mentions: $re" in_flat "$s" "$e" "$re"
done

# D12: docs.
section '### `ada-brief`' .claude/team/API.md > "$W/api"
row=$(grep -E '^\| *`overlap' "$W/api" | head -1)
t "API.md: an overlap row in the ada-brief table" test -n "$row"
for w in 'touching' 'waiting to ship:' 'open:' 'touches:' 'draft' 'unknown' 'none'; do
  t "API.md overlap row has: $w" eval 'printf "%s" "$row" | grep -qF -- "$w"'
done
t "SYSTEM.md: the ada-brief fragile-area section describes overlap" eval 'section "### ada-brief: brief approval through GitHub" .claude/team/SYSTEM.md | grep -q overlap'
t "DEVELOPING.md: Brief approval describes overlap" eval 'section "## Brief approval" DEVELOPING.md | grep -q overlap'
t "TEST-AREAS.md: area 01 mentions overlap" eval 'grep -E "^\| *01 *\|" .claude/team/TEST-AREAS.md | grep -q overlap'
section '## Unreleased' CHANGELOG.md > "$W/unrel"
t "CHANGELOG Unreleased: a '- **...**' bullet about overlap (S105)" grep -Eq '^- \*\*.*\*\*.*' "$W/unrel"
t "CHANGELOG Unreleased: mentions overlap" grep -qi 'overlap' "$W/unrel"
t "CHANGELOG Unreleased: 'Nothing yet.' replaced" not grep -q 'Nothing yet' "$W/unrel"

# Out of scope: unchanged against the base.
for f in plugins/ada/agents/claudia.md plugins/ada/agents/tess.md \
         plugins/ada/templates/repo/.github/pull_request_template.md plugins/ada/bin/ada-plan; do
  t "unchanged (out of scope): $f" git diff --quiet "$BASE" -- "$f"
done

echo "$fails failed"
rm -rf "$W"; [ "$fails" = 0 ]
```

### Part B: dry runs (a person, four sessions)

Each run starts a fresh session in the practice project with `/ada:begin`.

1. **Shared file.** Give a small task that plainly changes `X` (e.g. "change the wording of the
   heading in `X`"). Answer Ada until she writes the brief.
   - Before asking you to confirm the brief, she runs `ada-brief overlap` with the brief's
     expected files (look for the command in the session).
   - The brief's `Overlaps:` line names `#N`, and she **stops and asks you** what to do, giving
     four options: build on top of it (base this branch on the release branch or `#N`'s branch,
     `Ships: after #N`), fold this work into `#N`, narrow the scope, or go ahead independently
     with the reason recorded.
   - Answer "build on top of it, on #N's branch". Then: the brief records the new base and
     `Ships: after #N`; `git log -1 --format=%H` on the step's branch equals
     `git rev-parse origin/<#N's branch>` (the branch was recreated on it, nothing lost since
     nothing was committed).
2. **Shared concern, no shared file.** Give a task about the same thing as `#N`'s title but
   expected to change other files (e.g. if `#N` is "Rework the reports", ask for a new column
   in a report built in a different file). Ada still raises `#N` from its title, stops, and
   offers the four options. Answer "go ahead independently, because …"; the `Overlaps:` line
   records `#N` and that reason.
3. **No overlap.** A task unrelated to `#N` in name and files. The brief says `Overlaps: none`
   and Ada goes on to confirm the brief without stopping on overlap.
4. **PR gate.** Take run 3's step through to the PR gate (or a tiny step built for this). Before
   the gate, open a second PR in the practice repo, into `PR into`, changing a file this step
   changed. At the gate Ada lists the changed files with
   `{ git -c core.quotePath=false diff -z --name-only $(git merge-base origin/<PR into> HEAD); git ls-files -z --others --exclude-standard; } | xargs -0 ada-brief overlap`
   and runs it (the branch's uncommitted work against the merge base, plus new files; the build is not
   committed yet), finds the new PR, and **raises it with you before offering the PR** (before the
   summary). To see the exit-2 rule, run it once with a file argument `ada-brief overlap` refuses
   (for example a file whose name contains a newline, or break `origin`): Ada reports the
   `ada-brief:` message to you and does not write `Overlaps: none`. After you answer, `BUILD-LOG.md` records it and
   `.claude/handoff/PR.md`'s "For the reviewer" mentions it.
5. Read `plugins/ada/skills/begin/SKILL.md` end to end once, in this checkout: the two new
   passages read as plain British English (S107), fit the steps around them, and don't
   contradict the approval step (a brief that needs approval is still submitted after it's
   confirmed).

## Expected

Part A: every line `PASS`, last line `0 failed`, exit 0; step 3's broken copy prints `FAIL` for
`narrow`. Part B: each bullet above observed; any that isn't is a failure, with the session's
words quoted.

Sources: the brief's Decisions (`/ada:begin`, The brief and PR gate paragraphs; the four
options; `Overlaps:`; Claudia, Tess's role files and the PR template unchanged), its Amendments
of 22 Oct 2026 and 25 Oct 2026 (the PR gate's file-listing command, and what Ada does when `overlap` exits 2; the entry format, whose output words `draft`, `unknown`, `none` the API.md row
must carry, S103) and Definition of Done items for the skill and the docs; S105 (CHANGELOG bullet shape); S109 (skill wording is
behaviour, checked by a dry run).

## Observe at

Part A: the script's output. Part B: the session transcript (the `ada-brief overlap` command and
Ada's question), `.claude/handoff/BRIEF.md` (`Overlaps:`, `Ships:`), `git log` on the step's
branch, `BUILD-LOG.md`, `.claude/handoff/PR.md`.

## Regression

Part A's last group (Claudia's and Tess's files, the PR template and `ada-plan` unchanged). In
part B, the rest of begin is unchanged: run 3 confirms and, if the project needs approval,
submits the brief as before; run 4 still goes on to `ada-size`, the summary and "open the PR?".
