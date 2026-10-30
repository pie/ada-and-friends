# `/ada:begin`, `/ada:pickup` and planning: a worktree per step, the right checkpoint, the plan in the main checkout

Step 1 of 25 Oct 2026, "One worktree per step". Part A checks the words the skills, templates and
docs must carry. Part B is the behaviour: dry runs by a person in a practice project.

- Area: 08 Session flow (`begin`, `pickup`, `planning`); 11 Templates; 15 Worktrees
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219769422658693
- Automate as: none (part A is a self-checking shell script; part B is a dry run with a person)
- Last run: 28 Oct 2026 (after the rebase onto PR #39), part A 0 failed (106 PASS); both breaks fail as they should; part B not run (needs a person; see the PR)

## Precondition

- Part A: this checkout with the change in it, and `origin/next-release` fetched, and `python3`
  (3.8+). It only reads the checkout. Files that must not change are compared with the merge base of
  `origin/next-release` and HEAD.
- Part B: a practice project the tester owns (a PIE practice project or a scratch GitHub repo,
  never a client's), onboarded, with `ada@pie` off in its `.claude/settings.local.json` and this
  checkout's plugin loaded with `claude --plugin-dir <this checkout>/plugins/ada`
  (`DEVELOPING.md`, Try a local checkout). Its `.gitignore` has `.claude/worktrees/` (run this
  checkout's `plugins/ada/bin/ada-onboard gitignore` and commit it). `gh` logged in. Its
  `PROJECT.md` names `Branch from` and `PR into`. A small Asana task to hand Ada, or a sentence
  describing one. Note the auto-memory folder for the project
  (`~/.claude/projects/<the project path with / as ->/memory/`) and copy it aside before run 1.

## Steps

### Part A: the words

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: in a scratch copy of the repo (`cp -R`), delete every
   `EnterWorktree` from the copy's `checkpoint.md`, which pickup reads on `other`
   (`sed -i 's/EnterWorktree//g' <copy>/plugins/ada/skills/begin/checkpoint.md`; `sed -i ''` on macOS),
   and run `REPO=<the copy> bash check.sh`. It must print `FAIL` for the pickup `EnterWorktree`
   line(s) and exit 1.
4. Break it a second way: in a scratch copy, delete every `FINDINGS.md` from the copy's begin
   skill (`sed -i 's/FINDINGS\.md//g' <copy>/plugins/ada/skills/begin/SKILL.md`). It must print
   `FAIL` for the begin `FINDINGS.md` lines (Definition of Done 16) and exit 1.

```bash
#!/bin/bash
# The worktree-per-step words in skills, templates and docs. PASS/FAIL; exit 1 if any FAIL.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
cd "$REPO" || exit 2
BD=plugins/ada/skills/begin
PS=plugins/ada/skills/pickup/SKILL.md
PL=plugins/ada/skills/planning/SKILL.md
BH=plugins/ada/skills/bughunt/SKILL.md
DS=plugins/ada/skills/design/SKILL.md
W=$(mktemp -d)
# begin's SKILL.md plus the files it reads on demand (checkpoint.md, approval.md, design-step.md,
# pr-gate.md), and pickup's SKILL.md plus checkpoint.md, which it sends `other`, exit 2 and finished steps to.
B=$W/begin.md; { cat "$BD/SKILL.md"; for f in "$BD"/*.md; do [ "$f" = "$BD/SKILL.md" ] || { echo; cat "$f"; }; done; } > "$B"
P=$W/pickup.md; { cat "$PS"; echo; cat "$BD/checkpoint.md"; } > "$P"
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
has()  { grep -qF -- "$2" "$1"; }
hasi() { grep -Eqi -- "$2" "$1"; }
ln_of() { grep -nF -- "$2" "$1" | head -n 1 | cut -d: -f1; }
flat() { tr '\n' ' ' < "$1" | tr -s ' '; }
# near FILE A B N: A and B within N characters of each other, in either order, lines joined.
# (Python's re, not grep -E: grep takes minutes over a window of a thousand characters.)
near() { flat "$1" | python3 -c 'import re,sys
t=sys.stdin.read(); a,b,n=sys.argv[1:4]
sys.exit(0 if re.search("(%s).{0,%s}(%s)|(%s).{0,%s}(%s)"%(a,n,b,b,n,a),t,re.I) else 1)' "$2" "$3" "$4"; }
section() { awk -v h="$1" 'index($0,h)==1{p=1;next} p&&/^#+ /{exit} p' "$2"; }

# Begin (Definition of Done 8).
t "begin: runs \`ada-worktree new\` for a new step" has "$B" 'ada-worktree new'
t "begin: moves the session with EnterWorktree" has "$B" 'EnterWorktree'
t "begin: EnterWorktree's path form, next to ada-worktree new" near "$B" 'ada-worktree new' 'EnterWorktree' 600
t "begin: the fallback when EnterWorktree isn't available: cd <path> && claude" hasi "$B" 'cd [^`]*&& claude'
t "begin: the fallback stops" near "$B" '&& claude' 'stop' 300
t "begin: the exception: already in a worktree whose brief has no Outcome" near "$B" 'worktree' 'Outcome' 300
t "begin: the exception names the step's branch" near "$B" 'Outcome' 'branch' 300
t "begin: today's step 8 command is gone (git worktree add ../<repo>-<slug>)" not has "$B" 'git worktree add ../'
t "begin: runs \`ada-worktree check\`" has "$B" 'ada-worktree check'
c=$(ln_of "$B" 'ada-worktree check'); r=$(ln_of "$B" 'its step is still')
echo "info: begin: first 'ada-worktree check' at line ${c:-none}; 'its step is still' at ${r:-none}"
t "begin: 'its step is still' (the resume) still there" test -n "$r"
t "begin: the check comes before resuming a checkpoint" test -n "$c" -a -n "$r" -a "${c:-0}" -le "${r:-0}"
t "begin: on 'other' it doesn't resume" near "$B" 'ada-worktree check' '`?other`?' 500
t "begin: on 'other' it moves with EnterWorktree, or names the branch and checkout and stops" near "$B" '`?other`?' 'EnterWorktree' 500
t "begin: runs \`ada-worktree list\` from the main checkout" near "$B" 'ada-worktree list' 'main checkout' 400
t "begin: offers \`ada-worktree remove\`" has "$B" 'ada-worktree remove'
t "begin: removal only for finished steps: merged" near "$B" 'ada-worktree remove' 'merged' 500
t "begin: removal only for finished steps: closed" near "$B" 'ada-worktree remove' 'closed' 500
t "begin: removal only for finished steps: shelved" near "$B" 'ada-worktree remove' 'shelved' 500
t "begin: never removes a worktree whose PR is open" near "$B" 'ada-worktree remove' "PR is (still )?open|open PR|PR.{0,20}still open" 600
t "begin: removal offered one by one" near "$B" 'ada-worktree remove' "one by one|one at a time" 500
t "begin: removal waits for the developer's yes" near "$B" 'ada-worktree remove' "yes" 500
t "begin: no step facts in auto-memory" hasi "$B" 'auto-?memory'
t "begin: auto-memory: step notes go in the handoff files" near "$B" 'auto-?memory' 'handoff' 400
t "begin: auto-memory: durable knowledge goes in SYSTEM.md and friends" near "$B" 'auto-?memory' 'SYSTEM\.md' 400
t "begin: the local site still runs one checkout (PROJECT.md, Local copies)" hasi "$B" 'Local copies'
t "begin: removal offers are only for worktrees under the main checkout's .claude/worktrees/ (Ada, 2026-10-28)" \
  near "$B" 'ada-worktree remove' "only[^.]{0,80}\.claude/worktrees|\.claude/worktrees[^.]{0,80}only|outside" 700

# Begin: a Branch: the check can't read (Claudia's Must Fix; Definition of Done 3 and 8). check
# exits 2 for it, and begin step 6 says what Ada does.
t "begin: says what ada-worktree check's exit 2 means" near "$B" 'ada-worktree check' 'exit(s)? (code )?2' 900
t "begin: on exit 2 it shows the developer the Branch: line" near "$B" 'exit(s)? (code )?2' 'Branch:' 600
t "begin: on exit 2 it compares by hand with git branch --show-current" near "$B" 'exit(s)? (code )?2' 'git branch --show-current' 800
t "begin: on exit 2 it rewrites Branch: as the name alone" near "$B" 'exit(s)? (code )?2' "(rewrit|replac)[^.]{0,80}(name alone|only the name|just the name|the name)" 900
t "begin: the rewrite waits for the developer's confirmation" near "$B" "(rewrit|replac)[^.]{0,80}Branch:|Branch:[^.]{0,80}(rewrit|replac)" 'confirm|yes|agrees?' 400

# Begin: a finished step whose checkpoint belongs elsewhere is archived by Ada, after the developer's
# approval (Amendment 2026-10-28; Definition of Done 15).
t "begin: on \`other\` with the PR merged: archiving" near "$B" '`other`' 'merged' 1000
t "begin: on \`other\` with the PR closed: archiving" near "$B" '`other`' 'closed' 1000
t "begin: it asks the developer to approve archiving, and waits for yes" near "$B" 'archiv' 'approv|yes' 500
t "begin: on yes Ada archives it herself: ada-worktree remove for a worktree under .claude/worktrees/" near "$B" 'archiv' 'ada-worktree remove' 700
t "begin: otherwise ada-handoff-init --archive in that checkout" near "$B" '`other`' 'ada-handoff-init --archive' 2500
t "begin: never hands the developer a command to archive it" near "$B" '`other`' "(never|not|don't|do not)[^.]{0,50}(hand|give|print|show|tell)[^.]{0,60}command" 1500
t "begin: never archives unasked" near "$B" 'archiv' "unasked|without (a |the |their )?(yes|approval|asking)|(never|not|don't|do not)[^.]{0,40}(unless|until|without)|only (after|on|with)[^.]{0,30}(yes|approval)" 600

# Begin: a fix after a bughunt (Amendment 2026-10-28, KG-8; Definition of Done 16). Grace's
# FINDINGS.md is in her bughunt/* worktree; Ada finds it there, asks which, and copies it into the
# fix's worktree before step 5 reads it. Written from the brief, before the skill's words.
t "begin: looks for FINDINGS.md in the bughunt/* worktrees" near "$B" 'FINDINGS\.md' 'bughunt/' 700
t "begin: the bughunt worktrees are the ones under the main checkout's .claude/worktrees/" near "$B" 'bughunt/' 'main checkout' 700
t "begin: finds them with ada-worktree list" near "$B" 'FINDINGS\.md' 'ada-worktree list' 900
t "begin: asks the developer which bughunt the fix is for" near "$B" 'FINDINGS\.md' 'which( one)?( of them)?( the fix is for| is it for| bughunt| findings)?' 700
t "begin: copies FINDINGS.md into the fix's worktree" near "$B" 'cop(y|ies|ied)[^.]{0,120}FINDINGS\.md|FINDINGS\.md[^.]{0,120}cop(y|ies|ied)' "worktree" 400
t "begin: the copy is made before step 5 reads it" near "$B" 'cop(y|ies|ied)[^.]{0,200}FINDINGS\.md|FINDINGS\.md[^.]{0,200}cop(y|ies|ied)' "before[^.]{0,80}(step 5|reads?|reading)" 400
t "begin: step 5 still reads FINDINGS.md" near "$B" 'step 5|5\.' 'FINDINGS\.md' 1500
t "bughunt: the hand-off says /ada:begin finds FINDINGS.md in this worktree (consistent with begin)" near "$BH" 'FINDINGS\.md' 'worktree' 600

# Pickup (Definition of Done 8).
t "pickup: runs \`ada-worktree check\`" has "$P" 'ada-worktree check'
t "pickup: sends \`other\`, exit 2 and finished steps to begin's checkpoint.md" has "$PS" 'checkpoint.md'
pc=$(ln_of "$P" 'ada-worktree check'); pr=$(ln_of "$P" 'Report in one paragraph')
echo "info: pickup: 'ada-worktree check' at line ${pc:-none}; 'Report in one paragraph' at ${pr:-none}"
t "pickup: the check comes before the report (the resume)" test -n "$pc" -a -n "$pr" -a "${pc:-0}" -lt "${pr:-0}"
t "pickup: on 'other' it doesn't resume" near "$P" 'ada-worktree check' '`?other`?' 500
t "pickup: on 'other' it moves with EnterWorktree" has "$P" 'EnterWorktree'
t "pickup: otherwise it names the branch and checkout and stops" near "$P" '`?other`?' 'stop' 600
t "pickup: says what ada-worktree check's exit 2 means" near "$P" 'ada-worktree check' 'exit(s)? (code )?2' 900
t "pickup: on exit 2 it shows the developer the Branch: line" near "$P" 'exit(s)? (code )?2' 'Branch:' 600
t "pickup: on exit 2 it compares by hand with git branch --show-current" near "$P" 'exit(s)? (code )?2' 'git branch --show-current' 800
t "pickup: on exit 2 it rewrites Branch: as the name alone" near "$P" 'exit(s)? (code )?2' "(rewrit|replac)[^.]{0,80}(name alone|only the name|just the name|the name)" 900
t "pickup: the rewrite waits for the developer's confirmation" near "$P" "(rewrit|replac)[^.]{0,80}Branch:|Branch:[^.]{0,80}(rewrit|replac)" 'confirm|yes|agrees?' 400
t "pickup: on \`other\` with the PR merged: archiving" near "$P" '`other`' 'merged' 1000
t "pickup: on \`other\` with the PR closed: archiving" near "$P" '`other`' 'closed' 1000
t "pickup: it asks the developer to approve archiving, and waits for yes" near "$P" 'archiv' 'approv|yes' 500
t "pickup: on yes Ada archives it herself: ada-worktree remove for a worktree under .claude/worktrees/" near "$P" 'archiv' 'ada-worktree remove' 700
t "pickup: otherwise ada-handoff-init --archive in that checkout" near "$P" '`other`' 'ada-handoff-init --archive' 2500
t "pickup: never hands the developer a command to archive it" near "$P" '`other`' "(never|not|don't|do not)[^.]{0,50}(hand|give|print|show|tell)[^.]{0,60}command" 1500
t "pickup: never archives unasked" near "$P" 'archiv' "unasked|without (a |the |their )?(yes|approval|asking)|(never|not|don't|do not)[^.]{0,40}(unless|until|without)|only (after|on|with)[^.]{0,30}(yes|approval)" 600
t "pickup: no step facts in auto-memory" hasi "$P" 'auto-?memory'
t "pickup: auto-memory: notes go in the handoff files" near "$P" 'auto-?memory' 'handoff' 400

# Planning (Definition of Done 7; Decisions, a plan lives in the main checkout).
t "planning: the 'Building in' line" has "$PL" 'Building in `'
t "planning: '... the live brief is there.'" has "$PL" 'the live brief is there'
t "planning: the line goes under the plan/ brief's title" near "$PL" 'Building in' 'title' 400
t "planning: the plan/ copy isn't edited after that" near "$PL" 'plan/' "(isn't|is not|not) edited|no longer edited" 400
t "planning: amendments go in the worktree's brief" near "$PL" 'amendments?' "worktree.{0,20}brief|brief.{0,20}worktree" 300
t "planning: a plan is drafted and agreed from the main checkout" hasi "$PL" 'main checkout'
t "planning: each unit gets its own worktree (ada-worktree, or 'its own worktree')" hasi "$PL" 'ada-worktree|its own worktree' 

# Iris and Grace follow the same rules (Ada's In scope, 2026-10-27 and 2026-10-28).
t "bughunt: makes its worktree with ada-worktree new bughunt/" has "$BH" 'ada-worktree new bughunt/'
t "bughunt: moves there with EnterWorktree, with the cd && claude fallback" near "$BH" 'EnterWorktree' 'cd [^`]*&& claude' 900
t "bughunt: the old step 8 wording (git reflog) is gone" not has "$BH" 'git reflog'
t "design: Iris reads PLAN.md in the main checkout" near "$DS" 'plan/PLAN\.md' 'main checkout|ada-worktree main' 400
t "design: Iris changes plan/DESIGN-<task id>.md in the main checkout" near "$DS" 'DESIGN-<task id>\.md' 'main checkout|ada-worktree main' 400
t "DEVELOPING.md: the bin/ list names ada-worktree" has DEVELOPING.md 'ada-worktree'
t "DEVELOPING.md: ada-plan reads the main checkout's plan" near DEVELOPING.md 'ada-plan' 'main checkout' 500

# Templates (Definition of Done 9).
for f in plugins/ada/templates/handoff/CHECKPOINT.md plugins/ada/templates/handoff/BRIEF.md; do
  t "$f: a Branch: line" grep -Eq '^(- )?Branch:' "$f"
  t "$f: a Worktree: line" grep -Eq '^(- )?Worktree:' "$f"
  t "$f: one Branch: line only" test "$(grep -Ec '^(- )?Branch:' "$f")" = 1
done

# Docs (Definition of Done 10).
t "README: a 'Several steps at once' section" grep -Eq '^#+ Several steps at once' README.md
section '## Several steps at once' README.md > "$W/readme"; section '### Several steps at once' README.md >> "$W/readme"
t "README section: names ada-worktree" grep -q 'ada-worktree' "$W/readme"
t "SYSTEM.md: describes ada-worktree" grep -q 'ada-worktree' .claude/team/SYSTEM.md
t "SYSTEM.md: ada-worktree in the Scripts list" eval "grep -F '**Scripts**' -A 3 .claude/team/SYSTEM.md | grep -q ada-worktree"
t "SYSTEM.md: where the plan lives (the main checkout or worktree)" near .claude/team/SYSTEM.md 'plan/' 'main (checkout|worktree)' 300
t "API.md: an ada-worktree section" grep -Eq '^### `ada-worktree`' .claude/team/API.md
section '### `ada-worktree`' .claude/team/API.md > "$W/api"
for w in 'new' 'check' 'list' 'remove' 'ok' 'none' 'other'; do t "API.md ada-worktree section has: $w" grep -qw -- "$w" "$W/api"; done
t "API.md: ada-plan or ada-handoff-init says the plan is the main checkout's" \
  eval 'section "### \`ada-plan\`" .claude/team/API.md > "$W/ap"; section "### \`ada-handoff-init\`" .claude/team/API.md >> "$W/ap"; grep -Eqi "main (checkout|worktree)" "$W/ap"'
section '## Unreleased' CHANGELOG.md > "$W/unrel"
t "CHANGELOG Unreleased: a '- **...**' bullet (S105)" grep -Eq '^- \*\*.*\*\*' "$W/unrel"
t "CHANGELOG Unreleased: mentions worktrees" grep -qi 'worktree' "$W/unrel"
t "CHANGELOG Unreleased: 'Nothing yet.' replaced" not grep -q 'Nothing yet' "$W/unrel"

# Out of scope (P31, and the brief's Constraints): unchanged since the merge base.
MB=$(git merge-base "$BASE" HEAD 2>/dev/null) || MB=
t "merge base of $BASE and HEAD found" test -n "$MB"
for f in plugins/ada/.claude-plugin/plugin.json .claude-plugin/marketplace.json .claude/team/PROJECT.md; do
  t "unchanged (out of scope): $f" git diff --quiet "$MB" -- "$f"
done

echo "$fails failed"
rm -rf "$W"; [ "$fails" = 0 ]
```

### Part B: dry runs (a person; each run a fresh `claude --plugin-dir …` session)

Record for each run the session's words where they matter, and `git worktree list` before and
after.

1. **New step from the main checkout.** In the practice project's main checkout, `/ada:begin`
   and give the task. Answer Ada until she has the branch. Expected: she runs
   `ada-worktree new <branch> <base>` (look for the command), then `EnterWorktree` with the path
   it printed; the session carries on in `.claude/worktrees/<slug>`. Then: `git worktree list`
   shows it on `<branch>`; `git -C <that worktree> rev-parse --abbrev-ref <branch>@{u}` fails (no
   upstream); the brief she writes is in the worktree's `.claude/handoff/BRIEF.md` with
   `Branch:` and `Worktree:` filled in; the main checkout's `.claude/handoff/` is as it was
   (compare `ls -l`); `git status` in the main checkout shows nothing.
2. **The fallback.** As run 1, with a new task, starting the session with
   `claude --plugin-dir <this checkout>/plugins/ada --disallowedTools EnterWorktree`. Expected:
   after `ada-worktree new`, Ada prints `cd <the path> && claude` and stops; she writes no brief
   in the main checkout.
3. **The exception.** `cd` into run 2's worktree (fresh: its brief has no Outcome; on the step's
   branch) and start a session with `/ada:begin`. Expected: no second worktree is made
   (`git worktree list` unchanged); Ada carries on with the step here.
4. **Pickup in the right worktree.** End run 1's session at a phase break (Ada writes
   `CHECKPOINT.md`, with `Branch:` and `Worktree:`). Start a new session in that worktree with
   `/ada:pickup`. Expected: she runs `ada-worktree check`, gets `ok`, and reports where we
   stopped.
5. **Pickup in the wrong place, the worktree exists.** Copy run 1's `CHECKPOINT.md` into the
   main checkout's `.claude/handoff/` (save the main checkout's own first), start a session there
   with `/ada:pickup`. Expected: `ada-worktree check` says `other <run 1's branch> <its path>`;
   Ada doesn't resume here; she moves the session with `EnterWorktree` to run 1's worktree and
   picks up from its checkpoint. Put the main checkout's own checkpoint back afterwards.
6. **Pickup in the wrong place, no worktree.** Make a branch with no worktree (`git branch
   change/gone`). Edit the copied checkpoint's `Branch:` to it, start a session with `/ada:pickup`
   in the main checkout. Expected: `other change/gone -`; Ada names the branch and the checkout the
   checkpoint belongs to and **stops**, resuming nothing. (A name that is no local branch at all
   gives exit 2, not `other`: run 9.) Restore the checkpoint.
7. **Finished steps.** Push run 3's branch and open a PR, then close it; leave run 1's PR open
   (open one if needed). In the main checkout, `/ada:begin`. Expected: Ada runs
   `ada-worktree list`, offers `ada-worktree remove` for run 3's worktree only (closed PR), and
   waits for your yes. Answer yes: its handoff files are in the main checkout's
   `.claude/handoff/archive/<date>-<slug>/`, the worktree is gone, the branch remains
   (`git branch`). She never offers run 1's (open PR). Say no to anything else she offers.
8. **A plan.** In the main checkout, have Ada draft and agree a two-unit plan (`ada:planning`),
   then start Unit A's build. Expected: Unit A gets its own worktree (as run 1); its brief is
   copied into that worktree's `.claude/handoff/BRIEF.md`; the main checkout's
   `.claude/handoff/plan/<Unit A's task id>.md` has, under its title, "Building in
   `<the worktree path>`; the live brief is there."; the unit's worktree has no
   `.claude/handoff/plan/`. From the unit's worktree, `ada-plan status` (this checkout's, by path)
   reports the plan's two units. Ask Ada to amend Unit A's brief: the amendment goes in the
   worktree's `BRIEF.md`, and the `plan/` copy is not edited (`git diff --no-index` it against a
   copy taken before).
