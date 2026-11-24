# What to call for what

The scripts, skills and agents that already exist. Use these before writing anything new (P6).
Drafted by `/ada:onboard`; *(unverified)* marks entries nobody has checked yet. Line numbers are
as of 0.6.0 (`ada-brief`'s as of brief approval by area); paths are in `plugins/ada/`.

Every script: run from inside a git repo (it `cd`s to the root), exits **2** on a usage or
precondition error with `<script>: <message>` on stderr (`die`), and needs Python 3.8+ as
`python3`, `python` or `py -3`. Each script's header comment is its usage text; keep it current.

## Scripts (`bin/`)

### `ada-brief` (`bin/ada-brief:1-195`), needs `gh` logged in (`overlap`, `compare` and `areas` run without it)

| Subcommand | Does | Output others rely on |
|---|---|---|
| `who [<area>...]` | Approval policy for this developer from `PROJECT.md`. With no `<area>`, from the Approvers and Trusted lines only. With `<area>`s (slugs `areas` lists), for a brief touching those areas: Trusted approves every area; `not-needed` needs no `gh` | **First line**: `not-needed` \| `approver <login>` \| `trusted <login>` \| `required <login>` (`:1232-1256`). With `<area>`s, in this order: `not-needed` when no named area has an approver, `required` under `ADA_BRIEF_REQUIRE=1`, `approver` when the login approves every named area, `trusted`, else `required`; after `required`, one `needs <slug>: @a, @b` line per named area with approvers the login isn't one of (`area_report`, `:1155-1207`). An area not in effect exits 2 with `ada-brief: unknown area <x>; ada-brief areas lists them`. A bad areas table exits 2, with or without `<area>`. First line (no `<area>`) read by `/ada:begin` (`skills/begin/approval.md`), `ada-onboard check` (`bin/ada-onboard:351`), and `ada-plan status` (`bin/ada-plan:277-282`) for a unit with no `Areas:` line. With `<area>`s, read by `ada-plan status` (`bin/ada-plan:277-282`), once for each distinct set of a unit's areas (the slugs sorted, so the order on the `Areas:` line doesn't matter; only its first word is used, and `required` means the unit's brief needs approval), `/ada:begin` (`skills/begin/approval.md`, Getting the brief approved: the first line and the `needs` lines, whose approvers Ada names; `design-step.md`: when an area needs approval, the artifact goes to the design area's approvers) and `ada:planning` (step 6, each top-level brief's areas). |
| `areas` | Offline, no `gh`: the areas of concern in effect, from the areas table (`\| Area \| Slug \| Approved by \|`) in `PROJECT.md`'s Brief approval section; rules in `table_areas()` (`:516-571`) and `read_areas()` (`:577-588`); a table inside an HTML comment is ignored | One line per area in table order, `<slug>: @a, @b` or `<slug>: none`, the catch-all's ending ` (catch-all)`; then `trusted: @x, @y` or `trusted: none` (`:1171-1176`). No table: `everything: <Approvers> (catch-all)`. No section: `everything: none (catch-all)`, `trusted: none` and `who`'s warning on stderr, exit 0. A bad table: an `ada-brief:` message on stderr, nothing on stdout, exit 2. Read by `/ada:begin` (`skills/begin/approval.md`: no table leaves the brief's `Areas:` prompt; else the slugs Ada writes on `Areas:`; `design-step.md`: the design area's approvers, or the catch-all's) and `/ada:onboard` (checks the table it writes). |
| `submit <task-id> <base> [<branch>]` | Commits the brief (and plan) to `.claude/briefs/`, pushes, opens or updates the draft PR. On an existing PR with an approver's label it compares the approved version with the new brief: `same` keeps the label (the body is replaced only if it differs); `allowed` keeps the label, replaces the body and posts one PR comment listing the added Amendments rows, unless the body already shows this brief (a repeated submit changes nothing); `reapprove`, or no approval, removes `brief-approved`. A unit's draft brief and `PLAN.md` come from the main checkout's `.claude/handoff/plan/`, wherever it runs. On an existing PR, a bad areas table in `PROJECT.md` exits 2 with an `ada-brief:` message after the push, before the PR body or labels change | `opened   <url>`, `updated  <url> (approval kept)` or `updated  <url> (approval reset)` (`:1324-1357`); for a brief with `Areas:`, `updated  <url> (approval reset: <slug>, ...)`, the areas still to approve, when a label counted before the submit. Read by `/ada:begin` (`skills/begin/approval.md`, Getting the brief approved and Amending an approved brief) and `ada:planning` (step 6). |
| `status [<branch>]` | Approval state of the branch's PR. A bad areas table in `PROJECT.md` exits 2 with an `ada-brief:` message and nothing on stdout | **First line**: `approved @<login> <ISO time>` \| `changes-requested` \| `waiting` \| `not-submitted` \| `not-needed`. After `approved`, a second line `amended since approval; no re-approval needed` when the label is older than the brief's last change and `compare` says `same` or `allowed`. A `waiting` or `changes-requested` note for an earlier approval ends `: <reason>` from `compare`. Then notes, `--- @<login>, <where>, <time>` blocks with the approvers' words, and the PR URL last (`:1361-1520`). With no Brief approval section, or `Approvers: none` and no areas table, `not-needed` for every brief, before any call that reads the PR. **A brief with `Areas:`** (on the pushed brief; even with `Approvers: none` when there is a table) is approved area by area, each by its `approved:<slug>` label last added by one of its Approved by logins or a Trusted login (`brief-approved` counts only as the label of `everything`, the one area with no areas table). First line: `changes-requested` if any area is, else `waiting` if any is, else `approved @<login> <time>` from the latest counting label, or `not-needed` when every area needs none; the `amended …` line only under `approved`, when an area's label predates the brief's last change and `compare` doesn't send that area back. Then one line per area, in `Areas:` order, the catch-all last when an approved section changed with no row and `Areas:` doesn't name it: `area <slug>: approved @<login> <time>` \| `waiting (@a, @b)` (Approved by only) \| `changes-requested` \| `not needed (@<author> approves it)` (the PR's opener, named on `Author:`, counts for it; not under `ADA_BRIEF_REQUIRE=1`) \| `not needed (no approvers)`. A `waiting` line ends `; Author: @<x> is not the PR's opener @<y>, so it isn't exempt` or `; no Author: line` when the opener counts for the area. Notes: `the approved:<slug> label was added by @<login>, who doesn't approve <slug>, so it does not count`; `@<login> approved an earlier version of <slug>; the brief has changed since: <reason>`. An `Areas:` slug the project doesn't list exits 2 with `ada-brief: the brief names area <x>, which PROJECT.md doesn't list`, nothing on stdout (`area_state()`, `:680-722`; the area path, `:1436-1480`). First line read by `submit`, for a part's base (`:1280-1286`), `ada-plan status` (`bin/ada-plan:312-313`) and `/ada:begin`; the second line by `/ada:begin` and `/ada:pickup`; the `area <slug>: waiting` and `area <slug>: changes-requested` lines, when the first line is that state, by `ada-plan status` (`bin/ada-plan:314-315`); the `area` lines by `/ada:begin` (`skills/begin/approval.md`: which areas wait and on whom, the `Approval:` record, a wrong `Author:`) and `/ada:pickup` (step 4). |
| `compare <approved-brief> <current-brief>` | Offline (paths relative to the repo root): whether an amended brief keeps its approval. Approved sections: Decisions, and Constraints less `- Out of scope:`. Added rows: Amendments data rows in the current brief not in the approved one, as text | **First line**: `same` (ignoring trailing whitespace, CR included, and blank lines at the end) \| `reapprove <reason>` \| `allowed`. The reason is the first added row's `needed: <trigger>` cell, `amendment with no Agreed by`, or `amendment with no re-approval decision` (an empty or other cell, or a four-column row); else, with no added row and an approved section changed, `approved section changed with no amendment recorded`. After `allowed`, the added rows as `\| … \|` lines. After `reapprove`, when `<current-brief>` has an `Areas:` line (not empty, not the template's `<…>` prompt), a second line `areas: <slug>, …`: the areas to approve again, in `Areas:` order, from the added rows' `needed: <slug>, …` (all of them when a row's slugs aren't all on `Areas:`, or it has no Agreed by or no re-approval decision), or `areas: catch-all` for an approved section changed with no added row. Exit 0 for all three, 2 on usage or an unreadable file (`:1522-1549`; rules in `compare()`, `:370-391`). Used by `status` and `submit`. |
| `body <task-id> [<branch>]` | Prints the draft PR description for a committed brief | Markdown starting `<!-- ada-brief: .claude/briefs/<task>.md -->` (`:892`) |
| `ready <title> <file>` | Final title and description, out of draft | `ready    <url>`; a stderr note if `gh pr ready` fails (`:1569-1570`) |
| `overlap [<file>...]`, `overlap --gate` | Fetches `Branch from` and `PR into` (`PROJECT.md`; `PR into` defaults to `next-release`, a missing `Branch from` line exits 2 with `ada-brief: .claude/team/PROJECT.md has no **Branch from:** line; add one under Code and branches`); lists the work waiting to ship and the open PRs into either branch; marks entries that change a given file. `--gate` takes no files: it uses the branch's own, the working tree's changes since `git merge-base origin/<PR into> HEAD` plus untracked files, and exits 2 if `git merge-base` fails (a shallow clone) | **First line**: `touching <n>` (`touching 0` with `--gate` and no changed files), or `touching -` with no files and no `--gate`. Then `waiting to ship:` and `open:`, each entry two-space indented: `#N <subject> [<files>]` or `<short hash> <subject> [<files>]`; `#N [draft ]<title> [<files>]`. A marked entry ends ` touches: <file>, ...`; an empty group is `  none`. The open group is `  unknown (<reason>)`, exit 0, without `gh`, and with `more than 100 open PRs into <base>` or `#N has 100 or more files`. Read by `/ada:begin` (brief step, and `--gate` at the PR gate). |

