# Ada and friends

Status: in use on V&Me. The current version is the [latest release](https://github.com/pie/ada-and-friends/releases/latest); what changed is in the [CHANGELOG](CHANGELOG.md).

A Claude Code team for working on a project, from PIE Code:

| Who | Does |
|---|---|
| **Ada** (architect, the main session) | Talks to the developer, writes the brief, runs the others, opens the PR. |
| **Bob** (builder) | Builds exactly what the brief says. |
| **Claudia** (reviewer) | Reviews against numbered standards and the project's traps. |
| **Tess** (tester) | Writes tests from the brief, runs them, reports what was and wasn't verified. |
| `/ada:bughunt` | Investigates a reported problem: production read-only, replication local. |

The process lives here. **Each project's knowledge lives in its own repo**, in `.claude/team/`:
`PROJECT.md`, `SYSTEM.md`, `API.md`, `STANDARDS.md` (project rules) and `TEST-AREAS.md`.

## Brief approval

On a project where people outside PIE write code, PIE can approve each brief before anything is
built. The project's `PROJECT.md` has a "Brief approval" section with two lists of GitHub logins:

- **Approvers** can approve anyone's brief and never need approval themselves.
- **Trusted** developers don't need approval but can't approve.
- **Everyone else** needs approval, so a new developer is covered by default.

`Approvers: none` turns it off (an internal project). When approval is needed, Ada commits the
agreed brief to `.claude/briefs/<task id>.md`, opens a draft PR with it, and stops. An approver
adds the `brief-approved` label to approve, or leaves review comments to ask for changes; the
developer carries on with `/ada:pickup`. `bin/ada-brief` does the checking: only a label added by
an approver, after the brief last changed, counts. A changed brief is approved again. The draft
PR becomes the work's PR at the end. It needs `gh`, logged in.

The draft PR shows only what the approver checks: what to check and the expected review size,
then the outcome, decisions, out of scope and Definition of Done. The builder's constraints and
build order are folded away, and the full brief is committed on the branch. Ada asks the
developer to link each PR to its Asana task (on the task: Apps, GitHub, "Add GitHub pull
request"); `ada-brief` keeps the block the Asana app adds when it replaces a PR's description.

To try the approval flow as an approver, run Claude with `ADA_BRIEF_REQUIRE=1`: your briefs then
need approval, and your own label counts.

## Plans: work too big for one PR

The PIE review of a PR is the final gate, and small PRs get better reviews. When a piece of work
won't fit one PR, Ada recommends a plan and agrees it with the developer before writing any
brief. The work is an Epic in Asana. It is broken into **units**, each a subtask with its own
brief, branch and PR:

- **A unit** ships on its own: production still works if the next unit never comes. It branches
  from production and PRs into next-release.
- **A feature** is a unit that only works whole but is too big for one PR. Its branch, from
  production, holds its brief. Its **parts** branch from it and PR back into it, each with its
  own brief. When every part is in, the feature branch PRs into next-release, and that PR gets a
  functional smoke test, like a release, not a code review.
- Each unit ships **independently**, or **after** others: a unit after another is built once
  that one is in production; a part after another, once that one is in the feature branch.

All the briefs and branches are made at planning time. Each brief is approved on its own, in its
draft PR, with the plan (a short units table and why this breakdown) folded in, so the approver
sees where the unit fits. Units are always "Unit B", never "B" alone, and every mention of
another unit links to its PR. A feature's brief is approved before its parts' briefs are
submitted. The team then works through the units as they're approved and unblocked, one PR
each. `bin/ada-plan` reads the plan (`.claude/handoff/plan/PLAN.md`, committed as
`.claude/briefs/<Epic id>-plan.md`), drafts the briefs, creates the branches and reports each
unit's state.

What fits one PR is judged by what the reviewer has to understand (one behaviour change, a small
set of functions, at most one fragile area), not by a line count. At the PR gate `bin/ada-size`
estimates the review time, weighing lines by what they cost to read (changed code most, moved
and generated code almost nothing), and the PR opens with that estimate and where the review is.
The reviewer adds the actual review time; compare the two after a dozen or so PRs and tune the
rates at the top of `bin/ada-size`.

## Credit in commits and PRs

The developer is the author of every commit. Ada makes the commits and credits the roles with
trailers, in place of Claude Code's default line (switched off in the project's settings):
`Co-Authored-By: Tess (Ada and friends, Claude Code)` on the tests,
`Co-Authored-By: Bob (Ada and friends, Claude Code)` and `Reviewed-by: Claudia (…)` on the build,
and `Co-Authored-By: Ada (Claude Code)` on briefs and onboarding. PRs end with "Built by Bob,
reviewed by Claudia, tested by Tess. Ada and friends, Claude Code."

## Releasing

Work has no version number until it's released (P31). Feature branches and PRs don't touch the
version in `plugin.json`, don't put a version in their titles, and add their CHANGELOG entries
under `## Unreleased`.

To release, open a PR from `next-release` into `main`, decide Major, Minor or Patch from what's
under Unreleased, and label the PR `release:major`, `release:minor` or `release:patch`, as on
PIE's other repos. When it's merged, the Release workflow (`.github/workflows/release.yml`):

1. works out the version from the label and the latest GitHub release;
2. sets it in `plugins/ada/.claude-plugin/plugin.json`, moves the Unreleased entries under
   `## <version>, <date>`, and commits that to `main`;
3. tags the commit with the plain version (`0.4.1`, no `v`, as PIE's other repos do) and creates
   a GitHub release with those CHANGELOG entries as its notes;
4. recreates `next-release` from `main`, since merging deletes it.

A PR into `main` without a label releases nothing. A labelled PR with nothing under Unreleased
fails before changing anything. There's no zip or `update.json`, unlike PIE's WordPress plugins:
the `pie` marketplace installs straight from `main`, so a release reaches everyone who updates.

## Platforms

The helper scripts in `bin/` are bash with a little Python. They need bash 3.2 or later, Python
3.8 or later, `git`, `gh` (logged in) and, for `prod-wp`, `ssh`. The Python is written for 3.8
but has only been run on 3.12 so far.

| Platform | Status |
|---|---|
| Linux | Used daily. |
| macOS | All the scripts are tested under bash 3.2.57, the version macOS ships. Python 3 comes with Apple's command line tools, which `git` already needs. |
| Windows | Expected to work in Git Bash, which Claude Code uses on Windows; **not yet tested**. The scripts find Python as `python3`, `python` or `py -3`, skipping the Microsoft Store shortcut. First time on a PC: run `ada-onboard check` and `ada-brief who`, and report what breaks. WSL also works. |

## Install

```sh
claude plugin marketplace add pie/ada-and-friends
claude plugin install ada@pie
```

Then `/ada:begin` in a project that has been onboarded with `/ada:onboard`.

**The first time you run `claude` in a project, it asks whether to trust the folder. Say yes.**
Until a folder is trusted, Claude Code ignores the project's `.claude/settings.json` permission
rules, so the team's pre-allowed commands (`prod-wp`, `ada-brief`, `ada-plan`, `ada-size`, the
read-only git and grep commands) would all ask for permission instead. Claude Code also offers
to install the `ada` plugin that the project's settings name: say yes to that too.

To try a local checkout instead, start Claude with `claude --plugin-dir ~/path/to/ada-and-friends/plugins/ada`.

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
  skills/onboard/                 set a repo up for the team (/ada:onboard)
  skills/standards/               the PIE rules (P1 onwards), preloaded into the agents, not a command
  skills/efficiency/              how the team keeps its context down, preloaded, not a command
  agents/                         bob, claudia, tess (spawned as ada:bob etc.)
  bin/                            on the Bash PATH while enabled: ada-brief, ada-plan, ada-size, ada-handoff-init, ada-onboard, prod-wp
  templates/handoff/              the files the roles hand over through
  templates/plan/                 the plan, for work too big for one PR
  templates/repo/                 copied into a project by /ada:onboard, including PROJECT.md
```

## How the plugin mechanics shape the design

Tested 10 Oct 2026 on Claude Code 2.1.296:

- Agents are spawned by their prefixed name: `ada:bob`, never `bob`.
- `bin/` is on the PATH, so `prod-wp` ships here; each project still allows it in its settings.
- Reading files bundled in the plugin needs a permission rule, so the team avoids it:
  - **Shared standards** are a non-invocable skill, preloaded into each agent (`skills:` in its frontmatter).
  - **Templates** are copied into the repo by a `bin/` script.
- A plugin can't ship permissions. `/ada:onboard` writes them into the project's `.claude/settings.json`.

## History

What changed in each release, from 0.0.1, is in the [CHANGELOG](CHANGELOG.md).

## Licence

MIT. The role structure is adapted from Three Man Team (MIT, Russell Aaron); both notices are in
`LICENSE`.
