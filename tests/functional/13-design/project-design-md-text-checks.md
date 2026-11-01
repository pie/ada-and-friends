# The project's root `DESIGN.md`, in Google's DESIGN.md format, is written into the template, Iris's skill, the role files and the docs

- Area: 13 Design (`/ada:design`, Iris); also 02 Onboarding (the skill's Design guide row), 09 Roles, 11 Templates
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1219893199817979
- Automate as: none (part A is a self-checking shell script; part B is read by a person)
- Last run: 27 Oct 2026, part A 108 PASS, 0 failed, 0 skipped, lint 0 errors, both breaks FAIL; part B not run (see the PR)

From the brief of step 1, "Iris keeps the project's DESIGN.md in the Stitch format" (27 Oct 2026).
Two files, and the words keep them apart:

- the **root** `DESIGN.md`: the project's design system, at the repo root, in the spec's format
  (https://github.com/google-labs-code/design.md, `docs/spec.md`, `version: alpha`); written from
  `plugins/ada/templates/DESIGN.md`;
- **`.claude/handoff/DESIGN.md`**: the step's design (status, problem, sources, options, layout per
  breakpoint, states, copy, images, contrast), whose type, colour and spacing sections refer to the
  root tokens by name (`{colors.primary}`).

The behaviour (`ada-handoff-init` not copying the root template) is in
`tests/functional/05-handoff-files/design-md-copied-kept-archived.md`, section 7. The dry runs
are in `project-design-md-dry-runs.md`.

## Precondition

- This checkout, with the change in its working tree and `origin/next-release` fetched.
- `git`, `python3` (3.8 or later), `awk`, `cmp`, `mktemp`.
- For the lint check (Definition of Done 1): `npx` and `npm` on the PATH, and the network. The
  check runs `npx --yes @google/design.md@<pinned> lint DESIGN.md`, which downloads and runs the
  package: the person running it must be content to. `LINT=0` skips it; the check is then
  **not verified**, never passed.
- The `claude` CLI, for `claude plugin validate` (skipped without it).
- No scratch repo of your own: the script makes its own under a temporary folder and only reads
  this checkout.

## Steps

### Part A: the mechanical checks

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`. Keep the whole output: the `info:` and `mention`
   lines are part B's input, and the lint output goes in the Run record.
3. Break it on purpose, twice, each in a fresh scratch copy of the checkout (`cp -R`, so `.git`
   comes too), with `REPO=<the copy> bash check.sh`:
   - swap the `## Shapes` and `## Components` headings in the copy's
     `plugins/ada/templates/DESIGN.md`: it must print `FAIL` for the section order;
   - delete the `Project DESIGN.md:` line from the copy's
     `plugins/ada/templates/handoff/DESIGN.md`: it must print `FAIL` for that line.

```bash
#!/bin/bash
# Root DESIGN.md in Google's DESIGN.md format: template, skill, roles and docs.
# PASS/FAIL per check; SKIP is not a pass. Exit 1 if any FAIL.
# Env: REPO (checkout under test), BASE (default: the merge base of origin/next-release and HEAD),
# LINT (default 1: run the pinned linter with npx; 0 skips it).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
cd "$REPO" || exit 2
BASE=${BASE:-$(git merge-base origin/next-release HEAD 2>/dev/null)}
LINT=${LINT:-1}
if [ -n "$BASE" ] && ! git rev-parse -q --verify "$BASE^{commit}" >/dev/null; then echo "SETUP FAIL: base $BASE is not a commit"; exit 2; fi
P=plugins/ada
T=$P/templates/DESIGN.md
H=$P/templates/handoff/DESIGN.md
S=$P/skills/design/SKILL.md
B=$P/skills/begin/SKILL.md
O=$P/skills/onboard/SKILL.md
W=$(mktemp -d)
fails=0; skips=0
ok()   { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()    { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not()  { ! "$@"; }
skip() { echo "SKIP $1"; skips=$((skips+1)); }
joined() { tr '\n' ' ' | tr -s ' \t' '  '; }
# heading_section <regex> <file>: the lines under the first heading matching regex (any level, lower
# case), up to the next heading of the same or a higher level.
heading_section() { awk -v re="$1" '/^#+ /{ match($0,/^#+/); l=RLENGTH; if(p&&l<=lv) exit; if(!p&&tolower($0)~re){p=1;lv=l;next} } p' "$2"; }
in_section() { awk -v h="$1" '$0==h{p=1;next} p&&/^## /{exit} p' "$2" | grep -Eqi -- "$3"; }
has() { grep -Eqi -- "$1" "$2"; }
ROOT='(root|project.?s)[^.|]{0,20}DESIGN\.md'

# ---------------------------------------------------------------------------------------------
# DoD 1. plugins/ada/templates/DESIGN.md: the spec's front matter keys and its 8 sections in order.
t "templates/DESIGN.md exists" test -f "$T"
tr -d '\r' < "$T" > "$W/T" 2>/dev/null || : > "$W/T"
t "templates/DESIGN.md: line 1 is ---" test "$(head -1 "$W/T")" = "---"
t "templates/DESIGN.md: the front matter is closed by a --- line" test "$(grep -c '^---$' "$W/T")" -ge 2
awk 'NR==1{next} /^---$/{exit} {print}' "$W/T" > "$W/fm"
awk 'NR==1{next} f{print;next} /^---$/{f=1}' "$W/T" > "$W/body"
grep -oE '^[A-Za-z_][A-Za-z0-9_-]*:' "$W/fm" | tr -d ':' > "$W/keys"
for k in version name description colors typography rounded spacing components; do
  t "front matter: key '$k', once" test "$(grep -cx "$k" "$W/keys")" = 1
done
t "front matter: version: alpha" grep -Eq "^version: *[\"']?alpha[\"']? *(#.*)?$" "$W/fm"
extra=$(grep -vxE 'version|name|description|omitted|colors|typography|rounded|spacing|components' "$W/keys" | tr '\n' ' ')
t "front matter: no key outside the spec's schema (found: ${extra:-none})" test -z "$extra"
t "front matter: colors defines primary (the spec's minimum)" \
  sh -c "awk '/^colors:/{p=1;next} /^[^ #]/{p=0} p' '$W/fm' | grep -Eq '^ +[\"'\'']?primary[\"'\'']?:'"
awk '/^```/{f=!f;next} !f && /^## /{sub(/^## +/,""); sub(/ +$/,""); print}' "$W/body" > "$W/h2"
printf '%s\n' Overview Colors Typography Layout 'Elevation & Depth' Shapes Components "Do's and Don'ts" > "$W/want"
t "the 8 sections, as ## headings, exactly these, in the spec's order" cmp -s "$W/want" "$W/h2"
cmp -s "$W/want" "$W/h2" || { echo "info: ## headings found:"; sed 's/^/info:   /' "$W/h2"; }
t "templates/DESIGN.md: no Status: line (Ada's data stays in the handoff)" not grep -Eiq '^[-* ]*\**status\**:' "$W/T"
t "templates/DESIGN.md: no Asana, Definition of Done or requirements" not grep -Eiq 'asana|definition of done|requirement' "$W/T"
t "templates/DESIGN.md: no contrast-ratio table (it stays in the handoff)" not grep -Eiq '^\|.*contrast' "$W/T"
t "templates/DESIGN.md: no awesome-design-md sections" not grep -Eiq 'responsive behavio|agent prompt guide' "$W/T"
t "templates/DESIGN.md: no credit (README only)" not grep -Eiq 'google|credit|copyright|licen[cs]e|©' "$W/T"
t "templates/DESIGN.md: no CR, no trailing spaces" not grep -Eq "$(printf '\r')|[[:space:]]+$" "$T"

# The pinned linter: one exact version wherever an npx command names it.
grep -rhoE 'npx( +-[-a-z]+)* +@google/design\.md[^ `"'\'')]*' $P README.md .claude/team CHANGELOG.md 2>/dev/null \
  | sed -E 's/.*@google\/design\.md//' | sort -u > "$W/pins"