Env: `ADA_BRIEF_REQUIRE=1` makes `who` print `required` for everyone (`:25-26`).

### `ada-onboard` (`bin/ada-onboard:1-60`)

| Subcommand | Does | Output |
|---|---|---|
| `files KEY=value...` | Copies `templates/repo/`, never overwriting; fills `{{KEY}}` (keys at `:115`); records hashes. A template with a `.template-hashes` line but no file isn't copied: its line is recorded as `removed` | `created  <path>` (with `(still to fill: …)`), `kept     <path>` or `removed  <path>`; `Placeholders left: …` (`:134-154`) |
| `kb [<path>]` | For a product: copies `templates/knowledge-base/` (README.md, INDEX.md, config.json, issues/.gitkeep) into `<path>`, default `knowledge-base`, never overwriting; no hashes, no placeholders filled. `<path>` is relative to the repo root, no part starting with a dot; anything else, two arguments, a part of `<path>` that is a file, or one that is a symlink to a folder outside the repo exits 2 with nothing written (`:157-194`). Run by `/ada:onboard` (`skills/onboard/SKILL.md`, 5) | `created  <path>/<file>` or `kept     <path>/<file>` per file (`:187`, `:192`) |
| `refresh [--force \| --decline]` | Brings `.github/pull_request_template.md` up to the plugin's. A missing one with a `.template-hashes` line is `removed`: not written, its line recorded as `removed`; only `--force` writes it | One line per template, word padded to 9: `current`, `refreshed`, `removed`, `declined`, `edited` or `unknown`; the last two followed by a unified diff (`:211-233`). Read by `/ada:begin` (`skills/begin/SKILL.md`, Session start, 10). |
| `gitignore [--list]` | Adds the team's ignore lines, `.claude/worktrees/` among them. `--list` prints the lines and changes nothing | `kept` / `added` per line; with `--list`, one line each. `--list` is read by `ada-worktree new` |
| `settings` | Merges plugin, marketplace, permissions, attribution off, `promptCacheTtl` into `.claude/settings.json`. Allows `ada-worktree check`, `list` and `main`, and `ada-clock status`, `set *` and `reset`; `ada-worktree new` and `remove`, and `ada-clock install`, `uninstall`, `wp` and `extension`, still ask | The resulting file (`:319`) |
| `check` | What's missing or unfilled. When `PROJECT.md`'s `Kind:` reads `product` and `Knowledge base:` gives a path other than `none`, also the knowledge-base folder and its `config.json`; nothing more for `site`, no Kind line, or a `<prompt>` | `missing`, `fill in`, `draft`, `ok`, `error` per item (`:323-383`). Knowledge base: `missing  <path>/ (run: ada-onboard kb <path>)`, `missing  <path>/config.json (…)` or `fill in  <path>/config.json: <placeholders>`; no line once it's filled (`:369-382`) |
| `clock LOCAL_WP_PATH=<path> LOCAL_WP=<command>` | Picks the clock setup (`local-linux` on Linux when `~/.config/Local` is a folder, else `manual`) and appends `ADA_CLOCK_ADAPTER`, `LOCAL_WP_PATH` and `LOCAL_WP` to `.claude/team/local.env` when `ada-clock`'s parser reads them as empty; never changes a line already there. Exit 2, nothing written, for a missing, repeated or empty argument, any other key, or a value with a newline, a `#` or the `<…>` form. Run by `/ada:onboard` (`skills/onboard/SKILL.md`, 6) | Per key, word padded to 9: `added    KEY=value` or `kept     KEY`; then `setup: <manual\|local-linux>`, the value `local.env` now gives (`:364-433`) |

