# Onboarding non-WordPress projects

Status: proposal, for PIE developers to correct. Nothing here is built yet; the plugin's behaviour
is unchanged by this branch.

## What this is for

Ada assumes a WordPress or WooCommerce site. PIE also builds small services that need no
WordPress: webhooks, importers, APIs, a queue worker. Our preference for these is
**Slim Framework** (PHP). This document says how such a repo is onboarded and what in the plugin
has to become a choice instead of an assumption.

Scope: PHP micro services, one repo each, one deployable. Not in scope: non-PHP stacks, monorepos
and front-end apps. A second flavour should be a later decision, made against this layout.

## The idea: a flavour

A project has one **flavour**, set once at onboarding and recorded as a line in
`.claude/team/PROJECT.md`:

```
Flavour: wordpress | slim
```

`wordpress` is today's behaviour and the default for a repo with no line, so existing projects
don't change. Everything below hangs off that line. It follows the precedent of `Versioning:` and
`PressTest suite:`, which are also free-text lines in PROJECT.md that scripts and prompts read.

## What a Slim project is

The fixed answers the onboarding drafts from, so a developer corrects rather than invents:

| Concern | Choice |
|---|---|
| Framework | Slim 4, PSR-7 (slim/psr7), PHP-DI for the container |
| PHP | 8.1 or later, `declare(strict_types=1)` throughout |
| Layout | `src/` (code, PSR-4 namespace from `NAMESPACE`), `public/index.php`, `config/`, `tests/` |
| Code style | PSR-12, checked with PHP_CodeSniffer |
| Static analysis | PHPStan, level set in `phpstan.neon.dist` (start at 6) |
| Tests | PHPUnit; HTTP routes tested by building a PSR-7 request and calling `$app->handle()` |
| Config | Environment variables read in one place (`config/`); `.env` never committed |
| Logging | PSR-3 (Monolog) to stderr, structured, no secrets or personal data |
| Local run | `composer start` (PHP built-in server) or the repo's compose file; `LOCAL_URL` as today |
| Data | None, or one database through PDO with prepared statements; migrations committed |

## What changes in the plugin

Grouped by effort. Paths are under `plugins/ada/`.

### 1. Onboarding (`bin/ada-onboard`, `skills/onboard/SKILL.md`)

- `ada-onboard files` takes `--flavour <name>` and copies `templates/repo/` (shared) then
  `templates/flavours/<name>/` over it. Today it copies everything with no selection.
- Fixed keys become per flavour. Slim drops `TEXT_DOMAIN`, `PROD_PREFIX` and `PROD_LOGS`; it keeps
  `PROJECT_NAME`, `PACKAGE`, `NAMESPACE`, `TIMEZONE`, `CODE_DIR` (`src`) and `PROD_SSH` (optional).
- `check` requires `phpcs.xml.dist`, `phpunit.xml.dist`, `tools/composer.json` and `prod.env`
  for `wordpress`; for `slim` it requires `phpstan.neon.dist` in place of `prod.env` unless the
  service has production access.
- `settings` writes the `prod-wp *` allow rule for `wordpress` only. The phpcs, phpunit and
  `composer -d tools` rules and the push and SSH denies are generic and stay.
- The skill's step 1 stops when the repo root *is* the theme or plugin. For `slim` the code is
  `src/` at the root; that stop is skipped. Step 2's fact table gets a Slim column (no
  `style.css`, no WP timezone in a database, no `wc-logs`).
- `refresh` covers the PR template only; it grows to cover the flavour's PR template.

### 2. Templates

Split `templates/repo/` into shared files and `templates/flavours/<name>/`.

| Stays shared | Moves to `flavours/wordpress/` | New in `flavours/slim/` |
|---|---|---|
| `CLAUDE.md`, `API.md`, `STANDARDS.md`, most of `handoff/`, `plan/` | `phpcs.xml.dist`, `tools/composer.json`, `tests/unit/TestCase.php`, `bootstrap.php`, `tools/bughunt/`, `prod.env` | `phpcs.xml.dist` (PSR-12), `phpstan.neon.dist`, `tools/composer.json`, a `TestCase.php` with a request-building helper and a fixed-clock helper, a `PROJECT.md` and `SYSTEM.md` with Slim prompts |

The PR template's "Surfaces checked" (Checkout, Emails, Template `@version`) becomes per flavour.
For Slim: Routes and status codes, Request validation, Auth and middleware, Outbound calls,
Config and env vars, Logging. `handoff/FINDINGS.md` and `TEST-PLAN.md` have WP-only lines
(order IDs, WP-CLI, presstest) that become generic wording or flavour text.

### 3. Standards (`skills/standards`, `skills/standards-tests`)

