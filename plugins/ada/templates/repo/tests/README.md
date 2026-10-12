# Tests

Every PR supplies tests for what it changes (P29): PHPUnit first, then PressTest, then a committed
functional script. Tess writes them from the brief; Bob builds until they pass and never edits
them.

```
tests/
  unit/              PHPUnit, one directory per plugin or module
  functional/
    TEMPLATE.md
    01-<area>/       one folder per area, numbered as in .claude/team/TEST-AREAS.md
```

## Unit tests

PHPUnit with Brain Monkey to stub WordPress and WooCommerce, so no WordPress install or database
is needed.

```sh
composer install -d tools
tools/vendor/bin/phpunit                          # everything
tools/vendor/bin/phpunit --filter SomethingTest   # one class or test
```

- Tests live in `tests/unit/<plugin or module>/`, one `*Test.php` per unit under test, extending
  the `TestCase` in `tests/unit/TestCase.php`.
- `TestCase` stubs `wp_timezone()`, the WooCommerce price-precision helpers and `__()`. Stub
  anything else with `Brain\Monkey\Functions\when()`.
- **Fix the clock with `$this->set_now( '2026-10-09 15:00' )`.** Code that reads
  `current_datetime()` then sees that moment. Code that reads the system clock directly can't be
  unit-tested until it's changed to `current_datetime()`.
- Expected values come from the brief or `SYSTEM.md`, never from running the code. Before handing
  over, break the code on purpose and check a test fails.
- `tests/unit/bootstrap.php` loads the files under test. Only files that just define functions,
  classes and constants load cleanly.

## Functional scripts

Checks a person runs where neither a unit test nor PressTest reaches: admin screens, emails,
scheduled actions, exports. They're committed, so they build up into the regression checks for
each area.

- One file per behaviour, in the folder for its area. Add to an existing script for the same
  behaviour rather than writing a near-copy.
- Start from `functional/TEMPLATE.md`. Steps are exact enough that someone who has never seen the
  change gets the same answer.
- `Automate as: unit` or `Automate as: presstest` marks a script that should become an automated
  test.