### `ada-plan` (`bin/ada-plan:1-34`)

The plan is the main checkout's `.claude/handoff/plan/` (`ada-worktree main`), wherever it runs:
from a unit's worktree, every subcommand reads and writes the main checkout's, and the worktree
has no `plan/`.

Every subcommand but `init` needs `PROJECT.md` to have a `**Branch from:**` line: without it, exit 2
and `ada-plan: .claude/team/PROJECT.md has no **Branch from:** line; add one under Code and
branches`, before anything is fetched (`:75-91`). `PR into` falls back to `next-release`.

| Subcommand | Does | Output |
|---|---|---|
| `init` | Copies `templates/plan/PLAN.md` to `.claude/handoff/plan/PLAN.md` | `created  …`; exit 2 if it exists |
| `check` | Validates the Units table | `plan <file>: N units`, then a line per unit (`:219-223`); exit 2 with `the plan has problems:` list (`:183-184`) |
| `briefs` | Drafts `.claude/handoff/plan/<task>.md` per unit from `templates/handoff/BRIEF.md` | `created` / `kept` |
| `branches` | Creates missing unit branches locally (no push) | `created <b> from <start>` / `kept <b>` |
| `status` | Per unit: PR, brief approval, shipped, next action. A unit's areas are the slugs on the `Areas:` line of its draft `plan/<task>.md` (`unit_areas()`, `:198-217`; the header only, comments left out, lowercased, no repeats); none when the draft or the line is missing, has no slug, or starts `<`. `ada-brief who <areas>` is run once for each distinct set (`:274-283`). A unit with areas has `ada-brief status <branch>` asked for its open PR even when `who` says `not-needed`, because `status` knows the brief's `Author:`; a unit without areas has it asked only when `who` says `required`, else `not-needed` (`:309-317`). With no PR, `not-submitted` when `who` says `required`, else `not-needed` (`:297`). A merged PR is `approved`, a closed one `-` | Three lines per unit: `<id> <kind> <#pr> <state> brief: <state>`, `next: …`, branch (`:351-354`). When the brief is `waiting` or `changes-requested`, `brief:` ends ` (<slug>, <slug>)`: the slugs of the `area <slug>: <state>` lines `ada-brief status` printed (`:314-315`, `:351`). `next:` for `waiting` is `waiting for the brief to be approved (<slug>, <slug>)` (`:336-338`); for `changes-requested` it is unchanged (`:334-335`), without the areas |

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
| `check` | Compares `CHECKPOINT.md`'s `Branch:` with the current branch | **One line**: `ok`, `none` (no checkpoint, or no branch in it) or `other <branch> <path>`, where `<path>` is where `<branch>` is checked out (the rest of the line; may hold spaces) or `-`. **Exit 2**, nothing on stdout, when the first word of `Branch:` isn't a branch name or names no local branch (a checkpoint from before `Branch:` held the name alone). Read by `/ada:begin` (Session start, 6, and its `checkpoint.md`) and `/ada:pickup` |
| `list` | Every worktree, the main checkout first | One tab-separated line each: `<path>`, `<branch>` or `-`, the checkpoint's `Step:` or `-`, the PR: `#N OPEN`, `#N MERGED`, `#N CLOSED`, `none`, or `-` without `gh` (if `gh` fails, one note on stderr, and that line and the rest are `-`). Read by `/ada:begin` (Session start, 7) |
| `remove <path>` | Copies the worktree's `.claude/handoff/*.md` to the main checkout's `.claude/handoff/archive/<YYYY-MM-DD>-<slug>/`, then `git worktree remove`, which deletes the originals with the worktree; if that fails, deletes the copies and the archive folder. Never deletes the branch. Exit 2, nothing changed, for uncommitted changes or untracked files, anything but `*.md` in its handoff, an existing archive folder, the main checkout, the worktree it runs in, a locked worktree, or a path that isn't a worktree | `archived N file(s) to …` and `removed  <path>`, both after the worktree is gone |
| `main` | | The main checkout's path (the first entry of `git worktree list --porcelain`). Read by `ada-plan`, `ada-brief submit`, `ada-handoff-init --archive-plan` and the skills |

