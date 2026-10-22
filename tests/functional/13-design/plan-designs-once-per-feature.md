# In a plan, the design step runs once per feature, before its units are briefed

- Area: 13 Design; 03 Plans (`ada-plan`); 08 Session flow (`planning`)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (needs a dry run with a person)
- Last run: not run yet

## Precondition

As in `begin-offers-design-and-finishes-brief.md` (project, `ada@pie` off, `--plugin-dir` this
checkout, Chrome, the Design type). A piece of work too big for one PR with one visible feature
split into at least two units, e.g. a customer dashboard: a data endpoint, the dashboard page,
and its empty and error states as a later unit.

## Steps

1. `/ada:begin` with the work. Agree to a plan when Ada recommends one.
2. Answer her until she offers the design step. Note when it comes: before or after
   `ada-plan briefs` has drafted the units' briefs (`ls .claude/handoff/plan/`).
3. Go through the design with Iris and pick an option.
4. Let Ada draft the units' briefs. Open each one in `.claude/handoff/plan/`, and run
   `ls .claude/handoff/plan/`.
5. Let Ada start the first visible unit. Run
   `cmp .claude/handoff/plan/DESIGN-<task id>.md .claude/handoff/DESIGN.md`.
6. End that unit (carry it to the PR gate, or archive it as Ada does at a step's end:
   `~/Repos/ada-and-friends-iris/plugins/ada/bin/ada-handoff-init --archive <unit>`), then
   `/ada:pickup` in a fresh session and let Ada start the next visible unit of the same feature.
   Run the same `cmp`, and `ls .claude/handoff/archive/<unit>/`.

## Expected

1. Ada offers the design step **once** for the feature, after its requirements and **before**
   any of its units is briefed: no unit brief exists in `.claude/handoff/plan/` when she offers.
2. She doesn't offer it again for each unit.
3. Each visible unit's brief builds from the same design and the same artifact link, so the
   units share one design. The back-end-only unit isn't sent to Iris.
4. The agreed design is kept as `.claude/handoff/plan/DESIGN-<task id>.md` (step 4's `ls` shows
   it). At the start of each unit Ada copies it to `.claude/handoff/DESIGN.md`, beside the brief
   copy: `cmp` prints nothing in step 5 and again in step 6, after the first unit's `DESIGN.md`
   has gone to `archive/<unit>/` and the plan copy has stayed. (Amendment Q3)

Source: the brief's DoD 5 ("design runs once per feature, before its units are briefed"), the
source's "Plans", and Amendment Q3.

## Observe at

The terminal; `.claude/handoff/plan/PLAN.md` and the unit briefs; `.claude/handoff/DESIGN.md`.

## Regression

`ada-plan check` on the plan still passes, and `ada-plan briefs` still drafts every unit's brief.