PIN=$(sed -n 's/^@//p' "$W/pins" | head -1)
echo "info: linter versions named in npx commands: $(tr '\n' ' ' < "$W/pins")"
t "every npx @google/design.md command names the same version" test "$(wc -l < "$W/pins" | tr -d ' ')" = 1
t "that version is exact (x.y.z, not latest, a range or none): '${PIN:-none}'" \
  sh -c 'printf "%s\n" "$1" | grep -Eqx "[0-9]+\.[0-9]+\.[0-9]+"' _ "${PIN:-}"
t "design skill: the pinned lint command, exactly: npx @google/design.md@${PIN:-?} lint DESIGN.md" \
  grep -qF "npx @google/design.md@${PIN:-?} lint DESIGN.md" "$S"
RR=.claude/handoff/REVIEW-REQUEST.md
rrpin=$(grep -oE '@google/design\.md@[0-9A-Za-z.^~-]+' "$RR" 2>/dev/null | sed 's/.*@//' | sort -u | tr '\n' ' ')
if [ -n "$rrpin" ]; then
  t "REVIEW-REQUEST.md records the same version ($rrpin)" test "$rrpin" = "$PIN "
else
  skip "REVIEW-REQUEST.md records no @google/design.md version yet (Bob records it there)"
fi
if [ -n "${PIN:-}" ] && command -v npm >/dev/null 2>&1; then
  t "npm has @google/design.md@$PIN" test "$(npm view "@google/design.md@$PIN" version 2>/dev/null)" = "$PIN"
