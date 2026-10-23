# Changelog

Entries go under Unreleased. The version is chosen when next-release goes into main (see
Releasing in DEVELOPING.md).

## Unreleased

- **`presstest`**: runs the project's PressTest suite against your local site, or with
  `--env staging` the project's staging site. Never production. It ends with one line saying
  how many passed or failed. Needs Docker and a presstest.io clone (`PRESSTEST_DIR` in
  `.claude/team/local.env`).
- **Tess runs the suite checks herself** with `presstest run`, instead of handing them to the
  developer. She runs on staging only if you ask.
- **Onboarding asks for the suite and the staging site**, on two lines in `PROJECT.md`. In a repo
  onboarded earlier, add a `PressTest staging:` line (a URL or `none`) and run
  `ada-onboard settings` to allow `presstest`.

## 0.8.0, 22 Oct 2026

- **P20 and squash commit messages**: P20 now lets a PR's squash commit message leave what was
  verified to the PR description, so the shorter squash message meets the standard. Branch commits
  and PR descriptions still say what changed and what was verified, and whatever a squash message
  does say must still be true.
- **Shorter squash commit messages**: the message in each PR's Squash commit section holds only
  what the change does and why, the Asana link and the credit lines. The functions and tests
  paragraph and the `Verified:` and `Not verified:` paragraphs are gone; what was verified stays
  in the PR description. `ada-onboard refresh` offers the new PR template to onboarded repos.
- **Iris, the designer** (`/ada:design`): when work changes what a person sees or does, Ada now
  offers a design step once "done" is pinned down. Iris designs options with you on a Claude
  Design canvas, checks the chosen one against P32 to P39, and writes `DESIGN.md`. She starts
  from the project's design guide, then any designs you supply, then the local site; with none
  of these, from the spec and your brand, and a PDF is designed to its page size. Onboarding now
  writes a `Design guide:` line in `PROJECT.md`; in a repo onboarded earlier, add one (a path, a
  URL, a design system artifact link, or `none`) or Iris asks. Ada finishes
  the brief from it; Bob builds to it, Claudia reviews against it, and Tess compares the built
  page with it in Chrome. UI nobody specified is stopped and sent back to design. Without the
  Design type, the design is written as text. `/ada:design` also runs on its own, and the PR
  template gains a `Design:` line, which `ada-onboard refresh` offers to onboarded repos.

## 0.7.0, 21 Oct 2026

- **Grace, the bug hunter**: `/ada:bughunt` now runs as Grace, named after Grace Hopper. The
  command is unchanged. The README introduces her with the team, and `FINDINGS.md` is signed by
  her.
- **Accessibility standards (P32 to P39)**: a new `ada:standards-accessibility` skill sets the
  visual rules to WCAG 2.2 AA: text contrast, contrast for controls and focus, colour never the
  only signal, text size and spacing, reflow to 320px, target size, motion and pop-ups, and
  images of text. It also adds PIE's own floors, such as 16px body text and 44px touch
  controls. Preloaded into Bob and Claudia, so reviews can cite the rules.
- **Last run stays one line**: a functional script's `Last run` is the date, the result in a few
  words and a pointer to the detail, overwritten each run. Tess puts the full record (clock time,
  site, IDs, commits before and after, row counts, checks not run) in a new Run record section of
  TEST-REPORT.md, and never writes commit hashes or working-tree state into a script. Ada copies
  the Run record into the PR description (new line under Tested in the PR template).
- **Manual test steps in every PR:** the PR template has a section of numbered browser steps
  (where to go, what to do, what to see, and any day or time they depend on). Tess ends
  TEST-REPORT.md with them and Ada copies them into the PR. `ada-onboard refresh` offers the new
  template to onboarded repos.
- **Tess can check the local site in Chrome:** when the developer asks her to run the local
  checks and Claude in Chrome is connected, she opens the local site (`LOCAL_URL` in
  `.claude/team/local.env`), carries out the steps and records what she saw. The developer logs
  in to the admin first; for a customer's view she uses the User Switching plugin's "Switch To"
  and switches back after. She never types a password or opens another host. Without Chrome or
  User Switching, those checks go back to the developer as manual steps. Tess alone has Chrome.