Tag each P rule with where it applies, so Bob and Claudia read only what fits:

- **Generic, both flavours:** P1-5, P8, P16-18, P20, P21, P30, P31.
- **WordPress only:** P10, P11, P15, P19, P23, P28.
- **Generic idea, WordPress wording (needs a Slim reading):** P6, P7, P9, P12, P13, P24-27. For
  example, "nonces and capability checks" becomes "authentication middleware and input
  validation on every route", and "`$wpdb->prepare()`" becomes "PDO prepared statements".
- **P29 (tests):** PHPUnit applies to both. PressTest is WordPress only; Slim's equivalent is the
  PSR-7 route tests above, and a service with outbound calls fakes them at the HTTP client.

The Slim project's own rules go in its `STANDARDS.md` (S numbers). A starter set belongs in the
Slim template: one handler per route, no business logic in route closures, typed DTOs at the
edges, errors returned through one error middleware, no `die` or `exit` outside `public/`.

### 4. Agents and skills (prose)

- **Claudia:** the security and WooCommerce review blocks (`agents/claudia.md:50-63`) become
  flavour sections. Slim security: route auth and authorisation, input validation, output
  encoding, prepared statements, secrets in env only, no personal data in logs, outbound timeouts.
- **Tess:** local run mode (`:115-160`) is WordPress end to end (WP-CLI, User Switching,
  admin login). For Slim her local check is a `curl` or PSR-7 request against `LOCAL_URL`,
  with no browser, so she never needs Chrome. The same safety rules hold: only `LOCAL_URL`, no
  credentials typed, every created record listed by ID for cleanup.
- **Bob:** unchanged beyond swapping phpcs for the flavour's checks (`php -l`, phpcs, phpstan,
  phpunit).
- **`begin`:** the local-site and `LOCAL_URL` steps become "the flavour's local check".
- **`bughunt`:** entirely WordPress. A Slim version reads the service's logs, replays a
  captured request against a local run, and never writes to production. It is a separate
  skill file, not conditionals inside the WordPress one, and can follow the first release.
- **`prod-wp`:** stays WordPress only. Slim services get no equivalent at first: production is
  read through logs the developer pastes in. A `prod-log` helper is a later question.

### 5. Metadata

`plugin.json` keywords and the standards skill description say "wordpress, woocommerce". They
should say the plugin serves PHP projects, WordPress and Slim.

## Build order

Each is a PR into `next-release`, small enough to review alone (P30):

1. **Flavour line, no behaviour change.** Add `Flavour:` to PROJECT.md's template and read it in
   `ada-onboard`; `wordpress` stays the default. Move the WordPress templates under
   `flavours/wordpress/` and prove `ada-onboard files` output is byte-identical for an existing
   project.
2. **Tag the P rules** by flavour. Docs only.
3. **Slim templates and onboarding.** The `flavours/slim/` files, `--flavour slim`, the Slim
   fact table in the skill, `check` and `settings`.
4. **Agents.** Claudia's Slim review section; Tess's HTTP-based run mode; the `begin` wording.
5. **Slim `bughunt`.**
6. **Prove it on a real service.** Onboard one PIE micro service end to end with `/ada:begin`
   on a throwaway change, and fix what that shows before calling the flavour done.

## How it gets checked

There are no automated tests here (`tests/README.md`). Each PR above is checked by hand on a
scratch repo: onboard a fresh Slim skeleton and a copy of an existing WordPress project,
diff the output, then run `/ada:begin` on each. PR 1 is the regression guard for WordPress.

## Open questions

1. **Production access for services:** is there SSH to anything, or only a log platform? That
   decides whether `PROD_SSH` and a `prod-log` helper exist for Slim at all.
2. **Deployment target** (container, Plesk, a PaaS): the `settings` deny on pushes to
   `production` assumes PIE's branch names. Do services use the same branches?
3. **Static analysis level and PHP floor:** PHPStan 6 and PHP 8.1 are proposed; PIE may
   already have a preference.
4. **Versioning:** services are one-off client deliverables or shared software? P31 and
   `Versioning:` already distinguish these; the template should ask.
5. **Database:** is a standard migration tool wanted (Phinx, Doctrine Migrations), or is that left
   to each service?

## Found while surveying, to log separately

Not fixed on this branch:

- `templates/repo/phpcs.xml.dist:3` hardcodes a client description ("V&Me shop").
- `templates/repo/phpcs.xml.dist:11-12` hardcodes `themes` and `plugins` instead of `{{CODE_DIR}}`.
- `templates/repo/tests/unit/TestCase.php:4` hardcodes "a UK shop" and two-decimal prices.
- `templates/repo/.github/pull_request_template.md` and `templates/handoff/TEST-PLAN.md` cite
  "S29" where the rule is P29.
