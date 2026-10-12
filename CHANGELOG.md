# Changelog

## 0.2.1, 12 Oct 2026

- Each role is credited by name. Ada commits the team's work (Bob and Tess no longer may), with
  `Co-Authored-By: Bob / Tess / Ada (…, Claude Code)` trailers and `Reviewed-by: Claudia`; the
  developer stays the author. `ada-onboard settings` switches off Claude Code's default
  attribution, and the PR template ends with the team's credit line.

## 0.2.0, 12 Oct 2026

- Brief approval. `PROJECT.md` gets a "Brief approval" section (Approvers, Trusted). When the
  developer's briefs need approval, Ada commits the brief to `.claude/briefs/`, opens a draft PR
  and stops until an approver adds the `brief-approved` label. Amendments are approved again.
- New `bin/ada-brief` (`who`, `submit`, `status`), allowed by `ada-onboard settings`.
- `/ada:onboard` asks who approves; `ada-onboard check` checks the section.
- The brief template has an `Approval:` line and the PR template a `Brief:` line.

## 0.1.0, 12 Oct 2026

- First public release, published to `pie/ada-and-friends` with a single clean commit.
- `ada-onboard check` ignores `<...>` inside code spans and fences, so syntax like
  `<type>/<slug>` is no longer reported as a draft prompt.
- Smoke-tested: `/ada:onboard` and `/ada:begin` on a scratch repo with `--plugin-dir`.

## 0.0.4, 10 Oct 2026

- New `/ada:onboard` skill and `bin/ada-onboard` (`files`, `gitignore`, `settings`, `check`).
- Skeleton templates for the project knowledge files, the root `CLAUDE.md`, `tests/README.md` and
  `tools/bughunt/README.md`.

## 0.0.3, 10 Oct 2026

- `/ada:bughunt` is generic: it reads `PROJECT.md` and uses `prod-wp`.
- `bin/prod-wp` takes its settings from the project's `.claude/team/prod.env` (parsed, not
  executed; values checked) and its inspectors from the project's `tools/bughunt/`. New
  `prod-wp config` command. The never-query list is per project (`PROD_FORBIDDEN`).
- Repo templates no longer name V&Me. They carry `{{PLACEHOLDERS}}` for `/ada:onboard`, plus a
  `prod.env` template.

## 0.0.2, 10 Oct 2026

- `/ada:begin` holds Ada's playbook, generalised: she reads `.claude/team/PROJECT.md`, checks her
  model, runs `ada-handoff-init`, spawns `ada:tess`/`ada:bob`/`ada:claudia`, and starts Tess's run
  mode on Sonnet. `/ada:pickup` loads it.
- New `standards` skill: the PIE rules P1–P29 (numbers kept from V&Me's S rules), preloaded into
  the agents.
- Bob, Claudia and Tess are generic and set their effort; Tess moves to Opus.
- New `bin/ada-handoff-init` and a `PROJECT.md` template.
- Removed `reference/`: its contents now live in the skills.

## 0.0.1, 10 Oct 2026

- Imported the team from pie/vandme as it stood after its first full `/begin` run (PR #545)
  and the lessons from it (PR #547). Not yet generalised; see "Work to 0.1.0" in the README.
