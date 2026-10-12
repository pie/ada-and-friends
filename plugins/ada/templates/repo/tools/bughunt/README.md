# Bug-hunt inspectors

Read-only PHP scripts that `prod-wp inspect <name> [args]` runs on production through
`wp eval-file`. Each must say `READ-ONLY` in its first five lines, or `prod-wp` refuses it.

Rules for a script here:
- Read only: no `save()`, `update_*()`, `add_*()`, `delete_*()`, `set_*()` or `do_action()`.
- Don't construct classes whose constructors write (`PROJECT.md` lists them). Read raw data.
- Print IDs and states, not customer personal data.
