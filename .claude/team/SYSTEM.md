# How Ada and friends behaves

Drafted by `/ada:onboard` from the code and its history; **corrected by a developer**. Anything
marked *(unverified)* is a reading of the code nobody has confirmed yet. The team trusts this file,
so a wrong line here becomes wrong code: fix it, don't work round it.

Line numbers are as of release 0.6.0 (`d7a274b`, `origin/main`). Paths under `bin/`, `skills/`,
`agents/` and `templates/` are in `plugins/ada/`.

## The model

This repo is a Claude Code plugin marketplace, not a site. There is no database and no server.
What it "stores" is files in *other* repos (the onboarded projects) and labels and PRs on GitHub.

- **The marketplace and the plugin.** `.claude-plugin/marketplace.json:2` names the marketplace
  `pie`; it lists one plugin, `ada`, at `./plugins/ada` (`marketplace.json:7-8`). The version lives
  only in `plugins/ada/.claude-plugin/plugin.json:3`; `marketplace.json` has none. The marketplace
  installs straight from `main` (`DEVELOPING.md:134-136`, `release.yml:4-5`).
- **Skills** (`skills/*/SKILL.md`): `begin`, `pickup`, `bughunt`, `design`, `onboard` are
  commands (`/ada:begin` etc.); `planning`, `standards`, `standards-tests`,
  `standards-accessibility`, `efficiency` are loaded or preloaded, not commands
  (`DEVELOPING.md`, Layout). `design` (Iris) runs in the main session, usually loaded by Ada from
  `begin` or `planning`.
- **Agents** (`agents/*.md`): `bob`, `claudia`, `tess`, spawned as `ada:bob` etc.
  (`DEVELOPING.md:52`, `skills/begin/SKILL.md:184-185`). Each has a `tools:` allowlist
  (`agents/bob.md:6`, `agents/claudia.md:6`, `agents/tess.md:6`). Tess's also has `ToolSearch` and
  the Claude in Chrome tools (`mcp__claude-in-chrome__*`), for local checks the developer asks her
  to run (`agents/tess.md`, Running local checks yourself).
- **Subagents and the canvas.** No agent's `tools:` list has Artifact, so only the main session
  (Ada, and Iris in `skills/design/SKILL.md`) reaches a Design artifact. Bob, Claudia and Tess
  work from `.claude/handoff/DESIGN.md` instead, with token values from the project's root
  `DESIGN.md`. Don't add Artifact to an agent to change this.
- **Scripts** (`bin/`): on the Bash PATH while the plugin is enabled (`DEVELOPING.md:44,55`):
  `ada-brief`, `ada-plan`, `ada-size`, `ada-handoff-init`, `ada-worktree`, `ada-onboard`, `prod-wp`, `presstest`. Each is bash,
  and `ada-brief`, `ada-plan`, `ada-onboard` and `ada-size` also have Python 3.8+ heredocs.
