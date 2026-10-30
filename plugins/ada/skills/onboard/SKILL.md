---
name: onboard
description: Set a project repo up for the ada team. Copies the templates, writes the settings, and drafts the project knowledge (PROJECT.md, SYSTEM.md, API.md, STANDARDS.md, TEST-AREAS.md, CLAUDE.md) from the code for a developer to correct. Use once per repo, before the first /ada:begin.
---

# Onboarding a repo

You set this repo up so Ada, Bob, Claudia and Tess can work in it. The mechanical parts are done
by `ada-onboard` (on your PATH), so they're the same in every repo. Your part is the knowledge.
The team is only as good as `SYSTEM.md`: a thin or wrong one gives confident, wrong work. So
**draft from evidence, mark what you couldn't confirm, and hand it to the developer to correct**.
Never invent a fact to fill a gap.

**Load the `ada:efficiency` skill** (Skill tool) and work to it. Drafting the knowledge means
surveying a whole codebase: send each broad survey (the layout, the hooks, the data model, the
deploys) to an Explore agent and keep its findings with their file references, rather than
reading the code into this session.

## 1. Check before you start

- You're at the root of a git repo, and the working tree is clean. If not, stop and say so.
- `.claude/team/PROJECT.md` doesn't exist yet. If it does, the repo is already onboarded: run
  `ada-onboard check` and help with whatever it lists, rather than starting again.
- **The layout.** The team expects deployable code under folders (e.g. `themes/<theme>/`,
  `plugins/`), so the repo root can hold `tools/`, `tests/` and `.claude/`. If the repo root *is*
  the theme or plugin, say so and stop. Moving it is its own PR: `git mv` into `themes/<theme>/`,
  repoint the deploy workflows, and update the local setup. Offer to plan it, then onboard
  afterwards.
- Create a branch for the onboarding: `dev/ada-onboard/<task>`, from the branch the developer says
  work starts from. Ask them for the task link, if there is one.

## 2. Gather the facts

Read what you can before asking. Then put **one short list** to the developer for what you
couldn't find or aren't sure of, and wait for answers:

| Fact | Where to look first |
|---|---|
| Project name, client, people | git log authors, README, `style.css` / plugin headers |
| Code directory (`CODE_DIR`) | the repo layout |
| Text domain, package, namespace | `style.css`, plugin headers, `namespace` lines, `phpcs.xml.dist` |
| Timezone | ask (WordPress's own setting is in the database) |
| Branches, environments, deploys | `.github/workflows/`, branch names on the remote |
| Known-issues project | ask |
| Brief approval | ask: "Will anyone outside PIE write code here?" No → `Approvers: none`. Yes → the GitHub logins of the PIE people who approve briefs, and any client developers trusted to build without approval (`Trusted:`). Collaborator logins: `gh api repos/<owner>/<repo>/collaborators -q '.[].login'`. If briefs need approval, ask whether any change on this project should always be approved again, beyond the defaults in `/ada:begin` (its `approval.md`); list them on `Re-approval triggers:`, else leave `none` |
| Budget | ask: "Is this a project where a slip costs little (a brochure site, no payments or subscriptions)?" Yes → `lean`; otherwise `standard`. Say they can change it at any time, and that a step in a fragile area should run on `standard` |
| Fragile areas | ask, and look for the code that's changed most and broken most (git log, reverts, "fix" commits) |
| Clock-sensitive behaviour | look for date and weekday logic, cut-off times and scheduled actions; then ask: yes (with the times to test, e.g. Friday after noon) or no |
| Production SSH, table prefix, logs path | ask; WP Engine is `<install>@<install>.ssh.wpengine.net` and `sites/<install>/wp-content/uploads/wc-logs` |
| Expensive or sensitive tables, classes whose constructors write | ask, and grep for views, `CREATE VIEW` and constructors that save |
| How local copies are made safe | ask |
| PressTest suite | the folder names under `all-tests/` in a pie/presstest.io clone, if the developer has one (ask where); then ask them to confirm which is this project's, or `none` |
| PressTest staging site | ask: the staging URL PressTest may run against, or `none` if there's no staging or Cloudflare's bot challenge blocks PressTest there. Never production |
| Design guide (what Iris designs within) | if the repo has a root `DESIGN.md`, `Design guide: DESIGN.md`; else a style guide or pattern library in the repo (e.g. under `docs/`, a `styleguide` folder), or a link to one in the README; else `none`, for the developer to correct with a URL or a design system artifact link |

## 3. Copy the templates and write the settings

```sh
ada-onboard files PROJECT_NAME="..." PACKAGE=... NAMESPACE=... TEXT_DOMAIN=... TIMEZONE=... \
    CODE_DIR=... PROD_SSH=... PROD_PREFIX=... PROD_LOGS=...
ada-onboard gitignore
ada-onboard settings
```

- `files` never overwrites. Anything it reports as `kept` already existed: merge the template's
  intent into it by hand, if it's needed at all. It records a hash of each file it copies in
  `.claude/team/.template-hashes`; commit that with the templates. When a later plugin version
  changes the PR template, `/ada:begin` refreshes it from there (`ada-onboard refresh`).
- `settings` changes what Claude may run in this repo. **Show the developer the printed result and
  get their yes before you commit it.** It enables the plugin, allows the read-only and lint
  commands, and denies pushes to the production and release branches and direct SSH to
  production.
- If the project has no production access to set up, leave `PROD_SSH` out. `prod-wp` then says so
  instead of guessing.

If briefs need approval, suggest a `.github/CODEOWNERS` line, `/.claude/ @<an approver>`, so a
change to the approval lists (or any team file) needs a PIE review. It only takes effect with
branch protection requiring code-owner review; say so, and leave turning that on to PIE.

Commit the onboarding with the trailer `Co-Authored-By: Ada (Claude Code) <ada@ada.pie.co.de>`.

Then `composer install -d tools`. Run `tools/vendor/bin/phpunit` to check the harness loads; with
no tests yet, a "no tests executed" result is expected. Edit `tests/unit/bootstrap.php` to load the
first file worth testing, if there's an obvious one: a file of pure business rules.

## 4. Draft the knowledge

Fill the templates in `.claude/team/` and the root `CLAUDE.md` from the code, its history and any
existing docs:

- **`PROJECT.md`:** the facts from step 2. Fill every section; write "none" rather than leaving a
  prompt. Fill the `Design guide:` line under The project (add it if `PROJECT.md` was there
  before and has none): `DESIGN.md` for a root `DESIGN.md`, another path in the repo, a URL, a
  design system artifact link, or `none`. Iris reads it when she designs (`ada:design`, Look
  first). Write the `PressTest suite:` line as the suite's folder name in a code span, first on
  the line (`` `vandme` ``), and the `PressTest staging:` line as the staging URL in a code span,
  or `none` for either: `presstest run` reads them.
- **`SYSTEM.md`:** the model, what fires when (hooks, scheduled actions, webhooks, cron), each
  fragile area, the environments, and the gotchas. Trace each claim to code and cite it
  (`file:line`). Mark anything you inferred but couldn't trace with *(unverified)*.
- **`API.md`:** the classes and helpers that already exist, with signatures, and a "Never" list.
- **`STANDARDS.md`:** the project's own rules from S100 onwards, its logging sources, and its
  owning classes. Leave the P rules to the plugin; don't copy them in.
- **`TEST-AREAS.md`:** the areas, numbered; where to observe results; the clock positions, if
  anything depends on time. If nothing does, Dates and the clock says "none" and nothing else:
  delete its `ada-clock` steps and rules.
- **`CLAUDE.md`:** short. The layout, the team commands, and the project's one or two hard rules.
- **`prod.env`:** add the never-query list (`PROD_FORBIDDEN`) once you know it.

Depth matters most where the fragile areas are. A shallow line about a stable area is fine; a
shallow line about the area that breaks is not.

## 5. The local clock

Only when `PROJECT.md`'s Clock-sensitive behaviour line says yes. When it says no, skip this
step: don't run `ada-onboard clock` or any `ada-clock` command, and don't ask about the clock.

`ada-clock` (on your PATH) moves the local site's clock for date checks, so nobody changes the
computer's clock to test a date. Each developer's setup is in `.claude/team/local.env`, which
`ada-onboard gitignore` keeps out of git (step 3): nothing in this step is committed. Read only
the lines of `local.env` named here; the file also holds the developer's secrets.

1. **Write the setup.** Ask the developer for the site's WordPress root (on Local, the site's
   `app/public`, as an absolute path) and the command that runs WP-CLI on it (`PROJECT.md`,
   Running WP-CLI on the local site; else `wp --path=<the root>`), unless you have them. Then
   run `ada-onboard clock` with both, quoted:

   ```sh
   ada-onboard clock LOCAL_WP_PATH="<the root>" LOCAL_WP="<the command>"
   ```

   It picks the setup, `local-linux` on Linux with Local installed and `manual` anywhere else,
   and writes `ADA_CLOCK_ADAPTER`, `LOCAL_WP_PATH` and `LOCAL_WP` to `local.env`, keeping any
   value already there. Tell the developer the setup (its `setup:` line) and which keys it kept.