### `prod-wp` (`bin/prod-wp:1-39`)

Read-only production access for `/ada:bughunt` in WordPress projects: `config`, `sql`, `inspect`,
`notes`, `option`, `actions`, `logs`. Not used in this repo (no production). Read-only is enforced:
`sql` runs in a `READ ONLY` MySQL session; `inspect` refuses a script whose code calls a name in
`WRITE_CALLS` (`bin/prod-wp:82-86`), then runs it after a guard (`GUARD`, `bin/prod-wp:88-140`)
that makes the session `READ ONLY`, drops any `$wpdb` query that isn't SELECT, SHOW, DESCRIBE or
EXPLAIN, and stops `wp_mail` and outgoing HTTP, printing `prod-wp: blocked …` on stderr for
each. With `PROD_FORBIDDEN` set, a `sql` query on `posts` or `postmeta` must name its post type
or IDs.

### `presstest` (`bin/presstest:1-102`)

Runs the project's PressTest tests (`tests/presstest/`) and its shared suites in the runner image
on the developer's local site or the project's staging site: `config`, `list`,
`run [--env local|staging] [--clock] [-b <browser>] [<file>_test.js ...]`. `run` starts the site's test
session when there's a report token and always ends it (`presstest: ended test session <id>:
<summary>`), prints `presstest: <env>, <url>, <targets>` before the tests and ends with `presstest: passed <n>` or
`presstest: failed <n> of <total>` (exit 1), or `presstest: failed, no report written` (exit 1);
settings, usage and Docker errors exit 2. Tess reads the last line (`agents/tess.md`, Run mode).
Each `PRESSTEST_ENV_<NAME>=value` line in `local.env` is the project's own setting, passed to a
local `run` as `<NAME>` in the container's environment (`-e NAME`, the value exported only into
the `docker run`; never on a command line, never printed; `config` shows `NAME (set)`). Local only:
`--env staging` and the pre-flight check pass none. Any other key starting `PRESSTEST_ENV_` (the
name isn't `[A-Z][A-Z0-9_]*`: a dot, a space, lowercase), or a reserved name, exits 2 naming the key,
before Docker runs; `config` shows them `(invalid name)` and `(reserved)`, never a value. Reserved:
a name `run` or the container uses (`RESERVED` in the script, `EXECIGNORE` among them), anything
starting `PRESSTEST_`, `DOCKER_` or `BASH`, and any name the running bash won't export, tested in a
throwaway subshell before Docker runs (`SHELLOPTS`, `UID`, `EUID`, `PPID`, `GROUPS`, ...). The
environment variable `PRESSTEST_ENV_<NAME>`
overrides a listed name's value. The project's tests must not print the values (they would land in
the report).
`run --clock` (local only; with `--env staging` exit 2) is a clock run: `ada-clock status`
(`bin/ada-clock`, beside it) must exit 0 with first line `ada-clock: real` before anything runs,
else exit 2 with that line. The container gets `PRESSTEST_CLOCK=1` and
`PRESSTEST_CLOCK_DIR=/app/test-results/run/clock` (`.claude/presstest/run/clock/`) and Playwright
`--workers=1`. A background loop on the host answers each `request-<n>.json` there,
`{"action": "set", "when": "YYYY-MM-DD HH:MM"}` or `{"action": "reset"}`, by running `ada-clock`
with an argument array, writes `response-<n>.json` (`{"exit": <code>, "line": "<first line>"}`),
deletes the request and prints `presstest: clock request <n>: ...`; anything else is refused with
exit 2 and `ada-clock` isn't run. When the container exits, and in the EXIT trap, it stops the
loop, runs `ada-clock reset` and `ada-clock status` before ending the test session, and prints
`presstest: ada-clock reset` and the status's first line; if that isn't `ada-clock: real`, the run
exits 1 whatever the tests did. The spec side is the project's (V&Me:
`tests/presstest/shared/clock.js`). With no command or an unknown one, `presstest` prints the
header's command lines.
Not used in this repo (no suite).

### `ada-clock` (`bin/ada-clock:1-106`)

Puts the developer's local WordPress site at a fake time. Settings from `.claude/team/local.env`,
overridable by environment: `LOCAL_URL`, `LOCAL_WP_PATH`, `ADA_CLOCK_ADAPTER` (`manual` or
`local-linux`; unset means `manual`), `ADA_CLOCK_LOCAL_SITE` (local-linux: the Local site ID; unset
means the `sites.json` site whose `<path>/app/public` is `LOCAL_WP_PATH`), `LOCAL_WP` (the WP-CLI
command; unset means `wp --path=<LOCAL_WP_PATH>`). Every subcommand exits 2 unless `LOCAL_URL`'s
host is `localhost`, `127.0.0.1`, or ends in `.local` or `.test` (`is_local_url`), and for any
other adapter; `local-linux` also exits 2 off Linux. Nothing runs `sudo`. What `local-linux`
changes outside the repo, and how to undo it: `SYSTEM.md`, What `ada-clock install` changes
outside the repo.

| Subcommand | Does | Output others rely on |
|---|---|---|
| `set "<when>"` | `<when>`: `YYYY-MM-DD HH:MM[:SS]` in the site's timezone (one `ada-clock wp eval`), or ISO 8601 with `Z` or `±HH:MM` (no WP-CLI; the state keeps that offset as its timezone). Exit 2 for anything else, or a site-local time that doesn't exist there (`parse_when`). Writes the state file, adds it to `.git/info/exclude` when inside the repo. `manual`: prints the manual steps for this OS with the time in the computer's timezone. `local-linux`: exit 2 first when libfaketime is missing (naming `sudo apt install faketime`) or there is no clock file (install not run); writes `timestamp − set_at` to the clock file; prints the reload line and the webhook warning | First line `ada-clock: fake <local> <tz> (<setup>), written to <path>`. `<local>` is `YYYY-MM-DD HH:MM`, with `:SS` only when `<when>` gave seconds, `:00` included: `2026-11-06T11:55:00+00:00` gives `2026-11-06 11:55:00 +00:00`, `2026-11-06T11:55+00:00` gives `2026-11-06 11:55 +00:00`. `local-linux` then `clock file: <path> (<offset>)` and a line starting `Payments:` |
| `status` | Compares the site's time from the endpoint with the expected time: this computer's with no state; the state's timestamp under `manual`; this computer's plus `timestamp − set_at` when the state's setup is `local-linux` (the fake clock runs on). `local-linux` also checks the site's `php-fpm` is the wrapper and the compiled pool file in `~/.config/Local/run/<id>/conf/php/php-fpm.d/www.conf` has the three env lines | **First line**: `ada-clock: real` \| `ada-clock: fake <local> <tz> (<setup>)` (Unit D's Tess reads it). `local-linux` then `wrapper: installed <php-fpm>` \| `wrapper: missing <php-fpm>, run ada-clock install`, and `pool: loaded <www.conf>` \| `pool: needs restart <www.conf>`. Then `mu-plugin: installed <path>` \| `not installed <path>` \| `not installed, another file is at <path>`, then `site time: <UTC> (<n> s from …)`. **Exit** 0 within 120 seconds and (local-linux) wrapper and pool in place; 1 further, or `wrapper: missing`, or `pool: needs restart`; 2 when the endpoint doesn't answer or its `fake` disagrees with the state file (`LOCAL_URL` and `LOCAL_WP_PATH` not the same site), or local-linux can't find the site's `php-fpm`. Under `manual` the 0/1 check is for just after the clock is changed: the clock runs on, so it is 1 again two minutes later |
| `reset` | Deletes the state file if there. `manual`: prints the steps to switch automatic time back on. `local-linux`: writes `+0s` to the clock file when there is one | First line `ada-clock: real, deleted <path>` or `ada-clock: real, no state file at <path>`. `local-linux` then `clock file: <path> (+0s)` or `clock file: none at <path> (ada-clock install not run)` |
| `install` | Copies `templates/clock/ada-clock.php` to `wp-content/mu-plugins/ada-clock.php`; replaces only a file with its marker line (`Ada clock: copied by ada-clock install, which replaces it.`), else exit 2, file untouched. Adds the mu-plugin and the state file to `git rev-parse --git-path info/exclude` when inside the repo. `local-linux`: every check first (site, `php-fpm`, libfaketime, the pool template and any block in it), so a refusal changes nothing; then the wrapper, the pool block and the clock file (`SYSTEM.md`) | `installed <path>` or `replaced <path>`; `excluded <rel>` / `kept     <rel>` per exclude line. `local-linux` then `wrapper: installed\|replaced <php-fpm>`, `pool: added\|replaced <hbs>`, `clock file: <path> (<offset>)` |
| `uninstall` | Exit 2 while the state file exists. Removes the mu-plugin when it has the marker line; leaves any other file. `local-linux`: removes the pool block and the clock file, then, when `php-fpm` is the wrapper, puts `php-fpm.real` back unless another clock file's site uses the same `php-fpm` or can't be resolved; exit 2, nothing changed, when that `php-fpm.real` is missing or not Local's binary. When `php-fpm` is Local's binary, deletes a stale `php-fpm.real` unless such a site uses it | `mu-plugin: removed\|none at <path>` or `mu-plugin: left <path>, not ada-clock's`. `local-linux` then `pool: removed\|none at <hbs>`, `clock file: removed\|none at <path>`, and one of `wrapper: kept <php-fpm>, still used by <ids>` (the wrapper only), `wrapper: removed, php-fpm.real put back as <php-fpm>`, `wrapper: none at <php-fpm>[; deleted the old copy <php-fpm>.real]` (Local's binary, or no `php-fpm`), `wrapper: left <php-fpm>, not ada-clock's` |
| `wp <args…>` | Runs WP-CLI on the local site; every WP-CLI call `ada-clock` makes goes through it. `local-linux`, while the clock file exists: under `env` with `LD_PRELOAD`, `FAKETIME_TIMESTAMP_FILE`, `FAKETIME_NO_CACHE=1`, `FAKETIME_DONT_FAKE_MONOTONIC=1` (exit 2 when libfaketime is missing) | WP-CLI's own output and exit status |
| `extension` | Runs before the settings checks: needs no `local.env` and no local `LOCAL_URL`. Copies `extras/ada-clock-extension/` to `~/.config/ada-clock/chrome-extension/` (a copy beside it, then the old folder removed and the copy renamed), so Chrome loads from a path that doesn't change with the plugin's version. Replaces only a folder whose `manifest.json` has the line `"name": "ada-clock",`; anything else there exits 2, untouched. Exit 2 for any argument | `extension: installed <path>` or `extension: replaced <path>`, then the Chrome load steps |