- **Templates** (`templates/`): `handoff/` (the role handoff files), `plan/PLAN.md`, `repo/`
  (everything `/ada:onboard` copies into a project, including `PROJECT.md`), and `DESIGN.md` (the
  skeleton Iris fills to create a project's root `DESIGN.md`; no script copies it).

State the scripts read and write, in an onboarded project:

| State | Written by | Read by |
|---|---|---|
| `.claude/team/PROJECT.md` "Brief approval" section (`**Approvers:**`, `**Trusted:**`, optional `**Re-approval triggers:**`) | the developer | `ada-brief` `policy()` (`bin/ada-brief:121-148`) reads Approvers and Trusted only; Ada reads the triggers (`skills/begin/SKILL.md`, Amending an approved brief) |
| `PROJECT.md` `**Branch from:**` / `**PR into:**` | the developer | `ada-plan` (`bin/ada-plan:68-74`, `93-96`); `ada-brief overlap` (`branches()`) |
| `.claude/handoff/*.md` (BRIEF, BUILD-LOG, CHECKPOINT, DESIGN, FINDINGS, REVIEW-*, TEST-*), one set per checkout: the main checkout and each step's worktree | `ada-handoff-init` copies; roles fill (DESIGN: Iris) | the roles (DESIGN only when it says `Status: agreed`) |
| `CHECKPOINT.md` `- Branch:` (the branch alone) and `- Worktree:` | Ada, at session end | `ada-worktree check` (`Branch:`), `ada-worktree list` (`Step:`); `/ada:begin` and `/ada:pickup` run `check` before reading the checkpoint |
| `.claude/worktrees/<slug>/`: one git worktree per step, in the main checkout, git-ignored | `ada-worktree new` (from `/ada:begin` or `ada:planning`); removed by `ada-worktree remove` | git; `ada-worktree list`; Claude Code's `EnterWorktree` |
| `.gitignore` lines (`.claude/handoff/`, `.claude/worktrees/`, local config) | `ada-onboard gitignore`; its `--list` is the list | `ada-worktree new`, which copies the lines that name a file into a new worktree |
| `.claude/handoff/plan/DESIGN-<task id>.md` | Iris, in a plan: the only handoff design file she writes then, and `.claude/handoff/DESIGN.md` stays the template (`skills/design/SKILL.md`, Write DESIGN.md); the project's root `DESIGN.md` is written at agreement, as in a step | Ada, who copies it to `.claude/handoff/DESIGN.md` when a unit that names it is built (`skills/planning/SKILL.md`) |
| `DESIGN.md` at the repo root: the project's design system, in the DESIGN.md format (spec `version: alpha`: YAML front matter tokens, then the spec's 8 `##` sections). Committed in the PR of the step that builds from the design | Iris, from `templates/DESIGN.md`, when a design is agreed: created if missing, else new tokens added; an existing token changed or removed only with the developer's yes, recorded in the handoff `DESIGN.md`. Linted with the version pinned in `skills/design/SKILL.md` (Write DESIGN.md) when `npx` is available | Iris first (Look first); Bob, Claudia and Tess for token values; `/ada:onboard` sets `Design guide: DESIGN.md` when it exists |
| `.claude/handoff/archive/<name>/` | `ada-handoff-init --archive` / `--archive-plan` (`bin/ada-handoff-init:25-51`); `ada-worktree remove`, into the main checkout's, as `<YYYY-MM-DD>-<slug>/` | people |
| `.claude/handoff/plan/PLAN.md` and `plan/<task>.md`, in the main checkout only (the first entry of `git worktree list --porcelain`, printed by `ada-worktree main`) | `ada-plan init` / `briefs` (`bin/ada-plan:50-56`, `189-212`); Ada adds "Building in `<worktree path>`; the live brief is there." under a unit's title when its build starts | `ada-plan`, `ada-brief submit`, `ada-handoff-init --archive-plan`, from any worktree |
| `.claude/briefs/<task>.md`, `.claude/briefs/<epic>-plan.md` (committed) | `ada-brief submit` (`bin/ada-brief:625-653`) | `ada-brief status`/`body`/`submit` via `git show` and `git log` (`git_show`, `approved_version`, `bin/ada-brief:188-190`, `290-294`; `since`, `:709`) |
| `brief-approved` label on the draft PR | an approver, by hand; removed by `submit` when re-approval is needed (`bin/ada-brief:676-679`) | `approval()` (`bin/ada-brief:307-317`), from `status` (`:716-730`) and `submit` (`after_submit`, `:523-558`) |
| Amendments table of the brief (five columns: `Date`, `What changed (old → new)`, `Why (evidence)`, `Re-approval`, `Agreed by`) | Ada, with the developer's yes (`templates/handoff/BRIEF.md`) | `compare()` (`bin/ada-brief:268-288`); `render_body` (`amendments_table`, `:219-227`); Claudia and Tess read `Agreed by` |
| PR comment listing the Amendments rows added since approval | `submit`, when the approval is kept (`after_submit`, `bin/ada-brief:544-554`; posted at `:674`) | the approver |
| `<!-- ada-brief: <path> -->` marker in the PR body | `render_body` (`bin/ada-brief:428`) | `ada-brief status` (`bin/ada-brief:704-707`) |
| `.claude/team/.template-hashes` (`<copied\|declined> <sha256> <path>`) | `ada-onboard files` / `refresh` (`bin/ada-onboard:53-77`) | `ada-onboard refresh` |
| `.claude/settings.json` | `ada-onboard settings` (`bin/ada-onboard:172-221`) | Claude Code |
| `.claude/team/prod.env` | the developer | `prod-wp`; `ada-onboard settings` (SSH host to deny, `bin/ada-onboard:173-176`) |
| `PROJECT.md` `**PressTest suite:**` and `**PressTest staging:**` (first code span, else first word; `none` or a `<prompt>` means none) | the developer, at onboarding | `presstest` (`bin/presstest:57-71`) |
| `.claude/team/local.env` (`LOCAL_URL`, `PRESSTEST_*`, `PRESSTEST_STAGING_*`; git-ignored, other keys ignored) | each developer | `presstest` (`bin/presstest:73-93`); Tess and Iris read `LOCAL_URL` |

## What fires when

There are no hooks, cron or webhooks in the plugin: a plugin has no install or update hook
(`DEVELOPING.md:58`). Everything runs because a skill tells Ada to run a script, or a person runs
it, or (in this repo only) a push to `main` runs the Release workflow.

**A step, from `/ada:begin`** (`skills/begin/SKILL.md`):

