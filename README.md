# Ada and friends

A team of Claude Code agents for work on PIE projects. You tell Ada what you need. The team
builds it, reviews it and tests it, and gives you a pull request (PR) for PIE to review.

[Latest release](https://github.com/pie/ada-and-friends/releases/latest) ·
[What changed](CHANGELOG.md)

## The team

| Who | What they do |
|---|---|
| **Ada**, the architect | Talks to you. Writes the brief. Directs the others. Opens the PR. |
| **Bob**, the builder | Builds what the brief says, and nothing more. |
| **Claudia**, the reviewer | Reviews Bob's code against PIE's standards and the project's rules. |
| **Tess**, the tester | Writes tests from the brief. Runs them. Reports what she checked and what she didn't. |
| **Grace**, the bug hunter | Finds the cause of a problem on the live site, reading production only. Hands her findings to Ada. |
| **Iris**, the designer | Designs what people will see, as options on a Claude Design canvas, with you. Checks it meets the accessibility rules. Keeps your project's design system in `DESIGN.md`. Hands the agreed design to Ada. |

In a build you talk only to Ada. She gets the other agents to do their work. When a change needs
designing, Ada brings Iris in, and you work with Iris on the design in the same session. For a
problem on the live site, you talk to Grace first (`/ada:bughunt`).

## Get started

### 0. Check the requirements

Each developer needs:

- Claude Code 2.1.242 or later.
- `git`, and `gh` (the GitHub command line tool), logged in.
- bash 3.2 or later, and Python 3.8 or later.
- PHP and Composer, for the tests and the code checks.
- Linux or macOS. Windows with Git Bash or WSL is expected to work, but isn't tested yet.

### 1. Set up the repo: once per repo

Do this only if the repo has no `.claude/team/PROJECT.md` file. If one developer has done it,
nobody does it again.

1. Start `claude` in the repo.
2. Run `/ada:onboard`. Ada writes the team's files and drafts what the team must know about the
   project.
3. Read the files in `.claude/team/`, and correct them. The team trusts them, so they must be
   correct.
4. Merge the onboarding PR. Then every developer gets the team's files with the repo.

### 2. Set up your machine: once per developer

1. Start `claude` in the repo.
2. When Claude Code asks you to trust the folder, say yes. If you don't, the team's commands ask
   for permission each time.
3. When Claude Code asks you to install the `ada` plugin, say yes. If it doesn't ask, run:

   ```sh
   claude plugin marketplace add pie/ada-and-friends
   claude plugin install ada@pie --scope project
   ```

4. Run `composer install -d tools`. This installs the tools that run the tests and the code
   checks.
5. If the project uses a local copy of the site, put your own paths and the site's URL
   (`LOCAL_URL`) in `.claude/team/local.env`. `PROJECT.md` says what goes there. This file stays
   on your machine. If the project's dates matter, set up its clock too (A local site's clock,
   below).
6. If the project has a PressTest suite (`PROJECT.md`, Tests), also put in `local.env` where your
   clone of pie/presstest.io is (`PRESSTEST_DIR`) and the local site's report token
   (`PRESSTEST_SECRET`). `presstest config` shows what it found. Then `presstest run` runs the
   project's suite against your local site, for you or for Tess. `presstest run --env staging`
   runs it on the project's staging site, if `PROJECT.md` names one; its token goes in
   `PRESSTEST_STAGING_SECRET`. It needs Docker, and never runs on production.

## Use it

| Command | Use it to |
|---|---|
| `/ada:onboard` | Set up a repo for the team. Once per repo (see above). |
| `/ada:begin` | Start a piece of work. Give Ada the Asana task. |
| `/ada:pickup` | Continue work from where you stopped. |
| `/ada:bughunt` | Have Grace find the cause of a problem on the live site, before anything is built. |
| `/ada:design` | Have Iris design something with no build behind it yet, such as concepts for a proposal. For a build, `/ada:begin` brings her in. |

### What happens in a step

1. **You agree the brief with Ada.** The brief says what to build and how to know it's done.
2. **An approver approves the brief**, if the project needs it (see below).
3. **The team builds it.** Tess writes the tests. Bob builds. Claudia reviews. Tess runs the tests.
4. **You agree to open the PR.** Ada gives you a summary first. Nothing is merged by the team.

Ada asks you before she changes the brief. She also asks you about anything that changes what
the customer sees or pays.

**Start a new session when Ada tells you to.** She does this at the end of each stage. Run
`/ada:pickup` in the new session. A new session uses fewer tokens than a long one.

### Several steps at once

Each step gets its own worktree: a separate checkout of the repo, on the step's branch, in
`.claude/worktrees/<slug>`. Ada makes it with `ada-worktree new` when the step starts and moves
the session into it. So you can run several steps at once, each in its own session. No session
reads another step's brief, checkpoint or build log.