The state file, `<LOCAL_WP_PATH>/wp-content/ada-clock.json`, is the one record of the fake time,
read by later setups: `{"timestamp": <UTC Unix time>, "local":
"<YYYY-MM-DD HH:MM[:SS]>", "timezone": "<wp_timezone_string() or the ISO offset>", "setup":
"manual" or "local-linux", "set_at": <the computer's Unix time at set>}`. `local` has `:SS` only when
`set` was given seconds, and `status` prints it as it is stored. Under `local-linux`,
`timestamp − set_at` is the offset in the clock file.

The clock file, `~/.config/ada-clock/<Local site ID>` (local-linux): one line, `+<n>s` or `-<n>s`,
which libfaketime reads on every call (`FAKETIME_NO_CACHE=1`). `+0s` is real time. Replaced by a
rename, never emptied, while the wrapper is installed. The wrapper
(`templates/clock/php-fpm-wrapper.sh`) is POSIX `sh`; `install` fills in its `@LIBFAKETIME@` and
`@CLOCK_DIR@`, and recognises it by its line 2,
`# ada-clock: php-fpm wrapper, written by ada-clock install, which replaces it.`

The mu-plugin (`templates/clock/ada-clock.php`), inert unless `home_url()`'s host passes the same
local test (`ada_clock_is_local_site()`): `SET timestamp = <time()>` once on load while the state
file exists; `auth_cookie_expiration` ten years; `presstest_companion_session_ttl` plus ten years
(the Companion reads it once, at session creation; clock real or fake); cookie `ada_clock` (path
`/`, session, not HttpOnly) set to `time()` on `send_headers` and `admin_init` while the state
file exists, expired otherwise; `GET ada-clock/v1/time` (permission: the local test, no login), answering
`{"now": <time()>, "fake": <bool>, "timezone": "<wp_timezone_string()>"}`. `status` calls it as
`<LOCAL_URL>/?rest_route=/ada-clock/v1/time`, which answers whatever the permalink setting.

