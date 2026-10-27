# Look first takes its sources in order: the design guide, supplied designs, the local site

- Area: 13 Design; also 02 Onboarding (case E)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219483548031905
- Automate as: none (needs a dry run with a person)
- Last run: not run yet

From Definition of Done 2 as amended on 21 Oct 2026 (agreed by Neil) and the brief's fix round 3:
look first, in order of precedence, the project's design guide (`PROJECT.md` `Design guide:`),
designs the developer supplies, the existing local site (always looked at if it exists); with none
of these, the spec and the client's brand assets, else accessible defaults, said plainly; supplied
designs are checked against P32 to P39 and failures are raised, not overridden; a non-web output
(a PDF) is designed to its page size; `DESIGN.md` records the sources used. Case A is the case
the counselling-training-hub dry run found.

Two further amendments, also 21 Oct 2026 and agreed by Neil (the last two rows of the brief's
Amendments):

- **How a guide outside the repo is read** (cases F and G). A path in the repo: read the file.
  A design system artifact link: Artifact `read`, read-only. A URL: WebFetch, read-only; if it
  needs a login, she asks the developer to save it into the repo or paste it. Chrome stays on the
  local site only: it never opens the guide's host.
- **A supplied design failing P32 to P39** (case A). Iris never changes it on her own. She raises
  each failure with its values and proposes the smallest passing change; the developer chooses the
  fix or takes it to the client; `DESIGN.md` stays `Status: draft` until the design passes.
  Keeping a failure on purpose is a P-rule waiver for PIE, not settled in the session.
- **Q1 as amended** (the Amendments row on standalone runs): a standalone run is a `DESIGN.md`
  (`draft` or `agreed`) when no brief has been started (`BRIEF.md` has no Outcome yet). A `draft`
  design in a step Ada started is that step's design: Ada doesn't re-confirm its requirements
  (case A2a, including after `/ada:pickup`); the visual parts wait for a fix or a PIE waiver.

## Precondition

As in `begin-offers-design-and-finishes-brief.md`: a practice repo onboarded with `ada`, the
`ada@pie` plugin off, `claude --plugin-dir <this checkout>/plugins/ada`, the Design type offered by
`quickstart`, Claude in Chrome connected. Each case is a fresh session; put `.claude/handoff/` and
`PROJECT.md` back afterwards. Note in the Run record which repo, which `Design guide:` value and
whether a local site existed, per case.

- **Case A (no site, supplied design failing P32 to P39, a PDF):** a new app with nothing built:
  no `.claude/team/local.env` (or no `LOCAL_URL` in it), and `PROJECT.md`'s local copies section
  says there is no local site. `PROJECT.md` has the line `Design guide: none`. No brand assets in
  the repo. Save this as `supplied/certificate.html` in the repo (the client's design for the
  certificate):

  ```html
  <!-- Client's certificate design. Page: A4 portrait. -->
  <div style="background:#FFFFFF;width:210mm;height:297mm;font-family:Georgia,serif">
    <h1 style="color:#F2C94C;font-size:28pt">Certificate of Completion</h1>
    <p style="color:#AAAAAA;font-size:11pt">This certifies that [learner name] has completed
    [course name] on [date].</p>
  </div>
  ```

  The heading, #F2C94C on #FFFFFF, is about 1.6:1 (large text needs 3:1); the body text,
  #AAAAAA on #FFFFFF, is about 2.3:1 (needs 4.5:1). Both fail P32. To check any colour Iris
  proposes, save this as a file in the scratchpad (not in the repo) and run
  `python3 -I contrast.py '#RRGGBB' '#FFFFFF'`:

  ```python
  import sys
  def lum(h):
      c = [int(h[i:i+2], 16) / 255 for i in (1, 3, 5)]
      c = [x / 12.92 if x <= 0.03928 else ((x + 0.055) / 1.055) ** 2.4 for x in c]
      return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]
  a, b = sorted([lum(sys.argv[1]), lum(sys.argv[2])], reverse=True)
  print(round((a + 0.05) / (b + 0.05), 2))
  ```

  Also keep a copy of the original: `cp supplied/certificate.html /tmp/certificate.orig` (any
  scratch path), to compare with after the run.
- **Case A2 (as A, the developer will not fix it):** the same repo and files as case A, a fresh
  session. Run it twice, on a fresh copy of the repo each time: **A2a, from `/ada:begin`** (a
  step Ada started, so `BRIEF.md` has an Outcome by the time Iris is brought in); **A2b, from
  `/ada:design` on its own** (no brief started, `BRIEF.md` blank).