## 0.6.0, 15 Oct 2026

- **Squash commit in the PR**: Ada writes the squash-merge title and message, with the role
  credits, into a new section of the PR description, so whoever merges can paste them.
- **The PR template stays current**: `/ada:begin` runs `ada-onboard refresh`, which replaces a
  project's PR template with the plugin's newer one if it hasn't been edited, and shows the
  developer the diff and asks if it has. `ada-onboard files` now records template hashes for this.

## 0.5.1, 14 Oct 2026

- **The README is for users**: what the team is for, how to install it, the commands, what
  happens in a step, brief approval and setting up a project, in short, simple sentences.
- **DEVELOPING.md is for PIE developers**: how the plugin fits together, trying a local checkout,
  the layout, the plugin mechanics, the scripts behind approval, plans and PR size, credit in
  commits, releasing and platforms.

## 0.5.0, 14 Oct 2026

- **README brought up to date:** the draft PR format, Asana linking and the dry-run switch under
  Brief approval; `ada-plan` and `ada-size` among the pre-allowed commands; the release workflow
  and CHANGELOG in the layout; bash 3.2 tested for all the scripts, Python so far only on 3.12; a
  broken sentence in Releasing fixed. The finished "Work to 0.1.0" list is gone: the CHANGELOG
  has that history.
- **Each step uses fewer usage credits.** Every role still runs and every gate still applies.
  Measured on the V&Me dry run (12 Oct 2026): Ada's one long session was 40% of the cost, review
  and fix rounds about a quarter, and every role turn started from 37k tokens before reading
  anything. Output, thinking included, was about 1%: the cost is re-reading context.
  - **A fresh session per phase.** Ada ends the session once the brief is agreed and once Claudia
    and Tess both say `Ready for PR: YES`, and the developer carries on with `/ada:pickup`.
  - **An hour's prompt cache for Ada.** `ada-onboard settings` sets `promptCacheTtl` to `1h`
    unless the project sets it. On usage credits the default is five minutes, and Ada's waits for
    the roles and the developer were re-caching up to 150k tokens at a time.
  - **Fix rounds are focused.** Bob is spawned fresh to read only the feedback and the code it
    names. Claudia has a re-review mode, on Sonnet, that checks the fixed items and the functions
    around them, and asks for a full review if the fix went further. Ada continues a role with
    `SendMessage` only when she comes back to it at once, while its cache is still warm.
  - **Review items on the tests go to Tess**, on Sonnet, in a new Test fixes mode. Bob mustn't
    edit tests, so before this a Should Fix on a test could reach `Ready for PR: YES` unfixed.
  - **Each role has only the tools it needs** (`tools:` in its agent file): read, write, Bash and
    search, with Edit for Bob. The connectors (Gmail, Asana, Chrome and the rest) are no longer
    loaded into them.
  - **Fewer turns.** The efficiency skill asks for independent calls in one turn, since each turn
    re-reads the whole context. Tess, running local checks herself, scripts each check and runs
    it in one call.
  - **Claudia's effort is `high`, not `xhigh`.** Her first full review of a step stays on Opus.
  - **Tess plans and writes the tests in one spawn**, reading the brief and `TEST-AREAS.md` once.
    It still runs alongside Bob's plan.
  - **The plan playbook is its own skill, `ada:planning`**, loaded only when work is too big for
    one PR or a plan is under way. `ada:begin`, which stays in Ada's context for the whole
    session, is about a fifth shorter.
  - **P29 is in its own skill, `ada:standards-tests`.** Tess is preloaded with only that. Bob and
    Claudia get both. The rule's number and wording are unchanged.

## 0.4.1, 13 Oct 2026

- **P31: shared software gets its version number at release, not before.** For PIE software used
  in more than one place and versioned with semantic versioning, feature PRs change no version
  (headers, `plugin.json`, changelog headings, titles); entries go under Unreleased, and the
  release decides Major, Minor or Patch and tags main. Single-instance client projects aren't
  versioned this way. The `PROJECT.md` template says which kind a project is (Versioning).
