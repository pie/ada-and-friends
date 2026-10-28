# Developing Ada and friends

For PIE developers who change the plugin or release it. Users start with the [README](README.md).

## How it fits together

The process lives in this plugin. **Each project's knowledge lives in its own repo**, in
`.claude/team/`: `PROJECT.md`, `SYSTEM.md`, `API.md`, `STANDARDS.md` (the project's S rules) and
`TEST-AREAS.md`. The PIE rules (P1 onwards) are in `skills/standards/` and
`skills/standards-tests/` and `skills/standards-accessibility/`.

The roles hand over through files in the project's `.claude/handoff/`, copied in from
`templates/handoff/` by `ada-handoff-init`.

## Try a local checkout

```sh
claude --plugin-dir ~/path/to/ada-and-friends/plugins/ada
```

If the project enables `ada@pie`, turn it off for the test, so the two copies don't clash. Put
`"enabledPlugins": {"ada@pie": false}` in the project's `.claude/settings.local.json`.

## Layout

```
.claude-plugin/marketplace.json   the "pie" marketplace
.github/workflows/release.yml     makes a release when a labelled PR is merged into main
.github/scripts/release-bump.py   sets the version in plugin.json and the CHANGELOG, for it
CHANGELOG.md                      what changed in each release; new entries under Unreleased
plugins/ada/
  .claude-plugin/plugin.json      the "ada" plugin: commands are /ada:...
  skills/begin/                   Ada's playbook (/ada:begin)
  skills/pickup/                  resume from the checkpoint (/ada:pickup)
  skills/bughunt/                 read-only investigation (/ada:bughunt)
  skills/design/                  Iris, the designer (/ada:design); usually loaded by /ada:begin
  skills/onboard/                 set a repo up for the team (/ada:onboard)
  skills/planning/                Ada's playbook for work too big for one PR, loaded only then, not a command
  skills/standards/               the PIE rules (P1 onwards), preloaded into Bob and Claudia, not a command
  skills/standards-tests/         P29, on tests, preloaded into all three agents, not a command
  skills/standards-accessibility/ P32-P39, visual accessibility (WCAG 2.2 AA), preloaded into Bob and Claudia, not a command
  skills/efficiency/              how the team keeps its context down, preloaded, not a command
  agents/                         bob, claudia, tess (spawned as ada:bob etc.)
  bin/                            on the Bash PATH while enabled: ada-brief, ada-plan, ada-size, ada-handoff-init, ada-worktree, ada-onboard, prod-wp, presstest, ada-clock
  templates/handoff/              the files the roles hand over through
  templates/plan/                 the plan, for work too big for one PR
  templates/repo/                 copied into a project by /ada:onboard, including PROJECT.md
```

## Plugin mechanics that shape the design

Tested 10 Oct 2026 on Claude Code 2.1.296:

- Agents are spawned by their prefixed name: `ada:bob`, never `bob`.
- `bin/` is on the PATH, so `prod-wp` and `presstest` ship here. Each project still allows them in
  its settings.
- `presstest` runs a project's PressTest suite on the developer's local site. The suite is the
  repo's (`PROJECT.md`); the site, the presstest.io clone and the secrets are the developer's
  (`.claude/team/local.env`). It tests the local site (`.local` and `.test` hosts) or, with
  `--env staging`, the staging URL in `PROJECT.md`, with separate `PRESSTEST_STAGING_*` settings.
  There is no production option: the suites place orders, and nothing marks a test as safe for
  live. It only touches the clone's `playwright` container, recreating it when the local site's
  domain isn't mapped into it.
- `ada-clock` puts the developer's local WordPress site at a fake time. The site, its WordPress
  root and the WP-CLI command are the developer's (`.claude/team/local.env`); it refuses any site
  not on `localhost`, `127.0.0.1`, `.local` or `.test`. `install` copies a mu-plugin from
  `templates/clock/` into the site, which does nothing on any other host. Under the `manual` setup
  it prints the steps to change the computer's clock and never runs `sudo` itself. Under
  `local-linux` (Local on Linux, with libfaketime), `install` also puts a wrapper from
  `templates/clock/` in place of Local's `php-fpm` and adds three lines to the site's pool
  template, outside the repo; `uninstall` undoes them (`.claude/team/SYSTEM.md`, What
  `ada-clock install` changes outside the repo).
- Reading files bundled in the plugin needs a permission rule, so the team avoids it:
  - **Shared standards** are non-invocable skills, preloaded into each agent (`skills:` in its
    frontmatter).
  - **Templates** are copied into the repo by a `bin/` script.
- A plugin has no install or update hook, so it can't change a project's files when it updates.
  `/ada:begin` runs `ada-onboard refresh` at session start instead: the project's PR template is
  replaced if it's an untouched copy (its hash is in `.claude/team/.template-hashes`), and the
  developer is asked if it was edited. It runs after the branch step, so the change lands on the
  step's branch and doesn't trip the check for someone else's changes.
- A plugin can't ship permissions or settings. `ada-onboard settings` writes them into the
  project's `.claude/settings.json`, including `promptCacheTtl: "1h"` for Ada's cache.
- Each agent has a `tools:` allowlist, so connectors (Gmail, Asana, Chrome) aren't loaded into
  it. That keeps each role's starting context small.
- One exception: Tess has Claude in Chrome (`ToolSearch` and `mcp__claude-in-chrome__*`), only
  for local checks the developer asks her to run, and only on the local site. It is in her own
  role file rather than a second browser agent, so there is one set of testing rules to keep
  current. When tool search is on, the Chrome tools are deferred behind `ToolSearch`, so only
  their names are in her starting context. `ada-onboard settings` writes no Chrome permission:
  the developer answers the prompts.

## Brief approval

`bin/ada-brief` does the checking. Only a `brief-approved` label added by an approver, after the
brief last changed, counts. It commits the brief to `.claude/briefs/<task id>.md` and opens the
draft PR. The draft PR shows what the approver checks: what to check, the expected review size,
the outcome, decisions, out of scope and Definition of Done. The constraints and build order are
folded away. When `ada-brief` replaces a PR's description, it keeps the block the Asana app adds.

To try the flow as an approver, run Claude with `ADA_BRIEF_REQUIRE=1`. Your briefs then need
approval, and your own label counts.

`ada-brief overlap [<file>...]` lists the work in flight a step could overlap. It fetches the
`Branch from` and `PR into` branches `PROJECT.md` names. It lists the commits in `PR into` not in
`Branch from` (one entry per PR number in a squash commit's subject), and the open PRs into either
branch, and marks each entry that changes one of the files given. Ada runs it before confirming a
brief, and again at the PR gate as `ada-brief overlap --gate`, which takes the branch's own
changed and untracked files since it left `PR into` and exits 2 if it can't find that point (a
shallow clone). The open PRs need `gh`; without it, that group says `unknown` and the rest still
prints. It also says `unknown` when more than 100 PRs are open into a branch, or an open PR has
100 or more files, since `gh` lists no more.

## Plans and PR size

`bin/ada-plan` reads the plan (the main checkout's `.claude/handoff/plan/PLAN.md`, wherever it
runs, committed as `.claude/briefs/<Epic id>-plan.md`), drafts the briefs, creates the branches
and reports each unit's state. A unit branches from production and PRs into next-release. A
**feature** is a unit too big for one PR that only works whole: its parts PR into the feature
branch, and the feature PRs into next-release after a smoke test.

At the PR gate, `bin/ada-size` estimates the review time. It weighs lines by what they cost to
read: changed code most, moved and generated code almost nothing. The reviewer records the actual
time on the PR. After a dozen or so PRs, compare the two and tune the rates at the top of
`bin/ada-size`.

## Credit in commits and PRs

The developer is the author of every commit. Ada makes the commits and credits the roles with
trailers, in place of Claude Code's default line (switched off in the project's settings):

