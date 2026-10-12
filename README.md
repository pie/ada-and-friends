# Ada and friends

Status: **0.2.1.** Brief approval, and each role credited in commits and PRs. V&Me is the first project to use it.

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

## Credit in commits and PRs

The developer is the author of every commit. Ada makes the commits and credits the roles with
trailers, in place of Claude Code's default line (switched off in the project's settings):
`Co-Authored-By: Tess (Ada and friends, Claude Code)` on the tests,
`Co-Authored-By: Bob (Ada and friends, Claude Code)` and `Reviewed-by: Claudia (…)` on the build,
and `Co-Authored-By: Ada (Claude Code)` on briefs and onboarding. PRs end with "Built by Bob,
reviewed by Claudia, tested by Tess. Ada and friends, Claude Code."

## Install

```sh
claude plugin marketplace add pie/ada-and-friends
claude plugin install ada@pie
```

Then `/ada:begin` in a project that has been onboarded with `/ada:onboard`.

To try a local checkout instead, start Claude with `claude --plugin-dir ~/path/to/ada-and-friends/plugins/ada`.

## Layout

```
.claude-plugin/marketplace.json   the "pie" marketplace
plugins/ada/
  .claude-plugin/plugin.json      the "ada" plugin: commands are /ada:...
  skills/begin/                   Ada's playbook (/ada:begin)
  skills/pickup/                  resume from the checkpoint (/ada:pickup)
  skills/bughunt/                 read-only investigation (/ada:bughunt)
  skills/onboard/                 set a repo up for the team (/ada:onboard)
  skills/standards/               the PIE rules (P1-P29), preloaded into the agents, not a command
  agents/                         bob, claudia, tess (spawned as ada:bob etc.)
  bin/                            on the Bash PATH while enabled: ada-brief, ada-handoff-init, ada-onboard, prod-wp
  templates/handoff/              the files the roles hand over through
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

## Work to 0.1.0

Done in 0.0.2:
- Ada's playbook is `/ada:begin`. It reads the project's `PROJECT.md` instead of naming V&Me.
- The standards are split. The PIE rules are P1–P29 in the `standards` skill, with the same numbers
  as V&Me's old S rules, so existing citations still hold. Project rules stay in each repo as S
  numbers.
- The agents are generic. They have the standards preloaded, read `PROJECT.md`, and have the
  effort set: Bob high, Claudia xhigh, Tess high. Tess is on Opus to write tests, and Ada starts
  her on Sonnet to run them.
- `ada-handoff-init` copies the handoff templates in without overwriting.
- There's a `PROJECT.md` template.

Done in 0.0.3:
- `/ada:bughunt` is generic. It reads `PROJECT.md` for the known-issues project, the fragile areas
  and the classes never to construct on production.
- `bin/prod-wp` reads `.claude/team/prod.env`: the SSH host, table prefix, logs path and
  never-query list. The file is parsed, never executed, and each value is checked before use.
  Inspector scripts come from the project's own `tools/bughunt/`.
- The repo templates use `{{PLACEHOLDERS}}` for `/ada:onboard` to fill: `PROJECT_NAME`,
  `PACKAGE`, `NAMESPACE`, `TEXT_DOMAIN`, `TIMEZONE`, `CODE_DIR`, `PROD_SSH`, `PROD_PREFIX` and
  `PROD_LOGS`.

Done in 0.0.4:
- `/ada:onboard`: checks the layout, gathers the facts, drafts the knowledge from the code with
  *(unverified)* marks, and hands over for the developer to correct.
- `bin/ada-onboard`:
  - `files` copies the templates, never overwriting, and fills the placeholders;
  - `gitignore` adds the team's ignore lines;
  - `settings` merges the plugin, the marketplace, the permissions and the SSH denies into
    `.claude/settings.json`, keeping what's there;
  - `check` lists what's missing or still a draft.
- Skeleton templates for `CLAUDE.md`, `SYSTEM.md`, `API.md`, `STANDARDS.md` (S100 onwards),
  `TEST-AREAS.md`, `tests/README.md` and `tools/bughunt/README.md`.

Still to do:
1. ~~`/ada:bughunt` and `bin/prod-wp`~~ (done in 0.0.3).
2. ~~Write `/ada:onboard`~~ (done in 0.0.4, see below). It was to:
   - write the project's `.claude/settings.json`: enable the plugin, allow `Bash(prod-wp *)` and
     `Bash(ada-handoff-init*)`, and deny direct SSH;
   - copy `templates/repo/`;
   - draft `PROJECT.md`, `SYSTEM.md`, `API.md` and `TEST-AREAS.md` for the developer to correct.
3. ~~Smoke test~~ (done for 0.1.0). `/ada:onboard` and `/ada:begin` ran on a scratch clone with
   `--plugin-dir`. The one failure was Claude Code fetching the `pie` marketplace that onboarding
   writes into `.claude/settings.json`, before the repo existed. Publishing fixes it.
4. **Convert V&Me** in a PR to its holding branch:
   - Add `PROJECT.md` and enable the plugin.
   - Keep only its S rules (S14, S22, and the V&Me parts of S5, S6, S13, S15 and S25) with a note
     that S1–S29 otherwise map to P1–P29.
   - Remove the local copies of the agents and skills.

## Licence

MIT. The role structure is adapted from Three Man Team (MIT, Russell Aaron); both notices are in
`LICENSE`.