1. Session start: read `PROJECT.md`; load `ada:efficiency`; `ada-handoff-init` if `.claude/handoff/`
   is missing; `ada-worktree check` before reading a checkpoint (on `other`, `EnterWorktree` to that
   branch's worktree, or name it and stop; on exit 2, show the developer the `Branch:` line and
   rewrite it as the name alone once they confirm; on either, a step whose PR is merged or closed is
   archived by Ada on the developer's yes, with `ada-worktree remove` for a `.claude/worktrees/`
   worktree and `ada-handoff-init --archive` here); archive a finished step with
   `ada-handoff-init --archive` in the main checkout (in a step's worktree, send the developer to
   the main checkout and stop); in the main checkout, `ada-worktree list` and an offer of `ada-worktree remove` for
   each finished step under `.claude/worktrees/`, one yes each; `ada-plan status` if the main
   checkout's plan is under way; for a new step, `ada-worktree new <branch> <base>` (for a fix
   after a bughunt, the developer first picks the `bughunt/*` worktree from `ada-worktree list`,
   and Ada copies its `FINDINGS.md` into the new worktree before reading it) then
   `EnterWorktree` (`path`), or `cd "<path>" && claude` and stop, unless the session is already in a
   worktree with no step on the step's branch; check the branch; `ada-onboard refresh`. No step
   facts in auto-memory.
2. Brief (`:152-155`, `:170-295`): one PR or a plan (`ada:planning`); `.claude/handoff/BRIEF.md` with an
   `Expected review:` level. Before the rest of the brief, once "done" is pinned down, Ada
   applies the design test (begin, The design step); when it passes she offers the design step
   and loads `ada:design`, and finishes the brief from `.claude/handoff/DESIGN.md`. The same
   test runs again if undesigned UI turns up during the build. Before the brief is confirmed,
   `ada-brief overlap` with its expected files; Ada fills `Overlaps:` and stops for the developer
   on any overlap.
3. Approval (`:297-360`): `ada-brief who`; on `required <login>`, `ada-brief submit <task> <base>`
   opens a draft PR and the session stops until `ada-brief status` says `approved`. After
   approval, Ada checks each amendment against the re-approval triggers, records `not needed` or
   `needed: <trigger>` in its row, and runs `ada-brief submit`: `(approval kept)` carries on,
   `(approval reset)` stops for approval again (begin, Amending an approved brief).
4. Build (`:362-407`): Tess plan-and-write, Bob (plan then build), Claudia review, fix rounds
   (Bob fresh; Claudia re-review on Sonnet; Tess for test items), Tess run on Sonnet.
5. Session end at each phase break (`:546-559`): `CHECKPOINT.md`, then `/ada:pickup` in a fresh
   session.
6. PR gate (`:440-511`): `ada-size <base>`; `ada-brief overlap --gate` (the branch's changed and
   untracked files), any new overlap to the developer; summary; on yes, commit with role trailers
   (`:513-530`), push, `PR.md` from the PR template including the `## Squash commit` section, then
   `gh pr create` or `ada-brief ready "<title>" .claude/handoff/PR.md` if a draft PR exists.
   Nobody on the team merges (`:511`).

**`/ada:pickup`** (`skills/pickup/SKILL.md`) runs begin's Session start, `ada-worktree check` before
reading the checkpoint (on `other`, `EnterWorktree` or stop; on exit 2, the `Branch:` line is
confirmed with the developer; a finished step is archived on their yes, as in begin), re-checks
`ada-brief status` if the brief is `awaiting approval` or was approved and its draft PR hasn't been
made ready (`ada-brief ready` replaces the PR body, after which `status` says `not-submitted` and the
PR review is the gate), where a second line `amended since approval; no re-approval needed` means
carry on, and `ada-plan status` for the main checkout's plan.

**A release of this plugin** (`.github/workflows/release.yml`), on every push to `main`
(`:13-16`): see Fragile areas, The release workflow.

## Fragile areas

### ada-brief: brief approval through GitHub

How it works:

- `who` (`bin/ada-brief:583-598`): parses `PROJECT.md` with a regex for `## Brief approval` and the
  `- **Approvers:**` / `- **Trusted:**` bullets (`policy()`, `:121-148`). A value starting `none`
  means empty; otherwise every `@login` is taken, lowercased. Other bullets in the section, such as
  `- **Re-approval triggers:**`, are not read. No section prints `not-needed` plus a warning on
  stderr (`:587`). Then `gh api user` gives the login (`me()`, `:172`). `ADA_BRIEF_REQUIRE=1` forces
  `required` (`:593`).
- `brief_py()` (`:178-319`) prints the Python that reads briefs, shared by `render_body`,
  `after_submit`, `status` and `compare`: each runs `{ brief_py; cat <<'PY' ... PY; } | py -`. It
  holds `split()` and `table_rows()` (what a section and a table data row are), `compare()`,
  `approved_version()`, `since_approval()`, `gh()`/`flat()` and `approval()`.
