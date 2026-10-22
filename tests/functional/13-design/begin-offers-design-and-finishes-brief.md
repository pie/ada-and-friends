# `/ada:begin` offers design once "done" is pinned, Iris designs on a canvas, Ada finishes the brief from DESIGN.md

- Area: 13 Design; 08 Session flow
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (needs a person at the canvas)
- Last run: not run yet

Dry run 1 of the brief's Definition of Done. Also the source for DEVELOPING's "Cost" note.

## Precondition

- A practice or PIE project with `.claude/team/PROJECT.md`, a **local** site, and a real page that
  is due a visible change (a new section, a layout change, or a new state with no pattern yet).
  Note which project and page for the Run record; write no customer details anywhere.
- No step under way in that project: `.claude/handoff/CHECKPOINT.md` empty or for a finished step.
- `"enabledPlugins": {"ada@pie": false}` in the project's `.claude/settings.local.json`.
- Claude in Chrome connected, with the local site open (for "look first").
- The account offers the Design Artifact type (if it doesn't, run `fallbacks.md` instead).
- Start the session in the project with
  `claude --plugin-dir ~/Repos/ada-and-friends-iris/plugins/ada` (this checkout, not the
  installed plugin). Note the clock time.

## Steps

1. Type `/ada:` and look at the command list.
2. Type `/ada:begin` and give Ada the task: the visible change to the page, in a sentence or two,
   with its Asana link if there is one. Answer her questions about who it's for, what "done"
   looks like and what's out of scope.
3. When Ada offers the design step, open `.claude/handoff/BRIEF.md` before you answer. Then say
   yes.
4. Let Iris work. Answer her only if she asks something new.
5. When she gives the canvas link, open it in the browser.
6. Leave one comment on the canvas (e.g. "option B: make the button full width at 320px"). Tell
   Iris in the terminal that you've commented.
7. In the terminal, raise a case nobody listed (e.g. "what does it show when the list is empty?").
8. Pick one option.
9. When Iris hands back, open `.claude/handoff/DESIGN.md`, then let Ada finish the brief and open
   `.claude/handoff/BRIEF.md` again.
10. Run `/cost` (or note the usage figures the session shows) at the start of step 4 and at the
    end of step 8. The difference is the cost of the design rounds.
11. Optional, only if you carry the step through the build: at the end, read
    `.claude/handoff/REVIEW-FEEDBACK.md`, `.claude/handoff/TEST-REPORT.md` and
    `.claude/handoff/PR.md`.

## Expected

1. `/ada:design` is in the list, with a description saying when to use it and that `/ada:begin`
   usually brings it in. (DoD 1)
2. Ada pins down who it's for, "done" in observable terms, and out of scope first. (Source, flow
   step 1)
3. The offer comes after that, in words like "This changes the <page>. Shall Iris design it before
   I write the brief?". At the moment of the offer `BRIEF.md` has the outcome, Definition of Done
   and out of scope filled in, and its constraints and build order are not written yet. (DoD 4)
4. Iris takes over in the same session (no subagent is spawned for her). She says she has loaded
   `ada:standards-accessibility`. She does not ask again who it's for, what done is or what's out
   of scope. (DoD 2)
5. Look first: she screenshots the current page on the **local** site (the host in the address
   bar is the local one, never the live site) at 320, 768 and 1280px, and reports the P32 to P39
   failures she finds, with values, before proposing anything. (DoD 2)
6. She makes a Design artifact (through `quickstart` with intent `design`) and tells you to open
   it. It holds the current page beside 2 or 3 options, at mobile and desktop widths; the states
   hover, focus, error, empty and loading; and under each option a short note naming the
   requirement it answers. The artifact is private: she has not shared it. (Source, flow step 5)
7. After your comment she updates the **same** artifact: the link is unchanged and the comment is
   answered on the canvas. (DoD 2)
8. For the new case she asks you about it, and adds it to the Definition of Done in `BRIEF.md`
   (open the file: it is there), not only to the design. (DoD 2)
9. At sign-off she checks the chosen design against P32 to P39, fixes any failure and tells you
   what she changed (or that nothing needed changing, and what she checked). (DoD 2)
10. `DESIGN.md` has every section filled: Asana, Designed, `Status: agreed`, the artifact link (the
    same link as in 7); the problem; what was found (the P32 to P39 failures, with values); the
    options considered, one line each, and why the others weren't chosen; the agreed design
    (layout at 320, 768 and 1280, type scale in `rem`, colour tokens with their contrast ratios,
    spacing, every state including the one from 8, final copy, images and alt text);
    constraints; out of scope. (Source, "DESIGN.md sections")
11. Ada finishes `BRIEF.md` from `DESIGN.md`: its Build order draws on the design, and its
    Constraints carry the artifact link and a short summary of the agreed design. (DoD 4;
    Amendment Q4)
12. Approval follows as the project's `PROJECT.md` says. If `ada-brief who` says `required`, Ada
    asks **you** to share the artifact with the approver: she doesn't share it herself, and nor
    does Iris. If approval isn't needed, she doesn't ask. Note which happened. (Amendment Q4)
13. If carried through the build: `REVIEW-FEEDBACK.md` records a check of the build against
    `DESIGN.md` (sizes, colours, states, breakpoints); `TEST-REPORT.md` lists the differences
    between the built page and the design at 320, 768 and 1280px; `PR.md` has
    `Design: <the Design artifact link>`, the same link as in 7. (DoD 6 and 7; Amendment Q5)

Record the cost figure from step 10 for DEVELOPING's "Cost" note.

## Observe at

The terminal; the command list; `.claude/handoff/BRIEF.md` and `DESIGN.md` in the project; the
canvas in the browser and its share settings; Chrome's address bar while Iris looks first.

## Regression

- Session start runs as before: `ada-handoff-init` (now with `DESIGN.md` among the created
  files), the archive of a finished step, the branch, `ada-onboard refresh`.
- Bob, Claudia and Tess never open the canvas: their spawns carry `DESIGN.md`, not the link to
  open.
- After the brief is written, `/exit` and `/ada:pickup` in a fresh session: Ada does **not** ask
  you to confirm the design's requirements again (`Status: agreed` with a brief written is not a
  standalone run; Amendment Q1).