The Chrome extension (`extras/ada-clock-extension/`, Manifest V3, Chrome 111+) has one content
script, `content.js`. It runs at `document_start` in the page's own world (`"world": "MAIN"`), in
every frame, on `*://localhost/*`, `*://127.0.0.1/*`, `*://*.local/*` and `*://*.test/*`, any
port. It asks for no permissions. It reads the `ada_clock` cookie once from `document.cookie`.
With no cookie, a value that isn't digits (empty included), or a read that throws (a sandboxed
frame), nothing changes: `window.__adaClock` isn't set and nothing is logged. Otherwise
`offsetMs` is the cookie × 1000 − `Date.now()`, and `window.Date` becomes a
constructor function sharing the native `Date.prototype`: `new Date()`, `Date()` and
`Date.now()` add `offsetMs`; with arguments it builds the native date unchanged; `Date.UTC` and
`Date.parse` are inherited. It sets `window.__adaClock = { offsetMs }` (Tess's check that it ran).
`performance.now()`, timers, `Intl`, workers and `about:blank` or `srcdoc` frames are not shifted.
A page that fires neither `send_headers` nor `admin_init` (`wp-login.php`, a static file) reads the
cookie the last WordPress page set, so it runs behind by the time since then. Chrome's `*.local`
also matches a bare host `local`, which `is_local_url` refuses; such a host can't be reached.
The manifest's `"version": "1"` is fixed: it doesn't follow the plugin's version (P31).

### Release (`.github/`)

`release-bump.py <version> <notes-file>` (`.github/scripts/release-bump.py:1-13`): sets
`plugin.json`'s version, moves CHANGELOG Unreleased under the version, writes the notes; exits
non-zero changing nothing if Unreleased is empty. Run by `release.yml` only.

## Skills (`skills/`)

| Name | Invoked as | For |
|---|---|---|
| `begin` | `/ada:begin` | Ada's playbook: session start, brief, approval, roles, PR gate |
| `pickup` | `/ada:pickup` | Resume from `CHECKPOINT.md` |
| `bughunt` | `/ada:bughunt` | Grace's read-only investigation; writes `FINDINGS.md`, and in a product, for every hunt, a knowledge-base entry (and `INDEX.md` for a new one) in the main checkout, uncommitted |
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
- Edit a PR with `gh pr edit`: it fails before gh 2.50 (`bin/ada-brief:189-190`). Use `gh api`.
- Use `mapfile`/`readarray`, `declare -A`, `${var,,}` or other bash 4+ features in `bin/`
  (macOS ships bash 3.2; `CHANGELOG.md:191-192`).
- Call `python3` directly in `bin/`: use the `PY` discovery block, and strip `\r` from its output.
- Write files without `newline='\n'` from Python, or with CRLF.
- Rename brief or plan headings, field lines (`Plan:`, `Ships:`, `Asana:`, `Epic:`) or the Units
  table columns in `templates/` without changing the parsers in `ada-brief` and `ada-plan`.
- Overwrite a file in a project from `ada-onboard`, except the refresh list with an untouched copy.
- Push to `next-release` or `main` directly, or change `plugin.json`'s version by hand (P31).
- Have a script read plugin files at runtime other than through `$(dirname "$0")/../templates` or
  `$(dirname "$0")/../extras`.
- Change `ada-worktree check`'s first-line words or `new`'s last line without changing
  `/ada:begin`, `/ada:pickup` and `ada:planning`.
- Change `ada-clock status`'s first-line words, the state file's keys, the `ada_clock` cookie or the
  endpoint's fields without changing their readers (Tess, later `ada-clock` setups, the extension).
- Change the local-host rule in one of its three places without the other two: `ada-clock`'s
  `is_local_url`, `ada_clock_is_local_site()` in the mu-plugin, and the `matches` in
  `extras/ada-clock-extension/manifest.json`.
- Change the wrapper's line 2 (`templates/clock/php-fpm-wrapper.sh`, `WRAPPER_HEADER`), the pool
  block's marker lines (`POOL_START`, `POOL_END`), or the site-ID rule (`SITE_ID_PATTERN` and the
  wrapper's `case`) in one place without the other: `install`, `uninstall` and `status` recognise
  what an earlier `install` wrote by them.
- Remove a step's worktree other than with `ada-worktree remove`, which archives its handoff
  files first; or delete its branch with it.
