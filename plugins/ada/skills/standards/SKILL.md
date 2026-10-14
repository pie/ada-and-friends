---
name: standards
description: PIE's shared coding standards (P rules) for WordPress and WooCommerce work. Preloaded into Bob and Claudia; read it before building or reviewing. A project's own rules (S numbers) are in its .claude/team/STANDARDS.md.
user-invocable: false
---

# PIE standards

What a PIE review checks beyond formatting, on any project. Each rule says why, because the why
tells you when a rule does and doesn't apply.

- **Formatting** comes from the project: `.github/copilot-instructions.md` if it has one, otherwise
  the WordPress Coding Standards as configured in its `phpcs.xml.dist`.
- **P rules** are PIE-wide and live here. **S rules** are the project's own, in
  `.claude/team/STANDARDS.md`. A project rule can narrow or override a P rule, and must say why.
- **Rules are numbered so a review can cite them** (`P5`, `S14`). Numbers never change; a new rule
  takes the next free number. P numbers match the original V&Me S numbers, which is why P14 and P22
  are unused.
- **Severity:** "Must fix" for a rule broken outright; "Should fix" for a judgement call.

P29, on tests, is in the `ada:standards-tests` skill, preloaded alongside this one.

Names below such as "the project's main classes" are filled in by the project's `API.md` and
`SYSTEM.md`.

## Logic

**P1. Be prescriptive, not defensive.** When the possible values are known (statuses, ACF choices,
weekdays, response shapes), compare against exactly those. No truthiness checks, no `?? default`
fallbacks, no alternative keys or selectors "just in case". A fallback hides the day the assumption
breaks.

**P2. Don't code around states that can't happen.** If a state means the system is broken, don't
skip or hide it. Let it fail, or log it (P15) and stop. Don't add `isset()` for keys that exist by
construction.

If a broken state really exists in the data (bad rows from an old bug), fix it in a **separate PR**:
a data repair branched from the production branch, deployed on its own, then merged into your
feature branch. Never work around it inside the feature.

**P3. Pass known values in; don't rebuild them.** Give a hook callback the value with a closure
rather than parsing it back out of `current_action()` or the request.

**P4. Validate where the mistake is made.** Check admin input and customer input on save, and show
an error there. Don't guess or silently drop bad values at read time on every page view.

## One place for each rule

**P5. No duplicated business logic.** Each business rule (pricing, minimum charges, cut-offs,
statuses and what they mean) lives in one function, in the module that owns it. JavaScript receives
the *result* as a data attribute or localised value, never a copy of the rule. A rule copied into a
second place is wrong in one of them sooner or later.

**P6. Use the existing API before writing new code.** Prefer the project's own classes and helpers
(`API.md`) and WordPress, WooCommerce and WooCommerce Subscriptions functions over re-implementing
them. Don't wrap a platform function unless the wrapper adds something real. Hiding where business
data is stored always counts as real (P25).

**P7. Named constants for known values.** Types, statuses, contact details, cut-off hours, field
keys, tab keys, status groups: define once, reference everywhere, including the admin help text that
describes them.

**P8. Put code in the module that owns the data.** Code that changes a module's data belongs in that
module, not in a front-end module or an integration. Don't reach into another module's stored data;
add to its API.

## Safety and other surfaces

**P9. Escape on output**, even when the value was sanitised on input. Don't `phpcs:ignore` output
escaping.

**P10. Declare dependencies where they're visible.** A minimum WooCommerce version in the plugin
header when code relies on a newer API; `Requires Plugins` for hard dependencies.

**P11. WooCommerce template overrides live in the theme's `woocommerce/` folder.** Swapping templates
in from a module folder with a `wc_get_template` filter is only for trials, so a new UI can be
switched on and off while it's tested. Once approved, move the overrides into `woocommerce/` (same
sub-paths), remove the filter, delete any old copies, and set each `@version` to the WooCommerce or
Subscriptions version it was checked against. Never raise `@version` without diffing against that
core template and porting its changes in the same change. Only overrides in that folder are checked
by WooCommerce → Status, so that is what warns when the source templates change.

**P12. Check every surface a change reaches:** checkout, emails, admin, exports, other plugins'
hooks and templates. Keep WooCommerce hooks when overriding templates.

**P13. Writes that arrive from outside a browser session are idempotent and leave a trail.** An
import row, a REST call or a scheduled action can be retried. A repeat must not charge, credit or
create anything twice.