else
  skip "npm view: no npm or no pinned version"
fi

# Fill the template with sample values (front matter leaves replaced by valid samples of their
# type; references to tokens that exist kept; the body kept as it is), then lint the filled copy.
cat > "$W/fill.py" <<'PY'
import re, sys
src = open(sys.argv[1], encoding="utf-8").read().replace("\r\n", "\n").split("\n")
if not src or src[0] != "---":
    sys.exit("no front matter")
end = src.index("---", 1)
fm, rest = src[1:end], src[end:]
KEY = re.compile(r"""^(\s*)("[^"]+"|'[^']+'|[^\s:#'"-][^:#]*?):(?:\s+(.*))?$""")
def strip_comment(v):
    v = (v or "").strip()
    if v.startswith("#"):
        return ""
    if v and v[0] in "\"'":
        j = v.find(v[0], 1)
        return v[: j + 1] if j > 0 else v
    return re.sub(r"\s+#.*$", "", v).strip()
rows, stack = [], []
for line in fm:
    m = KEY.match(line)
    if not m or line.strip().startswith(("#", "- ")):
        rows.append((line, None, None, None))
        continue
    ind, key = len(m.group(1)), m.group(2).strip("\"'")
    while stack and stack[-1][0] >= ind:
        stack.pop()
    path = [k for _, k in stack] + [key]
    stack.append((ind, key))
    rows.append((line, path, strip_comment(m.group(3)), m.group(1)))
leaves = {".".join(p) for _, p, v, _ in rows if p and v}
typos = [p[1] for _, p, v, _ in rows if p and len(p) == 2 and p[0] == "typography"]
REF = re.compile(r"""^["']?\{([A-Za-z0-9_.-]+)\}["']?$""")
def colour(name):
    n = name.lower()
    light = any(w in n for w in ("surface", "background", "neutral", "bg", "white", "light", "canvas", "paper"))
    if n.startswith("on-") or n.startswith("on_"):
        return '"#1A1A1A"' if light else '"#FFFFFF"'
    if light:
        return '"#FFFFFF"'
    if "error" in n or "danger" in n:
        return '"#B3261E"'
    return '"#1A5276"'
TYPE = {"fontFamily": "Georgia", "fontSize": "16px", "fontWeight": "400", "lineHeight": "1.5",
        "letterSpacing": "0em", "fontFeature": '"normal"', "fontVariation": '"normal"'}
def sample(path, val):
    top, prop = path[0], path[-1]
    if len(path) == 1:
        return {"version": "alpha", "name": '"Sample Project"',
                "description": '"A sample design system for the lint check."'}.get(top, val)
    m = REF.match(val)
    if m and (m.group(1) in leaves or (top == "components" and m.group(1) in {"typography." + t for t in typos})):
        return '"{%s}"' % m.group(1)
    if top == "colors":
        return colour(path[1])
    if top == "typography":
        return TYPE.get(prop, val)
    if top == "rounded":
        return "9999px" if "full" in prop else "4px"
    if top == "spacing":
        return "8px"
    if top == "components":
        if prop == "backgroundColor":
            return '"#1A5276"'
        if prop == "textColor":
            return '"#FFFFFF"'
        if prop == "typography":
            return '"{typography.%s}"' % typos[0] if typos else val
        if prop in ("rounded", "padding", "size", "height", "width"):
            return "4px" if prop == "rounded" else "8px"
    return val
out = ["---"]
n = 0
for line, path, val, ind in rows:
    if path and val:
        out.append("%s%s: %s" % (ind, path[-1], sample(path, val)))
        n += 1
    else:
        out.append(line)
