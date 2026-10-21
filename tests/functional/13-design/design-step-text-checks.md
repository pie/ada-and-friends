# The design step is written into the skills, role files, templates and docs

- Area: 13 Design (`/ada:design`, Iris); also 08 Session flow, 09 Roles, 11 Templates
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (part A is a self-checking shell script; part B is read by a person)
- Last run: 21 Oct 2026, part A 124 PASS, 0 failed; part B 32 of 32 items found (see the PR)

## Precondition

- This checkout, with the change in its working tree and `origin/next-release` fetched.
- The `claude` CLI on the PATH, for the two plugin checks (they are skipped without it).
- No scratch repo: part A only reads this checkout.

## Steps

### Part A: the mechanical checks

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: in a scratch copy of the repo (`git worktree add` or `cp -R`), change
   one word of the "Don't offer it when" paragraph in the copy's `agents/claudia.md`, and run
   `REPO=<the copy> bash check.sh`. It must print `FAIL` for `claudia.md`.

```bash
#!/bin/bash
# Text checks for the design step. PASS/FAIL per check; exit 1 if any FAIL.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
cd "$REPO" || exit 2
P=plugins/ada
S=$P/skills/design/SKILL.md
W=$(mktemp -d)
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
sha() { if command -v sha256sum >/dev/null 2>&1; then tr -d '\r' < "$1" | sha256sum | cut -d' ' -f1; else tr -d '\r' < "$1" | shasum -a 256 | cut -d' ' -f1; fi; }
section() { awk -v h="$1" '$0==h{p=1;next} p&&/^## /{exit} p' "$2"; }
in_section() { section "$1" "$2" | grep -Eqi -- "$3"; }

# DoD 1: the skill is a command, described.
t "skills/design/SKILL.md exists" test -f "$S"
awk 'NR==1&&$0!="---"{exit} NR==1{next} /^---$/{exit} {print}' "$S" > "$W/fm" 2>/dev/null
t "frontmatter: name: design" grep -qx 'name: design' "$W/fm"
t "frontmatter: description mentions /ada:begin" grep -q '^description:.*/ada:begin' "$W/fm"
t "frontmatter: not hidden from the command list" not grep -q '^user-invocable: *false' "$W/fm"
skills_listed() { grep '^ *Skills (' "$W/details" | sed 's/^ *Skills ([0-9]*) *//' | tr ',' '\n' | sed 's/^ *//; s/ *$//' | grep -qx design; }
if command -v claude >/dev/null 2>&1; then
  claude --plugin-dir "$REPO/$P" plugin details ada > "$W/details" 2>&1
  t "claude --plugin-dir: plugin details lists the design skill" skills_listed
  t "claude plugin validate passes" claude plugin validate "$REPO/$P"
else
  echo "SKIP claude CLI not found: plugin details, plugin validate"
fi

# DoD 2: terms the skill must use (part B checks each is under its own heading).
for term in 'ada:standards-accessibility' 'quickstart' 'intent' 'ArtifactComments' '320' '768' '1280' \
            'local' 'screenshot' 'DESIGN.md' 'Definition of Done' 'P32' 'P39' '/ada:begin' 'brand'; do
  t "design skill mentions: $term" grep -qiF -- "$term" "$S"
done
t "nothing points at https://claude.ai/design" not grep -rqE 'https?://(www\.)?claude\.ai/design' "$P"
t "no hard-coded type URL in the design skill" not grep -qE 'type_url.*https?://' "$S"

# DoD 3: the test, word for word, in exactly four files. begin/SKILL.md holds the text.
B=$P/skills/begin/SKILL.md
lead1='**Offer a design step when the work adds or changes something a person sees or does:**'
lead2="**Don't offer it when:**"
lead3="**If you're unsure, ask yourself:**"
end1='with no existing pattern on the site to follow.'
end2='or to match a design already agreed.'
end3='offer the design step.'
i=0
for lead in "$lead1" "$lead2" "$lead3"; do
  i=$((i+1))
  eval "end=\$end$i"
  t "begin/SKILL.md: paragraph $i appears once" test "$(grep -cF -- "$lead" "$B")" = 1
  line=$(grep -F -- "$lead" "$B" | head -1 | sed 's/^[[:space:]>]*//; s/[[:space:]]*$//')
  case "$line" in *"$end") r=0 ;; *) r=1 ;; esac
  ok $r "begin/SKILL.md: paragraph $i is whole on one line (grep can find it)"
  for f in agents/bob.md agents/claudia.md agents/tess.md; do
    t "$f: paragraph $i word for word, once" test -n "$line" -a "$(grep -cF -- "$line" "$P/$f")" = 1
  done
done
copies=$(git grep --untracked -lF -- "$lead1" -- . ':!tests' ':!.claude/handoff' | sort | tr '\n' ' ')
want="$P/agents/bob.md $P/agents/claudia.md $P/agents/tess.md $P/skills/begin/SKILL.md "
t "the test appears in those four files and nowhere else" test "$copies" = "$want"

# DoD 4 and 5 (part B reads them): the words that must be there.
t "begin/SKILL.md loads ada:design" grep -qF 'ada:design' "$B"
t "begin/SKILL.md finishes the brief from DESIGN.md" grep -qF 'DESIGN.md' "$B"
t "planning/SKILL.md mentions the design step" grep -qiE 'design' "$P/skills/planning/SKILL.md"

# Amendments Q1 to Q6 (BRIEF.md, Amendments).
for f in agents/bob.md agents/claudia.md agents/tess.md skills/begin/SKILL.md; do
  t "$f keys on DESIGN.md saying Status: agreed (Q1)" grep -qF 'Status: agreed' "$P/$f"
done
t "begin/SKILL.md: draft, or agreed with no brief, is a standalone run (Q1)" grep -qE '`draft`|Status: draft' "$B"
t "design skill: archives a finished step first (Q2)" grep -qF -- '--archive' "$S"
t "design skill: checks CHECKPOINT for an open step (Q2)" grep -qF 'CHECKPOINT' "$S"
t "planning/SKILL.md: plan/DESIGN-<task id>.md (Q3)" grep -qF 'DESIGN-<task id>.md' "$P/skills/planning/SKILL.md"
t "begin/SKILL.md: asks the developer to share the artifact (Q4)" grep -qiE 'share' "$B"
t "begin/SKILL.md: a design summary in Constraints (Q4)" grep -qiE 'summary' "$B"
t "begin/SKILL.md: fills the PR's Design: line (Q5)" grep -qF 'Design:' "$B"
# Q6: a standalone design the developer says isn't for this task is set to Status: superseded. Check
# begin, not the DESIGN.md template: the template's status line already holds the word.
t "begin/SKILL.md: a design that isn't for this task is set to superseded (Q6)" grep -qF 'superseded' "$B"

# DoD 6: role files.
t "bob.md: UI not specified: <what> in REVIEW-REQUEST" grep -qF 'UI not specified:' "$P/agents/bob.md"
t "claudia.md: checks the build against DESIGN.md" grep -qF 'DESIGN.md' "$P/agents/claudia.md"
for w in sizes colours states breakpoints 'Must Fix'; do
  t "claudia.md mentions: $w" grep -qiF -- "$w" "$P/agents/claudia.md"
done
t "tess.md: design comparison when DESIGN.md exists" grep -qF 'DESIGN.md' "$P/agents/tess.md"
t "tess.md: not verified without Chrome" grep -qiF 'not verified' "$P/agents/tess.md"

# Decisions: Iris is a skill; no agent gets the canvas.
t "no agents/iris.md" test ! -e "$P/agents/iris.md"
t "no agent's tools: line changed" not sh -c "git diff '$BASE' -- $P/agents | grep -Eq '^[-+]tools:'"
t "no agent's tools: line has Artifact" not grep -qE '^tools:.*Artifact' $P/agents/*.md

# DoD 7: templates.
RR=$P/templates/handoff/REVIEW-REQUEST.md
PT=$P/templates/repo/.github/pull_request_template.md
GT=.github/pull_request_template.md
t "REVIEW-REQUEST template: one UI not specified: line" test "$(grep -c 'UI not specified:' "$RR")" = 1
t "plugin PR template: a Design: line" grep -q 'Design:' "$PT"
t "plugin PR template: the Design: line as amended (Q5)" grep -qF 'Design: <the Design artifact link | text only, in the brief | none>' "$PT"
t "this repo's PR template is the plugin's, byte for byte" cmp -s "$PT" "$GT"
t "plugin PR template: no line removed" not sh -c "git diff '$BASE' -- '$PT' | grep '^-' | grep -v '^---' | grep -q ."
t "this repo's .template-hashes: PR template is an untouched copy" \
  grep -Fxq "copied $(sha "$GT") $GT" .claude/team/.template-hashes

# DoD 8 static: ada-handoff-init (the behaviour is in tests/functional/05-handoff-files/).
t "bash -n ada-handoff-init" bash -n "$P/bin/ada-handoff-init"
t "bash --posix -n ada-handoff-init" bash --posix -n "$P/bin/ada-handoff-init"
t "ada-handoff-init: no bash 4 features" \
  not grep -nE 'mapfile|readarray|declare -A|local -A|\$\{[A-Za-z_][A-Za-z_0-9]*(,,|\^\^|,|\^)\}|&>>|coproc' "$P/bin/ada-handoff-init"

# DESIGN.md template sections (source, "DESIGN.md sections").
D=$P/templates/handoff/DESIGN.md
for term in Asana Designed Status draft agreed superseded artifact 320 768 1280 rem contrast spacing \
            state copy 'alt text' P32 P39; do
  t "DESIGN.md template mentions: $term" grep -qiF -- "$term" "$D"
done
for h in 'The problem' 'What was found' 'Options considered' 'The agreed design' 'Constraints' 'Out of scope'; do
  t "DESIGN.md template heading: $h" grep -Eqi "^#+ +$h" "$D"
done
t "DESIGN.md template has a Status: line" grep -qF 'Status:' "$D"
t "the blank template reads neither Status: agreed nor Status: draft (Q1)" \
  not grep -Eq 'Status: *\**(agreed|draft)\** *$' "$D"

# DoD 10: docs.
t "README: team row for **Iris**, the designer" grep -qF '| **Iris**, the designer' README.md
t "README: Use it has a /ada:design row" in_section '## Use it' README.md '^\| *`/ada:design`'
iris_para() { awk '/talk only to Ada/{p=1} p&&/^$/{exit} p' README.md | grep -q Iris; }
t "README: the 'talk only to Ada' paragraph brings Iris in" iris_para
t "DEVELOPING: names Iris" grep -q Iris DEVELOPING.md
t "DEVELOPING: lists skills/design/" grep -q 'skills/design/' DEVELOPING.md
t "DEVELOPING: has a Cost note" grep -Eq '^#+ .*Cost|\*\*Cost' DEVELOPING.md
t "API.md: Skills row for design" grep -Eq '^\| *`design` *\| *`/ada:design` *\|' .claude/team/API.md
t "SYSTEM.md, The model: names the design skill" in_section '## The model' .claude/team/SYSTEM.md '`design`'
t "SYSTEM.md, The model: subagents and the canvas" in_section '## The model' .claude/team/SYSTEM.md 'canvas|Artifact'
row=$(grep -Ei '^\| *[0-9]+ *\|[^|]*design' .claude/team/TEST-AREAS.md | head -1)
rowhas() { printf '%s\n' "$row" | grep -Eqi -- "$1"; }
t "TEST-AREAS: a design row" test -n "$row"
t "TEST-AREAS design row: the canvas route" rowhas 'canvas'
t "TEST-AREAS design row: the text-only fallback" rowhas 'text-only|text only'
t "TEST-AREAS design row: back to design partway through a step" rowhas 'partway|part way'
n=$(printf '%s\n' "$row" | sed 's/^| *\([0-9]*\).*/\1/')
t "tests/functional/$n-design/ matches the row's number" test -d "tests/functional/$n-design"
t "CHANGELOG Unreleased: an entry for /ada:design or Iris" in_section '## Unreleased' CHANGELOG.md '/ada:design|Iris'
t "CHANGELOG Unreleased: no 'Nothing yet.'" not in_section '## Unreleased' CHANGELOG.md '^Nothing yet\.'

# DoD 12 (P31): no version numbers.
t "plugin.json and marketplace.json unchanged" \
  test -z "$(git diff "$BASE" -- $P/.claude-plugin/plugin.json .claude-plugin/marketplace.json)"
t "CHANGELOG: no new version heading" not sh -c "git diff '$BASE' -- CHANGELOG.md | grep -Eq '^\+## +v?[0-9]'"

# New files: LF only, no trailing spaces.
for f in "$S" "$D"; do
  [ -f "$f" ] || continue
  t "$f: no CR" not grep -q "$(printf '\r')" "$f"
  t "$f: no trailing spaces" not grep -qE '[[:space:]]+$' "$f"
done
t "git diff --check against $BASE" git diff --check "$BASE"

echo "$fails failed"; rm -rf "$W"
[ "$fails" = 0 ]
```

