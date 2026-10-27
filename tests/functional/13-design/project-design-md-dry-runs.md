# Iris creates, reads first, updates and lints the project's root `DESIGN.md`

- Area: 13 Design (`/ada:design`, Iris; the design step in `begin`); also 08 Session flow, 09 Roles
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219893199817979
- Automate as: none (needs a dry run with a person, at the canvas or the terminal)
- Last run: not run yet

The dry runs of the brief of 27 Oct 2026, "Iris keeps the project's DESIGN.md in the Stitch
format" (Definition of Done 8), and its surfaces: standalone `/ada:design`, the design step inside
`/ada:begin`, back to design partway through a step, a project with no root `DESIGN.md`, a project
with one, and a project without `npx`.

Two files, as in the brief: the **root** `DESIGN.md` (the project's design system, at the repo
root, in Google's DESIGN.md format) and **`.claude/handoff/DESIGN.md`** (the step's design, which
refers to the root tokens by name, `{colors.primary}`, and has the line
`Project DESIGN.md: created | updated (<tokens>) | unchanged`).

## Precondition

As in `begin-offers-design-and-finishes-brief.md`: a practice repo onboarded with `ada`, the
`ada@pie` plugin off in its `.claude/settings.local.json`, sessions started with
`claude --plugin-dir <this checkout>/plugins/ada`. The Design type offered by `quickstart` if you
have it (else Iris's text-only fallback, which is fine here: nothing in these cases depends on the
canvas). Claude in Chrome is not needed. Each case is a fresh session on a fresh copy of the
practice repo (or put `.claude/handoff/`, `PROJECT.md` and the root `DESIGN.md` back afterwards).

- `PIN`: the linter version Bob recorded in `REVIEW-REQUEST.md`, the same one in Iris's skill
  (`npx @google/design.md@<PIN> lint DESIGN.md`).
- `npx` on the PATH (`command -v npx`) for every case but D.
- Save the two helpers below in a scratch folder (not in a repo): `check-root.sh` and `refs.py`.
  `bash check-root.sh <practice repo> <PIN>` checks the root file's shape, lints it with the
  pinned version, prints the handoff's `Project DESIGN.md:` line and checks every token the
  handoff names exists in the root file. It prints `PASS`, `FAIL` or `SKIP` per check.

```bash
#!/bin/bash
# check-root.sh <repo> <pin>: the root DESIGN.md's shape, lint, and the handoff's token names.
set -u
R=$1; PIN=$2; D=$(cd "$(dirname "$0")" && pwd)
fails=0
ok() { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()  { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
F=$R/DESIGN.md; HO=$R/.claude/handoff/DESIGN.md; W=$(mktemp -d)
t "root DESIGN.md exists" test -f "$F"
tr -d '\r' < "$F" > "$W/F" 2>/dev/null
t "line 1 is ---" test "$(head -1 "$W/F")" = "---"
awk 'NR==1{next} /^---$/{exit} {print}' "$W/F" | grep -oE '^[A-Za-z_][A-Za-z0-9_-]*:' | tr -d ':' > "$W/keys"
for k in version name description colors typography rounded spacing components; do
  t "front matter key '$k', once" test "$(grep -cx "$k" "$W/keys")" = 1
done
awk 'NR==1{next} f{print;next} /^---$/{f=1}' "$W/F" | awk '/^```/{f=!f;next} !f && /^## /{sub(/^## +/,""); sub(/ +$/,""); print}' > "$W/h2"
printf '%s\n' Overview Colors Typography Layout 'Elevation & Depth' Shapes Components "Do's and Don'ts" > "$W/want"
t "the 8 sections, in the spec's order, no others" cmp -s "$W/want" "$W/h2"
t "no Ada data (Status:, Asana, a contrast-ratio table)" not grep -Eiq '^[-* ]*\**status\**:|asana|^\|.*contrast' "$W/F"
if command -v npx >/dev/null 2>&1; then
  (cd "$R" && npx --yes "@google/design.md@$PIN" lint DESIGN.md) > "$W/lint" 2>&1; lrc=$?
  echo "info: npx @google/design.md@$PIN lint DESIGN.md: exit $lrc"; sed 's/^/info:   /' "$W/lint"
  t "lint: exit 0" test "$lrc" = 0
  t "lint: 0 errors" not grep -Eiq '(^|[^0-9])[1-9][0-9]* +errors?|"severity" *: *"error"|^ *error[: ]' "$W/lint"
