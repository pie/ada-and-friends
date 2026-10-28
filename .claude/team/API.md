# What to call for what

The scripts, skills and agents that already exist. Use these before writing anything new (P6).
Drafted by `/ada:onboard`; *(unverified)* marks entries nobody has checked yet. Line numbers are
as of 0.6.0; paths are in `plugins/ada/`.

Every script: run from inside a git repo (it `cd`s to the root), exits **2** on a usage or
precondition error with `<script>: <message>` on stderr (`die`), and needs Python 3.8+ as
`python3`, `python` or `py -3`. Each script's header comment is its usage text; keep it current.

## Scripts (`bin/`)

### `ada-brief` (`bin/ada-brief:1-94`), needs `gh` logged in (`overlap` and `compare` run without it)

| Subcommand | Does | Output others rely on |
|---|---|---|
| `who` | Approval policy for this developer from `PROJECT.md` | One line: `not-needed` \| `approver <login>` \| `trusted <login>` \| `required <login>` (`:583-598`). Read by `/ada:begin` (`skills/begin/SKILL.md:197-209`), `ada-plan status` (`bin/ada-plan:236`), `ada-onboard check` (`bin/ada-onboard:243`). |
| `submit <task-id> <base> [<branch>]` | Commits the brief (and plan) to `.claude/briefs/`, pushes, opens or updates the draft PR. On an existing PR with an approver's label it compares the approved version with the new brief: `same` keeps the label (the body is replaced only if it differs); `allowed` keeps the label, replaces the body and posts one PR comment listing the added Amendments rows, unless the body already shows this brief (a repeated submit changes nothing); `reapprove`, or no approval, removes `brief-approved`. A unit's draft brief and `PLAN.md` come from the main checkout's `.claude/handoff/plan/`, wherever it runs | `opened   <url>`, `updated  <url> (approval kept)` or `updated  <url> (approval reset)` (`:666-685`). Read by `/ada:begin` (Amending an approved brief). |
| `status [<branch>]` | Approval state of the branch's PR | **First line**: `approved @<login> <ISO time>` \| `changes-requested` \| `waiting` \| `not-submitted` \| `not-needed`. After `approved`, a second line `amended since approval; no re-approval needed` when the label is older than the brief's last change and `compare` says `same` or `allowed`. A `waiting` or `changes-requested` note for an earlier approval ends `: <reason>` from `compare`. Then notes, `--- @<login>, <where>, <time>` blocks with the approvers' words, and the PR URL last (`:688-751`). First line read by `submit` (`:617-623`), `ada-plan status` (`bin/ada-plan:260`) and `/ada:begin`; the second line by `/ada:begin` and `/ada:pickup`. |
| `compare <approved-brief> <current-brief>` | Offline (paths relative to the repo root): whether an amended brief keeps its approval. Approved sections: Decisions, and Constraints less `- Out of scope:`. Added rows: Amendments data rows in the current brief not in the approved one, as text | **First line**: `same` (ignoring trailing whitespace, CR included, and blank lines at the end) \| `reapprove <reason>` \| `allowed`. The reason is the first added row's `needed: <trigger>` cell, `amendment with no Agreed by`, or `amendment with no re-approval decision` (an empty or other cell, or a four-column row); else, with no added row and an approved section changed, `approved section changed with no amendment recorded`. After `allowed`, the added rows as `\| … \|` lines. Exit 0 for all three, 2 on usage or an unreadable file (`:753-776`; rules in `compare()`, `:268-288`). Used by `status` and `submit`. |
| `body <task-id> [<branch>]` | Prints the draft PR description for a committed brief | Markdown starting `<!-- ada-brief: .claude/briefs/<task>.md -->` (`:428`) |
| `ready <title> <file>` | Final title and description, out of draft | `ready    <url>`; a stderr note if `gh pr ready` fails (`:796-797`) |
| `overlap [<file>...]`, `overlap --gate` | Fetches `Branch from` and `PR into` (`PROJECT.md`, defaults `production` and `next-release`); lists the work waiting to ship and the open PRs into either branch; marks entries that change a given file. `--gate` takes no files: it uses the branch's own, the working tree's changes since `git merge-base origin/<PR into> HEAD` plus untracked files, and exits 2 if `git merge-base` fails (a shallow clone) | **First line**: `touching <n>` (`touching 0` with `--gate` and no changed files), or `touching -` with no files and no `--gate`. Then `waiting to ship:` and `open:`, each entry two-space indented: `#N <subject> [<files>]` or `<short hash> <subject> [<files>]`; `#N [draft ]<title> [<files>]`. A marked entry ends ` touches: <file>, ...`; an empty group is `  none`. The open group is `  unknown (<reason>)`, exit 0, without `gh`, and with `more than 100 open PRs into <base>` or `#N has 100 or more files`. Read by `/ada:begin` (brief step, and `--gate` at the PR gate). |

