# P40, on asset versions, is in the standards Bob and Claudia are preloaded with

- Area: 09 Roles (the P rules Bob builds to and Claudia reviews against)
- Automate as: none (part A is a self-checking shell script; part B is read by a person)
- Last run: 29 Oct 2026, part A 9 PASS, and the broken copy FAILs as expected; part B not run

## Precondition

- This checkout, with the change in its working tree.
- No scratch repo: part A only reads this checkout.

## Steps

### Part A: the text checks

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: in a scratch copy of the repo, delete the "next to its URL" bullet
   from the copy's `plugins/ada/skills/standards/SKILL.md` and run `REPO=<the copy> bash check.sh`.
   It must print `FAIL` for that bullet.

```bash
#!/bin/bash
# Text checks for P40. PASS/FAIL per check; exit 1 if any FAIL.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
cd "$REPO" || exit 2
S=plugins/ada/skills/standards/SKILL.md
fails=0
t() { name=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $name"; else echo "FAIL $name"; fails=$((fails+1)); fi; }
p40() { awk '/^\*\*P40\./{p=1} p&&/^\*\*P[0-9]+\./&&!/^\*\*P40\./{exit} p' "$S"; }

t "P40 appears once" test "$(grep -c '^\*\*P40\.' "$S")" = 1
t "no other rule is numbered P40" test "$(grep -rho '\*\*P40\.' plugins/ada/skills | wc -l | tr -d ' ')" = 1
t "P40: filemtime() of the served file" sh -c "$(declare -f p40); S=$S; p40 | grep -q 'filemtime()'"
t "P40: path and URL from the same base" sh -c "$(declare -f p40); S=$S; p40 | grep -q 'get_theme_file_path()'"
t "P40: never leave the version out" sh -c "$(declare -f p40); S=$S; p40 | grep -q 'Never leave the version out'"
t "P40: null for a URL that mustn't carry one" sh -c "$(declare -f p40); S=$S; p40 | grep -q '\`null\` when the URL'"
t "P40: remote version defined next to its URL" sh -c "$(declare -f p40); S=$S; p40 | grep -q 'next to its URL'"
t "P40: has a Why" sh -c "$(declare -f p40); S=$S; p40 | grep -q '^Why:'"
t "CHANGELOG: P40 under Unreleased" sh -c "awk '/^## Unreleased/{p=1;next} /^## /{exit} p' CHANGELOG.md | grep -q 'P40'"

[ "$fails" = 0 ] || exit 1
```

### Part B: a dry run

1. In a scratch WordPress theme, add a `wp_enqueue_style()` for a local `.css` file with no
   version, and a `wp_register_style()` for a Google Fonts URL with `null` as its version, the
   URL in a constant defined in another file.
2. Run `claude --plugin-dir <this checkout>/plugins/ada` and ask for a Claudia review of the
   change.

## Expected

- Part A: every line `PASS`, exit 0; the broken copy prints `FAIL P40: remote version defined
  next to its URL` and exits 1.
- Part B: the review cites P40 for the local stylesheet (no `filemtime()`), and for the Google
  Fonts `null` defined away from its URL. It does not ask for `filemtime()` on the Google Fonts
  stylesheet.

## Observe at

- Part A: stdout and `echo $?`.
- Part B: `.claude/handoff/REVIEW-FEEDBACK.md` in the scratch theme.

## Regression

- P numbers never change; P40 must not reuse a number taken elsewhere (P14 and P22 are unused,
  P29 and P32 to P39 are in other skills).