- **This repo follows it:** a Releasing section in the README, and 0.3.2, which was never
  released on its own, is folded into 0.4.0 below.
- **Tags drop the `v`**, to match PIE's other repos: `0.4.1`, not `v0.4.1`. The earlier tags were
  replaced (`v0.1.0` to `v0.4.0` are now `0.1.0` to `0.4.0`, on the same commits).
- **Releases are made by a workflow**, as on PIE's other repos: label the next-release → main PR
  `release:major`, `release:minor` or `release:patch`. `.github/workflows/release.yml` sets the
  version in `plugin.json`, moves the Unreleased entries under it, tags `main` and creates a
  GitHub release with those entries as notes, then recreates `next-release`. No zip or
  `update.json`: the marketplace installs from `main`. Its actions are pinned to commit SHAs.
- The README's Status line no longer carries a version; it points to the latest release.

## 0.4.0, 13 Oct 2026

Small PRs make the PIE review short. Work too big for one PR is now planned as several, each
with its own brief, branch and PR.

- **Plans.** Ada decides whether work fits one PR by what the reviewer has to hold in their
  head: one behaviour change or concern, a short description to check the diff against, a small
  set of functions, at most one fragile area. Not by a line count: there's no code yet at
  planning time, and lines differ in what they cost to review. If not, she recommends breaking the Epic into **units**, each shippable on
  its own: a branch from production and a PR into next-release. A unit too big for one PR but
  only useful whole is a **feature**: its branch holds its brief, and its **parts** branch from it
  and PR back into it, then the feature PRs into next-release after a functional smoke test. Two
  levels at most. Each unit ships `independent` or `after` others: after a top-level unit means
  once it's in production; after a part means once it's in the feature branch. Units and parts
  are Asana subtasks of the Epic.
- **New `ada-plan`:** `init`, `check` (validates the Units table: IDs, tasks, branches, two
  levels, order without cycles), `briefs` (drafts a brief per unit with its branch, base, plan
  and order filled in), `branches` (creates every branch without a checkout) and `status` (each
  unit's PR, approval, whether it has shipped, and what's ready next).
- **`ada-brief submit <task> <base> <branch>`** commits a planned brief to its branch without
  checking it out, and takes the plan with it: committed as `.claude/briefs/<Epic id>-plan.md`
  and shown under the brief in the draft PR. A part's brief is refused until its feature's brief
  is approved. `ada-brief status <branch>` checks any branch's brief.
- **New `ada-size <base>`** estimates how long a step takes to review: small (up to 15
  minutes), medium (45), large (90), or too big for one sitting. It weighs lines by what they
  cost to read: changed code most (about 300 lines an hour), then removals, comments and docs,
  then tests (skimmed: the reviewer relies on Tess's report); moved code (git's
  `--color-moved`) almost nothing, generated files nothing. The rates
  are in one place in the script, to be tuned against real reviews. It shows Ada the breakdown
  and includes the working tree and new files, through a throwaway index. At the PR gate Ada
  raises the estimate for a fragile area, checks it against the brief's new `Expected review:`
  level, and asks the developer before opening a PR that came out a level bigger. The PR
  template's "For the reviewer" opens with the estimate and says where the review is, and asks
  the reviewer for the actual review time, so the rates can be tuned.
- **The draft PR shows what the approver checks**, not the whole brief: what to check and the
  expected review level, the unit's place (Unit B of 6, ships after Unit A, with PR links), then
  the Outcome, Decisions, Out of scope and Definition of done in full. The builder's constraints
  and build order, and the plan (its outcome, a short units table with PR links, and why this
  breakdown), are folded away. No template guidance, empty sections or team-only lines; the full
  brief stays committed. `ada-brief body <task-id> [<branch>]` prints it.
