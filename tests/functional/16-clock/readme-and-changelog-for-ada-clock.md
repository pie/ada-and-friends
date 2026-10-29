# The README explains `ada-clock`, and the CHANGELOG tells existing projects how to adopt it

- Area: 16 A local site's clock (`ada-clock`): its user documentation
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219984586830313
- Automate as: none (the Steps block is a self-checking shell script)
- Last run: 29 Oct 2026, 0 failed (11 PASS); the ff93d6a break prints 11 FAIL (see the PR)

## Precondition

This checkout, with the change in its working tree. Nothing else.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo) and run
   `REPO=<this checkout> bash check.sh`.
2. Break it on purpose: run it with `REPO=` an export of ff93d6a
   (`git -C <this checkout> archive ff93d6a | tar -x -C <empty folder>`). The README and the
   existing-projects checks FAIL.
3. Read the README section once as a newcomer on a Mac would: could you get from it to a date
   check without asking anyone?

```bash
#!/bin/bash
# README's ada-clock section and the CHANGELOG's Unreleased entries. PASS/FAIL; exit 1 if any FAIL.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
RD=$REPO/README.md; CL=$REPO/CHANGELOG.md
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
# The README section whose heading names the clock or ada-clock, to the next heading of its level or above
rsec() { awk '
  !p && /^#+ / && tolower($0) ~ /clock/ { p=1; lvl=index($0," ")-1; print; next }
  p && /^#+ / { l=index($0," ")-1; if (l<=lvl) exit }
  p' "$RD"; }
in_r() { rsec | grep -Eiq -- "$1"; }
# Unreleased bullets, one per line (a bullet's indented continuation lines joined to it)
bullets() { awk '/^## Unreleased/{p=1;next} p&&/^## /{exit} p' "$CL" |
  awk '/^- /{if(b!="")print b; b=$0; next} /^  /{if(b!="")b=b" "$0; next} {if(b!="")print b; b=""} END{if(b!="")print b}'; }
bullet() { out=$(bullets); for re in "$@"; do out=$(printf '%s\n' "$out" | grep -Ei -- "$re"); done; [ -n "$out" ]; }

t "README: a section on the clock" test -n "$(rsec)"
t "README: it names ada-clock" in_r 'ada-clock'
t "README: it says what ada-clock does (a fake time on the local site)" in_r 'fake'
t "README: the one-off sudo apt install faketime on Linux" sh -c "$(declare -f rsec); RD='$RD'; rsec | grep -q 'sudo apt install faketime'"
t "README: the manual setup ..." in_r 'manual'
t "README: ... on Mac" in_r 'mac'
t "README: ... and Windows" in_r 'windows'
t "README: loading the extension (ada-clock extension, Chrome)" sh -c "$(declare -f rsec); RD='$RD'; rsec | grep -q 'ada-clock extension' && rsec | grep -qi 'chrome'"
t "CHANGELOG: an Unreleased bullet for existing projects naming TEST-AREAS" bullet 'existing|already onboarded|onboarded (repo|project)s?' 'TEST-AREAS'
t "CHANGELOG: an Unreleased bullet naming ada-onboard clock" bullet 'ada-onboard clock'
t "CHANGELOG: an Unreleased bullet on Tess setting the clock" bullet 'Tess' 'ada-clock'
echo "$fails failed"; [ "$fails" = 0 ]
```

## Expected

From the brief (Decisions, README; DoD 6) and Bob's approved Builder plan, item 6:

- README: a short section on the local site's clock: what `ada-clock` does; on Linux with Local
  the one-off `sudo apt install faketime`; on Mac and Windows the `manual` setup; `ada-clock
  extension` and loading it in Chrome.
- CHANGELOG, Unreleased: onboarding sets the clock up and Tess sets and resets it in run mode;
  for existing projects, what to do: run `ada-onboard clock …`, replace the `sudo date` steps in
  their own `TEST-AREAS.md` with the template's "Dates and the clock", then `ada-clock install`.
  No version number (P31).
- Every line `PASS`, `0 failed`, exit 0; on ff93d6a the README and existing-projects checks FAIL.
- Step 3, by a person: yes.

## Observe at

stdout and `echo $?`; `README.md` and `CHANGELOG.md`.

## Regression

- The README's other sections and their order are unchanged apart from the new section and a
  pointer to it (`git diff ff93d6a -- README.md`).
- The CHANGELOG entries already under Unreleased for Units A to C are unchanged
  (`git diff ff93d6a -- CHANGELOG.md` shows only added lines).