9. **A `Branch:` that isn't a plain name** (a checkpoint the released plugin wrote). In the main
   checkout, save its own checkpoint, then write `CHECKPOINT.md` with `- Branch:` as each of these
   in turn: `change/x, pushed` (with `change/x` a branch the repo has); `` `feature/gone` ``;
   `fix/a/…123`; ``none yet; on `production`, clean``. For each, start a new session with
   `/ada:pickup`, and once with `/ada:begin`. Expected: `ada-worktree check` exits 2 (run it by
   hand: `echo $?`); Ada shows you the `Branch:` line, compares it with `git branch
   --show-current`, resumes nothing, and asks you to confirm; only after your yes does she rewrite
   `Branch:` as the name alone. She never says the checkpoint belongs to a branch called `none` or
   `change/x,`. Restore your own checkpoint.
10. **A finished step is archived on approval.** (a) In the main checkout, write a checkpoint
    whose `Branch:` is a branch with no worktree, with a PR you have merged or closed. Start
    `/ada:pickup`, then `/ada:begin` in a new session. Expected: `other <branch> -`; Ada asks
    whether to archive it and waits. Say no: nothing moves. Ask again and say yes: Ada archives it
    herself (`ada-handoff-init --archive` in that checkout: the files are under
    `.claude/handoff/archive/`), and hands you no command. (b) The same with the checkpoint naming
    a step's worktree under `.claude/worktrees/` (its PR merged or closed): on yes she runs
    `ada-worktree remove` on it (the worktree is gone, the branch stays). (c) With the PR open she
    never offers to archive, and never archives without your yes.
