# Ada and friends

The project profile. Every role reads this first: it is what makes the shared team specific to
this project. Keep it short and factual; the detail lives in `SYSTEM.md`, `API.md`,
`STANDARDS.md` and `TEST-AREAS.md`.

## The project

- **What it is:** the `ada` Claude Code plugin (Ada, Bob, Claudia and Tess), distributed through
  the `pie` marketplace in this repo and used across PIE's WordPress and WooCommerce projects.
- **Client:** PIE (internal). Approves: Neil Miller. Releases: Neil Miller.
- **People:** Neil Miller (PIE). No client developers.
- **Known issues live in:** https://app.asana.com/1/100438094841948/project/1206474484537567/board/1206474588730442.
  Search it before investigating a bug.

## Code and branches

- **Code:** `plugins/ada/` (agents, skills, `bin/` scripts, templates); `.claude-plugin/` (the
  marketplace); `.github/workflows/` and `.github/scripts/` (the release).
- **Branch from:** `main`. **PR into:** `next-release`. **Test on:** a scratch git repo,
  or a PIE project with the plugin installed from the branch (`TEST-AREAS.md`).
  Releases: the `next-release` → `main` PR, labelled `release:major|minor|patch` (README,
  Releasing). The marketplace installs from `main`.
- **Branch names:** `<type>/<slug>`, e.g. `add/manual-test-steps`
- **Versioning:** `semver at release` (used in more than one place: no version numbers until
  next-release goes into main, P31)

## Brief approval

Whose briefs need approving before anything is built. `ada-brief` reads this section; keep the
two lines as they are and change only the values.

- **Approvers:** none
- **Trusted:** none

Approvers never need approval. Anyone on neither list does: a new developer is covered without
anyone remembering to add them. Approval is the `brief-approved` label on the brief's draft PR,
added by an approver.

## Environments

| Branch | Environment | Host / install |
|---|---|---|
| `main` | Released: what `claude plugin install ada@pie` installs | GitHub `pie/ada-and-friends` |
| `next-release` | Unreleased work, gathered for the next release | GitHub `pie/ada-and-friends` |

## Fragile areas

The areas where a change needs the most care. Ada escalates changes here to the developer; Bob
reads their `SYSTEM.md` sections before touching them; Tess tests them as sequences of events, not
single events.

None named yet (Neil, 16 Oct 2026). Changes that reach every onboarded repo deserve the most care:
the PR template and `ada-onboard settings`/`refresh`, which write into other projects.

## Review checks (Claudia's Must Fix list)

Project-specific checks on top of the P rules, each pointing at its S rule: see `STANDARDS.md`.

## Tests

- **Unit tests:** see `tests/README.md`.
- **PressTest suite:** none
- **Clock-sensitive behaviour:** no

## Production access (for `/ada:bughunt`)

None. This repo has no production server; `/ada:bughunt` doesn't apply.

## Local copies

None: there is no site. Scripts are checked by running them in a scratch git repo
(`TEST-AREAS.md`).
