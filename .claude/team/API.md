# What to call for what

The scripts, skills and agents that already exist. Use these before writing anything new (P6).
Drafted by `/ada:onboard`; *(unverified)* marks entries nobody has checked yet. Line numbers are
as of 0.6.0; paths are in `plugins/ada/`.

Every script: run from inside a git repo (it `cd`s to the root), exits **2** on a usage or
precondition error with `<script>: <message>` on stderr (`die`), and needs Python 3.8+ as
`python3`, `python` or `py -3`. Each script's header comment is its usage text; keep it current.

## Scripts (`bin/`)

### `ada-brief` (`bin/ada-brief:1-41`), needs `gh` logged in

| Subcommand | Does | Output others rely on |
|---|---|---|
| `who` | Approval policy for this developer from `PROJECT.md` | One line: `not-needed` \| `approver <login>` \| `trusted <login>` \| `required <login>` (`:350-365`). Read by `/ada:begin` (`skills/begin/SKILL.md:129-141`), `ada-plan status` (`bin/ada-plan:233`), `ada-onboard check` (`bin/ada-onboard:225`). |
| `submit <task-id> <base> [<branch>]` | Commits the brief (and plan) to `.claude/briefs/`, pushes, opens or updates the draft PR, removes `brief-approved` | `opened   <url>` or `updated  <url> (approval reset)` (`:428`, `:432`) |
| `status [<branch>]` | Approval state of the branch's PR | **First line**: `approved @<login> <ISO time>` \| `changes-requested` \| `waiting` \| `not-submitted` \| `not-needed`; then notes, `--- @<login>, <where>, <time>` blocks with the approvers' words, and the PR URL last (`:443-499`). First line read by `submit` (`:383-387`), `ada-plan status` (`bin/ada-plan:257`) and `/ada:begin` (`skills/begin/SKILL.md:145-153`). |
| `body <task-id> [<branch>]` | Prints the draft PR description for a committed brief | Markdown starting `<!-- ada-brief: .claude/briefs/<task>.md -->` (`:234`) |
| `ready <title> <file>` | Final title and description, out of draft | `ready    <url>`; a stderr note if `gh pr ready` fails (`:521-522`) |

Env: `ADA_BRIEF_REQUIRE=1` makes `who` print `required` for everyone (`:12-13`).

### `ada-onboard` (`bin/ada-onboard:1-28`)

| Subcommand | Does | Output |
|---|---|---|
| `files KEY=value...` | Copies `templates/repo/`, never overwriting; fills `{{KEY}}` (keys at `:82`); records hashes | `created  <path>` (with `(still to fill: …)`) or `kept     <path>`; `Placeholders left: …` (`:99-113`) |
| `refresh [--force \| --decline]` | Brings `.github/pull_request_template.md` up to the plugin's | One line per template, word padded to 9: `current`, `refreshed`, `declined`, `edited` or `unknown`; the last two followed by a unified diff (`:132-147`). Read by `/ada:begin` (`skills/begin/SKILL.md:58-63`). |
| `gitignore` | Adds the team's ignore lines | `kept` / `added` per line (`:152-157`) |
| `settings` | Merges plugin, marketplace, permissions, attribution off, `promptCacheTtl` into `.claude/settings.json` | The resulting file (`:203`) |
| `check` | What's missing or unfilled | `missing`, `fill in`, `draft`, `ok`, `error` per item (`:206-227`) |

### `ada-plan` (`bin/ada-plan:1-24`)

| Subcommand | Does | Output |
|---|---|---|
| `init` | Copies `templates/plan/PLAN.md` to `.claude/handoff/plan/PLAN.md` | `created  …`; exit 2 if it exists |
| `check` | Validates the Units table | `plan <file>: N units`, then a line per unit (`:180-184`); exit 2 with `the plan has problems:` list (`:165-166`) |
| `briefs` | Drafts `.claude/handoff/plan/<task>.md` per unit from `templates/handoff/BRIEF.md` | `created` / `kept` |
| `branches` | Creates missing unit branches locally (no push) | `created <b> from <start>` / `kept <b>` |
| `status` | Per unit: PR, brief state, shipped, next action | Three lines per unit: `<id> <kind> <#pr> <state> brief: <state>`, `next: …`, branch (`:290-292`) |