open(sys.argv[2], "w", encoding="utf-8", newline="\n").write("\n".join(out + rest))
print("info: %d front matter values filled with samples" % n)
PY
mkdir -p "$W/lint" "$W/raw"
if python3 -I "$W/fill.py" "$T" "$W/lint/DESIGN.md" > "$W/fill.out" 2>&1; then
  cat "$W/fill.out"; ok 0 "templates/DESIGN.md fills with sample values"
else
  cat "$W/fill.out"; ok 1 "templates/DESIGN.md fills with sample values"
fi
cp "$T" "$W/raw/DESIGN.md" 2>/dev/null
lint_errors() { grep -Eiq '(^|[^0-9])[1-9][0-9]* +errors?|"severity" *: *"error"|^ *error[: ]' "$1"; }
if [ "$LINT" != 1 ]; then
  skip "lint (LINT=$LINT): Definition of Done 1's lint is NOT VERIFIED"
elif ! command -v npx >/dev/null 2>&1 || [ -z "${PIN:-}" ]; then
  skip "lint: no npx or no pinned version: Definition of Done 1's lint is NOT VERIFIED"
else
  (cd "$W/lint" && npx --yes "@google/design.md@$PIN" lint DESIGN.md) > "$W/lint.out" 2>&1; lrc=$?
  echo "info: npx @google/design.md@$PIN lint DESIGN.md (the filled template): exit $lrc; output:"
  sed 's/^/info:   /' "$W/lint.out"
  t "the filled template lints with exit 0" test "$lrc" = 0
  t "the filled template lints with 0 errors" not lint_errors "$W/lint.out"
  (cd "$W/raw" && npx --yes "@google/design.md@$PIN" lint DESIGN.md) > "$W/raw.out" 2>&1
  echo "info: the template as shipped, unfilled: exit $?; output:"; sed 's/^/info:   /' "$W/raw.out"
fi

# ---------------------------------------------------------------------------------------------
# DoD 4. The handoff template: Project DESIGN.md: line; type, colour and spacing by token name.
t "handoff template: still at templates/handoff/DESIGN.md (not renamed)" test -f "$H"
t "handoff template: a 'Project DESIGN.md:' line" grep -Eq '(^|[^A-Za-z])Project DESIGN\.md:' "$H"
t "handoff template: that line offers created, updated (<tokens>) and unchanged" \
  sh -c "grep -E '(^|[^A-Za-z])Project DESIGN\.md:' '$H' | grep -i created | grep -i updated | grep -qi unchanged"
heading_section 'colou?r' "$H" > "$W/hcol"
heading_section 'typ' "$H" > "$W/htyp"
heading_section 'spacing' "$H" > "$W/hspc"
t "handoff template: a colour section" test -s "$W/hcol"
t "handoff template: a type section" test -s "$W/htyp"
t "handoff template: a spacing section" test -s "$W/hspc"
t "handoff template, colour section: refers to {colors.<name>}" grep -q '{colors\.' "$W/hcol"
t "handoff template, type section: refers to {typography.<name>}" grep -q '{typography\.' "$W/htyp"
t "handoff template, spacing section: refers to {spacing.<name>}" grep -q '{spacing\.' "$W/hspc"
t "handoff template, colour section: the table keeps its Contrast ratio column" grep -Eiq '^\|.*contrast ratio' "$W/hcol"
joined < "$H" > "$W/H1"
t "handoff template: a token the design adds is marked \"new\"" \
  grep -Eiq '(new.{0,160}token|token.{0,160}[^a-z]new[^a-z])' "$W/H1"
for s in col typ spc; do
  n=$(grep -oE '#[0-9A-Fa-f]{6}\b|[0-9]+(\.[0-9]+)?(px|rem)\b' "$W/h$s" | wc -l | tr -d ' ')
  echo "info: handoff template, $s section: $n literal colour or size values (part B item 9 reads them)"
done
heading_section 'what was found' "$H" > "$W/hfound"
t "handoff template, What was found: the root DESIGN.md is a source to record" grep -q 'DESIGN\.md' "$W/hfound"
for term in 'Status:' 'The problem' 'What was found' 'Options' 320 768 1280 state copy 'alt text|image' contrast P32; do
  t "handoff template still holds: $term" grep -Eqi -- "$term" "$H"
done

