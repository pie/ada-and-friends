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
| `who` | Approval policy for this developer from `PROJECT.md` | One line: `not-needed` \| `approver <login>` \| `trusted <login>` \| `required <login>` (`:583-598`). Read by `/ada:begin` (`skills/begin/SKILL.md:197-209`), `ada-plan status` (`bin/ada-plan:236`), `ada-onboard check` (`bin/ada-onboard:269`). |
| `submit <task-id> <base> [<branch>]` | Commits the brief (and plan) to `.claude/briefs/`, pushes, opens or updates the draft PR. On an existing PR with an approver's label it compares the approved version with the new brief: `same` keeps the label (the body is replaced only if it differs); `allowed` keeps the label, replaces the body and posts one PR comment listing the added Amendments rows, unless the body already shows this brief (a repeated submit changes nothing); `reapprove`, or no approval, removes `brief-approved`. A unit's draft brief and `PLAN.md` come from the main checkout's `.claude/handoff/plan/`, wherever it runs | `opened   <url>`, `updated  <url> (approval kept)` or `updated  <url> (approval reset)` (`:666-685`). Read by `/ada:begin` (Amending an approved brief). |
| `status [<branch>]` | Approval state of the branch's PR | **First line**: `approved @<login> <ISO time>` \| `changes-requested` \| `waiting` \| `not-submitted` \| `not-needed`. After `approved`, a second line `amended since approval; no re-approval needed` when the label is older than the brief's last change and `compare` says `same` or `allowed`. A `waiting` or `changes-requested` note for an earlier approval ends `: <reason>` from `compare`. Then notes, `--- @<login>, <where>, <time>` blocks with the approvers' words, and the PR URL last (`:688-751`). First line read by `submit` (`:617-623`), `ada-plan status` (`bin/ada-plan:260`) and `/ada:begin`; the second line by `/ada:begin` and `/ada:pickup`. |
| `compare <approved-brief> <current-brief>` | Offline (paths relative to the repo root): whether an amended brief keeps its approval. Approved sections: Decisions, and Constraints less `- Out of scope:`. Added rows: Amendments data rows in the current brief not in the approved one, as text | **First line**: `same` (ignoring trailing whitespace, CR included, and blank lines at the end) \| `reapprove <reason>` \| `allowed`. The reason is the first added row's `needed: <trigger>` cell, `amendment with no Agreed by`, or `amendment with no re-approval decision` (an empty or other cell, or a four-column row); else, with no added row and an approved section changed, `approved section changed with no amendment recorded`. After `allowed`, the added rows as `\| … \|` lines. Exit 0 for all three, 2 on usage or an unreadable file (`:753-776`; rules in `compare()`, `:268-288`). Used by `status` and `submit`. |
| `body <task-id> [<branch>]` | Prints the draft PR description for a committed brief | Markdown starting `<!-- ada-brief: .claude/briefs/<task>.md -->` (`:428`) |
| `ready <title> <file>` | Final title and description, out of draft | `ready    <url>`; a stderr note if `gh pr ready` fails (`:796-797`) |
| `overlap [<file>...]`, `overlap --gate` | Fetches `Branch from` and `PR into` (`PROJECT.md`, defaults `production` and `next-release`); lists the work waiting to ship and the open PRs into either branch; marks entries that change a given file. `--gate` takes no files: it uses the branch's own, the working tree's changes since `git merge-base origin/<PR into> HEAD` plus untracked files, and exits 2 if `git merge-base` fails (a shallow clone) | **First line**: `touching <n>` (`touching 0` with `--gate` and no changed files), or `touching -` with no files and no `--gate`. Then `waiting to ship:` and `open:`, each entry two-space indented: `#N <subject> [<files>]` or `<short hash> <subject> [<files>]`; `#N [draft ]<title> [<files>]`. A marked entry ends ` touches: <file>, ...`; an empty group is `  none`. The open group is `  unknown (<reason>)`, exit 0, without `gh`, and with `more than 100 open PRs into <base>` or `#N has 100 or more files`. Read by `/ada:begin` (brief step, and `--gate` at the PR gate). |

Env: `ADA_BRIEF_REQUIRE=1` makes `who` print `required` for everyone (`:13-14`).

### `ada-onboard` (`bin/ada-onboard:1-42`)

| Subcommand | Does | Output |
|---|---|---|
| `files KEY=value...` | Copies `templates/repo/`, never overwriting; fills `{{KEY}}` (keys at `:96`); records hashes | `created  <path>` (with `(still to fill: …)`) or `kept     <path>`; `Placeholders left: …` (`:113-127`) |
| `refresh [--force \| --decline]` | Brings `.github/pull_request_template.md` up to the plugin's | One line per template, word padded to 9: `current`, `refreshed`, `declined`, `edited` or `unknown`; the last two followed by a unified diff (`:146-161`). Read by `/ada:begin` (`skills/begin/SKILL.md:62-67`). |
| `gitignore [--list]` | Adds the team's ignore lines, `.claude/worktrees/` among them. `--list` prints the lines and changes nothing | `kept` / `added` per line; with `--list`, one line each. `--list` is read by `ada-worktree new` |
| `settings` | Merges plugin, marketplace, permissions, attribution off, `promptCacheTtl` into `.claude/settings.json`. Allows `ada-worktree check`, `list` and `main`; `new` and `remove` still ask | The resulting file (`:245`) |
| `check` | What's missing or unfilled | `missing`, `fill in`, `draft`, `ok`, `error` per item (`:248-270`) |
| `clock LOCAL_WP_PATH=<path> LOCAL_WP=<command>` | Picks the clock setup (`local-linux` on Linux when `~/.config/Local` is a folder, else `manual`) and appends `ADA_CLOCK_ADAPTER`, `LOCAL_WP_PATH` and `LOCAL_WP` to `.claude/team/local.env` when `ada-clock`'s parser reads them as empty; never changes a line already there. Exit 2, nothing written, for a missing, repeated or empty argument, any other key, or a value with a newline, a `#` or the `<…>` form. Run by `/ada:onboard` (`skills/onboard/SKILL.md`, 5) | Per key, word padded to 9: `added    KEY=value` or `kept     KEY`; then `setup: <manual\|local-linux>`, the value `local.env` now gives (`:272-341`) |

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
file exists; `auth_cookie_expiration` ten years; cookie `ada_clock` (path `/`, session, not
HttpOnly) set to `time()` on `send_headers` and `admin_init` while the state file exists, expired
otherwise; `GET ada-clock/v1/time` (permission: the local test, no login), answering
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