- Tests: `Co-Authored-By: Tess (Ada and friends, Claude Code) <tess@ada.pie.co.de>`
- The build: `Co-Authored-By: Bob (…)` and `Reviewed-by: Claudia (…)`
- Briefs and onboarding: `Co-Authored-By: Ada (Claude Code) <ada@ada.pie.co.de>`

The PR description carries a ready-made squash commit message with the same trailers, so the
credit survives the squash merge.

PRs end with "Built by Bob, reviewed by Claudia, tested by Tess. Ada and friends, Claude Code."

## Releasing

Work has no version number until it's released (P31). Feature branches and PRs don't change the
version in `plugin.json` or put a version in their titles. Add CHANGELOG entries under
`## Unreleased`. Feature PRs go into `next-release`.

To release:

1. Open a PR from `next-release` into `main`.
2. Decide Major, Minor or Patch from the entries under Unreleased.
3. Label the PR `release:major`, `release:minor` or `release:patch`.
4. Merge it.

The Release workflow (`.github/workflows/release.yml`) then:

1. works out the version from the label and the latest GitHub release;
2. sets it in `plugins/ada/.claude-plugin/plugin.json`, moves the Unreleased entries under
   `## <version>, <date>`, and commits that to `main`;
3. tags the commit with the plain version (`0.5.0`, no `v`) and creates a GitHub release with
   those entries as its notes;