# ---------------------------------------------------------------------------------------------
# DoD 3. Iris's skill.
joined < "$S" > "$W/S1"
heading_section 'look first' "$S" > "$W/look"; joined < "$W/look" > "$W/look1"
heading_section 'write design' "$S" > "$W/write"; joined < "$W/write" > "$W/write1"
heading_section 'sign.?off' "$S" | joined > "$W/sign1"
heading_section 'fallback' "$S" | joined > "$W/fall1"
heading_section 'standalone|on its own' "$S" | joined > "$W/alone1"
heading_section 'partway|part way' "$S" | joined > "$W/part1"
heading_section 'may not' "$S" | joined > "$W/may1"
t "skill: a Look first section" test -s "$W/look1"
t "Look first: names the root DESIGN.md" has "$ROOT" "$W/look1"
lnum() { grep -nE -- "$1" "$W/look" | grep -v 'handoff/DESIGN' | head -1 | cut -d: -f1; }
first_root=$(lnum 'DESIGN\.md'); first_guide=$(lnum 'Design guide')
t "Look first: the root DESIGN.md comes before the Design guide: line (lines ${first_root:-none} < ${first_guide:-none})" \
  test -n "$first_root" -a -n "$first_guide" -a "${first_root:-0}" -lt "${first_guide:-0}"
t "Look first: says it is read first" has 'DESIGN\.md.{0,200}first|first.{0,200}DESIGN\.md' "$W/look1"
t "Look first: recorded in What was found" has 'what was found' "$W/look1"
t "skill: a Write DESIGN.md section" test -s "$W/write1"
t "Write DESIGN.md: works on the root file" has "$ROOT" "$W/write1"
t "Write DESIGN.md: writes it from plugins/ada/templates/DESIGN.md" grep -qF 'templates/DESIGN.md' "$W/write1"
t "Write DESIGN.md: creates it when the project has none" has 'creat' "$W/write1"
t "Write DESIGN.md: adds the new tokens and components when it exists" \
  has '(add|append).{0,120}(token|component)' "$W/write1"
t "Write DESIGN.md: changing or removing an existing token needs the developer's explicit yes" \
  has '(chang|remov).{0,240}explicit|explicit.{0,240}(chang|remov)' "$W/write1"
t "Write DESIGN.md: that yes is recorded in the handoff" \
  has 'yes.{0,200}(record|handoff)|(record|handoff).{0,200}yes' "$W/write1"
t "Write DESIGN.md: the Project DESIGN.md: line, created | updated | unchanged" \
  has 'Project DESIGN\.md:.{0,160}created.{0,160}updated.{0,160}unchanged' "$W/S1"
t "skill: the pinned lint command" grep -qE 'npx @google/design\.md@[0-9]+\.[0-9]+\.[0-9]+ lint DESIGN\.md' "$S"
t "skill: fixes the lint's errors" has 'fix.{0,120}error|error.{0,120}fix' "$W/S1"
t "skill: before she signs the design off" has 'lint.{0,300}(before|until).{0,120}sign|sign.{0,120}(after|once).{0,300}lint' "$W/S1"
t "Sign-off: mentions the lint or the root DESIGN.md" has "lint|$ROOT" "$W/sign1"
t "skill: 'not linted' when npx is missing" has 'npx.{0,240}not linted|not linted.{0,240}npx' "$W/S1"
t "skill: P32 to P39 still checked when not linted" has 'not linted.{0,300}P32|P32.{0,300}not linted' "$W/S1"
t "Fallbacks: npx or 'not linted'" has 'npx|not linted' "$W/fall1"
t "Standalone: the root DESIGN.md" has "$ROOT" "$W/alone1"
t "Partway: the root DESIGN.md" has "$ROOT" "$W/part1"
t "May / may not: the root DESIGN.md" has "$ROOT" "$W/may1"

# ---------------------------------------------------------------------------------------------
# DoD 5. Role files: the root DESIGN.md tokens are the source of values.
for f in bob claudia tess; do
  joined < "$P/agents/$f.md" > "$W/$f.1"
  t "$f.md names the root DESIGN.md" has "$ROOT" "$W/$f.1"
  t "$f.md: its tokens are where values come from" has "$ROOT.{0,300}token|token.{0,300}$ROOT" "$W/$f.1"
done
t "bob.md: builds with the token values" has '(build|use).{0,200}(token|value)' "$W/bob.1"
t "claudia.md: built values match the tokens" has 'match.{0,200}token|token.{0,200}match' "$W/claudia.1"
t "tess.md: the design comparison includes the root DESIGN.md" \
  has "compar.{0,400}$ROOT|$ROOT.{0,400}compar" "$W/tess.1"
t "no agent's tools: line changed (no MCP, no Artifact)" \
  not sh -c "git diff '$BASE' -- $P/agents | grep -Eq '^[-+]tools:'"

