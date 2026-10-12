# <Project name>

The project profile. Every role reads this first: it is what makes the shared team specific to
this project. Keep it short and factual; the detail lives in `SYSTEM.md`, `API.md`,
`STANDARDS.md` and `TEST-AREAS.md`.

## The project

- **What it is:** <one sentence: the site, what it sells or does, who uses it>
- **Client:** <name>. Approves: <who>. Releases: <who>.
- **People:** <PIE developers>, <client developers>
- **Known issues live in:** <Asana project name and URL>. Search it before investigating a bug.

## Code and branches

- **Code:** <e.g. `themes/<theme>/`, `plugins/`>
- **Branch from:** `production`. **PR into:** `next-release`. **Test on:** merge into `review`.
  (Change if this project differs from the PIE workflow.)
- **Branch names:** `<type>/<slug>/<Asana task URL without https://>`

## Environments

| Branch | Environment | Host / install |
|---|---|---|
| `production` | Production | <install> |
| `review` | Review | <install> |

## Fragile areas

The areas where a change needs the most care. Ada escalates changes here to the developer; Bob
reads their `SYSTEM.md` sections before touching them; Tess tests them as sequences of events, not
single events.

1. <area>: `SYSTEM.md`, <section>
2. <area>: `SYSTEM.md`, <section>

## Review checks (Claudia's Must Fix list)

Project-specific checks on top of the P rules, each pointing at its S rule:

- <e.g. any date arithmetic that isn't `<the one helper>()` (S14)>

## Tests

- **Unit tests:** `tools/vendor/bin/phpunit` (`tests/README.md`).
- **PressTest suite:** <suite name, or "none">
- **Clock-sensitive behaviour:** <yes/no; if yes, the positions to test, e.g. Friday after noon,
  Sunday>

## Production access (for `/ada:bughunt`)

Read-only, through `prod-wp`. Never write. Its settings (SSH, table prefix, logs path, never-query
list) are in `.claude/team/prod.env`; `prod-wp config` shows them.

- **Never construct on production:** <classes whose constructors write, if any, and why>
- **Inspectors:** `tools/bughunt/*.php`, each marked `READ-ONLY`. <List them.>

## Local copies

<How a local copy is made and made safe, e.g. pulled from production; Stripe in test mode;
emails disabled. Say what a check should verify.>