else
  echo "SKIP lint: no npx"
fi
echo "info: handoff: $(grep -E '(^|[^A-Za-z])Project DESIGN\.md:' "$HO" 2>/dev/null || echo 'no Project DESIGN.md: line')"
echo "info: handoff: $(grep -E '^[-* ]*\**Status\**:' "$HO" 2>/dev/null | head -1)"
python3 -I "$D/refs.py" "$F" "$HO"; ok $? "every {group.token} the handoff names is in the root DESIGN.md"
rm -rf "$W"; echo "$fails failed"; [ "$fails" = 0 ]
```

```python
# refs.py <root DESIGN.md> <handoff DESIGN.md>: lists the handoff's {group.token} names and fails
# on any the root file's front matter doesn't define.
import re, sys
lines = open(sys.argv[1], encoding="utf-8").read().replace("\r\n", "\n").split("\n")
end = lines.index("---", 1)
paths, stack = set(), []
for l in lines[1:end]:
    m = re.match(r"""^(\s*)("[^"]+"|'[^']+'|[^\s:#'"-][^:#]*?):""", l)
    if not m or l.strip().startswith(("#", "- ")):
        continue
    ind, key = len(m.group(1)), m.group(2).strip("\"'")
    while stack and stack[-1][0] >= ind:
        stack.pop()
    stack.append((ind, key))
    paths.add(".".join(k for _, k in stack))
refs = sorted(set(re.findall(r"\{((?:colors|typography|rounded|spacing|components)\.[A-Za-z0-9_.-]+)\}",
                             open(sys.argv[2], encoding="utf-8").read())))
missing = [r for r in refs if r not in paths]
print("info: handoff names %d tokens: %s" % (len(refs), ", ".join(refs) or "none"))
for r in missing:
    print("missing from the root DESIGN.md: {%s}" % r)
