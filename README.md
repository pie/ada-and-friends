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

You talk only to Ada. She gets the other agents to do their work.

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
   on your machine.

## Use it

| Command | Use it to |
|---|---|
| `/ada:onboard` | Set up a repo for the team. Once per repo (see above). |
| `/ada:begin` | Start a piece of work. Give Ada the Asana task. |
| `/ada:pickup` | Continue work from where you stopped. |
| `/ada:bughunt` | Find the cause of a problem on the live site, before anything is built. |

### What happens in a step

1. **You agree the brief with Ada.** The brief says what to build and how to know it's done.
2. **An approver approves the brief**, if the project needs it (see below).
3. **The team builds it.** Tess writes the tests. Bob builds. Claudia reviews. Tess runs the tests.
4. **You agree to open the PR.** Ada gives you a summary first. Nothing is merged by the team.

Ada asks you before she changes the brief. She also asks you about anything that changes what
the customer sees or pays.

**Start a new session when Ada tells you to.** She does this at the end of each stage. Run
`/ada:pickup` in the new session. A new session uses fewer tokens than a long one.

### Large pieces of work

A PR must be small enough to review in one sitting. If the work is too large, Ada makes a plan.
The plan breaks the work into units. Each unit has its own brief, branch, PR and Asana subtask.
You agree the plan before Ada writes the briefs.

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
[LICENSE](LICENSE).