### Part B: read by a person

Read each file named. For each item, write down the heading it sits under (or "missing"). Every
item must be found, under a heading a reader would look in. The words in quotes are the brief's.

**`plugins/ada/skills/design/SKILL.md`** (Definition of Done 2; the source's flow, steps 3 to 7):

1. Iris loads `ada:standards-accessibility` first, before looking at pages or making options.
   P32 to P39 are the bar.
2. She reads the draft brief's outcome, Definition of Done and out of scope, and does not ask the
   developer those again.
3. Look first: with Claude in Chrome, screenshots of the current pages on the **local** site at
   320, 768 and 1280px, measured against P32 to P39, failures reported before any proposal.
   Without Chrome, she asks for screenshots or URLs.
4. The canvas: Artifact `quickstart` with intent `design`, then a publish with the `type_url` the
   quickstart returns. No type URL written into the skill; nothing points at claude.ai/design.
   The artifact holds the current page beside 2 or 3 options at mobile and desktop widths; the
   states hover, focus, error, empty, loading; a note under each option naming the requirement it
   answers. She tells the developer to open it.
5. Iterating: comments read with `ArtifactComments` (or replies in the terminal); she updates the
   **same** artifact, so the link stays the same.
6. A new requirement the design turns up: she asks the developer and adds it to the brief's
   Definition of Done; it never goes only into the design.
7. Sign-off: when the developer picks a design, she checks it against P32 to P39, fixes any
   failure, and says what she changed.
8. She writes `.claude/handoff/DESIGN.md` (its sections as in the template), with `Status: agreed`
   once the developer has picked a design (`draft` before that), and hands back to Ada.
9. Standalone use: at her start she checks `CHECKPOINT.md`. A finished step is archived first
   (`ada-handoff-init --archive`, as begin's session start does), so her `DESIGN.md` lands in a
   fresh set; an open step means she says so and stops (design for that step goes through Ada,
   `/ada:pickup`). Then she gathers light requirements herself (who it's for, what it must do)
   and follows look first to sign-off; `/ada:begin` later treats them as a draft to confirm.
   (Amendment Q2)
10. Back to design partway through a step: she designs only the new part, in the same artifact,
    and updates `DESIGN.md`.
11. The three fallbacks: no Design type (text spec in `DESIGN.md`, no canvas or iteration, the
    rest the same); no Chrome (screenshots or URLs; Tess's comparison is "not verified"); small
    tweaks (she says it doesn't need her and points to `/ada:begin`).
12. May: read the repo and the local site in Chrome; create and update Design artifacts.
    May not: change code, the live site or customer data; publish or share an artifact beyond the
    developer; copy another organisation's branding (she uses the client's own assets, from the
    repo or a brand kit the developer supplies).
13. The frontmatter description says when to use `/ada:design` and that `/ada:begin` usually
    brings it in.

**`plugins/ada/skills/begin/SKILL.md`** (Definition of Done 4):

14. In Direction mode, after "done" is pinned down (who, done in observable terms, out of scope
    written into the draft brief), Ada applies the test and, when it says so, offers the design
    step and loads `ada:design`. The offer comes before the rest of the brief is written.
15. The test is there in full, word for word (part A checks the words).
16. After Iris: Ada finishes the brief from `DESIGN.md`: build order, and Constraints carrying
    the artifact link and a short summary of the agreed design; for a text-only design, Ada
    copies `DESIGN.md`'s "The agreed design" into Constraints. When `ada-brief who` says
    `required`, Ada asks the developer to share the artifact with the approver (the developer's
    decision, not Iris's or Ada's). (Amendment Q4)
17. A standalone run is a `DESIGN.md` saying `Status: draft`, or `Status: agreed` with no brief
    written yet: Ada confirms its requirements with the developer before writing the brief. The
    blank template (neither) triggers nothing. (Amendment Q1)
18. Partway through a step, as the source's table says: when the test passes, Ada pauses the step
    and offers Iris; Iris designs only the new part; Ada updates the brief, which needs approving
    again. When it fails (an existing pattern, unchanged), Ada adds a line to the brief naming the
    pattern and the step carries on.
19. At the PR gate Ada fills the template's `Design:` line with one of: the Design artifact link;
    `text only, in the brief`; `none`. (Amendment Q5)

**`plugins/ada/skills/planning/SKILL.md`** (Definition of Done 5):

20. The design step runs once per feature, before its units are briefed, so the units share one
    design. The agreed design is kept as `.claude/handoff/plan/DESIGN-<task id>.md` (the plan
    folder survives `--archive`) and copied to `.claude/handoff/DESIGN.md` at the start of each
    unit, beside the brief copy. (Amendment Q3)

**Role files** (Definition of Done 6):

21. `bob.md`: he works to `DESIGN.md` only when it says `Status: agreed`. When the brief or that
    design doesn't specify some UI, he does not invent it: he
    stops that part and records `UI not specified: <what>` in `REVIEW-REQUEST.md`. The test is
    quoted in full.
22. `claudia.md`: when `DESIGN.md` says `Status: agreed`, a check that the build matches it (sizes, colours, states,
    breakpoints); unspecified UI is Must Fix, citing the test. The test is quoted in full.
23. `tess.md`: when `DESIGN.md` says `Status: agreed`, a Chrome comparison of the built page at the design's
    widths, with the differences listed in `TEST-REPORT.md`; "not verified" without Chrome. In
    planning, she flags a Definition of Done item that assumes UI nobody has designed. The test
    is quoted in full. The comparison stays inside the existing Chrome rules (only `LOCAL_URL`,
    only when Ada's spawn says Chrome is connected).
24. None of the three role files tells its role to open the canvas or call Artifact: `DESIGN.md`
    is their spec.

**Templates** (Definition of Done 7):

25. `templates/handoff/REVIEW-REQUEST.md`: the `UI not specified:` line says what to write there
    (what is missing, or none).
26. The PR template's line reads `Design: <the Design artifact link | text only, in the brief | none>` (Amendment Q5), in the plugin's
    template and this repo's `.github/` copy.

**Docs** (Definition of Done 10):

27. README: the team row reads "**Iris**, the designer"; a `/ada:design` row in Use it; next to
    "In a build you talk only to Ada", a line saying Ada brings Iris in when a change needs
    designing.
28. DEVELOPING: Iris in the layout and the team; a "Cost" note, filled from dry run 1 or saying
    "not measured yet".
29. `API.md`, Skills: a `design` row. `SYSTEM.md`, The model: `design` among the commands, and
    that subagents cannot reach the canvas because their `tools:` lists don't include Artifact.
30. `TEST-AREAS.md`: the design row covers the canvas route, the text-only fallback, and
    returning to design partway through a step.
31. `CHANGELOG.md`, Unreleased: one bullet with a bold lead naming the change (S105).
32. The `file:line` references to `skills/begin/SKILL.md` and `bin/ada-handoff-init` in
    `SYSTEM.md`, `API.md` and `DEVELOPING.md` still point at the lines they describe.

## Expected

Part A: every line `PASS`, last line `0 failed`, exit 0. Part B: all 32 items found. Sources: the
brief (Decisions, Constraints, Build order, Definition of Done 1 to 7, 10 and 12, and its
Amendments Q1 to Q5, agreed by Neil on 21 Oct 2026) and the source
spec it includes (`IRIS-SOURCE-BRIEF.md`: the flow, the test, the `DESIGN.md` sections, may and may
not, fallbacks).

## Observe at

The script's output; the files named in part B.

## Regression

- The agents' `tools:` lines are unchanged and none gains Artifact (part A).
- The plugin's PR template only gains lines; this repo's copy stays identical to it, and its hash
  in `.claude/team/.template-hashes` is an untouched copy, so the next refresh here still says
  `current` and later ones can still refresh it (part A).
- No version numbers move (part A).
