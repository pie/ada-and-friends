# Ada: the design step

Read from `/ada:begin` when the design test (The design step there) says to offer a design
step, when a design from a standalone `/ada:design` run is waiting, or when undesigned UI turns
up partway through a step.

**The offer.** When the test says so, offer it in one line that names what changes, e.g. "This
changes the checkout layout. Shall Iris design it before I write the brief?" On yes, load the
`ada:design` skill (Skill tool): Iris takes over in this session, reads the draft brief, and hands
back once `.claude/handoff/DESIGN.md` says `Status: agreed`. On no, write the brief as usual. When
the test says not to offer it, don't mention design.

**A design from a standalone run.** `ada-handoff-init` always puts a `.claude/handoff/DESIGN.md`
in place, so look at its `Status:` line, not whether it exists. The template's own line,
`Status: draft | agreed | superseded`, means there is no design. A standalone run is a handoff
`DESIGN.md` saying `draft` or `agreed` when no brief has been started (`BRIEF.md` has no Outcome
yet): `/ada:design` ran on its own before this step. Before you write the brief, check with the
developer that the design is for this task. If it isn't, set it to `Status: superseded`: it
stays until the step is archived, and the roles ignore it. Then show the developer the
uncommitted changes the design made to the project's `DESIGN.md` (`git diff -- DESIGN.md`, or the
whole file if the design created it) and revert them (`git checkout -- DESIGN.md`, or delete the
file it created), unless the developer says to keep them. Kept changes go in their own commit and
PR, not this step's. If the design is for this task, go through its requirements ("The problem")
with them, item by item: they are a draft, not agreed. If the design is only `draft`, offer Iris
to finish it. A `draft` design in a step you started (the brief has its Outcome) is that step's
design, not agreed yet: don't confirm its requirements again, and its visual parts wait until the
developer chooses a fix or PIE waives the rule.

**Finish the brief from `.claude/handoff/DESIGN.md`.** Only from `Status: agreed`: when Iris
hands back "Design not agreed:", the visual parts wait until the developer chooses a fix or PIE
waives the rule. Bob, Claudia and Tess never open the canvas, so the brief carries what they need
from it:

- **Constraints:** "`.claude/handoff/DESIGN.md` is the spec for the UI; its token values are in
  the project's `DESIGN.md`", the artifact link, and a summary of the agreed design in a few
  lines. For a text-only design (`Artifact: text only`), copy its "The agreed design" section
  into the Constraints, so the design travels with the brief.
- **Build order:** in the order the design builds up (shared tokens and styles, then each
  component, then its states).
- **Definition of Done:** an observable item for each state and each breakpoint the design
  specifies, so Tess can check it and Claudia can trace it.

The approver sees the design with the brief. The artifact is private, so when `ada-brief who`
says `required`, ask the developer to share it with the approver: who sees it is their decision,
not yours or Iris's.

**Back to design partway through a step.** UI that nobody thought was needed can turn up at any
point. Whoever finds it, you apply the test:

| Who finds it | What happens |
|---|---|
| You, writing the brief | Offer the design step there and then. |
| Bob, building | He stops that part and records `UI not specified: <what>` in `REVIEW-REQUEST.md`. |
| Claudia, reviewing | She raises unspecified UI as Must Fix, citing the test. |
| Tess, planning tests | She flags, under Escalate, a Definition of Done item that assumes UI no one has designed. |

- **If the test passes:** pause the step and offer Iris. She designs only the new part, in the
  same artifact, and updates the handoff `DESIGN.md` and the project's `DESIGN.md`. Then amend
  the brief from it (the Amendments rules in `/ada:begin`, and `approval.md` if it was approved): it needs the developer's yes again. Check it
  against the re-approval triggers there; new UI that the customer sees usually hits one.
- **If it fails** (the UI reuses an existing pattern unchanged): add a line to the brief's
  Constraints naming the pattern and where it is used, and the step carries on.
