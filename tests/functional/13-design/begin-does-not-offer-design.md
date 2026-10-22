# `/ada:begin` does not offer design for work that doesn't change what a person sees

- Area: 13 Design; 08 Session flow
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (needs a dry run with a person answering Ada)
- Last run: not run yet

Dry run 2 of the brief's Definition of Done, with the test's other "don't offer" cases.

## Precondition

As in `begin-offers-design-and-finishes-brief.md` (the project, `ada@pie` off, this checkout's
plugin through `--plugin-dir`), except that Chrome and the Design type don't matter. Each case
is a fresh session; stop it once Ada has started writing the brief, and put the project's
`.claude/handoff/` back afterwards (`git status` clean, or `ada-handoff-init --archive <name>`
from this checkout).

## Steps

For each case, start `/ada:begin`, give the task, and answer Ada's questions until "done" is
pinned down and she moves on to the brief.

1. **Back-end only** (the Definition of Done run): e.g. a WP-CLI command, a cron or scheduled
   action change, or a change to how an export is calculated, with no page or email changing.
2. **Wording only**: e.g. change the text of one existing button or notice, nothing else.
3. **An existing pattern, unchanged**: e.g. add another notice of a kind the site already shows,
   in the same component.
4. **Only to meet P32 to P39**: e.g. raise a text colour's contrast to 4.5:1 on an existing page.
5. **Control**: a new empty-list state on a page that has no empty state anywhere on the site.

## Expected

1 to 4: Ada does **not** offer the design step. She goes on to the brief, and `DESIGN.md` stays
the blank template. If the step reaches the PR gate, `PR.md` has `Design: none`.

5: Ada **does** offer it (a new state with no existing pattern on the site to follow).

Source: the test in the brief ("Don't offer it when: the work is back-end only; it changes only
the wording; it reuses an existing component or pattern unchanged; or it only fixes something to
meet P32 to P39 or to match a design already agreed"), and DoD 11, run 2.

## Observe at

The terminal; `.claude/handoff/BRIEF.md` and `.claude/handoff/DESIGN.md` in the project.

## Regression

- `DESIGN.md` is the blank template (its Status line reads neither `agreed` nor `draft`). Ada
  doesn't ask you to confirm design requirements, and if case 1 is carried through the build,
  Claudia's `REVIEW-FEEDBACK.md` has no check against `DESIGN.md` and Tess's `TEST-REPORT.md`
  has no design comparison. (Amendment Q1)
- The rest of Direction mode is unchanged for these cases: the same questions, the brief written in
the same order, the approval stop where `PROJECT.md` needs one.
