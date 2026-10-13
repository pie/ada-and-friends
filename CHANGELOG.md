# Changelog

Entries go under Unreleased. The version is chosen when next-release goes into main (see
Releasing in the README).

## Unreleased

Nothing yet.

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
  and the lessons from it (PR #547). Not yet generalised; see "Work to 0.1.0" in the README.