Env: `ADA_BRIEF_REQUIRE=1` makes `who` print `required` for everyone (`:13-14`).

### `ada-onboard` (`bin/ada-onboard:1-31`)

| Subcommand | Does | Output |
|---|---|---|
| `files KEY=value...` | Copies `templates/repo/`, never overwriting; fills `{{KEY}}` (keys at `:85`); records hashes | `created  <path>` (with `(still to fill: …)`) or `kept     <path>`; `Placeholders left: …` (`:102-116`) |
| `refresh [--force \| --decline]` | Brings `.github/pull_request_template.md` up to the plugin's | One line per template, word padded to 9: `current`, `refreshed`, `declined`, `edited` or `unknown`; the last two followed by a unified diff (`:135-150`). Read by `/ada:begin` (`skills/begin/SKILL.md:62-67`). |
| `gitignore [--list]` | Adds the team's ignore lines, `.claude/worktrees/` among them. `--list` prints the lines and changes nothing | `kept` / `added` per line; with `--list`, one line each. `--list` is read by `ada-worktree new` |
| `settings` | Merges plugin, marketplace, permissions, attribution off, `promptCacheTtl` into `.claude/settings.json`. Allows `ada-worktree check`, `list` and `main`; `new` and `remove` still ask | The resulting file (`:219`) |
| `check` | What's missing or unfilled | `missing`, `fill in`, `draft`, `ok`, `error` per item (`:222-243`) |

### `ada-plan` (`bin/ada-plan:1-26`)

The plan is the main checkout's `.claude/handoff/plan/` (`ada-worktree main`), wherever it runs:
from a unit's worktree, every subcommand reads and writes the main checkout's, and the worktree
has no `plan/`.

| Subcommand | Does | Output |
|---|---|---|
| `init` | Copies `templates/plan/PLAN.md` to `.claude/handoff/plan/PLAN.md` | `created  …`; exit 2 if it exists |
| `check` | Validates the Units table | `plan <file>: N units`, then a line per unit (`:183-187`); exit 2 with `the plan has problems:` list (`:168-169`) |
| `briefs` | Drafts `.claude/handoff/plan/<task>.md` per unit from `templates/handoff/BRIEF.md` | `created` / `kept` |
| `branches` | Creates missing unit branches locally (no push) | `created <b> from <start>` / `kept <b>` |
| `status` | Per unit: PR, brief state, shipped, next action | Three lines per unit: `<id> <kind> <#pr> <state> brief: <state>`, `next: …`, branch (`:293-295`) |

### `ada-size <base-branch>` (`bin/ada-size:1-25`)

Measures the branch against `origin/<base>` (else `<base>`), working tree and untracked files
included, via a throwaway index. Prints the breakdown by kind (code, comments, moved, tests, docs,
generated), code files biggest first, and last `Review: about <N> minutes (<small|medium|large|too
big for one sitting>)` (`:177`), which goes in the PR. Rates at `:76-86`.