# Every DESIGN.md mention: a block (paragraph, list item, table row, heading) with a bare
# DESIGN.md must also say which (root / project's, handoff, templates/, plan/).
cat > "$W/paras.py" <<'PY'
import re, sys
DIS = re.compile(r"handoff|\broot\b|project'?s|templates/DESIGN|plan/DESIGN|top of the repo", re.I)
BARE = re.compile(r"(?<![/\w.-])DESIGN\.md")
# "Write DESIGN.md" is the section's name in the brief; a heading names a section that may cover
# both files. Both are listed for part B but not failed here.
SECTION = re.compile(r"Write\s+DESIGN\.md")
bad = 0
for path in sys.argv[1:]:
    lines = open(path, encoding="utf-8").read().split("\n")
    blocks, cur, fence = [], [], False
    for i, l in enumerate(lines, 1):
        if l.startswith("```"):
            fence = not fence
        new = (not fence) and (l.strip() == "" or re.match(r"^\s*([-*+]|\d+\.)\s|^#|^\|", l))
        if new and cur:
            blocks.append(cur); cur = []
        if l.strip():
            cur.append((i, l))
    if cur:
        blocks.append(cur)
    for b in blocks:
        text = SECTION.sub("", " ".join(l for _, l in b))
        for i, l in b:
            for _ in BARE.finditer(l):
                print("mention %s:%d: %s" % (path, i, l.strip()))
        if BARE.search(text) and not DIS.search(text) and not b[0][1].startswith("#"):
            bad += 1
            print("UNCLEAR %s:%d: %s" % (path, b[0][0], text[:200]))
sys.exit(1 if bad else 0)
PY
files="$P/agents/bob.md $P/agents/claudia.md $P/agents/tess.md $B $P/skills/planning/SKILL.md $S"
python3 -I "$W/paras.py" $files > "$W/mentions"; prc=$?
grep -c '^mention' "$W/mentions" | sed 's/^/info: bare DESIGN.md mentions listed for part B: /'
cat "$W/mentions"
ok $prc "every bare DESIGN.md mention in bob, claudia, tess, begin, planning and the design skill sits in a block that says which"

# ---------------------------------------------------------------------------------------------
# DoD 6. Onboard's Design guide row.
joined < "$O" > "$W/O1"
t "onboard skill: Design guide: DESIGN.md" has 'Design guide: *`?DESIGN\.md' "$W/O1"
t "onboard skill: ... when the repo has a root DESIGN.md" \
  has "$ROOT.{0,300}Design guide: *\`?DESIGN\.md|Design guide: *\`?DESIGN\.md.{0,300}$ROOT" "$W/O1"
t "onboard skill: none is still the value with nothing to go on (regression)" has 'Design guide:.{0,300}none' "$W/O1"

# ---------------------------------------------------------------------------------------------
# DoD 7. README, CHANGELOG, SYSTEM.md; the credit in README only.
t "README links Google's DESIGN.md spec" grep -qF 'github.com/google-labs-code/design.md' README.md
t "README names Google with it" sh -c "grep -F 'google-labs-code/design.md' README.md | grep -qi google || grep -qi \"google's design\" README.md"
awk '$0=="## Unreleased"{p=1;next} p&&/^## /{exit} p' CHANGELOG.md | joined > "$W/unrel1"
t "CHANGELOG Unreleased: a bold-lead bullet about DESIGN.md" has '- \*\*[^*]+\*\*.{0,600}DESIGN\.md|- \*\*[^*]*DESIGN\.md' "$W/unrel1"
t "CHANGELOG Unreleased: no 'Nothing yet.'" not in_section '## Unreleased' CHANGELOG.md '^Nothing yet\.'
joined < .claude/team/SYSTEM.md > "$W/sys1"
t "SYSTEM.md: describes the root DESIGN.md" has "$ROOT" "$W/sys1"
t "SYSTEM.md: and .claude/handoff/DESIGN.md" grep -qF '.claude/handoff/DESIGN.md' "$W/sys1"
t "SYSTEM.md: the root file's tokens" has "$ROOT.{0,400}token|token.{0,400}$ROOT" "$W/sys1"
if [ -n "$BASE" ]; then
  # 'credit' also matches the pointers to begin's pr-gate.md "Commits and credit" (Co-Authored-By lines): not a credit to Google.
  t "no credit added in files an agent reads (plugins/ada, .claude/team, CLAUDE.md)" \
    not sh -c "git diff '$BASE' -- $P .claude/team CLAUDE.md | grep '^+' | grep -Eiv 'commits and credit|each role.s credit' | grep -Eiq 'credit|thanks to|courtesy|copyright|licen[cs]e|google-labs-code'"
