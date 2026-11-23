# Onboarding templates fit any project: the right test rule, the no-approvers case, no `none\Tests` namespace, and no V&Me-specific text

- Area: 02 Onboarding (`ada-onboard`, `templates/`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/1204538801343792/task/1220130062605486
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-05, 12 PASS, 0 FAIL under bash 5.2 and bash 3.2 (see the PR)

## Precondition

- This checkout (`REPO`). The script makes its own scratch repos (paths with spaces) and runs the
  checkout's `ada-onboard files` in them.
- `git`, `mktemp`, `python3` (`ada-onboard`'s `PY` block).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run it under a current bash: `REPO=<this checkout> bash check.sh`, and under bash 3.2:
   `docker run --rm -v <this checkout>:/repo:ro -v <scratch folder>:/c bash:3.2 bash -c 'apk add -q git python3 >/dev/null; REPO=/repo bash /c/check.sh'`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, change the copy's
   `templates/handoff/TEST-PLAN.md` back to `S29`, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` lines.

```bash
#!/usr/bin/env bash
# The onboarding templates after the Ascott review fixes, and ada-onboard files' NAMESPACE check.
# Prints PASS/FAIL per check; exits 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
TPL=$PLUGIN/templates
W=$(mktemp -d)
fails=0
ok()   { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()    { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not()  { ! "$@"; }
# onboard <dir name> <KEY=value...>: ada-onboard files in a fresh scratch repo.
onboard() { d="$W/$1"; shift; git init -q "$d"; (cd "$d" && bash "$PLUGIN/bin/ada-onboard" files "$@") > "$W/out" 2>&1; echo $? > "$W/rc"; }

t 'the PR template cites the test rule as P29'       grep -q 'Tests supplied (P29)' "$TPL/repo/.github/pull_request_template.md"
t '... and not S29'                                  not grep -q 'S29' "$TPL/repo/.github/pull_request_template.md"
t 'the test plan template cites P29, not S29'        sh -c "grep -q 'P29' '$TPL/handoff/TEST-PLAN.md' && ! grep -q 'S29' '$TPL/handoff/TEST-PLAN.md'"
t 'PROJECT.md says Approvers none means no approval' grep -q 'When Approvers is `none`, no brief needs approval' "$TPL/repo/.claude/team/PROJECT.md"
t 'bootstrap.php has no Project\DATE_FORMAT example' not grep -q 'DATE_FORMAT' "$TPL/repo/tests/unit/bootstrap.php"
t 'nothing in the repo templates names V&Me'         not grep -rqiE 'V&(amp;)?Me|Meal deliveries' "$TPL/repo"
t 'phpcs.xml.dist describes the project by name'     grep -q 'standards for {{PROJECT_NAME}}' "$TPL/repo/phpcs.xml.dist"
t 'TestCase marks the WooCommerce helpers optional'  grep -q 'no WooCommerce' "$TPL/repo/tests/unit/TestCase.php"

onboard 'repo none' NAMESPACE=none
t 'ada-onboard files refuses NAMESPACE=none'         sh -c "test \"\$(cat '$W/rc')\" != 0 && grep -q 'NAMESPACE must be a PHP namespace' '$W/out'"
t '... and copies nothing'                           test ! -f "$W/repo none/tests/unit/TestCase.php"
onboard 'repo bad' NAMESPACE='Bad Name'
t 'ada-onboard files refuses a NAMESPACE with a space' test "$(cat "$W/rc")" != 0
onboard 'repo ok' NAMESPACE='Acme\Shop' PROJECT_NAME='Acme Shop'
t 'a real namespace fills TestCase as Acme\Shop\Tests' grep -q 'namespace Acme\\Shop\\Tests;' "$W/repo ok/tests/unit/TestCase.php"

rm -rf "$W"
echo "$fails failed"
[ "$fails" = 0 ]
```

## Expected

Every line `PASS`, `0 failed`, exit 0, under both bashes. The source of each expectation is the
Asana task (Copilot's second review of the Ascott onboarding) and `ada-brief` (Approvers `none`
reports `not-needed`, `bin/ada-brief:588`). With the broken copy, the `TEST-PLAN.md` check prints
`FAIL`.

## Observe at

The script's output and exit code.

## Regression

`ada-onboard files` with every key still fills every placeholder and never overwrites
(`tests/functional/02-onboarding/clock-templates-reach-new-projects-only.md`), and `ada-onboard
refresh` offers the changed templates to onboarded projects as template updates.