11. **Memory.** After runs 1 to 10 and 13, compare the project's auto-memory folder with the copy taken in
   the precondition. Expected: nothing about any run's step (its branch, task, decisions or
   state) was added. Anything durable Ada learned went into `SYSTEM.md`, `API.md`,
   `STANDARDS.md` or `TEST-AREAS.md` in the step's branch instead.
12. Read `plugins/ada/skills/begin/SKILL.md` (Session start and steps 6 to 9) and `checkpoint.md`,
    `plugins/ada/skills/pickup/SKILL.md`, the new parts of `plugins/ada/skills/planning/SKILL.md`
    and README's "Several steps at once" once, in this checkout: plain British English (S107),
    and nothing contradicts the brief approval step or the PR gate.
13. **A fix after a bughunt (Definition of Done 16).** Needs two bughunts, so each worktree's
    `FINDINGS.md` is different. In the main checkout run `/ada:bughunt` twice, for two small made-up
    problems (say "orders page slow" and "email sent twice"; in a practice project, Grace may
    write the findings from what you tell her), and let each write its `FINDINGS.md` in its own
    `bughunt/<slug>` worktree (`git worktree list` shows both under `.claude/worktrees/`; each
    file's first heading names its own problem). Note `md5sum` of both files, and that the main
    checkout's `.claude/handoff/` has no `FINDINGS.md`.
    (a) End both sessions. In a new session in the **main checkout**, `/ada:begin`, and say the
    fix is for the second problem. Expected: Ada runs `ada-worktree list` (look for it), lists
    both bughunt worktrees, and **asks which one the fix is for**. Answer with the second. She makes
    the fix's worktree (as run 1). Then: the fix worktree's `.claude/handoff/FINDINGS.md` has the
    same `md5sum` as the second bughunt's file and a different one from the first's; the brief she
    writes builds from the second problem's findings and mentions nothing from the first (she read
    the file after the copy: the order in the transcript is `ada-worktree new`, then the copy,
    then reading `FINDINGS.md`); both bughunt files still exist, with their original `md5sum`
    (copied, not moved); the main checkout's `.claude/handoff/` still has no `FINDINGS.md`.
    (b) Remove the first bughunt's worktree (`ada-worktree remove`, by path) and start a fresh
    `/ada:begin` in the main checkout for the same fix. Expected: she still names the one
    bughunt worktree she found and asks you to confirm it is the one, before copying; say yes and
    the file is copied as in (a). (This one-worktree case isn't spelled out in the brief: if she
    copies without asking, record it for Ada, as a question about the brief, not a failure.)
    (c) In a practice project with no `bughunt/*` worktree, `/ada:begin` for a new fix: no
    `FINDINGS.md` question, no error, and the step goes on exactly as run 1.
    (d) With `claude --plugin-dir ... --disallowedTools EnterWorktree` (as run 2) and the (a)
    setup: after `cd <path> && claude` in the new worktree, `/ada:begin` still reaches
    `FINDINGS.md`: either it was copied before she stopped, or she asks which bughunt there (the
    brief says only "before step 5 reads it"). Record which.

