# Back to design partway through a step: pause and offer Iris, or name the reused pattern

- Area: 13 Design; 08 Session flow; 01 Brief approval (re-approval)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (needs a person at the canvas)
- Last run: not run yet

## Precondition

- Case A: the end state of `tests/functional/09-roles/roles-stop-on-undesigned-ui.md` part 1:
  Bob has stopped, and `REVIEW-REQUEST.md` has `UI not specified: <the error state …>`; the
  site has no pattern for it. `DESIGN.md` has an artifact link from an earlier Iris session (if
  it says none, the canvas steps below don't apply: check that Iris updates `DESIGN.md` only).
- For the re-approval check, a project whose brief needs approval: one with an approver in
  `PROJECT.md` who isn't you, or a scratch GitHub repo you own with `ADA_BRIEF_REQUIRE=1` set
  for the session. Its brief approved (`ada-brief status` says `approved`) before you start.
- Case B: the same, except the site **has** an existing pattern for the missing UI, unchanged
  (e.g. the theme's standard form-error notice). Name it in a note for the Run record.

## Steps

1. Case A: let Ada read Bob's handback (or tell her "Bob has handed back").
2. Say yes when she offers Iris. Comment once if you like, then accept the new part.
3. Open `DESIGN.md` and `BRIEF.md`. Run the checkout's `plugins/ada/bin/ada-brief status`.
4. Case B: the same start; let Ada read Bob's handback.

## Expected

1. Case A: Ada applies the test, finds it passes (a new state with no existing pattern), pauses
   the step and offers Iris. (DoD 4; the source's table)
2. Iris designs **only** the new part, in the **same** artifact (the link is unchanged), and
   updates `DESIGN.md`: the new state is added; the parts already agreed are not changed.
3. Ada updates `BRIEF.md` (an Amendments row, agreed by you), and the brief needs approving again:
   `ada-brief status` no longer says `approved` until the approver labels it again after the
   change. The build doesn't resume before then. (Source, "If the test passes"; `SYSTEM.md`,
   ada-brief `status`: only an approval after the brief last changed counts)
4. Case B: Ada applies the test, finds it fails (an existing pattern, unchanged), adds a line to
   the brief naming the pattern, and the step carries on without Iris. (DoD 4)

## Observe at

The terminal; `.claude/handoff/DESIGN.md` and `BRIEF.md`; the canvas link; the first line of
`ada-brief status`; the draft PR's labels on GitHub.

## Regression

The parts of `DESIGN.md` agreed before the pause are word for word the same afterwards (compare
with a copy taken before step 1).