- **Units are always "Unit D", never "D" alone**, in the plan, briefs, PRs, Asana and talk. The
  draft PRs link each mention of another unit ("Unit C", "Unit A's", "Units B and C", "**Unit A**")
  to its PR. The scripts' own output says "Unit A" too, and Ships accepts `after Unit A, Unit B`
  (or the short `after A, B`).
- **Asana links.** A PR is linked to its card with the Asana app for GitHub: on the task, Apps,
  GitHub, "Add GitHub pull request". The Asana tools can't do it, so Ada asks the developer, with
  each task and its PR URL, and checks the link is there: the task gets an attachment whose URL
  is the PR. The app may also append a block to the PR's description; `ada-brief` keeps it
  whenever it replaces the description (submit again, ready).
- **Branches are updated twice:** by merge when a unit's build starts, and before it's merged.
- The brief template has `PR into:`, `Plan:` and `Ships:` lines; the checkpoint and PR template
  say which unit of which plan. `ada-handoff-init --archive` leaves a plan in place;
  `--archive-plan` archives it when every unit has shipped. `ada-onboard settings` allows
  `ada-plan` and `ada-size`.

### Also in 0.4.0: stop on a refused tool call (12 Oct)

From V&Me, 12 Oct: auto mode's safety check refused Bob's shell edit to a docblock as "audit
tampering". Bob then made the same edit with the Edit tool and only said so in his report. The
change was one the developer had agreed, and Claudia confirmed the diff, but nothing in the role
files said a refusal means stop.

- **Bob, Claudia and Tess** never work around a refused or denied tool call (a declined prompt, a
  blocking hook, a safety check). They stop, record what was refused, and hand back to Ada.
- **Claudia** escalates any refused action that was made another way, and checks the change is
  only what the brief asked.
- **Ada** never performs a refused action herself or tells a role to retry it. She takes it to the
  developer, and tells them when a role worked around one.

## 0.3.1, 12 Oct 2026

From the review of the first full `/ada:begin` dry run (V&Me, 12 Oct).

- **New `efficiency` skill**, preloaded into Bob, Claudia and Tess, and loaded by Ada, the bug hunter
  and onboarding: grep before
  read, one read per file, no speculative calls, simple commands, docs first and code to confirm,
  conclusions not working, and Ada hands anything long to a subagent. Adapted from Three Man
  Team's token-optimisation rules.
- **Bob** no longer "matches the style" of the code around him: conventions yes, formatting per
  phpcs and P30. phpcs findings are counted from `--report=summary`'s total.
- **Commits:** a P30 reformat keeps its own commit, before the build commit (it was folded into
  the build commit by one rule and kept separate by another). Cite PIE rules as P, project rules
  as S.
- **Finished steps are archived:** `ada-handoff-init --archive <name>`. Ada archives a finished
  step's handoff files at session start instead of stopping on its checkpoint. The build log is
  per step; the checkpoint lists leftovers on the local site.
- **Tess can run the local checks herself** when the developer asks: preconditions she checks,
  no branch switching in a shared checkout, no logging in as a customer in the browser, and a
  cleanup list by ID. Customers are referred to by ID only.
- **Claudia checks the docs are still true:** a `SYSTEM.md`/`API.md` statement the diff makes
  untrue, or a missing `API.md` entry, is Should Fix.
- **Ada:** rules the developer sets for the team go to `STANDARDS.md` in a PR, never memory;
  decisions go where unit tests can reach them; the gate says whether the docs were updated.
- **Local site:** the `PROJECT.md` template says how to run WP-CLI there, puts each developer's
  paths in a git-ignored `.claude/team/local.env`, adds frozen runners, a sane clock and no
  leftovers to the safety checks, and says how to point the site at a worktree.
- **Permissions:** `ada-onboard settings` pre-allows common read-only commands and edits under
  `.claude/handoff/` and `tests/`. The first dry run ran in auto mode; the next one runs in
  default mode to find what's still missing.
- The PR template opens with "For the reviewer"; the checkpoint's resume prompt is `/ada:pickup`
  (it named a file that no longer exists); the `CLAUDE.md` template aims for about 50 lines.

## 0.3.0, 12 Oct 2026