sys.exit(1 if missing or not refs else 0)
```

- **The existing root file**, for cases B, C, F and G. Save it as `DESIGN.md` at the practice
  repo's root and commit it. Before the run, check it lints clean:
  `npx @google/design.md@<PIN> lint DESIGN.md` gives 0 errors (else fix this file first: a lint
  error later must be Iris's, not the fixture's).

  ```markdown
  ---
  version: alpha
  name: Practice Site
  description: Calm, plain and readable.
  colors:
    primary: "#1A5276"
    on-primary: "#FFFFFF"
    surface: "#FFFFFF"
    on-surface: "#222222"
    tertiary: "#7D3C98"
  typography:
    body-md:
      fontFamily: Georgia
      fontSize: 16px
      fontWeight: 400
      lineHeight: 1.5
    headline-md:
      fontFamily: Georgia
      fontSize: 28px
      fontWeight: 700
      lineHeight: 1.2
  rounded:
    sm: 4px
  spacing:
    sm: 8px
    md: 16px
    lg: 32px
  components:
    button-primary:
      backgroundColor: "{colors.primary}"
      textColor: "{colors.on-primary}"
      typography: "{typography.body-md}"
      rounded: "{rounded.sm}"
      padding: 12px
  ---

  ## Overview

  A calm, plain site for adult learners. Readable first.

  ## Colors

  Deep blue (#1A5276) for actions, near-black (#222222) text on white, purple (#7D3C98) for
  highlights.

  ## Typography

  Georgia throughout: 16px body, 28px headlines.

  ## Layout

  One column under 768px; a 1200px maximum width above. An 8px spacing scale.

  ## Elevation & Depth

  Flat: borders and colour, no shadows.

  ## Shapes

  A 4px radius on buttons and inputs.

  ## Components

  Primary button: deep blue, white text.

  ## Do's and Don'ts

  - Do keep text contrast at 4.5:1 or more.
  - Don't use more than one primary button per screen.
  ```

  Keep a copy outside the repo (`cp DESIGN.md <scratch>/root.orig`) to diff with afterwards.
- `PROJECT.md`'s `Design guide:` line: `none` in case A; `docs/design-guide.md` in case B, with
  that file holding "Body text 16px, #222222." (a guide that agrees with the root file).

## Steps

1. **Case A: standalone, no root `DESIGN.md`.** `ls DESIGN.md` says there is none. Start a session
   and run `/ada:design a newsletter sign-up box in the site footer`. Answer her questions and
   pick an option. When she has finished: `git status`, `bash check-root.sh <repo> <PIN>`, and
   open `.claude/handoff/DESIGN.md`. Note whether the terminal showed her running
   `npx @google/design.md@<PIN> lint DESIGN.md`, and when (before or after she said the design
   was signed off).
2. **Case B: with a root `DESIGN.md`, a design that needs a new token.** The existing root file in
   place, `Design guide: docs/design-guide.md`. `/ada:design a green "Thanks, you're signed up"
   message under the newsletter form`. Pick an option. Watch the order of the files she reads in
   the terminal. Then `git diff DESIGN.md`, `bash check-root.sh <repo> <PIN>`, and open the
   handoff's "What was found".
3. **Case C: a design that changes an existing token.** The existing root file in place.
   `/ada:design the buttons should be a lighter blue, #1F618D, everywhere`.
   - C1: when she asks whether to change the existing token, answer "no, don't change it". Finish
     the design. `git diff DESIGN.md`; open the handoff.
   - C2 (fresh copy): the same, answer "yes, change it". `git diff DESIGN.md`; open the handoff.
   - C3 (fresh copy): `/ada:design we don't need the purple highlight colour any more`. Answer
     "yes" when asked. `git diff DESIGN.md`.
4. **Case D: no `npx`.** As case A, but start the session with a PATH that has no `npx`:
   `env PATH="$(printf %s "$PATH" | tr ':' '\n' | grep -v -e nvm -e node | paste -sd: -)" claude --plugin-dir <this checkout>/plugins/ada`.
   First check that PATH in a shell: `command -v npx` prints nothing and `command -v claude`
   prints a path (if `claude` is itself under nvm or node, put a stand-in folder first on the PATH
   instead, holding only a `claude` symlink, and leave `npx` out). Then run case A's prompt. Open
   the handoff.
5. **Case E: the design step inside `/ada:begin`, through to the PR.** A fresh copy with no root
   `DESIGN.md`. `/ada:begin` "add a newsletter sign-up box to the site footer"; accept the design
   step; let the step run (Bob, Claudia, Tess) to the PR gate. Then:
   `git diff --name-only origin/<PR into>...HEAD`, the PR's files on GitHub (if a PR is opened on
   a scratch GitHub repo), and `grep -n 'DESIGN.md' .claude/handoff/REVIEW-REQUEST.md
   .claude/handoff/REVIEW-FEEDBACK.md .claude/handoff/TEST-PLAN.md`.
