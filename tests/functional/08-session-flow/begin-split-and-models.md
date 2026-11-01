# `/ada:begin` keeps a small core and reads the rest on demand, every Agent call names its model, Bob builds small steps on Sonnet, and a project can run lean

- Area: 08 Session flow (skills `begin`, `pickup`, `efficiency`)
- Asana: none (cost review of Ada usage, 30 Oct 2026)
- Automate as: none (part A is a self-checking shell script; part B is a dry run for a person)
- Last run: 30 Oct 2026, part A 0 failed; part B not run yet

## Precondition

- Part A: this checkout, with the change in its working tree, and `origin/next-release` fetched
  from before the change (or any commit before it, as `BASE`).
- Part B: `claude` logged in; an onboarded practice project with the plugin from this checkout
  (`claude --plugin-dir <this checkout>/plugins/ada`).

## Steps

### Part A: the text

1. Save the block below as `check.sh` in a scratch folder (not in this repo) and run
   `REPO=<this checkout> bash check.sh`.
2. Break it on purpose: in a copy of the checkout, delete `model: "sonnet", ` from the Tess run
   line in `plugins/ada/skills/begin/SKILL.md` and run `REPO=<the copy> bash check.sh`. The
   "every Agent call names its model" check FAILs.

```bash
#!/bin/bash
# begin's split into on-demand files, and model on every Agent call. PASS/FAIL; exit 1 if any FAIL.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
D=$REPO/plugins/ada/skills/begin
B=$D/SKILL.md
P=$REPO/plugins/ada/skills/pickup/SKILL.md
E=$REPO/plugins/ada/skills/efficiency/SKILL.md
for f in "$B" "$P" "$E"; do [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }; done
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
all() { cat "$D"/*.md; }

for f in checkpoint.md design-step.md approval.md pr-gate.md; do
  t "begin: $f exists" test -s "$D/$f"
  t "begin: SKILL.md tells Ada to read $f" grep -q "\`$f\`" "$B"
done
t "begin: SKILL.md is under 26000 bytes" test "$(wc -c < "$B")" -lt 26000
t "pickup: reads checkpoint.md" grep -q '`checkpoint.md`' "$P"
t "pickup: reads pr-gate.md at the PR gate" grep -q '`pr-gate.md`' "$P"
# every Agent( call in a skill or agent file names a model
missing=$(grep -rhn 'Agent(subagent_type' "$REPO/plugins/ada/skills" "$REPO/plugins/ada/agents" | grep -v 'model:')
t "every Agent call names its model" test -z "$missing"
[ -n "$missing" ] && printf '  %s\n' "$missing"
t "begin: model table puts Tess's run on Sonnet" grep -Eq '^\| Tess, run mode.*"sonnet"' "$B"
t "begin: model table puts Explore and general-purpose on Sonnet" grep -Eq '^\| An Explore or general-purpose.*"sonnet"' "$B"
t "efficiency: delegated reads name Sonnet" grep -q 'model: "sonnet"' "$E"
T=$REPO/plugins/ada/templates/repo/.claude/team/PROJECT.md
O=$REPO/plugins/ada/skills/onboard/SKILL.md
t "begin: Bob on Sonnet only for a small step with no fragile area" grep -Eq '^\| Bob, plan, build and fix rounds \| `"sonnet"` when .*small.*fragile area.*otherwise `"opus"`' "$B"
t "begin: Bob's model kept for the step and recorded in BUILD-LOG" sh -c "grep -q 'set by the step, not the moment' '$B' && grep -q 'BUILD-LOG.md' '$B'"
t "begin: Bob moves up to Opus, never down" grep -q 'Never move him down partway' "$B"
t "begin: Budget lean adds effort medium to Opus spawns" sh -c "grep -q 'Budget:' '$B' && grep -q 'effort: \"medium\"' '$B'"
t "PROJECT template: has a Budget line defaulting to standard" grep -q '^- \*\*Budget:\*\* standard' "$T"
t "onboard: asks for the budget" grep -q '^| Budget | ask' "$O"
t "begin: Claudia's full review stays on Opus" grep -Eq '^\| Tess, plan and write; Claudia, a full review \| `"opus"`' "$B"
# Regression: rules that moved out of SKILL.md are still in the begin skill
for re in 'ada-brief who' 'ada-brief status' 'Never archive unasked' 'Status: superseded' \
          'reverses a Decision' 'ada-size' 'ada-brief overlap --gate' 'Co-Authored-By: Tess' \
          'Reviewed-by: Claudia' 'Squash commit' 'Nothing is' 'git add -A' 'before anything runs'; do
  t "regression: begin still has '$re'" sh -c "cat '$D'/*.md | grep -qF -- '$re'"
done
t "regression: the design test stays in SKILL.md" grep -q 'would two good developers' "$B"
t "regression: the brief is still a contract in SKILL.md" grep -q 'contract with the developer' "$B"
echo "$fails failed"; [ "$fails" = 0 ]
```

### Part B: a step on the split skill

1. In the practice project, set `- **Budget:** lean` in `PROJECT.md`. Run `/ada:begin` with a small back-end task, through to the PR gate,
   starting a fresh session with `/ada:pickup` at each phase break as Ada asks.
2. Watch the Agent calls (the transcript, or `/agents` while they run).

## Expected

- Part A: every line `PASS`, `0 failed`, exit 0.
- Part B: Ada writes Bob's model in `BUILD-LOG.md`: Sonnet for a small step with no fragile area.
  With `Budget: lean` in `PROJECT.md`, the Opus spawns carry `effort: "medium"`. Ada reads `approval.md` only after the brief is agreed, `pr-gate.md` only in the PR
  gate session, and `design-step.md` and `checkpoint.md` not at all for a back-end step on a
  fresh checkout. Every Agent call has `model:`; Tess's run, Claudia's re-review and any Explore
  agent run on Sonnet.

## Observe at

- Part A: stdout and `echo $?`.
- Part B: the session transcript (`~/.claude/projects/<project>/<session>.jsonl`, the Agent
  tool calls and Read calls) and each subagent's `.meta.json`.

## Regression

- The text that moved is unchanged apart from cross-references; the regression lines in part A
  check the rules that matter most survived. `08-session-flow/*` and `13-design/*` cover the rest
  of the moved text.