### `ada-handoff-init` (`bin/ada-handoff-init:1-16`)

No args: copies missing `templates/handoff/*.md` into `.claude/handoff/` (`created` / `kept`),
`.claude/handoff/DESIGN.md` among them. It never copies `templates/DESIGN.md`, the template for a
project's root `DESIGN.md`.
`--archive <name>`: moves `.claude/handoff/*.md` to `archive/<name>/` then copies fresh ones; the
plan stays. Both work on the current checkout. `--archive-plan <name>`: moves the main checkout's
`.claude/handoff/plan/` to its `archive/<name>/plan/`, from any worktree.

### `ada-worktree` (`bin/ada-worktree:1-46`)

One worktree per step, at `.claude/worktrees/<slug>` in the main checkout (`<slug>`: the branch
less its `<type>/`). No Python of its own; `new` calls `ada-onboard`, which needs it.

| Subcommand | Does | Output others rely on |
|---|---|---|
| `new <branch> [<base>]` | With `<base>`: creates `<branch>` (no upstream) in a new worktree. Without: checks out an existing `<branch>` that isn't checked out anywhere (a plan's unit). Copies the file lines of `ada-onboard gitignore --list` that exist here, runs `ada-handoff-init` in it. Exit 2, nothing changed, if the path exists, the main checkout's git doesn't ignore it (the message says to run `ada-onboard gitignore`), the branch exists (with `<base>`) or doesn't (without), or the base doesn't resolve | `copied   <file>` and `created  …` lines, then the worktree's **absolute path on the last line**. Read by `/ada:begin` and `ada:planning`, which pass it to `EnterWorktree` |
| `check` | Compares `CHECKPOINT.md`'s `Branch:` with the current branch | **One line**: `ok`, `none` (no checkpoint, or no branch in it) or `other <branch> <path>`, where `<path>` is where `<branch>` is checked out (the rest of the line; may hold spaces) or `-`. **Exit 2**, nothing on stdout, when the first word of `Branch:` isn't a branch name or names no local branch (a checkpoint from before `Branch:` held the name alone). Read by `/ada:begin` (Session start, 6) and `/ada:pickup` |
| `list` | Every worktree, the main checkout first | One tab-separated line each: `<path>`, `<branch>` or `-`, the checkpoint's `Step:` or `-`, the PR: `#N OPEN`, `#N MERGED`, `#N CLOSED`, `none`, or `-` without `gh` (if `gh` fails, one note on stderr, and that line and the rest are `-`). Read by `/ada:begin` (Session start, 7) |
| `remove <path>` | Copies the worktree's `.claude/handoff/*.md` to the main checkout's `.claude/handoff/archive/<YYYY-MM-DD>-<slug>/`, then `git worktree remove`, which deletes the originals with the worktree; if that fails, deletes the copies and the archive folder. Never deletes the branch. Exit 2, nothing changed, for uncommitted changes or untracked files, anything but `*.md` in its handoff, an existing archive folder, the main checkout, the worktree it runs in, a locked worktree, or a path that isn't a worktree | `archived N file(s) to …` and `removed  <path>`, both after the worktree is gone |
| `main` | | The main checkout's path (the first entry of `git worktree list --porcelain`). Read by `ada-plan`, `ada-brief submit`, `ada-handoff-init --archive-plan` and the skills |

### `prod-wp` (`bin/prod-wp:1-26`)

Read-only production access for `/ada:bughunt` in WordPress projects: `config`, `sql`, `inspect`,
`notes`, `option`, `actions`, `logs`. Not used in this repo (no production).

### `presstest` (`bin/presstest:1-46`)

Runs the project's PressTest suite on the developer's local site or the project's staging site:
`config`, `list`, `run [--env local|staging] [-b <browser>] [<file>_test.js ...]`. `run` prints
`presstest: <env>, <url>` before the tests and ends with `presstest: passed <n>` or
`presstest: failed <n> of <total>` (exit 1), or `presstest: failed, no report written` (exit 1);
settings, usage and Docker errors exit 2. Tess reads the last line (`agents/tess.md`, Run mode).
Not used in this repo (no suite).

