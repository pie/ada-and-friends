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

## Brief approval

Whose briefs need approving before anything is built. `ada-brief` reads this section; keep the
two lines as they are and change only the values.

- **Approvers:** <`none` if no one outside PIE writes code here; otherwise the GitHub logins who
  can approve, e.g. `@neilpie, @annawerno`>
- **Trusted:** <`none`, or the GitHub logins who don't need approval but can't approve>

Approvers never need approval. Anyone on neither list does: a new developer is covered without
anyone remembering to add them. Approval is the `brief-approved` label on the brief's draft PR,
added by an approver.

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

- **The local site runs the code in:** <the checkout its theme or plugin folder links to, e.g.
  `~/Repos/<repo>`>. Testing on the local site means testing whatever that checkout has checked
  out, so a branch switched there changes the site for everyone using it. To test a worktree,
  repoint the link at it (`ln -sfn <worktree>/<code dir> <site>/wp-content/themes/<theme>`) and
  point it back afterwards.
- **Running WP-CLI on the local site:** <how: e.g. Local's "Open site shell", or the command line
  with Local's PHP and MySQL socket>. Each developer's own paths go in `.claude/team/local.env`
  (git-ignored), never in this file.
- **Also checked before anything runs on the local site:** the background runners (Action
  Scheduler, WP-Cron) are frozen; the time WordPress reports from a web request is the intended
  time; nothing from an earlier run is left over (`.claude/handoff/CHECKPOINT.md`).