6. **Case F: back to design partway through a step.** The existing root file in place. In a step
   whose brief has no design (e.g. "add an optional phone field to the contact form"), when Bob
   records `UI not specified:` (or say at the build phase "the field needs an error state nobody
   designed"), accept Ada's offer of Iris. Let Iris design only the new part. `git diff DESIGN.md`;
   open the handoff.
7. **Case G: a design that adds nothing new.** The existing root file in place. `/ada:design a
   second primary button, "Download the brochure", beside the existing one on the course page`.
   Pick an option that uses only the existing tokens. `git diff --quiet DESIGN.md; echo $?`; open
   the handoff.

## Expected

1. Case A:
   - Look first records that the project has no root `DESIGN.md`; she doesn't ask for one or treat
     its absence as an error.
   - After you pick an option she creates `DESIGN.md` at the repo root (`git status`: a new
     `DESIGN.md` at the root; nothing else outside `.claude/handoff/`).
   - `check-root.sh`: every line `PASS`, `0 failed`: the spec's front matter keys, the 8 sections
     in order and no others, no Ada data, lint exit 0 with 0 errors, and every `{group.token}` the
     handoff names is defined in the root file.
   - She ran the pinned lint command herself, and before she signed the design off (if it
     reported errors, she fixed them and ran it again before signing off).
   - The handoff: `Status: agreed`; `Project DESIGN.md: created`; its type, colour and spacing
     sections name root tokens (`{colors.…}`, `{typography.…}`, `{spacing.…}`) rather than repeat
     their values; the colour table still has its Contrast ratio column, filled.
2. Case B:
   - She reads the root `DESIGN.md` first: in the terminal, before `docs/design-guide.md` and any
     other source. "What was found" lists the root `DESIGN.md` first, then the guide.
   - She adds the new token (a green, e.g. `colors.success`, passing 4.5:1 for its text) to the
     root file: `git diff DESIGN.md` shows only added lines; no existing token line changed or
     removed (compare with `root.orig`).
   - The handoff: `Project DESIGN.md: updated (<the tokens she added>)`, naming exactly the tokens
     in the diff; those tokens marked "new" in its sections; `check-root.sh` `0 failed`.
3. Case C:
   - Before changing `colors.primary` (or `button-primary`) she asks you explicitly, saying it
     changes the whole site. She changes nothing in the root file before you answer.
   - C1 ("no"): `git diff DESIGN.md` doesn't touch `primary:` or `button-primary`; the design
     either keeps the existing blue or adds a new token; the handoff records your "no", and its
     `Project DESIGN.md:` line names no changed token.
   - C2 ("yes"): `primary:` changed to `#1F618D` (white on it is about 6.7:1, a pass); the
     handoff records your explicit yes (that you agreed, to what) and
     `Project DESIGN.md: updated (colors.primary …)`; `check-root.sh` `0 failed`.
   - C3: she asks before removing `colors.tertiary`; after "yes", it is gone from the front matter
     and nothing still refers to it (`grep -n tertiary DESIGN.md` prints nothing, or only prose
     she has updated); `check-root.sh` `0 failed`.
4. Case D: as case A, but she doesn't run the linter or fail on its absence: the handoff says
   "not linted". P32 to P39 are still checked: the contrast ratios are in the handoff's colour
   table, and her sign-off says what she checked. The root `DESIGN.md` is still created, and
   `check-root.sh` (run afterwards with `npx`) passes its shape checks; record its lint result in
   the Run record (it is not part of this case's pass).
5. Case E:
   - The design step creates the root `DESIGN.md` as in case A.
   - The root `DESIGN.md` is committed in the step's PR: it is in
     `git diff --name-only origin/<PR into>...HEAD` and among the PR's files. The handoff files
     are not.
   - The roles read the root tokens: Bob builds with their values (his `REVIEW-REQUEST.md` says
     so, or names tokens); Claudia's review checks the built values against the root tokens;
     Tess's test plan's design comparison includes the root `DESIGN.md`. Every `DESIGN.md` they
     write about says which file.
6. Case F: she designs only the new part. The root file gains only the tokens the new part needs,
   if any (`git diff DESIGN.md`: added lines only); the handoff's `Project DESIGN.md:` line is
   `updated (<those tokens>)` or `unchanged`, matching the diff. No existing token changes
   without asking, as in case C.
7. Case G: `git diff --quiet DESIGN.md` exits 0 (unchanged); the handoff says
   `Project DESIGN.md: unchanged`, and its sections name existing tokens only.

Sources: the brief's Decisions (Look first; When a design is agreed; Lint), Definition of Done 3
and 8, and Constraints (the surfaces).

## Observe at

The terminal in each session (the files she reads, in order; the lint command and when it ran;
what she asks); the practice repo's root `DESIGN.md` (`git status`, `git diff`, `check-root.sh`);
`.claude/handoff/DESIGN.md` ("What was found", the `Project DESIGN.md:` line, the token sections);
in case E, the PR's files and the handoff files Bob, Claudia and Tess write.

## Regression

- Without a root `DESIGN.md` and with a `Design guide:` line, look first still takes the guide,
  then supplied designs, then the local site, in that order (`look-first-sources.md`, case B).
- `.claude/handoff/DESIGN.md` keeps its name and its gate: Bob, Claudia and Tess act on it only
  when it says `Status: agreed` (case E: a draft design is still not built from).
- With `npx` missing, the design still goes ahead and P32 to P39 are checked as before (case D).