- `compare <approved> <current>` (`:753-776`, rules in `compare()`, `:268-288`): offline. `same`
  when the files match after each line is right-stripped (a CR too) and blank lines at the end are
  dropped. Otherwise the added rows are the Amendments data rows of `<current>` not in
  `<approved>`, as cell lists, counted as a multiset. The first added row, in table order, with
  Re-approval `needed: <trigger>`, no `Agreed by`, or no `not needed` (`row_problem()`, `:256-266`;
  a row of other than five cells has no Re-approval) gives `reapprove <reason>`. Then, with no
  added row, a change to Decisions or Constraints less its `- Out of scope:` bullet and that
  bullet's indented lines (`approved_sections()`, `:236-250`, comments stripped) gives
  `reapprove approved section changed with no amendment recorded`. Else `allowed`, followed by
  the added rows. Exit 0 for all three; 2 on usage or an unreadable file.
- The approved version (`approved_version()`, `:290-294`): `git log -1 --until=<label time>
  <ref> -- <brief>`, then `git show`. The label time is the last `labeled` event's `created_at`
  (`approval()`, `:307-317`, which returns nothing unless the label is on the PR).
- `submit` (`:600-686`): validates names (`:601-609`), refuses `production`, `next-release`,
  `review`, `main`, `master` (`:609`). If the base branch has its own PR, the base's brief must be
  `approved`, `not-needed` or `not-submitted` first, by calling itself as `"$here/ada-brief" status "$base"`
  (`:617-623`). On the current branch it copies `BRIEF.md` (and the plan) into `.claude/briefs/`,
  commits only those paths, pushes (`:633-643`). For another branch it commits without a checkout
  through a throwaway index and `git commit-tree` / `git update-ref` (`commit_to`, `:562-576`),
  refusing a branch checked out in another worktree (`:646-648`). It creates the label if missing
  (`:655`) and renders the PR body (`:656-657`). On a new branch it opens a draft PR (`:681-685`,
  prints `opened   <url>`). On an existing PR it runs `policy()` and `after_submit` (`:523-558`),
  which compares the approved version on `origin/<branch>` with the brief just pushed, when the
  label is on and was added by an approver, and prints an action (`:666-680`):
  - `keep` (`same` or `allowed`, PR body text unchanged): nothing changes on the PR. For
    `allowed` this is a repeat of a submit that already replaced the body and posted the comment.
  - `patch` (`same`, body differs, e.g. the plan in it changed): PATCH the body.
  - `comment` (`allowed`, body differs): PATCH the body, then POST one PR comment listing the
    added rows.
  - `reset` (`reapprove`, no approver's label, or no approved version): PATCH the body and DELETE
    the label.
  The first three print `updated  <url> (approval kept)`; `reset` prints
  `updated  <url> (approval reset)`.
- `status` (`:688-751`): the PR body's marker names the brief path (`:704-707`); `since` is the
  committer date of the last commit touching it on the pushed branch (`:709`). If the label is on
  and was last added by an approver (`approval()`): at or after `since`, `approved`; before it,
  `since_approval()` on the pushed branch, where `same` or `allowed` prints `approved @<login>
  <time>` and a second line `amended since approval; no re-approval needed`, and `reapprove
  <reason>` adds the note `@<login> approved an earlier version; the brief has changed since:
  <reason>` (`:716-730`). Otherwise `changes-requested` if any approver review, line comment or PR
  comment is newer than `since`, else `waiting` (`:732-748`).
- `ready` (`:787-798`): PATCHes title and body, keeps the Asana block, `gh pr ready`.
- `render_body` (`:325-496`) parses the brief's headings and bullets and the plan's Units table,
  and calls `gh pr list` once per unit (`:388-393`). The Amendments table keeps its column count:
  five columns if any row has five cells (a four-cell row then gets an empty Re-approval), else
  the old four (`amendments_table()`, `:219-227`). A filled `Overlaps:` header
  line becomes `> Overlaps with work in flight: …` in the note to the approver; without the line
  the body is as before.
- `overlap [<file>...]`: `branches()` reads `Branch from` and `PR into` from `PROJECT.md` with
  `ada-plan`'s `named()` regex and its defaults (`production`, `next-release`); no `PROJECT.md`
  dies. Arguments are normalised (`\` to `/`, leading `./` dropped) and refused with `die` if they
  start with `-` or `/`, have a `..` segment, or hold a control character such as a newline (each
  entry is one line); any other character is accepted. It fetches both branches
  into `origin/<name>` with explicit refspecs (a failure dies), then lists `git log --no-merges
  --no-renames --name-only origin/<PR into> ^origin/<Branch from>`, grouped by the trailing
  `(#N)` of each subject, and `gh pr list --base <b> --state open --limit 101 --json
  number,title,isDraft,headRefName,files` for each branch. `need_gh` runs in a command
  substitution, so its `die` only gives the reason printed as `unknown (<reason>)`.
- `overlap --gate` (no file arguments, or it dies): after the fetch, `git merge-base
  origin/<PR into> HEAD`, dying if it fails (a shallow clone). Then `git diff -z --no-renames
  --name-only <merge base>` (committed, staged and unstaged) and `git ls-files -z --others
  --exclude-standard` go NUL-separated into a temp file; each path is checked for control
  characters in bash and the file's path (not its contents, which could pass a Windows command
  line's limit of about 32,000 characters) is passed to the Python as an argument, so Git Bash
  converts it, and the Python reads it. No `xargs`, so the exit code reaches the caller.

Traps:

- **Rows are compared as text, not mapped to changes.** Once an agreed `not needed` row has been
  added since approval, a later change to Decisions or Constraints with no row of its own still
  gives `allowed` (`compare()`, `:268-288`). Claudia's review checks each change has its row.
- **Approval time is the committer date.** `since` uses `%cI` (`:709`) and the approved version
  uses `git log --until` (`:290-294`), so rebasing or amending the brief commit moves both: an
  earlier label stops counting, or no approved version is found and approval is reset.
- **`status <branch>` reads `origin/<branch>`** (`:699`) and never fetches, so a stale
  remote-tracking ref gives an old `since` and an old brief to compare.
- **One page only.** Every GitHub list is fetched with `per_page=100`, no paging (`gh()`,
  `:302-303`); a PR with over 100 events or comments would miss later ones. `flat()` is a no-op
  left for this.
- **PR edits go through `gh api`, not `gh pr edit`,** which fails before gh 2.50 (header,
  `:89-90`). Keep it that way.
- **`gh pr create` errors are swallowed** (`:682`, `2>/dev/null`): only "couldn't open the draft
  PR" reaches the user.
- **The first line of `status` is an interface** read by `/ada:begin` (`skills/begin/SKILL.md:313-322`),
  by `submit` (`:617-623`) and by `ada-plan status` (`bin/ada-plan:260`). Don't change its words.
- **The Asana block** is found by a fixed English sentence (`:508`); if Asana changes it, the
  block is dropped on the next description replace.
- **`overlap`'s first line is an interface** read by `/ada:begin`, as are its group headings,
  ` touches: ` and `unknown (`. Change them only with the begin skill.
- **`overlap` reads one page, and says so.** It asks for 101 open PRs per branch; if 101 come
  back the open group is `unknown (more than 100 open PRs into <base>)`. `gh pr list` gives at
  most 100 files per PR (`files(first: 100)` in gh 2.45), so a listed PR with 100 files makes it
  `unknown (#N has 100 or more files)`. The current branch's own PR is left out before that
  check.
- **`overlap` leaves out PRs by head branch name.** A PR whose head has the same name as the
  current branch is left out, even one from a fork.
- **`overlap` with the same `Branch from` and `PR into`** has nothing waiting to ship, by
  construction: the range is empty.
- Parsing depends on template wording: `# Brief…:` title (`:354`, `:626`), `Plan:` line pointing
  at `.claude/briefs/<id>-plan.md` (`:375`, `:629`), `- Out of scope:` / `- Expected review:`
  bullets under `## Constraints` (`:361-369`), `- [ ]` Definition of Done items (`:370`), the
  `Overlaps:` line (`:359`, `:437`). A change
  to `templates/handoff/BRIEF.md` can silently empty parts of the PR body.

Test it in a scratch GitHub repo you own (it pushes, labels and opens PRs).

### ada-onboard: templates, settings and refresh

- `files` (`bin/ada-onboard:80-118`): walks `templates/repo/`, never overwrites (prints `kept`,
  `:101-103`), fills `{{KEY}}` for nine known keys (`:85`), writes LF, keeps the mode bits (`:109`),
  and records `copied <sha>` of what it wrote (`:110`). Kept files get no hash.
- `refresh` (`:119-154`): only `.github/pull_request_template.md` (`:127`), because only
  placeholder-free templates can be refreshed (`:126`). Order of the decision (`:135-150`): same
  text → `current`; `--force`, file missing, or an untouched `copied` copy → `refreshed` (and
  rewrites the hash); `--decline` → records `declined <sha of plugin copy>`; already declined at
  this plugin hash → `declined`; otherwise `edited` (hash recorded) or `unknown` (none) followed
  by a unified diff, file left alone. Hashes are of LF-normalised text (`:53-61`), so CRLF
  checkouts on Windows still match.
- `settings` (`:172-221`): loads `.claude/settings.json` with `json.load` (`:181`), sets the
  marketplace and `ada@pie`, blanks commit/PR attribution (`:186`), sets `promptCacheTtl` only if
  absent (`:189`), appends allow and deny rules not already present (`:191-216`), denies SSH to
  the `PROD_SSH` host if `prod.env` has one (`:213-215`), and rewrites the file with two-space
  indent (`:218`).
- `check` (`:222-245`): reports `missing`, `fill in` (placeholders), `draft` (a `<prompt>` outside
  code, `:232-235`) or `ok` per file, then checks `ada@pie` and the Brief approval section, and
  runs `ada-brief who`.

Traps:

- `settings` crashes with a Python traceback on a `settings.json` that isn't strict JSON (comments,
  trailing commas) (`:181`), and rewrites the whole file in its own two-space format (`:218`), so
  a hand-formatted file comes back reformatted.
- `settings` sets `attribution` and the marketplace unconditionally (`:182-186`): a project's own
  values there are overwritten.
- `refresh` recreates the PR template if the project deleted it (`project is None`, `:137`).
- `check` and `settings` assume a PHP project: `check` lists `tools/composer.json`,
  `phpcs.xml.dist`, `phpunit.xml.dist` (`:224-226`); `settings` allows `php -l`, phpcs, phpunit
  (`:200-201`); `gitignore` adds `.phpunit.cache/` and `tools/vendor/` (`:157`). In this repo those
  show as missing and are expected to.
- `files` and `refresh` read templates from `$(dirname "$0")/../templates/repo` (`:37`), so the
  copy of the script you run decides which templates you get (see Environments).

### ada-plan: plans of several units

- The plan is the main checkout's: `ada-plan` `cd`s to `ada-worktree main`, not to
  `--show-toplevel`, so every subcommand reads and writes the main checkout's
  `.claude/handoff/plan/` (and its `PROJECT.md`) from any worktree. `ada-brief submit` takes a
  unit's draft brief and `PLAN.md` from there too, and `ada-handoff-init --archive-plan` archives
  it there.
- Reads the `## Units` table, which must have exactly the columns `ID | Unit | Task | Branch |
  Parent | Ships` (`bin/ada-plan:104-112`); rows whose cells all start `<` are the template's
  example (`:118-119`). Validates IDs, task ids, branch names, parents (two levels at most), Ships
  (`independent` or `after Unit X, ...`, same level), duplicates and cycles (`:125-169`).
- The plan file is `.claude/briefs/<epic>-plan.md`, where `<epic>` is the last run of 6+ digits in
  the `Epic:` URL (`:98-101`, `:178`). No Asana URL is a problem (`:126-127`).
- `branches` (`:214-229`) creates missing branches with `git branch --no-track`, parents first:
  from `origin/<branch>` if it exists, else the parent's branch, else `origin/<Branch from>`.
- `status` (`:231-295`) calls `gh pr list` and `ada-brief status` per unit. A top-level unit is
  "shipped" only if its merge commit is an ancestor of `origin/<Branch from>` (`:253-256`).

Traps:

- `Branch from` defaults to `production` when `PROJECT.md` doesn't say (`:71-72`, `:96`), and
  `branches`/`status` die if `git fetch origin <it>` fails (`:76`). This repo has no `production`
  branch, so `PROJECT.md` must name the right one before `ada-plan` works here.
- **Squash merges never "ship".** A squash-released unit's merge commit is never an ancestor of
  the production branch, so it stays "waiting for release" (`:268` says to ask).
- `status` is slow: two to three `gh` calls per unit plus one `ada-brief who`.

### The release workflow

`.github/workflows/release.yml`, on every push to `main` (`:13-16`), one at a time
(`concurrency`, `:22-24`):

1. `check_for_release` (`:29-42`): `rymndhng/release-on-push-action` in dry run, no prefix, with
   `bump_version_scheme: norelease`, so only a `release:major|minor|patch` label on the merged PR
   gives a version (`:1-2`).
2. `create_release` (`:44-70`): runs `.github/scripts/release-bump.py <version> <notes>`, which
   refuses a non-`x.y.z` version (`release-bump.py:25-26`), an absent Unreleased section (`:31`),
   or one that is empty or `Nothing yet.` (`:33-34`); moves the entries under
   `## <version>, <d Mon YYYY>` (`:36-38`); replaces the one `"version"` in `plugin.json` by regex,
   keeping its layout (`:41-45`). Then commits `Release <version>` as "Github Actions", tags with
   the plain version, pushes main and the tag (`release.yml:60-67`), and creates the GitHub
   release with those notes (`:69-70`).
3. `restore_next_release` (`:73-90`): if `next-release` is gone (GitHub deletes merged head
   branches, `:9-11`), pushes `main` as the new `next-release`.

Traps:

- **A failed release leaves `next-release` deleted.** `restore_next_release` runs only if
  `create_release` didn't fail (`:75`). A labelled PR with nothing under Unreleased fails in
  `release-bump.py`, so `next-release` must then be put back by hand.
- If `next-release` survives the merge (`:85-86`), it does not get the `Release x.y.z` commit, so
  it and `main` differ in `plugin.json` and `CHANGELOG.md`, and the next release PR will conflict
  in `CHANGELOG.md`. It normally doesn't survive: the repo deletes merged head branches
  (`delete_branch_on_merge` is on, checked 16 Oct 2026).
- The version comes from the latest GitHub *release* (`DEVELOPING.md:127`), not tags: the action
  reads `releases/latest` (`rymndhng/release-on-push-action` at the pinned SHA,
  `src/release_on_push_action/github.clj:56-64`, `core.clj:57-76`). A tag without a GitHub
  release is ignored, so never delete or skip the release.
- The workflow pushes to `main` with `GITHUB_TOKEN` (`release.yml:67`). That works because `main`
  has no branch protection and the repo has no rulesets (checked 16 Oct 2026). Adding protection
  to `main` will break releases unless the workflow is allowed to bypass it.
- The date is the runner's (UTC) date (`release-bump.py:36`).

### ada-worktree: one worktree per step

- `new <branch> [<base>]`: the path is always `<main checkout>/.claude/worktrees/<slug>` (slug:
  the branch less its first `<type>/`, further `/` as `-`), even when run from a worktree, so
  worktrees never nest. Every refusal (path or branch exists, the main checkout's git doesn't
  ignore the path, or with no `<base>` the branch is missing or checked out elsewhere, bad base)
  comes before anything is created. The ignore check uses the main checkout's rules
  (`git -C <main> check-ignore`), since the worktree sits in its folder. With a base,
  `git worktree add --no-track -b`; without, `git worktree add <path> <branch>` (a plan's unit).
  It then copies the file lines of `ada-onboard gitignore --list` that exist in the current
  checkout, runs `ada-handoff-init` there, and prints the absolute path last.
- `check` reads the current checkout's `CHECKPOINT.md`, the first `Branch:` or `- Branch:` line,
  first word. Empty or missing is `none`; a value that isn't a branch name, or names no local
  branch (`git show-ref --verify refs/heads/<b>`), exits 2.
- `list` reads `git worktree list --porcelain`; one `gh pr list --head` per worktree when `gh`
  is logged in.
- `remove <path>`: all checks first (a worktree of this repo, not the main checkout or the one
  it runs in, not locked, `git status --porcelain --untracked-files=all` empty, its
  `.claude/handoff/` holds only `*.md`, the archive folder doesn't exist). Then it copies the
  `*.md` files and runs `git worktree remove` without `--force`, which deletes the folder with
  its ignored files (the handoff files, the copied local config). If `git worktree remove`
  refuses anyway (a submodule, a file that appeared since the checks), it deletes the copies and
  the archive folder, so the run can be repeated. The branch is never touched.

Traps:

- **`remove` deletes a directory.** Ignored files in the worktree (anything git-ignored, not just
  the handoff files it archives) go with it. That is why it refuses a handoff folder with
  anything but `*.md` in it (a `plan/` or `archive/` there would be lost).
- **`new` needs Python** through `ada-onboard gitignore --list`, though `ada-worktree` itself has
  none.
- The local config is copied from the checkout `new` runs in, not from the main checkout.
- A checkpoint's `Branch:` in backticks, with words after the name, or naming a deleted branch
  makes `check` exit 2: Ada writes the name alone (`/ada:begin`, Session end). Checkpoints
  written by 0.9.0 and earlier hold free text there; begin and pickup handle the exit.

## Environments and flags

- **Branches.** `main` is released and is what the marketplace installs (`DEVELOPING.md:134-136`).
  Feature PRs go into `next-release` (`DEVELOPING.md:116`); releasing is the `next-release` →
  `main` PR with a label (`DEVELOPING.md:118-123`). Feature branches are e.g.
  `add/manual-test-steps`, `dev/ada-onboard/self` (from `git branch -a`).
- **Which copy of a script runs.** `ada-brief` etc. on the PATH are the *installed* plugin's
  (`~/.claude/plugins/cache/pie/ada/<version>/bin`, `DEVELOPING.md:42,53`), not this checkout's.
  To run edited scripts, call them by path (`plugins/ada/bin/ada-brief`) or start Claude with
  `--plugin-dir` and `ada@pie` switched off in `.claude/settings.local.json`
  (`DEVELOPING.md:15-22`). Template lookups follow the script's own location (`bin/ada-onboard:37`,
  `bin/ada-handoff-init:20`, `bin/ada-plan:31, 35`).
- **Flags.** `ADA_BRIEF_REQUIRE=1` makes everyone `required` for a dry run (`bin/ada-brief:13-14`,
  `:591`). `prod-wp` reads `PROD_*` from `.claude/team/prod.env`, overridable by environment
  (`bin/prod-wp:17-24`). No production server exists for this repo, so `prod-wp` is never used here.
  `presstest` reads `LOCAL_URL` and `PRESSTEST_*` from `.claude/team/local.env`, likewise
  overridable, and `PRESSTEST_SUITE` / `PRESSTEST_STAGING_URL` override `PROJECT.md`. This repo's
  suite is `none`.
- **Platforms** (`DEVELOPING.md:138-148`): bash 3.2+ (all scripts tested under 3.2.57), Python
  3.8+ (written for 3.8, only run on 3.12), `git`, `gh` logged in. Windows Git Bash expected but
  not tested. How the scripts cope:
  - Python is found as `python3`, `python` or `py -3`, each tried with a version check, which skips
    the Microsoft Store stub (`bin/ada-brief:103-112`; same block in `ada-onboard:38-47`,
    `ada-plan:37-44`, `ada-size:41-49`).
  - Python output has `\r` stripped (`bin/ada-brief:113-114`, `bin/ada-plan:84`, `bin/ada-size:72`);
    files are written with `newline='\n'`.
  - No `mapfile` (bash 3.2): a `while read` loop instead (`bin/ada-brief:585`, `:694`); CHANGELOG
    0.3.0 records the bug this fixed (`CHANGELOG.md:191-192`).
  - Empty arrays are guarded before `"${arr[@]}"` under `set -u` (`bin/ada-brief:595`,
    `bin/ada-size:58`), which bash before 4.4 treats as unbound.

## Known gotchas

- The scripts `cd` to the repo root (`git rev-parse --show-toplevel`) and die outside a repo
  (`bin/ada-brief:101-102` and the same in each); `ada-handoff-init` falls back to `pwd`
  (`bin/ada-handoff-init:21`). In a worktree, the root is the worktree's, so its handoff files
  are its own. The plan is the exception: `ada-plan`, `ada-brief submit`'s plan paths and
  `ada-handoff-init --archive-plan` use the main checkout's (`ada-worktree main`).
- A script that runs a sibling script works out its own folder (`here=`) before that `cd`
  (`bin/ada-brief:99-100`), because a relative `$0` resolves against the folder it was started
  from. `ada-brief` runs `"$here/ada-worktree"` and `"$here/ada-brief"`, and `ada-onboard check`
  runs `"$here/ada-brief"` (`bin/ada-onboard:223-224`, `:243`), never `$0`.
- Exit code 2 is every script's usage or precondition failure (`die`, e.g. `bin/ada-brief:98`).
- `ada-brief who` with no Brief approval section succeeds with `not-needed` (`bin/ada-brief:587`):
  a missing section means no approval, not an error.
- `ada-size` counts everything under `.claude/` as docs and `tests/` as tests (`bin/ada-size:92-104`);
  `bin/` scripts have no extension, so their `#` comments count as code, not comments
  (`bin/ada-size:110-111`).
- `ada-size` prefers `origin/<base>` over the local branch (`bin/ada-size:35-38`); fetch first.
- The skills and agents are the product: wording changes in them change behaviour in every
  onboarded repo on the next release. Words that scripts parse (brief headings, `Approval:`,
  `Plan:`, status lines) are interfaces; see `API.md`.
- `.claude/handoff/DESIGN.md` always exists once `ada-handoff-init` has run, as `FINDINGS.md`
  does, so "is there a design?" is read from its `Status:` line, never from the file being
  there. The roles work to it only at `Status: agreed`; `/ada:begin` reads `draft` or `agreed` as
  a standalone `/ada:design` run only when no brief has been started (`BRIEF.md` has no Outcome
  yet). A `draft` design in a step Ada started is that step's design, not agreed yet: she doesn't
  confirm its requirements again, and its visual parts wait for a fix or a PIE waiver. The
  template's own line, `Status: draft | agreed | superseded`, means no design. When the developer
  says a standalone design isn't for this task, Ada sets it to `Status: superseded`; it stays
  until the step is archived, and the roles ignore it.
- There are two `DESIGN.md` files. `.claude/handoff/DESIGN.md` is the step's design (status,
  states, copy, contrast ratios) and names tokens (`{colors.primary}`) without their values. The
  project's `DESIGN.md`, at the repo root, holds the token values and only the format's own
  sections, so other tools can read it. `ada-handoff-init` copies only `templates/handoff/`
  (`bin/ada-handoff-init:20`), never `templates/DESIGN.md`.
- `templates/repo/` is PHP/WordPress-shaped (`phpunit.xml.dist`, `tools/composer.json`,
  `tests/unit/TestCase.php`). This repo was onboarded with them; its own knowledge files say
  where they don't apply.