### `ada-size <base-branch>` (`bin/ada-size:1-25`)

Measures the branch against `origin/<base>` (else `<base>`), working tree and untracked files
included, via a throwaway index. Prints the breakdown by kind (code, comments, moved, tests, docs,
generated), code files biggest first, and last `Review: about <N> minutes (<small|medium|large|too
big for one sitting>)` (`:177`), which goes in the PR. Rates at `:76-86`.

### `ada-handoff-init` (`bin/ada-handoff-init:1-13`)

No args: copies missing `templates/handoff/*.md` into `.claude/handoff/` (`created` / `kept`).
`--archive <name>`: moves `.claude/handoff/*.md` to `archive/<name>/` then copies fresh ones; the
plan stays. `--archive-plan <name>`: moves `.claude/handoff/plan/` to `archive/<name>/plan/`.

### `prod-wp` (`bin/prod-wp:1-26`)

Read-only production access for `/ada:bughunt` in WordPress projects: `config`, `sql`, `inspect`,
`notes`, `option`, `actions`, `logs`. Not used in this repo (no production).

### Release (`.github/`)

`release-bump.py <version> <notes-file>` (`.github/scripts/release-bump.py:1-13`): sets
`plugin.json`'s version, moves CHANGELOG Unreleased under the version, writes the notes; exits
non-zero changing nothing if Unreleased is empty. Run by `release.yml` only.

## Skills (`skills/`)

| Name | Invoked as | For |
|---|---|---|
| `begin` | `/ada:begin` | Ada's playbook: session start, brief, approval, roles, PR gate |
| `pickup` | `/ada:pickup` | Resume from `CHECKPOINT.md` |
| `bughunt` | `/ada:bughunt` | Read-only investigation; writes `FINDINGS.md` |
| `onboard` | `/ada:onboard` | Set a repo up; drafts the knowledge files |
| `planning` | loaded by Ada | Work too big for one PR |
| `standards` | preloaded (Bob, Claudia) | P rules except P29 |
| `standards-tests` | preloaded (all three) | P29 |
| `efficiency` | preloaded; Ada loads it | Context and token discipline |

## Agents (`agents/`)

| Spawn as | Role | Tools |
|---|---|---|
| `ada:bob` | Builds from the brief | Read, Edit, Write, Bash, Grep, Glob (`agents/bob.md:7`) |
| `ada:claudia` | Reviews; writes `REVIEW-FEEDBACK.md` | Read, Write, Bash, Grep, Glob (`agents/claudia.md:7`) |
| `ada:tess` | Test plan, tests under `tests/`, test report | Read, Write, Bash, Grep, Glob (`agents/tess.md:7`) |

## Logging

Sources are listed in `STANDARDS.md`. The scripts don't log: they print results on stdout and
errors on stderr.

## Never

- Change the first-line words of `ada-brief status` or `who`, or the `refresh` status words,
  without changing every reader (`/ada:begin`, `/ada:pickup`, `ada-plan`, `ada-onboard check`).
- Edit a PR with `gh pr edit`: it fails before gh 2.50 (`bin/ada-brief:36-37`). Use `gh api`.
- Use `mapfile`/`readarray`, `declare -A`, `${var,,}` or other bash 4+ features in `bin/`
  (macOS ships bash 3.2; `CHANGELOG.md:185-186`).
- Call `python3` directly in `bin/`: use the `PY` discovery block, and strip `\r` from its output.
- Write files without `newline='\n'` from Python, or with CRLF.
- Rename brief or plan headings, field lines (`Plan:`, `Ships:`, `Asana:`, `Epic:`) or the Units
  table columns in `templates/` without changing the parsers in `ada-brief` and `ada-plan`.
- Overwrite a file in a project from `ada-onboard`, except the refresh list with an untouched copy.
- Push to `next-release` or `main` directly, or change `plugin.json`'s version by hand (P31).
- Have a script read plugin files at runtime other than through `$(dirname "$0")/../templates`.
