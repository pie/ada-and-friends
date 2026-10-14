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

## Install

1. Run:

   ```sh
   claude plugin marketplace add pie/ada-and-friends
   claude plugin install ada@pie
   ```

2. Start `claude` in the project.
3. When Claude Code asks you to trust the folder, say yes. If you don't, the team's commands ask
   for permission each time.
4. When Claude Code asks you to install the `ada` plugin, say yes.

The project must be set up for the team first. If it isn't, see [Set up a project](#set-up-a-project).

## Use it

| Command | Use it to |
|---|---|
| `/ada:begin` | Start a piece of work. Give Ada the Asana task. |
| `/ada:pickup` | Continue work from where you stopped. |
| `/ada:bughunt` | Find the cause of a problem on the live site, before anything is built. |
| `/ada:onboard` | Set up a project for the team. Do this once per project. |

### What happens in a step

1. **You agree the brief with Ada.** The brief says what to build and how to know it's done.
2. **PIE approves the brief**, if the project needs it (see below).
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

On some projects, people outside PIE write code. On those projects, PIE approves each brief
before the team builds it. The project's `PROJECT.md` file lists:

- **Approvers.** They approve briefs. They don't need approval.
- **Trusted developers.** They don't need approval, and they can't approve.

All other developers need approval.

When a brief needs approval, Ada opens a draft PR that contains the brief. Then she stops.

- **To approve**, an approver adds the `brief-approved` label to the draft PR.
- **To ask for changes**, an approver adds review comments.

When the approver has replied, run `/ada:pickup`. If the brief changes, it needs approval again.
The draft PR becomes the PR for the work.

Link each PR to its Asana task: on the task, open **Apps**, then **GitHub**, then
**Add GitHub pull request**.

## Set up a project

1. Start `claude` in the project.
2. Run `/ada:onboard`.
3. Read the files that Ada writes in `.claude/team/`. Correct them.

The team's knowledge of the project is in `.claude/team/`: what the project is, how it works,
its rules and what to test. Keep those files correct. The team trusts them.

## Requirements

- Claude Code 2.1.242 or later.
- `git`, and `gh` (the GitHub command line tool), logged in.
- bash 3.2 or later, and Python 3.8 or later.
- Linux or macOS. Windows with Git Bash or WSL is expected to work, but isn't tested yet.

## For PIE developers

To change the plugin or make a release, see [DEVELOPING.md](DEVELOPING.md).

## Licence

MIT. The team's structure comes from Three Man Team (MIT, Russell Aaron). Both notices are in
[LICENSE](LICENSE).