- A write made for a person (a staff tool's REST call, an import) requires an `actor`: who made it.
  Reject the request without one.
- Each request leaves **one private** order note (or the project's equivalent record) listing every
  change it made and where it came from.
- The actor never reaches customer-facing text.
- Keep the actor out of any idempotency fingerprint, so the same change retried by someone else is
  still recognised as a repeat.
- Records that make a write idempotent are durable (P28).

## Logging

**P15. Log unexpected states with `wc_get_logger()`, not `error_log()`.** Level `warning` or `error`
for anything a person should look at; `debug` only for diagnostic output. One agreed source per
module, so the logs can be filtered in WooCommerce → Status → Logs. The project lists its sources in
`STANDARDS.md`; a new module gets a new source, named there. Include enough to find the record: the
ID and the bad value.

## Readability

**P16. No unclear abbreviations** in class names, variables, functions or CSS classes (`is-active`,
not `is-act`).

**P17. Use `getElementById` for single elements with an ID;** give an element an ID when it is
expected to be unique.

**P18. Comment non-obvious ordering or guards:** why a revert re-ticks before it unticks, why a
re-entrancy guard exists, why a hook has priority 99.

**P19. Pluralise with the translation functions, never by comparing to 1.** `_n()` in PHP;
`wp.i18n._n()` and `wp.i18n.sprintf()` in JS, with the `wp-i18n` dependency and
`wp_set_script_translations()`. Don't pass "one" / "many" strings for the JS to pick between.

**P20. Comments describe what the code does, not what it is meant to do.** Before writing "X is
checked in A, B and C" or "every Y goes through Z", trace each path, including hooks that only run
in some requests and other plugins' subclasses. If a path isn't covered, say so. Commit messages and
PR descriptions follow the same rule: what changed and what was verified, never coverage that wasn't
checked.

## Structure

**P21. One concern per PR**, so each can be reviewed and reverted separately.

**P23. Shared assets and constants live at theme level.** A feature module may use theme-level code;
nothing outside a module may depend on the module, so it can be switched off or moved without
breaking other pages.

**P30. Bring each function you touch up to standard, whole.** When a change touches a function or
method, the whole of it is made phpcs-clean (indentation, spacing, naming, the project's
formatting rules), not just the lines that changed. Never mix standards within one function;
within a class it's tolerated, but it isn't the aim. Legacy code gets better as it's worked on.

- The reformat is formatting only: no behaviour change rides along. It goes in its own commit, so
  the behaviour change can be reviewed on its own.
- A file riddled with legacy errors outside the touched functions is **not** swept into the PR.
  It goes in Known Gaps with its phpcs count and a suggested refactor task.
- Count findings from the total line of `phpcs --report=summary`, never by counting output lines.
- "phpcs clean on changed files" can't be met on a legacy file, so a brief says "phpcs clean on
  every touched function", with the file's count before and after.

Why: a function in two styles is harder to read than either, and "clean on the new lines" lets a
legacy file stay legacy for ever.

**P31. Shared software gets its version number at release, not before.** For PIE software used
in more than one place and versioned with semantic versioning (a plugin or tool we distribute),
a feature PR changes no version: not the plugin header, the `Stable tag`, the version in
`plugin.json`, `package.json` or `composer.json`, or a changelog heading, and its commits and
title carry no version number. Changelog entries go under `## Unreleased`. When next-release
goes into main, the developer decides Major, Minor or Patch from everything under Unreleased,
sets the version, renames the heading, and tags the main commit.

- `PROJECT.md` says which kind a project is (Versioning). A single-instance project (a client's
  own plugin or theme) isn't versioned this way: leave its version headers as they are unless
  the brief says otherwise.
- Not the same as a WooCommerce template override's `@version`, which tracks the template it
  copies (P11).

Why: several features land between releases, and the number a release gets depends on all of
them together. A number chosen on a feature branch is a guess the next feature can make wrong.

## Data and boundaries

**P24. Read REST input with the explicit getters.** `$request->get_url_params()`,
`get_json_params()` or `get_query_params()`, never `$request['key']` or `get_param()`. The shorthand
checks the JSON body before the URL, so a body `id` silently overrides the route's `id` and the write
lands on a different record from the one in the URL.

**P25. Business data is read and written through the object that owns it.** In functional code, no
`get_post_meta()`, `update_post_meta()`, `get_field()` or `update_field()`, and no WooCommerce
`->get_meta()` / `->update_meta_data()` on business data from outside the class that owns it. Call a
method on the owner that wraps the storage call. If the method doesn't exist, add it to the class;
don't go round it. Reads and writes alike. Why: when the storage changes (HPOS, custom tables, a
different field plugin), the change is made once in the class, not chased through the codebase.

- **Exempt:** ACF options pages, theme content fields read by templates, and a module's own custom
  post type meta inside that module.
- Applies to new and changed code. Existing direct calls are moved when touched. The project's
  `STANDARDS.md` names its owning classes and any missing accessors.

**P26. Text from outside that reaches the customer is cleaned and capped.** `sanitize_text_field()`
plus a length cap measured with `mb_strlen()`. Over-long input is rejected with a 400, never
truncated, or the caller's record and the invoice disagree.

**P27. A write that has landed is never reported as failed.** A REST write handler has a
`catch ( \Throwable )` beside any lock `finally`, which logs and returns the plugin's own JSON 500
rather than WordPress's critical-error page. Steps after the write (notes, transients, re-reads) sit
in their own try/catch: log the failure and still return 200. Otherwise the caller retries a change
that already happened.

**P28. Nothing that correct operation depends on is kept in a transient.** A transient is a cache
and can be gone before its expiry: with a persistent object cache it lives only in memory and is
evicted under pressure; without one it can be deleted by the expired-transient clean-up,
`wp transient delete --all` or a cache-flush plugin. Use transients only where losing the value costs
a repeat of some work (log throttles, cached reads). Idempotency records, locks-by-flag, "already
sent" markers and anything else that prevents a double charge, double email or double write go in
durable storage: meta on the owning object through its class (P25), or a table.