- **`/ada:pickup` checks the checkpoint is this branch's.** If it belongs to another step, Ada
  moves to that step's worktree, or tells you where it is and stops. If that step's PR is merged
  or closed, she asks whether to archive it, and does it once you say yes.
- **A plan stays in the main checkout.** Agree it there. Each unit is built in its own worktree.
- **A fix after a bughunt starts from Grace's findings, when she tried a fix in a worktree.**
  She writes them in the worktree she tried her fix in. `/ada:begin` asks which bughunt the fix
  is for and copies its `FINDINGS.md` into the fix's worktree.
- **Finished steps are tidied from the main checkout.** When a step's PR is merged or closed,
  `/ada:begin` in the main checkout offers to remove its worktree. It asks about each one. The
  branch stays, and the step's handoff files go to `.claude/handoff/archive/`.
- **`ada-worktree list`** shows each worktree with its branch, step and PR.
- **Your local site still runs one checkout.** To test a step on it, point the site at the
  step's worktree, as `PROJECT.md` says.

If your Claude Code can't move a session into a worktree, Ada gives you a `cd … && claude`
command to run instead. `ada-onboard gitignore` keeps `.claude/worktrees/` out of git;
`ada-worktree new` refuses until it has.

### Large pieces of work

A PR must be small enough to review in one sitting. If the work is too large, Ada makes a plan.
The plan breaks the work into units. Each unit has its own brief, branch, PR and Asana subtask.
You agree the plan before Ada writes the briefs.

### A local site's clock

Some sites behave differently by day or time: a cut-off at noon on Friday, a weekly renewal. To
test that, `ada-clock` puts your local site at a fake time, and puts it back. Tess uses it for
her date checks, and you can run it yourself. It works only on a site at `localhost`,
`127.0.0.1`, or an address ending in `.local` or `.test`.

`/ada:onboard` sets it up in a project whose `PROJECT.md` says it is clock-sensitive. On your
own machine, or in a project onboarded before `ada-clock`, run this in the repo:

```sh
ada-onboard clock LOCAL_WP_PATH="<the site's WordPress root>" LOCAL_WP="wp --path=<the same root>"
```

It writes your setup to `.claude/team/local.env`, which stays on your machine. The site's
`LOCAL_URL` goes there too.

- **Linux with Local** (the `local-linux` setup): only that site's clock moves, and your
  computer's stays real. Install libfaketime once with `sudo apt install faketime`, run
  `ada-clock install`, then restart the site in Local (Stop site, Start site).
  `ada-clock uninstall` undoes the install.
- **Mac and Windows** (the `manual` setup): run `ada-clock install` once. `ada-clock set` then
  prints the steps to change your computer's clock, and `ada-clock reset` the steps to switch
  automatic time back on.

Then:

```sh
ada-clock set "2026-11-06 12:30"   # in the site's timezone
ada-clock status                   # exits 0 when the site has that time
ada-clock reset                    # back to real time
```

For the site's own JavaScript, run `ada-clock extension` and load the folder it names in Chrome
(`chrome://extensions`, Developer mode, Load unpacked). While the clock is set, `new Date()` on
the site gives the fake time.

Test payments on real time: payment webhooks fail their signature checks while the clock is
moved.

## Brief approval

On some projects, PIE approves each brief before the team builds anything. This makes sure that
PIE agrees with the work before time is spent on it.

The **Brief approval** section of `PROJECT.md` says who needs approval:

| Who | Needs approval? | Can approve? |
|---|---|---|
| **Approvers** | No | Yes |
| **Trusted** developers | No | No |
| **All other developers** | Yes | No |

A new developer is in the last group until someone adds them to a list. If the Approvers list is
`none`, nobody needs approval.

When your brief needs approval:

1. Ada opens a draft PR that contains the brief. Then she stops.
2. Give the PR link to an approver.
3. The approver reads the brief on GitHub, and does one of these:
   - **Approve:** add the `brief-approved` label to the PR.
   - **Ask for changes:** add review comments to the PR.
4. Run `/ada:pickup`. If the brief is approved, the build starts. If the approver asked for
   changes, Ada goes through them with you, changes the brief, and sends it for approval again.

Only a label that an approver adds after the last change to the brief counts. The draft PR
becomes the PR for the work. You need `gh`, logged in.

Link each PR to its Asana task: on the task, open **Apps**, then **GitHub**, then
**Add GitHub pull request**.

## For PIE developers

To change the plugin or make a release, see [DEVELOPING.md](DEVELOPING.md).

## Licence

MIT. The team's structure comes from Three Man Team (MIT, Russell Aaron). Both notices are in
[LICENSE](LICENSE). A project's `DESIGN.md` follows Google's
[DESIGN.md format](https://github.com/google-labs-code/design.md), the one Stitch uses.