2. **`LOCAL_URL`.** Every `ada-clock` command but `extension` needs the local site's address in
   `local.env`. Check for that line only:
   `grep -E '^[[:space:]]*LOCAL_URL[[:space:]]*=' .claude/team/local.env`. If it's missing, ask
   the developer to add `LOCAL_URL=<the site's address>` (its host `localhost`, `127.0.0.1`, or
   ending in `.local` or `.test`), and wait before step 3.
3. **Ask before `ada-clock install`.** It changes the site, and under `local-linux` Local's own
   files outside this repo, so ask the developer first, saying what it changes, and run it only
   on their yes:
   - Either setup: it copies a mu-plugin into the site's `wp-content/mu-plugins/` and, when the
     site is inside this repo, adds two lines to `.git/info/exclude`.
   - `local-linux`, outside the repo: Local's `php-fpm` for the site's PHP build is wrapped, and
     that wrapper is shared by every Local site on the same build (the others stay on real
     time); three lines are added to the site's pool template
     (`conf/php/php-fpm.d/www.conf.hbs`); and a clock file is written in
     `~/.config/ada-clock/`. `ada-clock uninstall` undoes each of them.
   - `local-linux` needs libfaketime first: the developer runs `sudo apt install faketime`
     themselves. Never run `sudo` yourself.
   - `local-linux`: afterwards the developer restarts the site in Local (Stop site, then Start
     site), so the wrapper and the pool lines load.
   - Then run `ada-clock status`. Exit 0 with the first line `ada-clock: real` means it works;
     otherwise show the developer what it printed.
4. **Offer the Chrome extension only once `ada-clock install` is done.** The extension takes the
   fake time from the `ada_clock` cookie that the installed mu-plugin sets, so without the
   install it does nothing.
   - If the developer declined `ada-clock install`, don't offer it. Tell them the Chrome
     extension comes with the install: once they've run `ada-clock install`, they can run
     `ada-clock extension`.
   - If the install is done, offer it for date checks in the browser. While the clock is set, it
     gives the site's own JavaScript the fake time. On the developer's yes, run
     `ada-clock extension`; it copies the extension to `~/.config/ada-clock/chrome-extension/` and
     prints how to load it in Chrome (`chrome://extensions`, Developer mode, Load unpacked).

## 6. Hand over

1. Run `ada-onboard check` and fix what it lists, except the prompts only the developer can
   answer.
2. Commit on the onboarding branch: the templates and settings in one commit, the drafted
   knowledge in another, so the knowledge can be reviewed on its own.
3. Tell the developer, in a short list:
   - what you drafted, and how many lines are marked *(unverified)*;
   - what you couldn't find out;
   - that **they must read and correct `SYSTEM.md` and `PROJECT.md` before the first
     `/ada:begin`**;
   - if the project has a PressTest suite, that each developer adds their `PRESSTEST_*` lines to
     `.claude/team/local.env` (`PROJECT.md`, Local copies), then checks them with
     `presstest config`;
   - if the project is clock-sensitive, the clock setup `ada-onboard clock` chose, and what is
     still to do for it (`LOCAL_URL`, `sudo apt install faketime`, `ada-clock install`, the
     restart in Local). Each other developer runs `ada-onboard clock` and `ada-clock install`
     on their own machine.
4. Open the PR as `PROJECT.md` says, with `gh` if it's available. The PR description lists the
   unverified claims so the reviewer can check them.
5. Tell them how teammates get the plugin. When they open the repo, they accept the folder trust
   prompt, then run `claude plugin install ada@pie --scope project` once. Their machine needs git
   access to `pie/ada-and-friends`.

## Never

- Invent a fact about the system to fill a section. "Not known yet" is a valid entry.
- Overwrite an existing file. `ada-onboard` won't, and you don't by hand either.
- Commit `settings.json` changes the developer hasn't seen.
- Move the code layout as part of onboarding.
