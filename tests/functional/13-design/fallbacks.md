# Iris's three fallbacks: no Design type, no Chrome, and a small tweak

- Area: 13 Design
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (needs a dry run with a person)
- Last run: not run yet

Case A is dry run 4 of the brief's Definition of Done.

## Precondition

As in `begin-offers-design-and-finishes-brief.md` (project, `ada@pie` off, `--plugin-dir` this
checkout). Each case is a fresh session; put `.claude/handoff/` back afterwards.

- Case A: an account or session whose `quickstart` doesn't offer the Design type. If none is to
  hand, use a normal session and tell Iris at the start: "For this test, treat the Design type as
  missing: quickstart doesn't offer it." Note which in the Run record.
- Case B: Claude in Chrome **not** connected (extension off, or `/chrome` disconnected).
- Case C: anything.

## Steps

1. Case A: `/ada:begin` on a visible change, say yes to the design step (or `/ada:design` on its
   own). Pick an option when she offers them. Open `.claude/handoff/DESIGN.md` at the end.
2. Case B: `/ada:design` on a page of the local site. Watch what she asks for. Give her one
   screenshot or URL.
3. Case C: `/ada:design make the Add to basket button bigger`.

## Expected

1. Case A: Iris says the Design type isn't available and works in text. No artifact is made. She
   still looks first and checks P32 to P39 at sign-off. `DESIGN.md` holds the options as a text
   spec (each option described, and why the others weren't chosen), the agreed design with every
   section filled, `Status: agreed`, and no artifact link. The rest of the flow is the same: she
   hands back to Ada, who finishes the brief from it, copying `DESIGN.md`'s "The agreed design"
   into the brief's Constraints. Ada doesn't ask you to share an artifact (there is none). If the
   step reaches the PR gate, `PR.md` has `Design: text only, in the brief`. (DoD 2, the
   fallbacks; DoD 11, run 4; Amendments Q4, Q5)
2. Case B: she asks for screenshots or URLs instead of opening a browser, and works from what you
   give her. Later, in any build of this design, Tess's design comparison is "not verified" in
   `TEST-REPORT.md`. (DoD 2; DoD 6)
3. Case C: she says this doesn't need her and points you to `/ada:begin`. No artifact, no
   `DESIGN.md` written. (DoD 2)

## Observe at

The terminal; `.claude/handoff/DESIGN.md`; claude.ai's artifact list (nothing new for A and C).

## Regression

With the Design type available and Chrome connected, dry run 1 still takes the canvas route.