### Release (`.github/`)

`release-bump.py <version> <notes-file>` (`.github/scripts/release-bump.py:1-13`): sets
`plugin.json`'s version, moves CHANGELOG Unreleased under the version, writes the notes; exits
non-zero changing nothing if Unreleased is empty. Run by `release.yml` only.

## Skills (`skills/`)

| Name | Invoked as | For |
|---|---|---|
| `begin` | `/ada:begin` | Ada's playbook: session start, brief, approval, roles, PR gate |
| `pickup` | `/ada:pickup` | Resume from `CHECKPOINT.md` |
| `bughunt` | `/ada:bughunt` | Grace's read-only investigation; writes `FINDINGS.md` |
| `design` | `/ada:design` | Usually loaded by Ada from `begin` (or `planning`) when the design test passes. Iris designs on a Design artifact (Artifact `quickstart`, intent `design`) in the main session; writes `.claude/handoff/DESIGN.md`, or in a plan only `plan/DESIGN-<task id>.md`, and creates or updates the project's root `DESIGN.md` (linted with the version pinned in the skill) |
| `onboard` | `/ada:onboard` | Set a repo up; drafts the knowledge files |
| `planning` | loaded by Ada | Work too big for one PR |
| `standards` | preloaded (Bob, Claudia) | P rules except P29 and P32-P39 |
| `standards-tests` | preloaded (all three) | P29 |
| `standards-accessibility` | preloaded (Bob, Claudia); Iris loads it first | P32-P39, WCAG 2.2 AA visual rules |
| `efficiency` | preloaded; Ada loads it | Context and token discipline |

## Agents (`agents/`)

| Spawn as | Role | Tools |
|---|---|---|
| `ada:bob` | Builds from the brief | Read, Edit, Write, Bash, Grep, Glob (`agents/bob.md:6`) |
| `ada:claudia` | Reviews; writes `REVIEW-FEEDBACK.md` | Read, Write, Bash, Grep, Glob (`agents/claudia.md:6`) |
| `ada:tess` | Test plan, tests under `tests/`, test report | Read, Write, Bash, Grep, Glob, ToolSearch, `mcp__claude-in-chrome__*` (`agents/tess.md:6`) |

## Logging

Sources are listed in `STANDARDS.md`. The scripts don't log: they print results on stdout and
errors on stderr.

## Never

- Change the first-line words of `ada-brief status` or `who`, or the `refresh` status words,
  without changing every reader (`/ada:begin`, `/ada:pickup`, `ada-plan`, `ada-onboard check`).
- Edit a PR with `gh pr edit`: it fails before gh 2.50 (`bin/ada-brief:89-90`). Use `gh api`.
- Use `mapfile`/`readarray`, `declare -A`, `${var,,}` or other bash 4+ features in `bin/`
  (macOS ships bash 3.2; `CHANGELOG.md:191-192`).
- Call `python3` directly in `bin/`: use the `PY` discovery block, and strip `\r` from its output.
- Write files without `newline='\n'` from Python, or with CRLF.
- Rename brief or plan headings, field lines (`Plan:`, `Ships:`, `Asana:`, `Epic:`) or the Units
  table columns in `templates/` without changing the parsers in `ada-brief` and `ada-plan`.
- Overwrite a file in a project from `ada-onboard`, except the refresh list with an untouched copy.
- Push to `next-release` or `main` directly, or change `plugin.json`'s version by hand (P31).
- Have a script read plugin files at runtime other than through `$(dirname "$0")/../templates`.
- Change `ada-worktree check`'s first-line words or `new`'s last line without changing
  `/ada:begin`, `/ada:pickup` and `ada:planning`.
- Remove a step's worktree other than with `ada-worktree remove`, which archives its handoff
  files first; or delete its branch with it.
