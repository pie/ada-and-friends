# Ada and friends standards (S rules)

The PIE standards (P1 onwards) come with the `ada` plugin and apply here. This file holds only
this project's own rules, numbered S100 onwards so they never clash with a P rule. A project rule
may narrow or override a P rule; when it does, it says which and why.

Many P rules are about WordPress and PHP and have nothing to apply to here. P20 (what was
verified), P29 (tests), P30 and P31 (versions at release) do apply.

## Logging sources (P15)

| Source | Module |
|---|---|
| `none` | The scripts print results to stdout and errors to stderr; nothing is logged. |

## Owning classes (P25)

No classes. Each script owns its state, and nothing else writes it:

| State | Owner |
|---|---|
| `.claude/briefs/*.md`, the `brief-approved` label's removal, draft PR bodies | `ada-brief` |
| `.claude/team/.template-hashes`, `.claude/settings.json` merges | `ada-onboard` |
| `.claude/handoff/plan/PLAN.md` (created), unit briefs and branches | `ada-plan` |
| `.claude/handoff/` templates and `archive/` | `ada-handoff-init` |
| `plugin.json` version, CHANGELOG release headings, tags, `next-release` recreation | the Release workflow and `release-bump.py` |

## Project rules

**S100. Scripts run on bash 3.2 and Git Bash.** No `mapfile`, associative arrays, `${x,,}` or
other bash 4+ features; guard `"${arr[@]}"` on possibly empty arrays under `set -u`. Use only
POSIX-compatible `sed`/`grep` options that BSD tools also have. Evidence: the 0.3.0 Mac fix
(`CHANGELOG.md:191-192`), `bin/ada-brief:352`, `DEVELOPING.md:140-148`.

**S101. Python in scripts is 3.8+, found through the `PY` block.** Try `python3`, `python`,
`py -3` with a version check; strip `\r` from output; write files with `newline='\n'`; read with
`encoding='utf-8'`. No syntax or library newer than 3.8 (no `match`, no `str.removeprefix`, no
`X | Y` types). Evidence: `bin/ada-brief:48-59`, `CHANGELOG.md:193-194`.

**S102. Every script keeps the shared shape.** `set -euo pipefail`; a `die` that prints
`<script>: <message>` to stderr and exits 2; `cd` to `git rev-parse --show-toplevel`; validate
every argument used in a git ref or path with a regex before use; a header comment that is the
complete usage text, kept current with the code. Evidence: `bin/ada-brief:1-47`, `:371-374`.

**S103. Script output words are interfaces.** A status word or line format that a skill or
another script reads (`API.md`) changes only with every reader in the same PR.

**S104. Templates and parsers change together.** A heading, field line or table column in
`templates/` that a script parses changes only with that parser, in the same PR.

**S105. A CHANGELOG entry under `## Unreleased` in every PR that changes behaviour.** One bullet,
a bold lead naming the change, then what it means for a user. Replace `Nothing yet.` rather than
adding under it. Versions come at release (P31; not repeated here). Evidence: `CHANGELOG.md:3-8`,
`DEVELOPING.md:114-116`, `.github/scripts/release-bump.py:33-34`.

**S106. Feature PRs go into `next-release`.** Only the release PR goes into `main`, and it is
labelled `release:major|minor|patch` (`DEVELOPING.md:116-123`).

**S107. Docs, skills and agent files are in plain British English, short sentences.** One idea a
sentence, active voice, no filler. The README is for users; plugin internals go in
`DEVELOPING.md` (`CHANGELOG.md`, 0.5.1).

**S108. GitHub Actions are pinned to a full commit SHA**, with the version in a comment
(`.github/workflows/release.yml:36`, `:53`, `:79`).

**S109. Skill and agent wording is behaviour.** A change to `skills/` or `agents/` is reviewed as
code: say in the PR what behaviour changes in onboarded projects and how it was checked.