fi
grep -rniE 'google' $P | grep -v '@google/design\.md@' | sed 's/^/info: Google named in the plugin (part B item 12): /'

# ---------------------------------------------------------------------------------------------
# Out of scope and regression.
if [ -n "$BASE" ]; then
  t "plugin.json and marketplace.json unchanged (P31)" \
    test -z "$(git diff "$BASE" -- $P/.claude-plugin/plugin.json .claude-plugin/marketplace.json)"
  t "CHANGELOG: no new version heading (P31)" not sh -c "git diff '$BASE' -- CHANGELOG.md | grep -Eq '^\+## +v?[0-9]'"
  t "bin/ unchanged: ada-handoff-init and ada-onboard as released (the onboard change is in the skill)" \
    sh -c "git diff --quiet '$BASE' -- $P/bin && test -z \"\$(git ls-files --others --exclude-standard -- $P/bin)\""
  t "templates/handoff/ holds the same files as before (the handoff DESIGN.md not renamed)" \
    test "$(git ls-tree --name-only "$BASE" $P/templates/handoff/ | sed 's|.*/||' | sort | tr '\n' ' ')" = "$(ls $P/templates/handoff | sort | tr '\n' ' ')"
  t "no MCP added (.mcp.json, mcp__ tools)" \
    not sh -c "test -e .mcp.json || test -e $P/.mcp.json || git diff '$BASE' -- . ':!tests' ':!.claude/handoff' | grep '^+' | grep -q 'mcp__'"
  t "git diff --check against the base" git diff --check "$BASE"
  echo "info: files changed against the base (scope is Claudia's; listed for the Run record):"
  { git diff --name-only "$BASE"; git ls-files --others --exclude-standard; } | sort -u | sed 's/^/info:   /'
else
  skip "no git base: P31, bin/, templates/handoff/, MCP and diff checks"
fi
t "templates/repo/ has no DESIGN.md (onboarding never copies the root template)" test ! -e $P/templates/repo/DESIGN.md
t "this repo gains no root DESIGN.md (onboarded projects are out of scope)" test ! -e DESIGN.md
mkdir -p "$W/onb" && git -C "$W/onb" init -q
(cd "$W/onb" && bash "$REPO/$P/bin/ada-onboard" files PROJECT_NAME=Scratch CODE_DIR=src) > "$W/onb.out" 2>&1
t "ada-onboard files in a scratch repo: no DESIGN.md at its root" test ! -e "$W/onb/DESIGN.md"
if command -v claude >/dev/null 2>&1; then
  t "claude plugin validate passes" claude plugin validate "$REPO/$P"
else
  skip "claude CLI not found: plugin validate"
fi

