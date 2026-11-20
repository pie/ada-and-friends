# On a feature step Tess runs the whole unit-test suite, only the PressTest files for what the step changes, clock positions only for date or time logic, and lists the rest under "Left to the release run"

- Area: 09 Roles (`agents/tess.md`, Plan mode and Run mode); 11 Templates (`templates/handoff/TEST-PLAN.md`,
  `TEST-REPORT.md`, `templates/repo/tests/README.md`)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220741559487512
- Automate as: none (part A is a self-checking shell script; parts B and C are dry runs of Tess in
  plan mode, each with a self-checking after-script)
- Last run: 2026-11-20, part A 0 failed (break-on-purpose: 22 failed as expected, staging-scope check added after PR #76 round 2); parts B and C run interactively by the developer, check-plan.sh (fixed) 0 failed on both plans; see the PR

Written by Tess from the brief (step 1, change/feature-step-tests), not from the change.

## Precondition

- Part A: this checkout, with the change in its working tree; `origin/next-release` fetched.
  Nothing else.
- Parts B and C: `claude` on the PATH, signed in; `git`. A scratch folder
  `S` outside any repo holding this script's blocks as `check.sh`, `make-sample.sh` and
  `check-plan.sh`. `REPO` is this checkout.
- `make-sample.sh <folder> display|dates` builds the practice project from nothing: a git repo
  with `ada@pie` off in `.claude/settings.local.json`; `PROJECT.md` naming a PressTest image, the
  shared suite `woocommerce-core` and clock-sensitive behaviour; `TEST-AREAS.md` with three
  PressTest files (`checkout_test.js`, `cart_test.js`, `account_test.js`) and four clock positions;
  `tests/README.md` whose "everything" unit command is `tools/vendor/bin/phpunit`; fresh handoff
  files from `ada-handoff-init`; and an agreed `BRIEF.md`:
  - `display`: change one checkout file to **display** a date `vm_next_delivery_date()` already
    gives, in an existing `.vm-note` line (an existing pattern, so no design step).
  - `dates`: change `vm_next_delivery_date()`'s cut-off from Thursday 23:59 to 18:00 (date logic).

## Steps

### Part A: the role file and templates

1. Save the first block as `$S/check.sh` and run `REPO=<this checkout> bash $S/check.sh`.
2. Break it on purpose: export the base (`git -C <this checkout> archive $(git -C <this checkout>
   merge-base HEAD origin/next-release) | tar -x -C <empty folder>`) and run
   `REPO=<that folder> BASE_REPO=<this checkout> BASE=<the merge-base> bash $S/check.sh`. Every
   `DoD` line FAILs except "says what a step runs (unit and PressTest)", the three "no version"
   lines, "tess.md has a staging run command" and the three "no bare ... staging in <template>" lines (the
   base has no bare staging command in the templates); every `regression` line PASSes. The "every ... names the
   scoped files" line for tess.md FAILs (the base staging run is bare).

```bash
#!/bin/bash
# A feature step's test scope, by the text of tess.md and the templates. PASS/FAIL; exit 1 if any FAIL.
# Env: REPO (the checkout under test), BASE_REPO (where to export BASE from; default REPO),
# BASE (default: the merge-base of BASE_REPO's HEAD and origin/next-release).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE_REPO=${BASE_REPO:-$REPO}
BASE=${BASE:-$(git -C "$BASE_REPO" merge-base HEAD origin/next-release 2>/dev/null)}
P=$REPO/plugins/ada
T=$P/agents/tess.md
TP=$P/templates/handoff/TEST-PLAN.md
TR=$P/templates/handoff/TEST-REPORT.md
RM=$P/templates/repo/tests/README.md
for f in "$T" "$TP" "$TR" "$RM" "$REPO/CHANGELOG.md"; do [ -f "$f" ] || { echo "SETUP FAIL: no $f"; exit 2; }; done
[ -n "$BASE" ] || { echo "SETUP FAIL: no BASE (set BASE=<commit>)"; exit 2; }
W=$(mktemp -d "${TMPDIR:-/tmp}/step-scope.XXXXXX") || exit 2
mkdir "$W/base" "$W/scratch"
git -C "$BASE_REPO" archive "$BASE" | tar -x -C "$W/base" || { echo "SETUP FAIL: can't export $BASE"; exit 2; }
B=$W/base
echo "info: base $BASE"
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
# items <section> <file>: each list item or paragraph of "## <section>", on one line
items() { awk -v h="## $1" '
  index($0, h) == 1 && length($0) == length(h) {p=1; next}
  p && /^## / {exit}
  !p {next}
  /^[[:space:]]*$/ {if (cur != "") print cur; cur = ""; next}
  /^[[:space:]]*([0-9]+\.|-|\*) / {if (cur != "") print cur; cur = $0; next}
  {sub(/^[[:space:]]+/, ""); cur = cur " " $0}
  END {if (cur != "") print cur}' "$2"; }
# sentences <section> <file>: the section's items split at ". "
sentences() { items "$1" "$2" | awk '{n = split($0, a, "[.] "); for (i = 1; i <= n; i++) print a[i]}'; }
# all_match: stdin lines that match every regex (any case); true if any
all_match() { out=$(cat); for re in "$@"; do out=$(printf '%s\n' "$out" | grep -Ei -- "$re"); done; [ -n "$out" ]; }
item_has() { s=$1; f=$2; shift 2; items "$s" "$f" | all_match "$@"; }
# para_has <file> <regex>...: some blank-line-separated paragraph matches every regex
para_has() { f=$1; shift; awk -v RS= '{gsub(/\n/, " "); print}' "$f" | all_match "$@"; }
# section_of <heading> <file>: the lines under "## <heading>" up to the next "## "
section_of() { awk -v h="## $1" 'index($0, h) == 1 {p=1; next} p && /^## /{exit} p' "$2"; }
headings() { grep -E '^#{1,3} ' "$1" | sed 's/[[:space:]]*$//'; }
sent_has() { s=$1; f=$2; shift 2; sentences "$s" "$f" | all_match "$@"; }

# DoD 1: run mode step 1, the whole unit suite on every step
t "DoD1: run mode runs the whole unit-test suite on every step, recording command and result" \
  item_has "Run mode" "$T" 'unit' '(whole|full|entire|complete|all)[^.]{0,40}unit|unit[^.]{0,40}(in full|whole|entire)' '(every|each)[^.]{0,20}step' 'record' 'command'
t "DoD1: run mode, for a project whose tests/README.md names no unit-test suite, says to record 'Unit tests: none in this project'" \
  item_has "Run mode" "$T" 'Unit tests: none in this project' 'tests/README\.md'
t "DoD1: plan mode's Unit tests item says the same (the amendment names both)" \
  item_has "Plan mode" "$T" 'Unit tests: none in this project' 'tests/README\.md'

# DoD 2: run mode step 2, PressTest scoped to the step
hits=$(grep -rniE 'everything if (the step |it )?touches checkout' "$P" "$REPO/.claude/team/SYSTEM.md" 2>/dev/null)
t "DoD2: 'everything if the step touches checkout' found nowhere under plugins/ada/ or SYSTEM.md" test -z "$hits"
[ -n "$hits" ] && printf '      found: %s\n' "$hits"
t "DoD2: run mode runs only the PressTest files covering what the step changes" \
  item_has "Run mode" "$T" 'presstest' 'only' 'cover' 'chang'
t "DoD2: run mode says a step never runs the shared suites" \
  sent_has "Run mode" "$T" 'shared suite' "never (run|runs)|(do|does)( not|n't) run|not (run|ran)|never[^.]*shared|no shared suite"
bad=$(sentences "Run mode" "$T" | grep -Ei 'shared suite' | grep -Eiv "never|not|n't|no |only|release")
t "DoD2: no run-mode sentence has the step's run include the shared suites" test -z "$bad"
[ -n "$bad" ] && printf '      sentence: %s\n' "$bad"
t "DoD2: run mode says fewer files rather than more" item_has "Run mode" "$T" 'presstest|files' 'fewer'
every=$(awk -v RS= '{gsub(/\n/, " "); print}' "$T" | awk '{n = split($0, a, "[.] "); for (i = 1; i <= n; i++) print a[i]}' \
  | grep -Ei 'presstest' | grep -Ewi 'everything' | grep -Eiv 'release')
t "DoD2: no sentence in tess.md runs PressTest 'everything' on a step (outside the release run)" test -z "$every"
[ -n "$every" ] && printf '      sentence: %s\n' "$every"
# A bare `presstest run` runs everything, shared suites included, so no sentence may hand one to the
# developer or run one on a step. Not counted: a sentence about the release run, `presstest run --env
# staging` or `presstest run <files>` (not bare), a negation just before it ("never ... `presstest run`"),
# and one describing what the command did ("`presstest run` said it wasn't started", "also printed").
bt='`'
bare=$(awk -v RS= '{gsub(/\n/, " "); print}' "$T" | awk '{n = split($0, a, "[.] "); for (i = 1; i <= n; i++) print a[i]}' \
  | tr 'A-Z' 'a-z' | grep 'presstest run' | grep -v 'release' \
  | sed -E "s/((^|[^a-z])(never|not|no|without)([^a-z]|$)|n't)[^${bt}]{0,40}${bt}?presstest run/X/g; s/presstest run${bt}? (also )?(said|says|printed|prints|runs|ran|exits|stops|stopped|wasn.t|isn.t|refuses)/X/g" \
  | grep -E "presstest run([${bt}|),;:]|[[:space:]]*\$|[[:space:]]+(#|\())")
t "DoD2: no sentence in tess.md runs or hands over a bare 'presstest run' (outside the release run)" test -z "$bare"
[ -n "$bare" ] && printf '      sentence: %s\n' "$bare"
# A staging run is still a feature step's run: `presstest run --env staging` with no files runs the whole
# project suite and every shared suite, so each one in a step's context must name the same scoped files
# (`presstest run --env staging <files>`). Flatten each file (wrapped lines, runs of spaces), take each
# occurrence with 12 characters before it and 40 after. Not counted: one just after "bare", "never",
# "not" or "without" (a sentence that forbids it).
stg() { awk -v RS= '{gsub(/\n/, " "); print}' "$1" | tr -s ' ' | grep -Eo '.{0,12}presstest run --env staging.{0,40}' | grep -Eiv '(bare|never|not|without)[^a-z]{0,6}presstest'; }
stg_files='presstest run --env staging[[:space:]]+(<[A-Za-z _-]*files?[A-Za-z _-]*>|[A-Za-z0-9_./-]+_test\.js)'
t "DoD2: tess.md has a staging run command (opt-in, kept)" test -n "$(stg "$T")"
t "DoD2: every 'presstest run --env staging' in tess.md names the scoped files (<files> or a *_test.js), none is bare" \
  test -z "$(stg "$T" | grep -Ev "$stg_files")"
[ -n "$(stg "$T" | grep -Ev "$stg_files")" ] && stg "$T" | grep -Ev "$stg_files" | sed 's/^/      bare: /'
for f in "$TP" "$TR" "$RM"; do
  t "DoD2: no bare 'presstest run --env staging' in $(basename "$(dirname "$f")")/$(basename "$f")" test -z "$(stg "$f" | grep -Ev "$stg_files")"
done

# DoD 3: plan mode Dates only for date or time logic
t "DoD3: plan mode's Dates item applies to date or time logic" item_has "Plan mode" "$T" '\*\*Dates\*\*' '(date|time)[^.]{0,25}logic'
t "DoD3: plan mode's Dates item excludes a step that only displays a date" \
  item_has "Plan mode" "$T" '\*\*Dates\*\*' 'display' "(not|n't|no)[^.]{0,80}display|display[^.]{0,80}(not|no clock|none|n't)"

# DoD 4: Left to the release run
t "DoD4: TEST-PLAN template has '## Left to the release run'" grep -Eq '^## Left to the release run[[:space:]]*$' "$TP"
left=$(section_of "Left to the release run" "$TP")
t "DoD4: its guidance names PressTest files, shared suites, clock positions and 'none'" \
  sh -c "printf '%s\n' \"\$1\" | grep -qi presstest && printf '%s\n' \"\$1\" | grep -qi 'shared suite' && printf '%s\n' \"\$1\" | grep -qi clock && printf '%s\n' \"\$1\" | grep -qi none" _ "$left"
t "DoD4: tess.md plan mode tells her to fill 'Left to the release run'" item_has "Plan mode" "$T" 'Left to the release run'
t "DoD4: TEST-REPORT template carries the release run forward" grep -qi 'release run' "$TR"
t "DoD4: wording names the release run before the release PR into main" grep -Eqi 'release run[^.]{0,60}main' "$T"
( cd "$W/scratch" && git init -q . && bash "$P/bin/ada-handoff-init" ) >/dev/null 2>&1
t "DoD4: ada-handoff-init gives a new step's TEST-PLAN.md the section" grep -Eq '^## Left to the release run' "$W/scratch/.claude/handoff/TEST-PLAN.md"

# DoD 5: the onboarded project's tests/README.md
t "DoD5: repo tests/README template says what the release run runs" para_has "$RM" 'release run' '(everything|all|every|whole|full)'
t "DoD5: repo tests/README template says what a step runs (unit and PressTest)" para_has "$RM" 'step' 'unit' 'presstest'

# DoD 7: CHANGELOG under Unreleased, no version changes
ur() { awk '/^## Unreleased/{p=1; next} p && /^## /{exit} p && /^- /' "$1" | wc -l | tr -d ' '; }
t "DoD7: CHANGELOG has a new entry under Unreleased ($(ur "$B/CHANGELOG.md") -> $(ur "$REPO/CHANGELOG.md"))" test "$(ur "$REPO/CHANGELOG.md")" -gt "$(ur "$B/CHANGELOG.md")"
t "DoD7: the new entry is about this step" sh -c "awk '/^## Unreleased/{p=1; next} p && /^## /{exit} p' '$REPO/CHANGELOG.md' | grep -Eqi 'release run'"
t "DoD7: no version heading added to CHANGELOG" test "$(grep '^## ' "$REPO/CHANGELOG.md")" = "$(grep '^## ' "$B/CHANGELOG.md")"
for j in plugins/ada/.claude-plugin/plugin.json .claude-plugin/marketplace.json; do
  t "DoD7: version unchanged in $j" test "$(grep '"version"' "$REPO/$j" 2>/dev/null)" = "$(grep '"version"' "$B/$j" 2>/dev/null)"
done

# Regression
missing=$(awk '/^## Never/{p=1;next} p&&/^## /{exit} p&&NF' "$B/plugins/ada/agents/tess.md" | while IFS= read -r l; do grep -Fxq -- "$l" "$T" || echo "$l"; done)
t "regression: every Never line of base still in tess.md" test -z "$missing"
[ -n "$missing" ] && printf '      missing: %s\n' "$missing"
t "regression: tess.md tools: line unchanged" test "$(grep -m1 '^tools:' "$T")" = "$(grep -m1 '^tools:' "$B/plugins/ada/agents/tess.md")"
for k in Static Dates Sequences Surfaces Regression Design; do
  t "regression: plan mode still has the **$k** item" item_has "Plan mode" "$T" "^- \*\*$k\*\*"
done
t "regression: run mode still runs the terminal checks" item_has "Run mode" "$T" 'terminal' 'record'
t "regression: run mode still keeps staging opt-in and never production" item_has "Run mode" "$T" 'presstest run --env staging' 'never production'
t "regression: run mode still records the 'ended test session' line" item_has "Run mode" "$T" 'ended test session'
for f in templates/handoff/TEST-PLAN.md templates/handoff/TEST-REPORT.md templates/repo/tests/README.md; do
  m=$(headings "$B/plugins/ada/$f" | while IFS= read -r l; do headings "$P/$f" | grep -Fxq -- "$l" || echo "$l"; done)
  t "regression: every heading of base $f still there" test -z "$m"
  [ -n "$m" ] && printf '      missing: %s\n' "$m"
done
for d in plugins/ada/bin plugins/ada/skills/planning/SKILL.md plugins/ada/templates/repo/.github/pull_request_template.md \
  .github/workflows .github/scripts; do
  t "regression: $d unchanged (out of scope)" diff -r "$B/$d" "$REPO/$d"
done
rm -rf "$W"
echo "$fails failed"; [ "$fails" = 0 ]
```

### The practice project

Save this as `$S/make-sample.sh` (four-backtick fence: it writes a README with code blocks).

````bash
#!/bin/bash
# make-sample.sh <new folder> display|dates: a practice WooCommerce project for a dry run of Tess in
# plan mode, with three PressTest files, a shared suite, unit tests and clock positions, and an agreed
# brief: "display" shows an existing date at checkout; "dates" moves the order cut-off.
# Env: REPO (this checkout).
set -eu
: "${REPO:?this checkout}"
D=${1:?new folder}; MODE=${2:?display or dates}
case $MODE in display|dates) ;; *) echo "mode is display or dates" >&2; exit 2 ;; esac
[ ! -e "$D" ] || { echo "$D exists" >&2; exit 2; }
mkdir -p "$D/.claude/team" "$D/tests/presstest" "$D/tests/unit" "$D/tests/functional" \
  "$D/wp-content/themes/shop/woocommerce/checkout" "$D/wp-content/plugins/vm-delivery/includes"
cd "$D"
git init -q .
printf '{\n  "enabledPlugins": { "ada@pie": false }\n}\n' > .claude/settings.local.json
printf '.claude/settings.local.json\n.claude/handoff/\n.claude/worktrees/\n' > .gitignore

cat > CLAUDE.md <<'EOF'
# Practice shop

A practice WooCommerce site for dry runs of the ada plugin. Nothing here is deployed.

This repo uses the `ada` plugin from PIE. If you have not been given a role, you are Ada.
EOF

cat > .claude/team/PROJECT.md <<'EOF'
# Practice shop

## The project

- **What it is:** a practice WooCommerce shop selling weekly veg boxes. For dry runs only.
- **Client:** PIE (internal). Approves: none.

## Code and branches

- **Code:** `wp-content/themes/shop/`, `wp-content/plugins/vm-delivery/`.
- **Branch from:** `main`. **PR into:** `main`.

## Brief approval

- **Approvers:** none
- **Trusted:** none

## Fragile areas

- Payments (the Stripe webhook handler).

## Tests

- **Unit tests:** see `tests/README.md`.
- **PressTest image:** `ghcr.io/pie/presstest:1.4.0`
- **PressTest shared suites:** `woocommerce-core`
- **Clock-sensitive behaviour:** yes

## Local copies

None for this practice project: nothing is run, only planned.
EOF

cat > .claude/team/SYSTEM.md <<'EOF'
# How the practice shop works

## Delivery dates

`vm_next_delivery_date( DateTimeImmutable $now ): DateTimeImmutable`, in
`wp-content/plugins/vm-delivery/includes/dates.php`, gives the first delivery for an order placed at
`$now`: deliveries are on Mondays; an order placed by the cut-off, Thursday 23:59 site time
(Europe/London), gets the next Monday; after it, the Monday after. A bank-holiday Monday moves to
the Tuesday.

## Checkout

The order review table is the theme's override,
`wp-content/themes/shop/woocommerce/checkout/review-order.php`. Under the order total it shows
notes in a `.vm-note` line (today: "Free delivery" for orders over 40 GBP).
EOF

cat > .claude/team/TEST-AREAS.md <<'EOF'
# Testing the practice shop

## Dates and the clock

The site's clock is moved with `ada-clock`: `ada-clock set "<time>"`, `ada-clock status`,
`ada-clock reset`. Clock positions (site time, Europe/London):

- Thursday 2026-12-03 23:59 (last minute before the cut-off)
- Friday 2026-12-04 00:01 (first minute after it)
- Sunday 2026-12-06 12:00 (a weekend order)
- Thursday 2026-12-24 23:59 (the Monday after is a bank holiday)

## The areas

| # | Area | Scope | PressTest |
|---|---|---|---|
| 01 | Checkout | The checkout page, the order review table and its notes, placing an order | `checkout_test.js` |
| 02 | Cart | The cart page and the mini-cart | `cart_test.js` |
| 03 | My account | Orders list, a subscription's page | `account_test.js` |
| 04 | Delivery dates | `vm_next_delivery_date()`: cut-off, weekends, bank holidays | none (unit: `tests/unit/DeliveryDateTest.php`) |
EOF

cat > tests/README.md <<'EOF'
# Tests

## Unit tests

```sh
tools/vendor/bin/phpunit                          # everything
tools/vendor/bin/phpunit --filter DeliveryDateTest # one class
```

## PressTest

```sh
presstest list                       # the files
presstest run checkout_test.js       # one file
presstest run                        # everything, with the shared suites
```

## Functional scripts

`tests/functional/<area number>-<area>/<slug>.md`, from `functional/TEMPLATE.md`.
EOF
cp "$REPO/plugins/ada/templates/repo/tests/functional/TEMPLATE.md" tests/functional/TEMPLATE.md

for f in checkout cart account; do
  printf "const { test, expect } = require('@pie/presstest');\n\ntest('%s page loads', async ({ page }) => {\n  await page.goto('/%s/');\n  await expect(page.locator('main')).toBeVisible();\n});\n" "$f" "$f" > "tests/presstest/${f}_test.js"
done
printf '<?php\n// Unit tests for vm_next_delivery_date(): cut-off, weekend, bank holiday.\nclass DeliveryDateTest extends TestCase {}\n' > tests/unit/DeliveryDateTest.php
printf '<?php\n// Order review table (theme override). Shows the .vm-note line under the total.\n' > wp-content/themes/shop/woocommerce/checkout/review-order.php
printf '<?php\n// vm_next_delivery_date( DateTimeImmutable $now ): DateTimeImmutable\n' > wp-content/plugins/vm-delivery/includes/dates.php

git add -A && git -c user.name=dry -c user.email=dry@example.invalid commit -qm 'Practice shop'
bash "$REPO/plugins/ada/bin/ada-handoff-init" >/dev/null

if [ "$MODE" = display ]; then
cat > .claude/handoff/BRIEF.md <<'EOF'
# Brief — Step 1: Show the first delivery date at checkout

Status: agreed
Approval: not needed

## Outcome

At checkout, under the order total, the customer sees the date of their first delivery.

## Decisions

- The date comes from the existing `vm_next_delivery_date()`, unchanged; the template only
  displays it, formatted with `wp_date( 'l j F' )` (e.g. "Monday 7 December").
- It reuses the existing `.vm-note` line under the order total, unchanged (same markup and
  style as "Free delivery"), with the text "First delivery: <date>".

## Constraints

- Files expected to change: `wp-content/themes/shop/woocommerce/checkout/review-order.php` only.
- Surfaces to check (P12): the checkout's order review table.
- Out of scope: the delivery-date calculation and its cut-off, the cart, My account, emails.

## Definition of done

- [ ] The checkout's order review table shows "First delivery: <date>" in a `.vm-note` line under
      the order total, where <date> is `vm_next_delivery_date()` formatted `l j F`.
- [ ] Nothing else in the order review table changes.

## Amendments

| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |
|---|---|---|---|---|
EOF
else
cat > .claude/handoff/BRIEF.md <<'EOF'
# Brief — Step 1: Move the order cut-off to Thursday 18:00

Status: agreed
Approval: not needed

## Outcome

Orders placed after 18:00 on a Thursday wait for the Monday after next.

## Decisions

- `vm_next_delivery_date( DateTimeImmutable $now ): DateTimeImmutable` changes its cut-off from
  Thursday 23:59 to Thursday 17:59:59 site time (Europe/London): an order placed up to
  17:59:59 on Thursday gets the next Monday; from 18:00:00, the Monday after. The weekend and
  bank-holiday rules are unchanged.

## Constraints

- Files expected to change: `wp-content/plugins/vm-delivery/includes/dates.php` only.
- Surfaces to check (P12): the checkout's order review table, which displays the date (unchanged).
- Out of scope: how the date is displayed, the cart, My account, emails.

## Definition of done

- [ ] `vm_next_delivery_date()` at Thursday 2026-12-03 17:59 returns Monday 2026-12-07.
- [ ] `vm_next_delivery_date()` at Thursday 2026-12-03 18:00 returns Monday 2026-12-14.
- [ ] A bank-holiday Monday still moves to the Tuesday.

## Amendments

| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |
|---|---|---|---|---|
EOF
fi
echo "sample ready: $D ($MODE)"
````

### The after-script

Save this as `$S/check-plan.sh`.

```bash
#!/bin/bash
# check-plan.sh: run from the practice project once Tess has written TEST-PLAN.md.
# Env: MODE (display: the step only shows a date; dates: it changes the cut-off).
set -u
: "${MODE:?display or dates}"
PLAN=${PLAN:-.claude/handoff/TEST-PLAN.md}
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
[ -s "$PLAN" ] || { echo "FAIL no $PLAN"; echo "1 failed"; exit 1; }
# Left: the "Left to the release run" section; rest: everything else in the plan
left=$(awk 'index($0, "## Left to the release run") == 1 {p=1; next} p && /^## /{exit} p' "$PLAN")
rest=$(awk 'index($0, "## Left to the release run") == 1 {p=1; next} p && /^## /{p=0} !p' "$PLAN")
in_rest() { printf '%s\n' "$rest" | grep -Eq -- "$1"; }
in_left() { printf '%s\n' "$left" | grep -Eq -- "$1"; }
no_rest() { ! printf '%s\n' "$rest" | grep -Eq -- "$1"; }
# A step's unit run: the bare "everything" command, or the words for it
unit_all='tools/vendor/bin/phpunit([`|),.;]|[[:space:]]*$|[[:space:]]+#|[[:space:]]+\()|([Ww]hole|[Ff]ull|[Ee]ntire|[Aa]ll( the)?)[^|]{0,30}unit'
# Every PressTest run (presstest run, then files or nothing) runs everything only when bare
bare_run='presstest run([`|),;]|[[:space:]]*$|[[:space:]]+(#|\())'

t "plan: TEST-PLAN.md written for this step" grep -q 'Step 1' "$PLAN"
t "plan: has '## Left to the release run'" grep -Eq '^## Left to the release run' "$PLAN"
t "plan: runs the whole unit-test suite" in_rest "$unit_all"
t "plan: no PressTest run of everything (bare presstest run)" no_rest "$bare_run"
t "plan: never runs the shared suite woocommerce-core" no_rest 'woocommerce-core'

if [ "$MODE" = display ]; then
  t "display: names checkout_test.js" in_rest 'checkout_test\.js'
  t "display: no other PressTest file outside Left (cart_test.js)" no_rest 'cart_test\.js'
  t "display: no other PressTest file outside Left (account_test.js)" no_rest 'account_test\.js'
  # No clock positions: no ada-clock, no fake time, none of TEST-AREAS' positions (a line that
  # only says they are left to the release run doesn't count, nor one that only states the real
  # clock, such as "real clock (`ada-clock status` says `ada-clock: real`)")
  clock=$(printf '%s\n' "$rest" | grep -Ei 'ada-clock|20[0-9]{2}-[01][0-9]-[0-3][0-9][ T][0-2][0-9]:[0-5][0-9]|23:59|00:01|12:00' | grep -Eiv 'release run|real clock|ada-clock: real')
  t "display: no clock positions outside Left" test -z "$clock"
  [ -n "$clock" ] && printf '      line: %s\n' "$clock"
  cl=$(grep -Ei '^- Clock positions' "$PLAN" | sed 's/^[^:]*:[[:space:]]*//')
  t "display: the Clock positions line, if any, is empty or none" sh -c "[ -z \"\$1\" ] || printf '%s\n' \"\$1\" | grep -Eqi '^(none|n/a|no|—|-|not)'" _ "$cl"
  # Left holds what the step "could reach but doesn't run": a checkout order-review change doesn't
  # reach the cart or My account, so cart_test.js and account_test.js there are not required
  t "display: Left lists the shared suite woocommerce-core" in_left 'woocommerce-core'
  t "display: Left lists the clock positions" in_left '[Cc]lock|23:59'
  t "regression: Tests supplied names a PressTest test for the checkout line" \
    sh -c "awk 'index(\$0, \"## Tests supplied\") == 1 {p=1; next} p && /^## /{exit} p' '$PLAN' | grep -Eqi 'presstest|checkout_test'"
else
  t "dates: a date check at the new cut-off, 17:59" in_rest '17:59'
  t "dates: a date check at the new cut-off, 18:00" in_rest '18:0[01]'
  t "dates: a fake time in the form ada-clock takes (YYYY-MM-DD HH:MM)" in_rest '20[0-9]{2}-[01][0-9]-[0-3][0-9][ T][0-2][0-9]:[0-5][0-9]'
  t "dates: Tests supplied has a unit test for vm_next_delivery_date()" \
    sh -c "awk 'index(\$0, \"## Tests supplied\") == 1 {p=1; next} p && /^## /{exit} p' '$PLAN' | grep -Eqi 'unit|phpunit'"
fi
printf '%s\n' "$left" | sed 's/^/info: left: /'
echo "$fails failed"; [ "$fails" = 0 ]
```

### Part B: a step that changes one checkout file and only displays a date

1. `REPO=<this checkout> bash $S/make-sample.sh $S/shop-display display`
2. `cd $S/shop-display && claude --plugin-dir <this checkout>/plugins/ada`. Say: "This is a dry
   run of the ada plugin. The brief in .claude/handoff/BRIEF.md is agreed. Don't make a worktree
   or a branch and don't open a PR. Spawn ada:tess in plan mode only, not write mode, for step 1,
   then stop." Approve the write to `.claude/handoff/TEST-PLAN.md` when asked. The dry runs need an
   interactive session: a headless run (`claude -p`) is refused the write to `.claude/handoff/`.
3. When Tess has handed back, quit, then from `$S/shop-display`: `MODE=display bash $S/check-plan.sh`
4. Break it on purpose (optional, costs a second dry run): repeat steps 1 to 3 in a new folder with
   `--plugin-dir <the base export from part A>/plugins/ada`. At least the `Left to the release run`
   lines FAIL.

### Part C: a step that changes date logic (the regression next to the change)

1. `REPO=<this checkout> bash $S/make-sample.sh $S/shop-dates dates`
2. As part B, step 2, in `$S/shop-dates`.
3. From `$S/shop-dates`: `MODE=dates bash $S/check-plan.sh`

### Afterwards

`rm -rf $S/shop-display $S/shop-dates`. Nothing else was created: the practice projects have no
remote and no site.

## Expected

From the brief's Decisions and Definition of Done (step 1, agreed 2026-11-20):

- Part A: every line `PASS`, `0 failed`, exit 0. In detail:
  - DoD 1: Run mode has a step that runs the project's whole unit-test suite on every step and
    records the command and result.
    For a project whose `tests/README.md` names no unit-test suite, Run mode step 1 and Plan
    mode's Unit tests item say to record `Unit tests: none in this project` and go on (amended
    2026-11-20).
  - DoD 2: `everything if the step touches checkout` (or `… if it touches checkout`) is found
    nowhere under `plugins/ada/` or in `SYSTEM.md`; Run mode runs only the PressTest files covering
    what the step changes, fewer rather than more, and says a step never runs the shared suites;
    no Run mode sentence has the step's run include them, and no sentence in `tess.md` runs
    PressTest "everything" or hands the developer a bare `presstest run` (no files), except the
    release run's. The opt-in staging run is scoped the same way: every `presstest run --env
    staging` in `tess.md` (and the templates) names the step's files (`presstest run --env staging
    <files>`), never bare, because a bare staging run runs the whole project suite and every
    shared suite (PR #76 review round 2).
  - DoD 3: Plan mode's **Dates** item applies when the step changes date or time logic and says,
    in words, that a step that only displays a date gets no clock checks.
  - DoD 4: the `TEST-PLAN.md` template has `## Left to the release run` with guidance naming the
    PressTest files, shared suites, clock positions and `none`; Plan mode tells Tess to fill it;
    the `TEST-REPORT.md` template carries the release run forward; the wording names "the release
    run before the release PR into main"; a new step's handoff (`ada-handoff-init`) gets the
    section.
  - DoD 5: the repo `tests/README.md` template says what a step runs (all unit tests, the
    PressTest files for what it changes) and that the release run runs everything.
  - DoD 7: a new CHANGELOG entry under Unreleased mentioning the release run; no new version
    heading; the `"version"` lines of `plugin.json` and `marketplace.json` unchanged.
- Part B (DoD 6): `check-plan.sh` every line `PASS`, `0 failed`. The plan:
  - runs the whole unit suite (`tools/vendor/bin/phpunit`, or says "the whole unit suite");
  - names `checkout_test.js` and no other PressTest file outside "Left to the release run", and no
    bare `presstest run` and no `woocommerce-core` there;
  - has no clock positions: no `ada-clock`, no fake time, none of `TEST-AREAS.md`'s positions,
    and its Clock positions line, if it has one, empty or `none`;
  - lists `woocommerce-core` and the clock positions under `## Left to the release run`. It may
    also list `cart_test.js` and `account_test.js` there (info only: the brief's "could reach but
    doesn't run" lets Tess judge that a checkout change can't reach them); it must not name them
    outside it.
- Part C: `check-plan.sh` every line `PASS`: the plan still has date checks at 17:59 and 18:00 with
  a fake time `ada-clock` takes (`YYYY-MM-DD HH:MM`), a unit test for `vm_next_delivery_date()`,
  the whole unit suite, no shared suite, and a `Left to the release run` section.

## Observe at

- `check.sh`'s and `check-plan.sh`'s output and exit code.
- `$S/shop-display/.claude/handoff/TEST-PLAN.md` and `$S/shop-dates/.claude/handoff/TEST-PLAN.md`.
- The session: what Ada spawns (Tess, plan mode only) and what Tess reads.

## Regression

- Part A, `regression` lines: every line of `tess.md`'s Never list and its `tools:` line are as
  they were; Plan mode keeps its Static, Dates, Sequences, Surfaces, Regression and Design items;
  Run mode still runs the terminal checks, keeps staging opt-in, never production, and records
  the `ended test session` line; every heading of the base `TEST-PLAN.md`, `TEST-REPORT.md` and
  repo `tests/README.md` templates is still there; `plugins/ada/bin/`, the planning skill (its
  feature's-last-PR smoke run), the plugin's PR template
  (`plugins/ada/templates/repo/.github/`) and `.github/workflows`, `.github/scripts` are
  unchanged (out of scope).
- Part C: a step that does change date logic still gets its clock positions (Plan mode's Dates
  item is narrowed, not removed).
- Part B, `regression` line: Tess still supplies a PressTest test for the visible change.