Afterwards: `ada-worktree remove` (this checkout's, by path) each worktree the runs made (run 13's
bughunt worktrees too), close their PRs, and delete their branches by hand if you want them gone (the script never does).

## Expected

Part A: every line `PASS`, last line `0 failed`, exit 0; step 3's broken copy prints `FAIL` for
the pickup `EnterWorktree` line. Part B: each Expected observed; any that isn't is a failure,
with the session's words quoted. (Part A's archiving and exit 2 checks look for the wording in the
skills; they were written from the brief before the skills' text, so a check that fails on
wording alone is for Tess and Bob to settle, not to loosen.)

Sources: the brief's Decisions (every new step gets its own worktree, `ada-worktree new` then
`EnterWorktree`, path form; the `cd <path> && claude` fallback; the exception; replaces begin
step 8; `ada-worktree check` before reading a checkpoint, on `other` move or stop; a plan lives in
the main checkout, its `plan/<task id>.md` marked "Building in `<worktree path>`; the live brief
is there." and not edited after; finished steps only, one by one with a yes, never an open PR;
memory stays out of steps; Amendment 2026-10-28: a finished step's checkpoint is archived by Ada
after the developer's yes; Amendment 2026-10-28, KG-8: a fix after a bughunt finds `FINDINGS.md` in
the `bughunt/*` worktrees via `ada-worktree list`, asks which, and copies it into the fix's worktree
before step 5 reads it) and Definition of Done 7 to 10, 15 and 16; `REVIEW-FEEDBACK.md` (Must Fix:
`check`'s exit 2 for a `Branch:` it can't read, and what begin and pickup do then); Constraints (the local site's single
checkout unchanged; no version change, P31); S105, S107, S109.

## Observe at

Part A: the script's output. Part B: the session transcript (the commands Ada runs, her
questions), `git worktree list`, `git branch`, the handoff files named, the main checkout's
`.claude/handoff/archive/` and `plan/`, the PRs on GitHub, the auto-memory folder.

## Regression

Part A's last group: `plugin.json`, `marketplace.json` and `PROJECT.md` unchanged. In part B, the
rest of begin is unchanged: run 1 still asks for the task, writes the brief, checks overlap and
confirms the brief with the developer before anything is built; the brief approval step still
applies when `PROJECT.md` names approvers.
