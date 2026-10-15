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
| Brief approval | ask: "Will anyone outside PIE write code here?" No → `Approvers: none`. Yes → the GitHub logins of the PIE people who approve briefs, and any client developers trusted to build without approval (`Trusted:`). Collaborator logins: `gh api repos/<owner>/<repo>/collaborators -q '.[].login'` |
| Fragile areas | ask, and look for the code that's changed most and broken most (git log, reverts, "fix" commits) |
| Production SSH, table prefix, logs path | ask; WP Engine is `<install>@<install>.ssh.wpengine.net` and `sites/<install>/wp-content/uploads/wc-logs` |
| Expensive or sensitive tables, classes whose constructors write | ask, and grep for views, `CREATE VIEW` and constructors that save |
| How local copies are made safe | ask |

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
  prompt.
- **`SYSTEM.md`:** the model, what fires when (hooks, scheduled actions, webhooks, cron), each
  fragile area, the environments, and the gotchas. Trace each claim to code and cite it
  (`file:line`). Mark anything you inferred but couldn't trace with *(unverified)*.
- **`API.md`:** the classes and helpers that already exist, with signatures, and a "Never" list.
- **`STANDARDS.md`:** the project's own rules from S100 onwards, its logging sources, and its
  owning classes. Leave the P rules to the plugin; don't copy them in.
- **`TEST-AREAS.md`:** the areas, numbered; where to observe results; the clock positions, if
  anything depends on time.
- **`CLAUDE.md`:** short. The layout, the team commands, and the project's one or two hard rules.
- **`prod.env`:** add the never-query list (`PROD_FORBIDDEN`) once you know it.

Depth matters most where the fragile areas are. A shallow line about a stable area is fine; a
shallow line about the area that breaks is not.

## 5. Hand over

1. Run `ada-onboard check` and fix what it lists, except the prompts only the developer can
   answer.
2. Commit on the onboarding branch: the templates and settings in one commit, the drafted
   knowledge in another, so the knowledge can be reviewed on its own.
3. Tell the developer, in a short list:
   - what you drafted, and how many lines are marked *(unverified)*;
   - what you couldn't find out;
   - that **they must read and correct `SYSTEM.md` and `PROJECT.md` before the first
     `/ada:begin`**.
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