- **Case B (guide, conflicting supplied design, site):** a project with a local site (`LOCAL_URL`
  set, the site up). Save `docs/design-guide.md` with: "Primary colour #1A5276. Buttons: #1A5276
  background, #FFFFFF text, 4px radius. Body text 16px, #222222." Set `PROJECT.md`'s line to
  `Design guide: docs/design-guide.md`. Supplied design: tell Iris, "The client wants the
  enquiry button orange, #E67E22, with white text." (White on #E67E22 is about 2.9:1, which also
  fails P32's 4.5:1 for button text; she may raise that as well as the conflict.)
- **Case C (no `Design guide:` line):** as case B, with the `Design guide:` line deleted from
  `PROJECT.md` (as in a repo onboarded before this change). No supplied design.
- **Case D (nothing to look at):** as case A, but no supplied design and no brand assets.
- **Case E (onboarding drafts the line):** scratch repos, not onboarded: E1 with
  `docs/style-guide.md` (any short style guide); E2 with nothing design-related; E3 with a
  `DESIGN.md` at its root (the fixture in `project-design-md-dry-runs.md`); E4 with both that root
  `DESIGN.md` and `docs/style-guide.md`. (E3 and E4: the brief of 27 Oct 2026, "Iris keeps the
  project's DESIGN.md in the Stitch format", Definition of Done 6.)
- **Case F (guide held as a URL):** a project with a local site (`LOCAL_URL` set, the site up), no
  `docs/design-guide.md` in the repo. Publish the guide text from case B ("Primary colour
  #1A5276. Buttons: #1A5276 background, #FFFFFF text, 4px radius. Body text 16px, #222222.") at a
  URL that needs no login and is not the local site (for example a public gist or a page on a
  site the developer controls); call it `<GUIDE_URL>`. Set `PROJECT.md`'s line to
  `Design guide: <GUIDE_URL>`. No supplied design.
- **Case F2 (the URL needs a login):** as F, but the URL is a page that asks for a login (any page
  behind a login the developer has); `Design guide: <PRIVATE_URL>`.
- **Case G (guide held as a design system artifact link):** a project with a local site, no
  `docs/design-guide.md`. In claude.ai create a design system (or any Design type) artifact
  holding the guide text from case B; note its link `<GUIDE_ARTIFACT_LINK>`, and its version and
  the number of comments on it now. Set `PROJECT.md`'s line to
  `Design guide: <GUIDE_ARTIFACT_LINK>`. No supplied design.

## Steps

1. Case A: `/ada:design a certificate of completion, issued to learners as an A4 PDF. The
   client's design is in supplied/certificate.html.` Answer her questions.
   1. When she raises the contrast failures and proposes a change for each, answer "keep the
      client's colours for now, I'll take it to them." Before answering anything else, open
      `.claude/handoff/DESIGN.md` if she has written it, and note its `Status:` line.
   2. Run `contrast.py` on each colour she proposed against #FFFFFF. Note the ratios.
   3. Choose the fix: answer "use your proposed colours; I'll tell the client." Pick one option.
   4. Open `.claude/handoff/DESIGN.md` at the end, and run
      `diff supplied/certificate.html /tmp/certificate.orig` (the scratch copy from the
      precondition).
2. Case A2a: `/ada:begin` and describe the task as "a certificate of completion, issued to
   learners as an A4 PDF, the client's design is in supplied/certificate.html". Answer who it is
   for, what done looks like and what is out of scope, and accept the offer of the design step.
   When she raises the failures, answer "keep the client's colours, don't change them" to every
   proposal and every later question about them, and pick one option. Open
   `.claude/handoff/DESIGN.md` at the end. Note what she says at hand-back, whether she raised
   each failure once or more than once, and whether she changed or presented as agreed any
   colour. Then, still in this session, note what Ada says and asks next. Then end the session,
   start a fresh one in the same repo with `/ada:pickup`, and note again what Ada says and asks
   (in particular whether she asks you to confirm the requirements, "The problem" in
   `DESIGN.md`, again).
   Case A2b: `/ada:design` with the same text as case A, and the same answers as A2a. Note what
   she says at hand-back. Then end the session, start a fresh one and run `/ada:begin` with the
   task description as in A2a, and note whether Ada confirms the design's requirements with you
   before writing the brief.
3. Case B: `/ada:design the enquiry form on the contact page`, then give her the supplied design
   from the precondition. When she asks about the conflict, answer "follow the guide."
4. Case C: `/ada:design the enquiry form on the contact page`. When she asks whether there is a
   design guide, answer "no". Then leave one comment on the artifact and ask her to update it.
5. Case D: `/ada:design a certificate of completion, issued to learners as an A4 PDF.`
6. Case E: in each scratch repo, `/ada:onboard`; open the drafted `.claude/team/PROJECT.md`.
7. Case F: `/ada:design the enquiry form on the contact page`. Watch the terminal for the tool
   call that reads the guide, and Chrome's tabs (and address bar) for every page she opens.
8. Case F2: the same command. Answer her question when she says she can't read the guide: paste
   the case B guide text into the terminal. Note whether she then goes on with it.
9. Case G: `/ada:design the enquiry form on the contact page`. Watch the terminal for the tool
   call that reads the guide, and Chrome's tabs for every page she opens. At the end, open the
   guide artifact on claude.ai and compare its version and comment count with the numbers noted
   in the precondition.

## Expected

1. Case A:
   - She reads `PROJECT.md`'s `Design guide: none` and goes on without a guide; she doesn't ask
     whether there is one.
   - She says there is no site to look at, opens no URL in Chrome, and doesn't ask for
     screenshots or URLs of a site.
   - She reads `supplied/certificate.html` and, before any option goes on the canvas, raises both
     P32 failures with you, each with its rule, the colours and the ratio (about 1.6:1 against
     3:1; about 2.3:1 against 4.5:1).
   - For each failure she proposes the smallest passing change: a replacement for that colour
     only, with its new ratio. The type, sizes, layout and wording of the client's design are not
     in her proposal. Step 1.2: each proposed colour measures at least 3:1 (heading) or 4.5:1
     (body text) against #FFFFFF, and is not a wider change than needed (for example, not plain
     black where a lighter colour passes). Record what she proposed.
   - Raised, not overridden: she doesn't change the client's colours on her own. Until you choose
     a fix, no option or file presents the changed colours as the client's design.
   - Step 1.1, after "keep the client's colours for now": she does not treat the failure as
     settled. She says keeping it on purpose is a P-rule waiver that PIE gives, not something
     decided in the session. If `DESIGN.md` exists at that point, its `Status:` is `draft`, not
     `agreed`. She goes on to ask you to choose a fix.
   - Step 1.3, after you choose the fix: she uses it in the options, says what changed, and the
     options pass P32 to P39.
   - She designs to A4 (210 by 297mm), sizes in pt or mm, not 320, 768 and 1280px; the options
     on the canvas are A4 pages.
   - `DESIGN.md`, "What was found": the supplied design used; missing: a design guide (`none`)
     and a site (nothing built). It records both P32 failures with their values and the fix you
     chose. The agreed design gives the page size, not breakpoints, and the passing colours.
     `Status: agreed`, and only after you chose the fix (the `draft` seen in step 1.1 changed to
     `agreed` after step 1.3, not before).
   - Step 1.4: `diff` prints nothing: she did not edit `supplied/certificate.html`.
2. Case A2:
   - As case A up to your first answer. She raises both failures with values and proposes the
     smallest passing change for each.
   - After you refuse, she does not change the colours herself and does not mark the design
     `agreed`; she says keeping them is a waiver that PIE gives, not settled in session.
   - `DESIGN.md` ends `Status: draft`, not `agreed`: the design doesn't pass. It records the
     failures with values and that the developer kept them.
   - She hands back with "Design not agreed:" (not "Design agreed; back to Ada for the brief"),
     naming the open failures and their values (the heading #F2C94C on #FFFFFF about 1.6:1
     against 3:1; the body text #AAAAAA on #FFFFFF about 2.3:1 against 4.5:1), and saying the
     visual parts can't be briefed until a fix is chosen or PIE waives.
   - A2a (from `/ada:begin`, a step Ada started, `BRIEF.md` has an Outcome): this `draft`
     `DESIGN.md` is that step's design, not agreed yet. After "Design not agreed:" Ada does not
     finish the brief from it (the visual parts wait for a fix or a PIE waiver), and she does not
     ask you to confirm its requirements again: not at hand-back, and not after `/ada:pickup`
     in a fresh session either. (A re-confirmation here is the defect the amendment of Q1
     fixes: the requirements were given in this step.)
   - A2b (from `/ada:design` on its own, no brief started): the `draft` is a standalone run, as
     before. After the hand-back, in a fresh session, `/ada:begin` confirms its requirements
     ("The problem") with you before it writes the brief, and offers Iris to finish the design.
3. Case B:
   - She reads `docs/design-guide.md` first, then the supplied design, then looks at the local
     site (at 320, 768 and 1280px with Chrome).
   - She says the orange button conflicts with the guide's #1A5276 and asks you which to follow;
     she doesn't choose. After "follow the guide", the options use #1A5276.
   - She doesn't write, extend or offer to make a design guide (reading one is not making one).
   - `DESIGN.md`, "What was found": the guide, the supplied design and the local site, all used;
     nothing missing. The conflict and your answer are recorded.
4. Case C: she asks you once whether the project has a design guide. After "no", she goes on to
   the local site and doesn't ask again, including after the comment round. `DESIGN.md`, "What
   was found": no design guide (you said so). Whether she offers to add the line to `PROJECT.md`
   is not in the brief: record what she does.
5. Case D: she says plainly that there is no guide, no supplied design, no site and no brand
   assets, so she is working from the spec and accessible defaults. The design is to A4. Her
   options pass P32 to P39 at sign-off (item 7 of part B). `DESIGN.md`, "What was found": the
   guide, supplied designs, the site and brand assets listed as missing; the spec and
   accessible defaults as what she used.
6. Case E: E1's `PROJECT.md` has `Design guide: docs/style-guide.md`; E2's has
   `Design guide: none`. E3's and E4's have `Design guide: DESIGN.md`: a root `DESIGN.md` sets it,
   whatever else the repo holds. The onboarding PR description asks the developer to correct the
   drafted facts, this line among them. `git diff` shows no change to `plugins/ada/bin/` in this
   checkout (no script change for this).
7. Case F (URL):
   - She reads the guide with WebFetch (the terminal shows a WebFetch call on `<GUIDE_URL>`), not
     with Chrome and not by asking you to paste it. She makes no write, post or form submission
     to that URL.
   - Chrome opens only pages under `LOCAL_URL` (the local site at 320, 768 and 1280px): the
     guide's host is in no tab and never in the address bar.
   - She uses the guide: the options use #1A5276, #FFFFFF button text, a 4px radius, body text
     16px in #222222. She doesn't write or extend a guide.
   - `DESIGN.md`, "What was found": the guide, with its URL, as a source used.
8. Case F2 (URL behind a login):
   - She tries WebFetch (or tells you she can't read it); she doesn't open the URL in Chrome and
     doesn't ask for your password or any credential.
   - She asks you to save the guide into the repo or paste it. After you paste, she goes on and
     uses the pasted guide as the guide (the options use #1A5276).
   - She doesn't guess the guide's contents or carry on without it silently.
9. Case G (design system artifact link):
   - She reads the guide with Artifact `read` on `<GUIDE_ARTIFACT_LINK>` (the terminal shows an
     Artifact read call with that link), not with WebFetch and not in Chrome.
   - The read is read-only: the guide artifact's version and comment count are the same as before
     the run. The only artifact she creates or updates is the Design canvas for this task, at its
     own link.
   - Chrome opens only pages under `LOCAL_URL`.
   - She uses the guide: the options use #1A5276. `DESIGN.md`, "What was found": the guide
     artifact, with its link, as a source used.

## Observe at

The terminal (the tool calls: WebFetch, Artifact read); the artifact on claude.ai (and the guide
artifact's version and comments, case G); `.claude/handoff/DESIGN.md`; the practice repo's
`.claude/team/PROJECT.md`; Chrome's tabs and address bar (case A: no site opened; cases F, F2 and
G: only `LOCAL_URL`); `diff` of the supplied design against its copy (case A).

## Regression

- With a local site and Chrome, the site is still looked at at 320, 768 and 1280px, and its P32
  to P39 failures are still reported before any proposal (case B; dry run 1).
- Without Chrome and with a site, she still asks for screenshots or URLs of it (`fallbacks.md`,
  case B).
- Her own options are still checked against P32 to P39 at sign-off and fixed, with what she
  changed said (case D; part B item 7). The new rule is about a design the developer supplied;
  it doesn't stop her fixing her own options.
- A guide that is a path in the repo is still just read (case B); nothing in cases F and G
  changes that.