echo "$fails failed, $skips skipped"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W (the filled template is lint/DESIGN.md)"; exit 1; fi
```

### Part B: read by a person

Use part A's output. For each item write down the heading it sits under, or what you found. The
words in quotes are the brief's.

**Every `DESIGN.md` mention** (Definition of Done 5: "No line in them, in begin or in planning
leaves it unclear which DESIGN.md is meant"):

1. For each `mention` line part A printed (bob, claudia, tess, begin, planning, and Iris's skill),
   write `root`, `handoff`, `template` or `plan` for the file it means, reading only that line and
   its sentence. Any you can't tell is a fail: list it with its `file:line`. Part A's `UNCLEAR`
   lines are fails already; this item catches a block that names both files and leaves one
   sentence open.
2. In `begin/SKILL.md` and `planning/SKILL.md`, the lines that key on `Status: agreed`, copy
   `plan/DESIGN-<task id>.md`, or finish the brief from the design are about
   `.claude/handoff/DESIGN.md`, and say so or are unmistakable.

**`plugins/ada/templates/DESIGN.md`** (Definition of Done 1):

3. It reads as a skeleton to fill: each section says what goes in it, as the spec describes the
   section (Overview: the look and feel and audience; Colors: the palettes, at least `primary`;
   Typography: the levels; Layout: grid and spacing; Elevation & Depth; Shapes: corner radii;
   Components; Do's and Don'ts). Nothing in it is Ada's (status, requirements, states, contrast
   ratios).

**`plugins/ada/skills/design/SKILL.md`** (Definition of Done 3, and the Decisions):

4. Look first: when the project has a root `DESIGN.md`, she reads it first, before the
   `Design guide:` line and the other sources, and records it in "What was found". When it has
   none, nothing asks her for it (a project without one is not an error).
5. Write DESIGN.md: on agreement she creates the root file from `plugins/ada/templates/DESIGN.md`
   when there is none (from the agreed design and what she found), or adds the new tokens and
   components when there is one. Changing or removing an existing token needs the developer's
   explicit yes, because it changes the whole site, and the yes is recorded in the handoff.
   Nothing lets her change or remove one without it. The handoff gets
   `Project DESIGN.md: created | updated (<tokens>) | unchanged`.
6. Lint: when `npx` is available she runs the pinned command, fixes its errors, and only then
   signs the design off; without `npx` the handoff says "not linted" and P32 to P39 are still
   checked as before. A reader can tell which comes first, the lint or the sign-off.
7. Sign-off, Hand back, standalone, partway and Fallbacks agree with 4 to 6: the standalone run
   and the partway return both write the root file the same way; Hand back says what happened to
   it (the `Project DESIGN.md:` line); the root file is committed in the step's PR, not left
   behind.
8. What you may and may not do: she may write the root `DESIGN.md`; nothing lets her change or
   remove an existing token without the developer's yes. Nothing tells her to import from or
   export to Stitch, or to use an MCP server (out of scope).

**`plugins/ada/templates/handoff/DESIGN.md`** (Definition of Done 4):

9. Its type, colour and spacing sections refer to the root tokens by name (`{colors.primary}`)
   and don't repeat their values (part A printed how many literal values each holds: any that
   is a token's value, not a "new" token's proposal or a contrast ratio, is a fail). A token the
   design adds is marked "new". The colour table keeps its Contrast ratio column.

**Role files and docs** (Definitions of Done 5 to 7):

10. `bob.md`: he builds with the root tokens' values. `claudia.md`: she checks the built values
    match the root tokens. `tess.md`: the design comparison includes the root file. All three still
    act on `.claude/handoff/DESIGN.md` only when it says `Status: agreed`.
11. `onboard/SKILL.md`: when the repo has a root `DESIGN.md`, the drafted `PROJECT.md` gets
    `Design guide: DESIGN.md`; otherwise the rule is as before (a guide found, else `none`).
12. README credits Google's DESIGN.md spec, with its link. The credit appears nowhere else: not
    in the plugin, `.claude/team/` or `CLAUDE.md` (part A's `info: Google named` lines should be
    the `@google/design.md` package only, or a plain name of the format, not a credit).
    CHANGELOG has one Unreleased bullet with a bold lead (S105). SYSTEM.md describes the two
    files: which is the design system, which is the step's design, who writes and reads each.
13. Any `file:line` reference in `SYSTEM.md`, `API.md` or `DEVELOPING.md` to a skill or agent
    this change edits still points at the line it describes.

## Expected

Part A: every line `PASS`, last line `0 failed, 0 skipped`, exit 0. A `SKIP` is not a pass: with
lint skipped, Definition of Done 1's lint is not verified. If the linter's only errors are about
the contrast between sample colours the filler chose (its output names the two tokens), the fault
is the filler's, not the template's: edit those values in the kept `lint/DESIGN.md`, rerun the
lint by hand, and record both. Every break in step 3 prints `FAIL`. Part B: every item found;
every mention in item 1 says which file.

Sources: the brief (Decisions, Constraints, Definition of Done 1 and 3 to 7, Out of scope) and the
spec it names (`docs/spec.md`, `version: alpha`: the front matter keys `version`, `name`,
`description`, `colors`, `typography`, `rounded`, `spacing`, `components`; the `##` sections
Overview, Colors, Typography, Layout, Elevation & Depth, Shapes, Components, Do's and Don'ts, in
that order; `colors` defines at least `primary`).

## Observe at

The script's output and exit code; the files named in part B. On a failure the scratch folder is
kept: the filled template is `lint/DESIGN.md` there.

## Regression

- `tests/functional/13-design/design-step-text-checks.md` part A still passes: the design step,
  the `Status: agreed` gates and the handoff template's other sections are unchanged. Where a
  failure there comes from this brief changing the handoff template (its type, colour and
  spacing sections now by token name), report it under Escalate; don't edit the old check to
  pass.
- `ada-handoff-init` and `ada-onboard` are unchanged, onboarding never copies the root template,
  and this repo gains no root `DESIGN.md` (part A, "Out of scope and regression").
- No version numbers move (P31).
