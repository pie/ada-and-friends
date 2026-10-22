# A standalone `/ada:design`, then `/ada:begin` confirms its requirements before the brief

- Area: 13 Design; 05 Handoff files; 08 Session flow
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (needs a person at the canvas)
- Last run: not run yet

Dry run 3 of the brief's Definition of Done, with Amendments Q1, Q2 and Q6. It is a sequence: the
second session must find the first session's `DESIGN.md`.

## Precondition

As in `begin-offers-design-and-finishes-brief.md` (project, `ada@pie` off, `--plugin-dir` this
checkout, Chrome, the Design type). Cases A, B and D start with the **last step in the project
finished** (its `CHECKPOINT.md` says so). Case C starts with a step **open** (`CHECKPOINT.md`
records a step in progress). Copy `.claude/handoff/` aside before each case and put it back
afterwards.

## Steps

**Case A: agreed design, then a build.**

1. Run `ls -l .claude/handoff/ .claude/handoff/archive/` and note what's there.
2. Session 1: `/ada:design`, with design work that has no build behind it yet, e.g. "concepts for
   a new pricing page, to agree with the client".
3. Before Iris gathers requirements, run the same `ls` again.
4. Answer Iris's questions. Comment once on the canvas, then pick an option.
5. When she has finished, open `.claude/handoff/DESIGN.md`. Note its Status line and its
   requirements. `/exit`.
6. Session 2, fresh: `/ada:begin` "build the pricing page from the design".
7. After Ada's session start, run the `ls` again and open `.claude/handoff/DESIGN.md`.
8. Answer Ada until she writes the brief.

**Case B: draft design.** As case A, but at step 4 don't pick an option: tell Iris "leave it as a
draft for now". At step 5 the Status line should read `draft`. Carry on from step 6.

**Case C: a step is open.** `/ada:design` "a new pricing page". Then run `git status` and the `ls`.

**Case D: the design isn't for this task.** As case A up to step 5 (an agreed `DESIGN.md`), then:

1. Session 2, fresh: `/ada:begin` for a different piece of work that has nothing to do with the
   pricing page, e.g. "fix the typo in the footer".
2. When Ada raises the design (she reads `DESIGN.md` and asks about its requirements), tell her:
   "that design isn't for this task".
3. Run `grep -n Status .claude/handoff/DESIGN.md` and `ls .claude/handoff/`.
4. Answer Ada until the brief is written and approved, then let Bob build, Claudia review and
   Tess test the step (a small one).
5. Read `BRIEF.md`, `REVIEW-REQUEST.md`, `REVIEW-FEEDBACK.md` and `TEST-REPORT.md` for any use of
   the design: a Constraints link or summary, a `UI not specified:` line, a design review, a
   comparison against the design.
6. `/exit`, start a fresh session, `/ada:pickup`, and note whether Ada asks again about the design.
7. At the PR gate, run `ada-handoff-init --archive` as Ada does and `ls` the new `archive/` folder.

## Expected

Case A:

1. to 3. At her start Iris checks `CHECKPOINT.md`, finds the step finished, and archives it first
   (as begin's session start does): a new folder in `archive/` holds the old handoff files, and
   `.claude/handoff/` has a fresh set, `DESIGN.md` the blank template. (Amendment Q2)
4. Iris gathers light requirements herself: who it's for and what it must do. She does not run
   Ada's full brief questions or write `BRIEF.md`. She follows look first, the canvas, iterating
   and sign-off as in dry run 1. (Source, "/ada:design on its own"; DoD 2)
5. `DESIGN.md` is filled, `Status: agreed`, with the artifact link and the agreed design. No code
   changed (`git status` shows nothing outside `.claude/handoff/`, which is ignored). Nothing is
   handed to Bob. (DoD 2)
6. to 7. After session start, `.claude/handoff/DESIGN.md` is still Iris's, and no new archive
   folder has appeared: her design is in a fresh set, so session start has nothing finished to
   archive. (Amendment Q2)
8. `Status: agreed` with no brief written yet is a standalone run: before writing the brief, Ada
   reads `DESIGN.md`, treats its requirements as a draft, and confirms them with you ("Iris
   recorded these requirements: … Are they still right?"). She then finishes the brief from
   `DESIGN.md` (Constraints with the artifact link and a summary, build order), as in dry run 1.
   (DoD 4; DoD 11, run 3; Amendments Q1, Q4)

Case B: the same, except `DESIGN.md` says `Status: draft`. Ada still treats it as a standalone
run and confirms its requirements with you before writing the brief. Record what she does about
the design not yet being picked: the brief doesn't say. (Amendment Q1)

Case C: Iris says a step is open and stops. She points you to Ada (`/ada:pickup`): design for an
open step goes the partway route. Nothing is archived, `DESIGN.md` isn't written, no artifact is
made. (Amendment Q2)

Case D (Amendment Q6):

1. to 2. Ada reads Iris's `DESIGN.md`, as in case A, and you tell her it isn't for this task.
3. Ada sets the Status line to `Status: superseded`. The file stays in `.claude/handoff/`: it is
   not deleted, moved or emptied, and no new archive folder has appeared.
4. to 5. The roles ignore a design that isn't `Status: agreed`: the brief's Constraints carry no
   artifact link or design summary from it, and Ada does not confirm its requirements with you.
   `REVIEW-REQUEST.md` has no `UI not specified:` entry drawn from it. Claudia makes no design
   check against it, and Tess makes no design comparison (no "not verified" line about it either).
   The step is built to the brief alone.
6. `/ada:pickup` does not ask about the design again.
7. The superseded `DESIGN.md` goes into `archive/<name>/` with the rest of the step, still
   reading `Status: superseded`, and the fresh `DESIGN.md` is the blank template. (Q6: it stays
   until the step is archived.)

## Observe at

The terminal in each session; `.claude/handoff/DESIGN.md`, `BRIEF.md`, `CHECKPOINT.md` and
`archive/` in the project; `git status`.

## Regression

A `/ada:begin` with an `agreed` `DESIGN.md` and no developer objection (case A) must not set it to
`superseded`: only the developer saying it isn't for this task does.

A `/ada:begin` with only the blank template `DESIGN.md` (its Status line reads neither `agreed`
nor `draft`) must not make Ada ask you to confirm design requirements: see
`begin-does-not-offer-design.md`, Regression.
