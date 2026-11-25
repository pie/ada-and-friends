# `/ada:onboard` asks who writes code and who decides what, and writes the areas table

Unit E of the plan "Brief approval by area": onboarding asks whether anyone outside PIE will write
code, and who decides what; it offers the starter set of areas as a table to edit, never imposes
it, asks for each row's approvers (naming a client developer where they own the area), and writes
the agreed table into `PROJECT.md`.

- Area: 02 Onboarding (the onboard skill's Brief approval fact; `ada-onboard check`); 11 Templates
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198325269356
- Automate as: none (part A is a self-checking shell script; part B is a dry run for a person)
- Last run: 2026-11-23, Part A 17 passed, 0 failed on the host; Part B not run; see the PR

## Precondition

- Part A: this checkout (`REPO`) with the change in its working tree.
- Part B: `claude` and `gh` logged in. A new scratch folder: `git init`, one commit with a
  `README.md` ("A small shop site."), no `.claude/`. `claude --plugin-dir <this
  checkout>/plugins/ada` started in it, with `ada@pie` off in `.claude/settings.local.json`
  (`DEVELOPING.md`).

## Steps

### Part A: the wording, checked

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, change `fragile` to `fragil`
   everywhere in the copy's `skills/onboard/SKILL.md`, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. "starter row 'Fragile areas' with
   slug fragile" prints `FAIL`.

```bash
#!/bin/bash
# Unit E: /ada:onboard asks for the areas of concern. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (default $REPO/plugins/ada).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
O=$PLUGIN/skills/onboard/SKILL.md
[ -f "$O" ] || { echo "SETUP FAIL: no $O"; exit 2; }
W=$(mktemp -d); trap 'rm -rf "$W"' EXIT
fails=0; passes=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; passes=$((passes+1)); else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { local name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
tr '\n' ' ' < "$O" | tr -s ' \t' '  ' > "$W/o"
has()  { grep -qiE -- "$1" "$W/o"; }
hasF() { grep -qF -- "$1" "$W/o"; }

t "regression: onboard still asks whether anyone outside PIE will write code" has 'outside PIE.{0,40}(write|writes) code|(write|writes) code.{0,80}outside PIE'
t "onboard: asks who decides what" has 'who decides'
# Amendment 2026-11-23: both questions are always asked; "who decides what" doesn't depend on the first answer.
t "onboard: 'who decides what' is not asked only after a Yes (no 'Yes → ask who decides')" not has 'yes.{0,12}ask (who|them who|about who) decides'
t "onboard: says the second question is asked whatever the first answer" has 'both questions|always asks?|asks? both|either way|whether or not|regardless|whatever (the|their|your)( first)? answer|(does not|doesn.t|not) depend|even (when|if) (no one|nobody|only PIE|PIE)'
# The starter set (Decisions: Onboarding), each area with its slug.
for pair in 'Wording and content|content' 'Design and UI|design' 'Price|price' 'System behaviour|system' \
            'Fragile areas|fragile' 'Security, auth and payments|security' 'New dependency, data repair or migration|dependencies'; do
  name=${pair%|*}; slug=${pair#*|}
  t "onboard: starter row '$name' with slug $slug" has "$(printf '%s' "$name" | sed 's/[.()]/\\&/g').{0,60}(\`|\\| *)$slug(\`| *\\|)"
done
t "onboard: System behaviour is the catch-all" has 'System behaviour.{0,60}catch-all'
t "onboard: the starter set is offered as a table to edit, never imposed" has 'starter.{0,200}(edit|change|never impos|not impos)|(edit|change|never impos|not impos).{0,200}starter'
t "onboard: asks for each row's approvers" has "each (row|area).{0,80}approvers?|approvers?.{0,80}each (row|area)"
t "onboard: a client developer named where they own the area" has 'client developer.{0,160}(own|owns) (the|an|that|their) area|(own|owns) (the|an|that|their) area.{0,160}client developer'
t "onboard: the CODEOWNERS note is still there" hasF 'CODEOWNERS'
t "onboard: no 'Approvers never need approval'" not hasF 'Approvers never need approval'
echo "info: onboard names the table header | Area | Slug | Approved by |: $(grep -cF '| Area | Slug | Approved by |' "$O")"

echo "$passes passed, $fails failed"
[ "$fails" = 0 ] || exit 1
exit 0
```

### Part B: a dry run (a person, 20 to 30 minutes)

1. Run `/ada:onboard`. Answer its questions; when it asks about brief approval, say: "Yes, a client
   developer, `<client>`, will write code. PIE (`@<you>`) decides everything, except that
   `<client>` decides wording and content."
2. When it offers the areas, ask it to drop the Price row and keep the rest.
3. Let it finish. Then run `bash <this checkout>/plugins/ada/bin/ada-onboard check` and
   `bash <this checkout>/plugins/ada/bin/ada-brief areas` in the scratch folder.

4. In a second new scratch folder (as in the Precondition), run `/ada:onboard` again. When it asks
   about brief approval, say: "No, only PIE writes code. The client, `<client>`, decides wording and
   design; PIE (`@<you>`) decides everything else." (The answer to the first question is No.)
5. Let it finish. Then run `bash <this checkout>/plugins/ada/bin/ada-brief areas` in that folder.

## Expected

Part A: every line `PASS`, last line `<n> passed, 0 failed`, exit 0.

Part B:

1. Onboarding asks whether anyone outside PIE will write code, and who decides what.
2. It offers the seven starter rows as a table you can edit (Wording and content / `content`;
   Design and UI / `design`; Price / `price`; System behaviour / `system`, the catch-all; Fragile
   areas / `fragile`; Security, auth and payments / `security`; New dependency, data repair or
   migration / `dependencies`), asks for each row's approvers, and drops Price when asked: it
   doesn't insist on the starter set.
3. `PROJECT.md`'s Brief approval section has the agreed table: header
   `| Area | Slug | Approved by |`, six rows, `content` approved by `@<client>`, the rest by
   `@<you>` or `Approvers`, `system` marked `(catch-all)`. `ada-onboard check` prints no `error`
   line. `ada-brief areas` exits 0 and prints the six slugs in table order, `system`'s line ending
   ` (catch-all)`, then `trusted: …`.

4. Although the first answer is No, onboarding still asks who decides what, and offers the seven
   starter rows to edit. It doesn't write `Approvers: none` and leave the table in its comment on
   the strength of that No alone.
5. `PROJECT.md`'s Brief approval section has the agreed table, uncommented, with `content` and
   `design` approved by `@<client>`. `ada-brief areas` exits 0 and prints `content: @<client>` and
   `design: @<client>` among the slugs, `system`'s line ending ` (catch-all)`.

Sources, Amendment of 2026-11-23 to Unit E's brief (Decisions, Onboarding: both questions are always
asked; "who decides what" doesn't depend on the first answer), and all Unit E's brief (task 1220198325269356): Outcome ("onboarding asks for the areas");
Decisions, Onboarding (the questions, the starter set as a table to edit, never imposed, each
row's approvers, a client developer named where they own the area); Definition of Done 9; Files
expected to change (`skills/onboard/SKILL.md`: the Brief approval fact, the CODEOWNERS note).
`API.md`, `areas` (the output).

## Observe at

Part A: the script's output. Part B: the session transcript (the questions asked, in both runs), the scratch
folder's `.claude/team/PROJECT.md`, and the two commands' output.

## Regression

The CODEOWNERS note is still in the skill (part A). A project made from the template with the
starter set filled in passes `ada-onboard check` (`tests/functional/11-templates/templates-with-areas.md`,
section 4).
