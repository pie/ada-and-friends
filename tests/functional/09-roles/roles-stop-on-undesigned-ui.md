# Bob, Claudia and Tess stop at UI nobody designed, and Claudia checks the build against DESIGN.md

- Area: 09 Roles (agents `bob`, `claudia`, `tess`); 13 Design
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (needs a dry run of the agents)
- Last run: not run yet

Part 1 is dry run 5 of the brief's Definition of Done. `back-to-design-partway-through-a-step.md`
carries on from its result.

## Precondition

- A practice project with a local site, on a scratch branch, `ada@pie` off in
  `.claude/settings.local.json`, and a session started with
  `claude --plugin-dir ~/Repos/ada-and-friends-iris/plugins/ada`.
- A small visible change, e.g. a newsletter sign-up form in the footer. Write by hand:
  - `.claude/handoff/BRIEF.md` (from the template), agreed, whose Definition of Done includes
    "an invalid email address shows an error next to the field";
  - `.claude/handoff/DESIGN.md` (from the template), Status `agreed`, with layout, type, colours,
    copy and the states default, hover, focus and success, and **no error state**.
- The site has no existing inline form-error pattern (otherwise use another state it lacks).

## Steps

1. Tell Ada: "The brief and design are agreed. Start the build." Let her spawn Tess (plan and
   write) and Bob.
2. When Tess has written it, open `.claude/handoff/TEST-PLAN.md`.
3. When Bob hands back, open `.claude/handoff/REVIEW-REQUEST.md` and look at the built form.
4. Claudia, the missing state: on a copy of the branch, add an inline error message under the
   field by hand (any colour and wording), set REVIEW-REQUEST's line to `UI not specified: none`,
   and ask Ada for a review.
5. Claudia, the match: on another copy, change one built value from `DESIGN.md` (e.g. the button
   colour to one that isn't a design token, or its 768px layout), and ask Ada for a review.
6. Draft design: on another copy, change `DESIGN.md`'s status line to `Status: draft`, keep the
   changed value from step 5, and ask Ada for a review, then for Tess's test plan.

## Expected

1. Tess's `TEST-PLAN.md` flags the Definition of Done item about the error as assuming UI that
   nobody has designed (citing the test), rather than writing an expected look for it. (DoD 6)
2. Bob builds the rest of the form to `DESIGN.md` and does **not** invent the error's look. He
   stops that part, and `REVIEW-REQUEST.md` has a line `UI not specified: <the error state for an
   invalid email>`. (DoD 6; DoD 11, run 5)
3. Step 4: Claudia's `REVIEW-FEEDBACK.md` has a Must Fix for the error message: UI not
   specified in `DESIGN.md`, citing the test. (DoD 6)
4. Step 5: Claudia's `REVIEW-FEEDBACK.md` records her check of the build against `DESIGN.md`
   (sizes, colours, states, breakpoints) and names the changed value as not matching. (DoD 6)
5. Step 6: with `Status: draft`, Claudia doesn't review the build against `DESIGN.md` (no
   mismatch raised against the draft) and Tess plans no design comparison: the roles work to
   `DESIGN.md` only when it says `Status: agreed`. (Amendment Q1)
6. None of the three opens the canvas or asks for its link; they work from `DESIGN.md`.

## Observe at

`.claude/handoff/TEST-PLAN.md`, `REVIEW-REQUEST.md` and `REVIEW-FEEDBACK.md` in the project; the
built form on the local site; the terminal (what Ada spawns, and with which files).

## Regression

With a `DESIGN.md` that covers every state the brief needs, Bob writes `UI not specified: none`
(or leaves the line as the template has it) and builds everything; Claudia raises no design Must
Fix for a build that matches.