4. recreates `next-release` from `main`, since merging deletes it.

A PR into `main` without a label releases nothing. A labelled PR with nothing under Unreleased
fails before it changes anything. There's no zip or `update.json`: the `pie` marketplace installs
straight from `main`.

## Platforms

The scripts in `bin/` are bash with a little Python. They need bash 3.2 or later, Python 3.8 or
later, `git`, `gh` (logged in) and, for `prod-wp`, `ssh`. The Python is written for 3.8 but has
only been run on 3.12.

| Platform | Status |
|---|---|
| Linux | Used daily. |
| macOS | All the scripts are tested under bash 3.2.57, the version macOS ships. Python 3 comes with Apple's command line tools, which `git` already needs. |
| Windows | Expected to work in Git Bash, which Claude Code uses on Windows. **Not yet tested.** The scripts find Python as `python3`, `python` or `py -3`, and skip the Microsoft Store shortcut. On a first run, try `ada-onboard check` and `ada-brief who`, and report what breaks. WSL also works. |

## Iris and the canvas

- **Iris is a skill, not an agent.** Design goes back and forth with the developer, and a
  subagent's output is never shown to them, so `/ada:design` runs in the main session, as
  `/ada:bughunt` does. Ada loads it from `/ada:begin` when the design test in
  `skills/begin/SKILL.md` passes. The test is also quoted, word for word, in the three agent
  files, so each stands on its own: change all four together.
- **Only the main session reaches the canvas.** The canvas is the Design Artifact type, found
  through Artifact `quickstart` with intent `design`, which returns the type URL to publish with.
  No agent's `tools:` list has Artifact, so Bob, Claudia and Tess work from
  `.claude/handoff/DESIGN.md` instead, with token values from the project's root `DESIGN.md`.
  Checked on 21 Oct 2026: `quickstart` offers the Design type in a main session. Whether a plugin
  subagent could call Artifact at all hasn't been checked.
- **`.claude/handoff/DESIGN.md` is always there**, because `ada-handoff-init` copies every
  handoff template. The roles act on it only when its `Status:` line says `agreed`.
- **Two DESIGN.md files.** `.claude/handoff/DESIGN.md` is the step's design. The project's
  `DESIGN.md`, at the repo root, is its design system in the DESIGN.md format, which Iris
  creates from `templates/DESIGN.md` and keeps. `ada-handoff-init` copies only
  `templates/handoff/`, so it never copies the root template.

## Cost

A design step's cost against a typical build: not measured yet. Fill this in from the first dry
run of `/ada:begin` with a design step.
