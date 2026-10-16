# Ada and friends

The `ada` Claude Code plugin and the `pie` marketplace it installs from, maintained by PIE Code.
This file loads into every Claude session and every subagent, so it stays short (about 50 lines at
most); the detail lives in `.claude/team/` and is read on demand.

## Layout

| Path | What |
|---|---|
| `plugins/ada/` | The plugin: `agents/`, `skills/`, `bin/` (bash + Python scripts), `templates/`, `.claude-plugin/plugin.json` |
| `.claude-plugin/marketplace.json` | The `pie` marketplace |
| `.github/` | The Release workflow and `release-bump.py` |
| `README.md`, `DEVELOPING.md`, `CHANGELOG.md` | For users; for PIE developers; what changed (entries under Unreleased) |
| `.claude/team/` | The project knowledge: `PROJECT.md` first, then `SYSTEM.md`, `API.md`, `STANDARDS.md`, `TEST-AREAS.md`. |
| `tests/` | Functional scripts, run by hand. `tests/README.md`. |

The `ada-*` commands on the PATH are the **installed** plugin's, not this checkout's. To run an
edited script, call it by path (`plugins/ada/bin/ada-brief`) or use `claude --plugin-dir`.

## The team

This repo uses the `ada` plugin from PIE. Type `/ada:begin` to start a piece of work,
`/ada:pickup` to carry on from a checkpoint, or `/ada:bughunt` to investigate a reported problem.
If you have not been given a role, you are Ada.

## Rules for everyone

1. Branch, name and open PRs as `.claude/team/PROJECT.md` says. Feature PRs go into
   `next-release`; only the labelled release PR goes into `main`. **Nobody on the team merges.**
2. The PIE standards (P rules, in the plugin) and `.claude/team/STANDARDS.md` (S rules) apply.
   Reviews cite them by number.
3. No version changes in feature PRs (P31); add a CHANGELOG entry under Unreleased instead (S105).
   Scripts in `bin/` run on bash 3.2, Git Bash and Python 3.8 (S100, S101).
4. There is no production server here; `prod-wp` is for onboarded WordPress projects only.
5. Every PR supplies tests for what it changes (P29).
6. No secrets in source.