- **Mac fix.** `ada-brief` used `mapfile`, which macOS's bash 3.2 doesn't have, so brief approval
  failed on a stock Mac in 0.2.x. All the scripts are now tested under bash 3.2.57.
- **Windows.** The scripts find Python as `python3`, `python` or `py -3` (skipping the Microsoft
  Store shortcut), strip CRLF from its output, and write files with LF endings.
- **P30: bring each function you touch up to standard, whole.** Formatting-only reformat in its
  own commit; legacy files go in Known Gaps with their phpcs count, not into the PR. Bob, Claudia
  and the brief's Definition of Done follow it.
- **Working-tree safety.** Ada checks the working tree is hers before switching branches and
  sends the developer to a worktree if someone else's work is there; the team stages files by
  name, never `git add -A` or `commit -a`. `/ada:bughunt` checks before its scratch branch and
  before replicating on a shared local site. `PROJECT.md` says which checkout the local site runs.

## 0.2.1, 12 Oct 2026

- Each role is credited by name. Ada commits the team's work (Bob and Tess no longer may), with
  `Co-Authored-By: Bob / Tess / Ada (…, Claude Code)` trailers and `Reviewed-by: Claudia`; the
  developer stays the author. `ada-onboard settings` switches off Claude Code's default
  attribution, and the PR template ends with the team's credit line.

## 0.2.0, 12 Oct 2026

- Brief approval. `PROJECT.md` gets a "Brief approval" section (Approvers, Trusted). When the
  developer's briefs need approval, Ada commits the brief to `.claude/briefs/`, opens a draft PR
  and stops until an approver adds the `brief-approved` label. Amendments are approved again.
- New `bin/ada-brief` (`who`, `submit`, `status`), allowed by `ada-onboard settings`.
- `/ada:onboard` asks who approves; `ada-onboard check` checks the section.
- The brief template has an `Approval:` line and the PR template a `Brief:` line.

## 0.1.0, 12 Oct 2026

- First public release, published to `pie/ada-and-friends` with a single clean commit.
- `ada-onboard check` ignores `<...>` inside code spans and fences, so syntax like
  `<type>/<slug>` is no longer reported as a draft prompt.
- Smoke-tested: `/ada:onboard` and `/ada:begin` on a scratch repo with `--plugin-dir`.

## 0.0.4, 10 Oct 2026

- New `/ada:onboard` skill and `bin/ada-onboard` (`files`, `gitignore`, `settings`, `check`).
- Skeleton templates for the project knowledge files, the root `CLAUDE.md`, `tests/README.md` and
  `tools/bughunt/README.md`.

## 0.0.3, 10 Oct 2026

- `/ada:bughunt` is generic: it reads `PROJECT.md` and uses `prod-wp`.
- `bin/prod-wp` takes its settings from the project's `.claude/team/prod.env` (parsed, not
  executed; values checked) and its inspectors from the project's `tools/bughunt/`. New
  `prod-wp config` command. The never-query list is per project (`PROD_FORBIDDEN`).
- Repo templates no longer name V&Me. They carry `{{PLACEHOLDERS}}` for `/ada:onboard`, plus a
  `prod.env` template.

## 0.0.2, 10 Oct 2026

- `/ada:begin` holds Ada's playbook, generalised: she reads `.claude/team/PROJECT.md`, checks her
  model, runs `ada-handoff-init`, spawns `ada:tess`/`ada:bob`/`ada:claudia`, and starts Tess's run
  mode on Sonnet. `/ada:pickup` loads it.
- New `standards` skill: the PIE rules P1–P29 (numbers kept from V&Me's S rules), preloaded into
  the agents.
- Bob, Claudia and Tess are generic and set their effort; Tess moves to Opus.
- New `bin/ada-handoff-init` and a `PROJECT.md` template.
- Removed `reference/`: its contents now live in the skills.

## 0.0.1, 10 Oct 2026

- Imported the team from pie/vandme as it stood after its first full `/begin` run (PR #545)
  and the lessons from it (PR #547). Not yet generalised; that came in 0.0.2 to 0.1.0 above.
