# In a product repo Ada writes or updates the knowledge-base entry (uncommitted) before Claudia reviews, the PR gate says whether it was updated (and deletes the line for a site), and Claudia's Docs still true makes a missing or mismatched entry Should Fix; a site, or a project with no `Kind:` line, sees none of it

- Area: 08 Session flow (`skills/begin/SKILL.md` points to `skills/begin/knowledge-base.md`, where Ada's
  entry step is written: Ada writes the entry before spawning Claudia;
  `skills/begin/pr-gate.md`: item 3 and the PR.md step); 09 Roles
  (`agents/claudia.md`: 13. Docs still true); 11 Templates (the PR template line is in
  `02-onboarding/refresh-adds-pr-template-lines.md`)
- Asana: https://app.asana.com/0/0/1220058523035214/f
- Automate as: none (part A is a self-checking shell script; parts B and C are dry runs with
  `claude --plugin-dir` in scratch repos, each with a self-checking after-script)
- Last run: 18 Nov 2026, Part A 0 failed (67 passed, after fix round 6, claudia.md item 13 re-pointed), start commit not re-run; Parts B3 and B6 driven by hand earlier, 0 failed; the rest not run (see the PR)

## Precondition

- Part A: this checkout, with the change in its working tree, on a branch whose history holds the
  commit it started from (`origin/add/knowledge-base`, a376b09 when this was written). Nothing
  else.
- Parts B and C:
  - `python3` (3.8 or later), `git` 2.28 or later, and `claude` logged in. Asana and GitHub
    aren't needed: the prompts say the task isn't real, and `origin` is a local bare repo.
  - An empty scratch folder outside any repo, `S`, holding this script's `setup.sh` and
    `after.py`. Then, with `REPO` this checkout:

    ```sh
    REPO=<this checkout> S=<the scratch folder> bash <the scratch folder>/setup.sh
    ```

    It prints `made:` and eight repos. Each is a made-up add-on whose `src/shipping.php` charges
    shipping on a basket of exactly the £50.00 threshold (`>` where `>=` is meant), with the fix
    committed on the checked-out branch `fix/free-shipping-at-threshold` and `main` before it.
    The brief's Asana URL is a card in project `1299999999999300`.
    - `product-missing`, `product-mismatch`, `product-match`, `gate-product` and `ada-writes` have
      `Kind: product`, `Knowledge base: knowledge-base/`, and a knowledge base whose
      `config.json` has `asana.projectGid` `1299999999999300`: the card is from the product's
      support project.
      - `product-missing` and `ada-writes`: the fix, and no entry for it.
      - `product-mismatch`: the fix and an entry `issues/free-shipping-at-threshold.md` with
        `status: fixed-in-release` and `fixed_in: 1.3.0`, though the CHANGELOG has no 1.3.0 (the
        fix is under Unreleased).
      - `product-match` and `gate-product`: the fix and that entry with `status: open-bug`, no
        `fixed_in`, the Cause naming the `>` in `dryrun_free_shipping_applies()` and the Solution
        describing the fix as not yet released, with its `INDEX.md` line.
        Fixture artefact, left as it is: `setup.sh` commits that entry (commit `d1b8a1a` in the
        2026-11-18 B3 run) so the repo starts clean, though the README's step 5 says to leave
        it uncommitted and a real step has Ada commit it at the gate, after review. Claudia
        may raise that under Escalate; `after.py` doesn't check Escalate, and the checks on
        "nothing committed" compare against that commit.
    - `site` and `gate-site` have `Kind: site`, `Knowledge base: none` and no knowledge base;
      `nokind` is an older project with neither line. Each has the fix only.
    - `product-*`, `ada-writes`, `site` and `nokind` have a brief and `REVIEW-REQUEST.md`
      (`Ready for Review: YES`), for Claudia (and, in `ada-writes`, for Ada first). `gate-*` have every handoff file of a finished step (`Ready for PR: YES`
      from Claudia and Tess) and an `origin` bare repo with `main` pushed, for the gate.
    - Every repo has this checkout's PR template as `.github/pull_request_template.md`, and
      `ada@pie` off in `.claude/settings.local.json`, so only this checkout's plugin loads.

## Steps

### Part A: the text

1. Save the first block as `check.sh` in the scratch folder and run
   `REPO=<this checkout> bash <scratch>/check.sh`.
2. Break it on purpose: export the commit the branch started from
   (`git -C <this checkout> merge-base HEAD origin/add/knowledge-base`, a376b09 when this was
   written) with `git -C <this checkout> archive -o <scratch>/base.tar <that commit>`, unpack it
   into an empty folder with `tar -xf <scratch>/base.tar -C <empty folder>`, and run
   `REPO=<that folder> BASE_REPO=<this checkout> bash <scratch>/check.sh`.

```bash
#!/bin/bash
# Ada writes the entry before Claudia reviews, and the PR gate and Claudia's Docs still true
# check a product's knowledge-base entry, by the text of begin/SKILL.md, begin/pr-gate.md,
# agents/claudia.md, SYSTEM.md and the CHANGELOG; nothing else moved.
# PASS/FAIL; exit 1 if any FAIL, 2 on a setup error.
# Env: REPO (the checkout under test), BASE_REPO (a git checkout to read BASE from; default REPO),
# BASE (the commit the change started from; default the merge-base of BASE_REPO's HEAD and
# origin/add/knowledge-base, else a376b09).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE_REPO=${BASE_REPO:-$REPO}
BASE=${BASE:-$(git -C "$BASE_REPO" merge-base HEAD origin/add/knowledge-base 2>/dev/null || echo a376b09)}
GATE=plugins/ada/skills/begin/pr-gate.md
CL=plugins/ada/agents/claudia.md
PRT=plugins/ada/templates/repo/.github/pull_request_template.md
SK=plugins/ada/skills/begin/SKILL.md
KBM=plugins/ada/skills/begin/knowledge-base.md   # the entry paragraph, moved out of SKILL.md (fix round 5b)
KBR=plugins/ada/templates/knowledge-base/README.md   # changed under amendment 7 (fixed-in-release wording)
for f in "$GATE" "$CL" "$PRT" "$SK" CHANGELOG.md .claude/team/SYSTEM.md; do
  [ -f "$REPO/$f" ] || { echo "SETUP FAIL: no $REPO/$f"; exit 2; }
done
W=$(mktemp -d "${TMPDIR:-/tmp}/gate-kb.XXXXXX") || exit 2
trap 'rm -rf "$W"' EXIT
git -C "$BASE_REPO" show "$BASE:$GATE" > "$W/old-gate.md" 2>/dev/null \
  || { echo "SETUP FAIL: no $GATE at $BASE in $BASE_REPO"; exit 2; }
git -C "$BASE_REPO" show "$BASE:$CL" > "$W/old-claudia.md" || exit 2
git -C "$BASE_REPO" show "$BASE:$SK" > "$W/old-skill.md" || exit 2
echo "info: BASE $BASE"
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
not() { ! "$@"; }
# items <file>: one line per list item or paragraph (a list item, a heading or a blank line
# starts a new one; continuation lines are joined).
items() { awk '
  /^[[:space:]]*$/ { if (b != "") print b; b = ""; next }
  /^#/ || /^[[:space:]]*([-*]|[0-9]+\.) / { if (b != "") print b; b = $0; next }
  { sub(/^[[:space:]]+/, ""); b = (b == "" ? $0 : b " " $0) }
  END { if (b != "") print b }' "$1"; }
# has <file> <regex>...: one item of the file matches every regex, any case
has() { f=$1; shift; out=$(items "$f"); for re in "$@"; do out=$(printf '%s\n' "$out" | grep -Ei -- "$re"); done; [ -n "$out" ]; }
# flat <file>: the whole file on one line, runs of white space as one space
flat() { tr '\n' ' ' < "$1" | tr -s ' \t' '  '; }
# inn <file> <regex>: the file, flattened, matches the regex, any case
inn() { flat "$1" | grep -Eqi -- "$2"; }
hasf() { flat "$1" | grep -qF -- "$2"; }
# innall <file> <regex>...: the file, flattened, matches every regex, any case
innall() { f=$1; shift; for re in "$@"; do inn "$f" "$re" || return 1; done; }
# between <file> <start regex> <stop regex>: from the first line matching start to the line
# before the next line matching stop
between() { awk -v a="$2" -v z="$3" 'p && $0 ~ z { exit } $0 ~ a { p = 1 } p' "$1"; }
# without <file> <start regex> <stop regex>: the file less that stretch
without() { awk -v a="$2" -v z="$3" 'p && $0 ~ z { p = 0 } $0 ~ a { p = 1 } !p' "$1"; }
G=$REPO/$GATE; C=$REPO/$CL
between "$G" '^3\. ' '^4\. ' > "$W/g3.md"
between "$G" '^3\. ' '^6\. ' > "$W/g35.md"
between "$G" '^5\. ' '^6\. ' > "$W/g5.md"
between "$C" '^13\. ' '^14\. ' > "$W/c13.md"
awk '/^## Unreleased/{p=1;next} p&&/^## /{exit} p' "$REPO/CHANGELOG.md" > "$W/unrel.md"
t "setup: pr-gate.md item 3 found" test -s "$W/g3.md"
t "setup: pr-gate.md item 5 found" test -s "$W/g5.md"
t "setup: claudia.md item 13 found" test -s "$W/c13.md"

KB='knowledge.base'
# DoD 3: gate item 3 names the knowledge-base entry for products; the line is deleted for a site
t "DoD 3: item 3 names the knowledge-base entry" has "$W/g3.md" "$KB" 'entry'
t "DoD 3: ... for a product" has "$W/g3.md" "$KB" 'product'
t "DoD 3: ... whether it was updated, or why none was needed" has "$W/g3.md" "$KB" 'updat' 'why none|none was needed|why (one|it) (was|is)n.t|why not'
t "DoD 3: item 3 still names SYSTEM.md / API.md, updated or why none was needed" has "$W/g3.md" 'SYSTEM\.md' 'API\.md' 'why none was needed'
t "DoD 3: the PR.md step fills the PR's Knowledge base: line (items 3 to 5)" has "$W/g35.md" 'Knowledge base:' 'fill|PR\.md|issues/'
t "DoD 3: for a site, the Knowledge base: line is deleted (items 3 to 5)" has "$W/g35.md" 'Knowledge base' 'site' 'delet|remov|drop'
# Amendments, numbered in the order of the brief's table: A1 and A2 (2026-11-13), A3 to A5
# (2026-11-17), A8 (2026-11-18, the last row), all agreed by Anna Werno. A3 replaces A2's "only when reusable" with the README as
# merged; A4 puts awaiting-customer and ## Open issues out of scope, so nothing checks them.
t "A1: item 3 covers a card from the product's support project (config.json asana.projectGid)" inn "$W/g3.md" 'support project|projectGid'
t "A2, A3: item 3 follows the knowledge-base README's When to write an entry" inn "$W/g3.md" 'When to write an entry'
t "A3: for the entry, item 3's why none was needed names the README's exception (What doesn't get an entry)" has "$W/g3.md" "$KB" "What doesn.t get an entry|exception"
t "A3: item 3 doesn't make the entry depend on reusable knowledge" not inn "$W/g3.md" 'reusable'
# DoD 4: Claudia's item 13
t "DoD 4: item 13 covers a product's knowledge-base entry" has "$W/c13.md" "$KB|entry" 'product'
t "DoD 4: an item naming the entry makes it Should Fix" has "$W/c13.md" 'entry' 'Should Fix'
t "DoD 4: a missing entry" has "$W/c13.md" 'entry' 'missing|no entry|without an entry'
t "DoD 4: an entry whose Cause, Solution or status doesn't match the fix" has "$W/c13.md" 'Cause' 'Solution' 'status' 'match'
t "DoD 4 (Decisions): fixed-in-release with fixed_in only once a version exists" has "$W/c13.md" 'fixed-in-release' 'fixed_in' 'version'
t "DoD 4 (Decisions): until then open-bug, with the fix described" has "$W/c13.md" 'open-bug' 'fix' 'until|before|not yet|no version|unreleased'
t "A1: item 13 covers a card from the product's support project" inn "$W/c13.md" 'support project|projectGid'
t "A2, A3: item 13 follows the knowledge-base README's When to write an entry" inn "$W/c13.md" 'When to write an entry'
t "A3: item 13 doesn't make a missing entry depend on reusable knowledge" not inn "$W/c13.md" 'reusable'
# A5: begin/SKILL.md. For a product, once Bob's build is ready for review, Ada writes or updates
# the entry in the step's worktree before spawning Claudia, commits it in the PR (her commit),
# and fixes Claudia's knowledge-base finding herself.
SKF=$REPO/$SK; KBF=$REPO/$KBM
# 5b: the paragraph about a product's knowledge-base entry lives in begin/knowledge-base.md, read on
# demand like design-step.md and approval.md; SKILL.md keeps a short pointer to it.
t "A5: begin/knowledge-base.md exists" test -s "$KBF"
t "A5: SKILL.md tells Ada to read knowledge-base.md for a product" has "$SKF" '`knowledge-base\.md`' 'product' 'read'
t "A5: the entry paragraph left SKILL.md (it no longer names the README's No customer data rule; only the pointer stays)" not has "$SKF" 'No customer data'
t "A5: knowledge-base.md has Ada write or update the knowledge-base entry" has "$KBF" "$KB" 'entry' 'writ|updat'
t "A5: ... for a product" has "$KBF" "$KB" 'product'
t "A5: ... in the step's worktree" has "$KBF" "$KB" 'worktree'
t "A5: ... once Bob's build is ready for review" has "$KBF" "$KB" 'Ready for Review|ready for review|REVIEW-REQUEST'
t "A5: ... before spawning Claudia" has "$KBF" "$KB" 'Claudia' 'before|first|then spawn'
t "A5: ... and commits it in the PR (Ada's commit)" has "$KBF" "$KB" 'commit'
t "A5: Ada fixes Claudia's knowledge-base finding herself, not Bob" has "$KBF" "$KB" 'Claudia|finding|Should Fix|REVIEW-FEEDBACK' 'yourself|herself|not Bob|you fix|Ada fixes'
# A5, scoped (the README's "To write one" step 5 says "Leave the entry and INDEX.md uncommitted; the
# developer commits them", and A4 leaves the README as it is): in a PR Ada commits the entry, because
# it was written in the step's worktree; the README's rule is for an entry written outside a PR.
t "A5 scope: knowledge-base.md says Ada commits the entry, not the developer, because it was written in the step's worktree" has "$KBF" "$KB" 'commit(s)? (it|them),? not the developer' 'because' 'worktree'
t "A5 scope: knowledge-base.md says the README's step 5 (leave it uncommitted) is for an entry written outside a PR (Grace's in /ada:bughunt)" has "$KBF" "$KB" 'README' 'step 5' 'uncommitted' 'outside a PR' 'bughunt'
t "A5 scope: pr-gate.md step 5 has Ada commit the entry and INDEX.md in a commit of their own, not the developer, because they were written in the step's worktree" has "$W/g5.md" "$KB" 'INDEX\.md' 'commit' 'not the developer' 'because' 'worktree'
t "A5 scope: pr-gate.md step 5 says the README's step 5 (leave them uncommitted) is for an entry written outside a PR (Grace's in /ada:bughunt)" has "$W/g5.md" 'README' 'step 5' 'uncommitted' 'outside a PR' 'bughunt'
# A8 (2026-11-18, agreed by Anna Werno; PR #71 review points 1 to 5). (a) begin/knowledge-base.md, pr-gate.md
# (item 3) and claudia.md (item 13) each say how the support-project check is made (the brief's
# Asana: task's projects, from the Asana task lookup, against asana.projectGid) and that when it
# can't be checked the developer is asked, never "not a support card" assumed. The lookup is Ada's
# (Claudia has no Asana tools); Claudia checks the BUILD-LOG Knowledge base: line says how it was
# checked, and treats a "not a support card" that doesn't say how as Should Fix. (b) begin/knowledge-base.md names
# the README's "No customer data" rule for the entry Ada writes; Claudia's item 13 makes customer
# data in an entry Must Fix. (c) the PR template's Knowledge base: line says "written or updated".
SUPP='support project|projectGid'
HOW='task.s projects|projects? (of|that|the task)|project memberships|memberships|project list|list of projects'
ASK='ask(s|ed)? (the )?developer'
t "A8 (a): knowledge-base.md says how the support-project check is made (the Asana task's projects)" has "$KBF" "$SUPP" 'Asana' "$HOW"
t "A8 (a): pr-gate.md item 3 says how the support-project check is made (the Asana task's projects)" innall "$W/g3.md" "$SUPP" 'Asana' "$HOW"
# Claudia has no Asana tools (B4 fix round 6): the lookup is Ada's; Claudia checks that the BUILD-LOG line says how it was checked.
t "A8 (a): claudia.md item 13 says the support-project check is Ada's lookup of the Asana task's projects, not Claudia's, and that Claudia checks the BUILD-LOG Knowledge base: line says how it was checked" innall "$W/c13.md" "$SUPP" 'Asana' "$HOW" 'Ada looks' "(you|Claudia) (don.t|do not|doesn.t|does not) look it up" 'BUILD-LOG' 'Knowledge base:' 'says how it was checked'
t "A8 (a): knowledge-base.md says that when the check can't be made, Ada asks the developer" has "$KBF" "$SUPP" "$ASK"
t "A8 (a): pr-gate.md item 3 says that when the check can't be made, Ada asks the developer" innall "$W/g3.md" "$SUPP" "$ASK"
t "A8 (a): claudia.md item 13 says that when Ada can't make the check, she asks the developer" innall "$W/c13.md" "$SUPP" 'Ada looks' "can.t" "$ASK"
t "A8 (a): item 13 treats a 'not a support card' that doesn't say how it was checked as Should Fix, never assumed" innall "$W/c13.md" 'not a support card' 'Should Fix' 'assum|how (it|the card|the task)( was|.s been)? checked|says? how|explain'
t "A8 (b): knowledge-base.md names the README's No customer data rule for the entry Ada writes" has "$KBF" 'No customer data' 'README' 'entry'
t "A8 (b): item 13 makes customer data in an entry Must Fix" inn "$W/c13.md" 'customer data[^.]*Must Fix|Must Fix[^.]*customer data'
t "A8 (c): the PR template's Knowledge base: line says the entry written or updated" hasf "$REPO/$PRT" 'Knowledge base: the entry written or updated ('
t "A8 (c): ... and no longer says only 'the entry updated' (passes on the start commit: no line yet)" not hasf "$REPO/$PRT" 'Knowledge base: the entry updated'
# DoD 5: CHANGELOG, and the docs (Build order 4)
awk '/^- /{ if (b != "") print b; b = $0; next } /^[[:space:]]*$/{ if (b != "") print b; b = ""; next } { sub(/^[[:space:]]+/, ""); b = b " " $0 } END { if (b != "") print b }' "$W/unrel.md" > "$W/bullets.txt"
t "DoD 5: an Unreleased bullet with a bold lead names the PR template and the knowledge base" sh -c "grep '^- \*\*' '$W/bullets.txt' | grep -Ei 'PR template|pull.request template|pull_request_template' | grep -Eqi '$KB'"
t "DoD 5: ... and says onboarded repos will be offered the new template (refresh)" sh -c "grep -Ei 'PR template|pull.request template|pull_request_template' '$W/bullets.txt' | grep -Ei 'onboarded|refresh' | grep -Eqi 'offer'"
t "DoD 5: no 'Nothing yet.' left under Unreleased" sh -c "! grep -qx 'Nothing yet\.' '$W/unrel.md'"
t "docs: SYSTEM.md names the PR gate's knowledge-base check" has "$REPO/.claude/team/SYSTEM.md" 'PR gate|pr-gate' "$KB"
# One item (a list item, or the table, which items() joins) must hold the knowledge base and
# Claudia's item 13 (Docs still true): her check, not just her name somewhere beside it.
t "docs: SYSTEM.md names Claudia's knowledge-base check" has "$REPO/.claude/team/SYSTEM.md" 'Claudia(.s| \(`[^`]*`,) item 13' "$KB"

# Regression: what sits next to the change
without "$W/old-gate.md" '^3\. ' '^4\. ' | without - '^5\. ' '^6\. ' > "$W/gate-rest-old"
without "$G" '^3\. ' '^4\. ' | without - '^5\. ' '^6\. ' > "$W/gate-rest-new"
t "regression: pr-gate.md unchanged since BASE outside items 3 and 5" cmp -s "$W/gate-rest-old" "$W/gate-rest-new"
cmp -s "$W/gate-rest-old" "$W/gate-rest-new" || diff "$W/gate-rest-old" "$W/gate-rest-new" | sed -n '1,12s/^/      /p'
t "regression: item 3 still summarises what was built, the review, the tests, what was verified" hasf "$W/g3.md" 'what was built, what the review found and how it was resolved, which tests are supplied, what was verified and what was not'
t "regression: item 5 still updates BUILD-LOG.md" hasf "$W/g5.md" 'Update `BUILD-LOG.md`.'
t "regression: item 5 still pushes the branch" hasf "$W/g5.md" 'Push the branch.'
t "regression: item 5 still writes PR.md, every section filled" hasf "$W/g5.md" 'Fill every section; "none" is an answer, blank is not.'
t "regression: item 5 still fills the Design: line from DESIGN.md" hasf "$W/g5.md" 'its `Design:` line from `.claude/handoff/DESIGN.md`'
t "regression: item 5 still writes the Squash commit section" hasf "$W/g5.md" 'Fill its `## Squash commit` section.'
without "$W/old-claudia.md" '^13\. ' '^14\. ' > "$W/cl-rest-old"; without "$C" '^13\. ' '^14\. ' > "$W/cl-rest-new"
t "regression: claudia.md unchanged since BASE outside item 13" cmp -s "$W/cl-rest-old" "$W/cl-rest-new"
cmp -s "$W/cl-rest-old" "$W/cl-rest-new" || diff "$W/cl-rest-old" "$W/cl-rest-new" | sed -n '1,12s/^/      /p'
t "regression: item 13 is still Docs still true" grep -q '^13\. \*\*Docs still true\.\*\*' "$C"
t "regression: item 13 still makes an untrue SYSTEM.md or API.md statement Should Fix" has "$W/c13.md" 'statement in `SYSTEM\.md` or `API\.md` that the diff makes untrue' 'Should Fix'
t "regression: item 13 still covers a new function API.md doesn't list" hasf "$W/c13.md" "a new function others should call that \`API.md\` doesn't list"
# SKILL.md (A5): every paragraph added or changed names the knowledge base or the entry, and no
# more paragraphs went than came (a changed paragraph is one of each). Reflowing is no change.
items "$W/old-skill.md" | LC_ALL=C sort > "$W/sk-old"; items "$SKF" | LC_ALL=C sort > "$W/sk-new"
LC_ALL=C comm -13 "$W/sk-old" "$W/sk-new" > "$W/sk-added"; LC_ALL=C comm -23 "$W/sk-old" "$W/sk-new" > "$W/sk-gone"
t "regression: every SKILL.md paragraph added or changed since BASE names the knowledge base or the entry" not grep -Eqvi "$KB|entry|issues/" "$W/sk-added"
grep -Evi "$KB|entry|issues/" "$W/sk-added" | sed -n '1,6s/^/      /p'
t "regression: no SKILL.md paragraph removed since BASE, other than ones changed" test "$(grep -c '' "$W/sk-gone")" -le "$(grep -c '' "$W/sk-added")"
# Out of scope (No change to ada-onboard refresh; Units B2 and B3; P31; A4: no knowledge-base
# template): nothing else moved. begin/SKILL.md is expected since A5; the knowledge-base README
# template is expected since amendment 7 (fixed-in-release only once the fix is released).
changed=""
for p in $(git -C "$BASE_REPO" ls-tree -r --name-only "$BASE" -- plugins/ada/bin plugins/ada/.claude-plugin/plugin.json .claude-plugin/marketplace.json plugins/ada/templates plugins/ada/skills plugins/ada/agents .github); do
  case $p in "$GATE"|"$CL"|"$PRT"|"$SK"|"$KBR") continue ;; esac   # ($KBM is new: see "added")
  git -C "$BASE_REPO" show "$BASE:$p" | cmp -s - "$REPO/$p" || changed="$changed $p"
done
t "out of scope: no script, version, other template, skill file or agent changed since BASE" test -z "$changed"
[ -n "$changed" ] && printf '      changed: %s\n' $changed
added=$(cd "$REPO" && find plugins/ada/bin plugins/ada/skills plugins/ada/agents plugins/ada/templates -type f | while IFS= read -r f; do git -C "$BASE_REPO" cat-file -e "$BASE:$f" 2>/dev/null || echo "$f"; done | grep -vxF "$KBM")
t "out of scope: no file added to the plugin's bin, skills, agents or templates, but begin/knowledge-base.md" test -z "$added"
[ -n "$added" ] && printf '      added: %s\n' $added
echo "$fails failed"; [ "$fails" = 0 ]
```

### The setup script for parts B and C

Save this as `setup.sh` in the scratch folder.

```bash
#!/bin/bash
# setup.sh: the scratch repos for the dry runs, in $S (see Precondition). Env: REPO (this
# checkout), S (an empty scratch folder outside any repo).
set -eu
: "${REPO:?this checkout}" "${S:?the scratch folder}"
SUPPORT=1299999999999300
TASK=1299999999999010
BR=fix/free-shipping-at-threshold
ENTRY=knowledge-base/issues/free-shipping-at-threshold.md

make_repo() { # make_repo <dir> <kind line> <knowledge base line>
  R=$S/$1
  mkdir -p "$R/src" "$R/.claude/team" "$R/.github"
  cd "$R"
  git init -q -b main
  git config user.name "Dry Run"; git config user.email "dry-run@example.com"
  bash "$REPO/plugins/ada/bin/ada-onboard" gitignore >/dev/null
  git check-ignore -q .claude/handoff/x || printf '.claude/handoff/\n' >> .gitignore
  echo '{"enabledPlugins":{"ada@pie":false}}' > .claude/settings.local.json
  git check-ignore -q .claude/settings.local.json || printf '.claude/settings.local.json\n' >> .gitignore
  cp "$REPO/plugins/ada/templates/repo/.github/pull_request_template.md" .github/
  cat > .claude/team/PROJECT.md <<EOF
# Dry run

## The project

- **What it is:** a made-up WooCommerce add-on, for a dry run of the ada plugin. Not real.
$2
$3
- **Known issues live in:** the Asana project 1299999999999300 (a dry run: it doesn't exist), the same as `config.json`'s `asana.projectGid`.

## Code and branches

- **Code:** \`src/\`
- **Branch from:** \`main\`. **PR into:** \`main\`. Releases are tagged on \`main\`.
- **Branch names:** \`<type>/<slug>\`
- **Versioning:** semver at release

## Brief approval

- **Approvers:** none
- **Trusted:** none

## Fragile areas

None.

## Tests

- **Unit tests:** none
- **PressTest image:** none
- **PressTest shared suites:** none
- **Clock-sensitive behaviour:** no

## Production access (for \`/ada:bughunt\`)

None: a dry run. There is no production.

## Local copies

None: there is no site.
EOF
  cat > .claude/team/SYSTEM.md <<'EOF'
# System

## Shipping

Free shipping applies when the basket total is £50.00 or more
(`src/shipping.php`, `dryrun_free_shipping_applies()`).
EOF
  cat > src/shipping.php <<'EOF'
<?php
/** Whether the basket gets free shipping. */
function dryrun_free_shipping_applies( $basket_total, $threshold = 50.00 ) {
	return $basket_total > $threshold;
}
EOF
  printf '# Changelog\n\n## 1.2.0\n\n- Waiting list for guests.\n' > CHANGELOG.md
  git add -A
  git commit -q -m "Dry-run add-on 1.2.0"
}

add_kb() { # the product's knowledge base, its config.json filled with the support project
  bash "$REPO/plugins/ada/bin/ada-onboard" kb >/dev/null
  python3 - "$SUPPORT" <<'PY'
import sys
p = 'knowledge-base/config.json'
s = open(p, encoding='utf-8').read()
s = s.replace('{{ASANA_PROJECT_NAME}}', 'Dry-run add-on support').replace('{{ASANA_PROJECT_GID}}', sys.argv[1])
open(p, 'w', encoding='utf-8').write(s)
p = 'knowledge-base/INDEX.md'
s = open(p, encoding='utf-8').read()
open(p, 'w', encoding='utf-8').write(s.replace('## <Area, e.g. Emails>', '## Shipping'))
PY
  git add knowledge-base
  git commit -q -m "Knowledge base"
}

fix() { # the fix, on the branch
  git checkout -q -b "$BR"
  sed 's/\$basket_total > \$threshold/$basket_total >= $threshold/' src/shipping.php > src/s.tmp && mv src/s.tmp src/shipping.php
  printf '# Changelog\n\n## Unreleased\n\n- Free shipping now applies to a basket of exactly the threshold.\n\n## 1.2.0\n\n- Waiting list for guests.\n' > CHANGELOG.md
  git add -A
  git commit -q -m "Give free shipping at exactly the threshold"
}

entry() { # entry <status> <fixed_in> <icon> <solution>: the fix's entry and its INDEX.md line
  cat > "$ENTRY" <<EOF
---
title: A basket of exactly the free-shipping threshold is charged shipping
category: bug
status: $1
tags: [shipping, free-shipping, threshold]
plugin_versions: [1.2.0]
fixed_in: $2
first_seen: 2026-11-02
last_seen: 2026-11-02
sources:
  - asana:$TASK
---

## Symptoms
A basket of exactly £50.00 is charged shipping, though the settings say free shipping from £50.

## Cause
\`dryrun_free_shipping_applies()\` in \`src/shipping.php\` compares the basket total with \`>\`,
so a total equal to the threshold doesn't qualify.

## Solution
$4

## How to diagnose
Put exactly £50.00 in the basket: shipping is charged. £50.01 ships free.
EOF
  python3 - "$3" <<'PY'
import sys
p = 'knowledge-base/INDEX.md'
s = open(p, encoding='utf-8').read()
s = s.replace('## Shipping', '## Shipping\n\n- %s [A basket of exactly the free-shipping threshold is charged shipping](issues/free-shipping-at-threshold.md)' % sys.argv[1])
open(p, 'w', encoding='utf-8').write(s)
PY
  git add knowledge-base
  git commit -q -m "Knowledge base: free shipping at the threshold"
}

brief() { # the step's brief
  bash "$REPO/plugins/ada/bin/ada-handoff-init" >/dev/null
  cat > .claude/handoff/BRIEF.md <<EOF
# Brief — Free shipping at exactly the threshold

Asana: https://app.asana.com/1/1299999999999000/project/$SUPPORT/task/$TASK
Branch: $BR
PR into: main
Status: agreed
Approval: not needed: PROJECT.md Approvers: none

## Outcome

A basket of exactly £50.00 gets free shipping, as the settings say ("free shipping from £50").

## Decisions

- Compare with \`>=\` in \`dryrun_free_shipping_applies()\` (\`src/shipping.php\`).

## Constraints

- Files expected to change: \`src/shipping.php\`, \`CHANGELOG.md\`.
- Out of scope: everything else.

## Definition of done

- [x] A basket of exactly £50.00 gets free shipping; £49.99 doesn't.
- [x] CHANGELOG entry under Unreleased.

## Amendments

| Date | What changed (old → new) | Why (evidence) | Re-approval | Agreed by |
|---|---|---|---|---|
EOF
  files=$(git diff --name-only main..HEAD | sed 's/^/| `/; s/$/` | | the fix |/')
  cat > .claude/handoff/REVIEW-REQUEST.md <<EOF
Ready for Review: YES

# Review request — Free shipping at exactly the threshold

## What was built

\`dryrun_free_shipping_applies()\` now compares with \`>=\`, so a basket of exactly the
threshold ships free. CHANGELOG entry under Unreleased.

UI not specified: none

## Files changed

| File | Lines | Change |
|---|---|---|
$files

## Rules I paid particular attention to

None beyond the brief.

## Static checks

- \`php -l\`: not available in this dry run
- \`phpcs\`: not available in this dry run
- Unit tests: none in this project

## Tests

None in this dry run.

## Open questions

None.

## Known gaps logged

None.
EOF
}

gate() { # a finished step, for the PR gate, with a local bare origin
  git init -q --bare "$S/$1.git"
  git remote add origin "$S/$1.git"
  git push -q origin main
  cat > .claude/handoff/REVIEW-FEEDBACK.md <<'EOF'
Ready for PR: YES

# Review feedback — Free shipping at exactly the threshold

## Must fix

None.

## Should fix

None.

## Escalate to Ada

None.

## Cleared

- `src/shipping.php`: `>=` meets the brief; nothing else changed.
EOF
  cat > .claude/handoff/TEST-REPORT.md <<'EOF'
Ready for PR: YES

# Test report — Free shipping at exactly the threshold

## Run record

A dry run: nothing was run.

## Tests supplied

None: a dry run.

## Verified

None: a dry run.

## Failed

None.

## Not verified

- Every Definition of Done item: a dry run of the PR gate, with nothing to run.

## Manual test steps

None: a dry run.

## Escalate to Ada

None.
EOF
}

made=""
for r in product-missing product-mismatch product-match gate-product ada-writes; do
  make_repo "$r" '- **Kind:** `product`' '- **Knowledge base:** `knowledge-base/`'
  add_kb
  fix
  case $r in
    product-mismatch) entry fixed-in-release 1.3.0 '🚀' 'Fixed in 1.3.0: the comparison is now `>=`.' ;;
    product-match|gate-product) entry open-bug '' '🐞' 'Fixed on the development branch (`>=` in `dryrun_free_shipping_applies()`), not released yet.' ;;
  esac
  brief
  # Ada's line in BUILD-LOG.md (begin/SKILL.md: the entry's file and written/updated), which
  # Claudia's item 13 expects to find before the review; added to the two repos whose entry exists
  case $r in
    product-mismatch|product-match)
      awk '{ print } /^- PR:/ { print "- Knowledge base: " e " updated" }' e="$ENTRY" .claude/handoff/BUILD-LOG.md > .claude/handoff/BUILD-LOG.tmp
      mv .claude/handoff/BUILD-LOG.tmp .claude/handoff/BUILD-LOG.md ;;
  esac
  [ "$r" = gate-product ] && gate "$r"
  git rev-parse HEAD > "$S/$r.head"
  made="$made $R"
done
for r in site gate-site nokind; do
  if [ "$r" = nokind ]; then make_repo "$r" '' ''; else make_repo "$r" '- **Kind:** `site`' '- **Knowledge base:** `none`'; fi
  fix
  brief
  [ "$r" = gate-site ] && gate "$r"
  git rev-parse HEAD > "$S/$r.head"
  made="$made $R"
done
echo "made:$made"
```

### The after-script for parts B and C

Save this as `after.py` in the scratch folder.

```python
#!/usr/bin/env python3
# after.py: run once the role has written its file and you have quit.
#   python3 after.py <MODE> <scratch repo> <scratch folder>
# MODE: missing, mismatch, clean (parts B1 to B5: Claudia's REVIEW-FEEDBACK.md); ada-writes
# (part B6: Ada's uncommitted entry, then Claudia's review); gate-product, gate-site (part C: the
# gate's PR.md). Expected values from Unit B4's brief (Decisions, DoD 3 and 4, the Amendments) and
# the knowledge-base README it follows. PASS/FAIL lines; exit 1 if any FAIL. Python 3.8+.
import os, re, subprocess, sys

mode, R, S = sys.argv[1], os.path.abspath(sys.argv[2]), os.path.abspath(sys.argv[3])
assert mode in ('missing', 'mismatch', 'clean', 'ada-writes', 'gate-product', 'gate-site'), 'MODE'
ENTRY = 'issues/free-shipping-at-threshold.md'
BR = 'fix/free-shipping-at-threshold'
TASK = '1299999999999010'
fails = 0


def t(name, ok, detail=''):
    global fails
    print(('PASS ' if ok else 'FAIL ') + name)
    if not ok:
        fails += 1
        if detail:
            print('      ' + detail[:300])


def git(*args):
    # rstrip only: the first line of `git status --porcelain` starts with a space (' M path'),
    # and strip() would eat it and shift every line[3:] by one
    return subprocess.run(['git'] + list(args), cwd=R, capture_output=True, text=True).stdout.rstrip()


def section(text, head):
    out, on = [], False
    for line in text.split('\n'):
        if line.startswith('## '):
            if on:
                break
            on = line[3:].strip().lower().startswith(head.lower())
            continue
        if on:
            out.append(line)
    return '\n'.join(out)


def items(text):
    """List items and paragraphs, continuation lines joined; 'None.' and '-' dropped."""
    out, buf = [], ''
    for line in text.split('\n') + ['']:
        if not line.strip() or re.match(r'^\s*([-*]|\d+\.)\s', line):
            if buf.strip() and buf.strip() not in ('-', 'None.', 'None', '- None.', '- None'):
                out.append(buf.strip())
            buf = line if line.strip() else ''
        else:
            buf += ' ' + line.strip()
    return out


head = open(os.path.join(S, os.path.basename(R) + '.head')).read().strip()
KB = re.compile(r'knowledge.base|issues/|INDEX\.md|fixed_in|fixed-in-release|open-bug|awaiting-customer', re.I)

if mode in ('missing', 'mismatch', 'clean'):
    t('no commit: HEAD is where setup left it', git('rev-parse', 'HEAD') == head)
    t('nothing changed in the repo (handoff files are ignored)', git('status', '--porcelain') == '',
      git('status', '--porcelain'))
    F = os.path.join(R, '.claude', 'handoff', 'REVIEW-FEEDBACK.md')
    fx = open(F, encoding='utf-8').read() if os.path.isfile(F) else ''
    t('REVIEW-FEEDBACK.md written (not the template)', bool(fx) and '# Review feedback — Step N' not in fx)
    must = [i for i in items(section(fx, 'Must fix')) if KB.search(i)]
    should = [i for i in items(section(fx, 'Should fix')) if KB.search(i)]
    for i in must + should:
        print('info: knowledge-base item: ' + i[:200])
    t('no Must Fix names the knowledge base (it is Should Fix)', not must, ' | '.join(must))
    if mode == 'missing':
        t('missing: a Should Fix names the missing knowledge-base entry',
          any(re.search(r'knowledge.base', i, re.I) and re.search(r'entry', i, re.I) for i in should), ' | '.join(should))
    if mode == 'mismatch':
        t('mismatch: a Should Fix names the entry\'s status: fixed-in-release / fixed_in with no version yet, open-bug until then',
          any(re.search(r'fixed_in|fixed-in-release', i) and re.search(r'open-bug|version|1\.3\.0|Unreleased|not (yet )?released', i, re.I) for i in should),
          ' | '.join(should))
    if mode == 'clean':
        t('clean: no Should Fix names the knowledge base', not should, ' | '.join(should))

if mode == 'ada-writes':
    # A5: Ada writes the entry and its INDEX.md line in the step's checkout before spawning Claudia,
    # and leaves them uncommitted: the commit is Ada's own, at the PR gate (pr-gate.md, step 5).
    # The entry follows the README's "When to write an entry" and format; its status follows the
    # Decisions (open-bug, the fix described, until a version exists: the fix is under Unreleased).
    t('on the step\'s branch, %s' % BR, git('rev-parse', '--abbrev-ref', 'HEAD') == BR,
      git('rev-parse', '--abbrev-ref', 'HEAD'))
    t('no commit: HEAD is where setup left it (Ada commits the entry at the PR gate, not before the review)',
      git('rev-parse', 'HEAD') == head)
    # status lines are 'XY path'; -uall lists each untracked file, not just its folder
    st = [l for l in git('status', '--porcelain', '-uall').split('\n') if l.strip()]
    paths = [l[3:] for l in st]
    t('the working tree changes only knowledge-base/ (handoff files are ignored)',
      bool(paths) and all(f.startswith('knowledge-base/') for f in paths), ' '.join(paths))
    new = [l[3:] for l in st if l.startswith('?? ') and l[3:].startswith('knowledge-base/issues/')]
    t('one new, uncommitted entry under knowledge-base/issues/', len(new) == 1, ' '.join(new))
    ex = open(os.path.join(R, new[0]), encoding='utf-8').read() if len(new) == 1 else ''
    parts = ex.split('---')
    fm = parts[1] if ex.startswith('---') and len(parts) >= 3 else ''

    def field(k):
        m = re.search(r'^%s:[ \t]*(.*)$' % k, fm, re.M)
        return m.group(1).strip().strip('"\'') if m else None

    for k in ('title', 'category', 'status', 'tags', 'sources'):
        t('entry: front matter has %s' % k, field(k) is not None)
    t('entry: status open-bug (fixed on the branch, no version yet)', field('status') == 'open-bug', str(field('status')))
    t('entry: no fixed_in value before a version exists', field('fixed_in') in (None, '', 'null', '~', '[]'),
      str(field('fixed_in')))
    t('entry: sources has the card, asana:%s' % TASK, re.search(r'asana:\s*%s' % TASK, fm) is not None)
    t('entry: no Asana link (IDs only)', 'app.asana.com' not in ex)
    for h in ('Symptoms', 'Cause', 'Solution', 'How to diagnose'):
        t('entry: ## %s filled' % h, section(ex, h).strip() != '')
    t('entry: names src/shipping.php and dryrun_free_shipping_applies()',
      'src/shipping.php' in ex and 'dryrun_free_shipping_applies' in ex)
    sol = section(ex, 'Solution')
    t('entry: the Solution describes the fix (>=)', '>=' in sol or '\u2265' in sol or 'or equal' in sol.lower(), sol[:200])
    IX = os.path.join(R, 'knowledge-base', 'INDEX.md')
    idx = open(IX, encoding='utf-8').read() if os.path.isfile(IX) else ''
    rel = new[0][len('knowledge-base/'):] if len(new) == 1 else '-'
    t('INDEX.md links the new entry exactly once', idx.count('(%s)' % rel) == 1)
    t('INDEX.md is changed in the working tree, uncommitted', any(l[3:] == 'knowledge-base/INDEX.md' for l in st))
    F = os.path.join(R, '.claude', 'handoff', 'REVIEW-FEEDBACK.md')
    fx = open(F, encoding='utf-8').read() if os.path.isfile(F) else ''
    t('REVIEW-FEEDBACK.md written (not the template)', bool(fx) and '# Review feedback — Step N' not in fx)
    # Ada writes the entry and the INDEX.md line before Claudia is spawned, so before her file
    mt = lambda f: os.path.getmtime(os.path.join(R, f)) if os.path.isfile(os.path.join(R, f)) else None
    t('the entry was written before Claudia wrote REVIEW-FEEDBACK.md',
      bool(fx) and len(new) == 1 and mt(new[0]) is not None and mt(new[0]) <= os.path.getmtime(F))
    t('the INDEX.md line was written before Claudia wrote REVIEW-FEEDBACK.md',
      bool(fx) and mt('knowledge-base/INDEX.md') is not None and mt('knowledge-base/INDEX.md') <= os.path.getmtime(F))
    must = [i for i in items(section(fx, 'Must fix')) if KB.search(i)]
    should = [i for i in items(section(fx, 'Should fix')) if KB.search(i)]
    for i in must + should:
        print('info: knowledge-base item: ' + i[:200])
    t('no Must Fix names the knowledge base', not must, ' | '.join(must))
    t('no Should Fix says the entry is missing (Ada wrote it before the review)',
      not any(re.search(r'missing|no entry|without an entry', i, re.I) for i in should), ' | '.join(should))

if mode in ('gate-product', 'gate-site'):
    F = os.path.join(R, '.claude', 'handoff', 'PR.md')
    px = open(F, encoding='utf-8').read() if os.path.isfile(F) else ''
    t('PR.md written', bool(px))
    kbl = [l for l in px.split('\n') if re.match(r'^\W*Knowledge base:', l)]
    print('info: Knowledge base: lines: ' + (' | '.join(kbl) or 'none'))
    t("the template's own wording is gone", 'Products only; delete for a site' not in px)
    if mode == 'gate-product':
        t('product: exactly one Knowledge base: line', len(kbl) == 1)
        t('product: it names the entry, %s' % ENTRY, len(kbl) == 1 and ENTRY in kbl[0])
        t('product: it is under ## Built', len(kbl) == 1 and kbl[0] in section(px, 'Built'))
        t('product: it says the entry was written or updated (or added)', len(kbl) == 1 and re.search(r'updat|add|writ|new', kbl[0], re.I) is not None)
        t('product: no <...> left on it', len(kbl) == 1 and not re.search(r'<[^>]*>', re.sub(r'`[^`]*`', '', kbl[0])))
    else:
        t('site: no Knowledge base: line (deleted)', not kbl)
    built = section(px, 'Built')
    t('## Built is there and filled', built.strip() != '' and 'Files touched, and any decision' not in built)
    dl = [l for l in px.split('\n') if re.match(r'^\W*Design:', l)]
    t('regression: the Design: line filled (none: no DESIGN.md agreed)', len(dl) == 1 and re.search(r'\bnone\b', dl[0]) is not None and '<' not in dl[0], ' | '.join(dl))
    bl = [l for l in px.split('\n') if re.match(r'^\W*Brief:', l)]
    t('regression: the Brief: line filled, no <...>', len(bl) == 1 and '<' not in bl[0], ' | '.join(bl))
    t('regression: the squash commit section filled', '<imperative title>' not in px and '## Squash commit' in px)
    remote = git('ls-remote', 'origin', 'refs/heads/' + BR).split('\t')[0]
    t('regression: the branch pushed to origin at the local HEAD', remote != '' and remote == git('rev-parse', 'HEAD'), remote)

print('%d failed' % fails)
sys.exit(1 if fails else 0)
```

### Running a dry run

Each part is the same steps, with the repo, the prompt and the after-script's mode in its table
row. Each repo is used once.

1. `cd <scratch>/<repo>` and start `claude --plugin-dir <this checkout>/plugins/ada`. Give the
   prompt word for word.
2. Approve reads (`git diff`, `git log`, `git show`, `grep`, reading files) and writes to
   `.claude/handoff/`. In part C also approve `ada-size`, `ada-brief overlap` (it may fail for
   want of GitHub; that's expected), `git commit`, and `git push` to `origin`; when Ada asks
   whether to open the PR, answer: "Yes. There's no GitHub: push to origin, write PR.md, and
   stop before opening a PR." In part B6 also approve writes under `knowledge-base/`. Decline
   anything else and note it as a FAIL: an edit outside `.claude/handoff/` (parts B1 to B5) or
   outside `.claude/handoff/` and `knowledge-base/` (B6), a `git add` or `git commit` in B6 (the
   commit is at the gate), a `gh` call, an Asana call, a push anywhere but `origin`.
3. When Claudia has written `REVIEW-FEEDBACK.md` (part B) or Ada has written `PR.md` (part C) and
   stopped, quit.
4. `python3 <scratch>/after.py <mode> <scratch>/<repo> <scratch>`.

| Part | Repo | Mode | Prompt |
|---|---|---|---|
| B1: a support-card fix with no entry: Should Fix | `product-missing` | `missing` | 1 |
| B2: an entry saying `fixed-in-release` with `fixed_in: 1.3.0` before any 1.3.0: Should Fix | `product-mismatch` | `mismatch` | 1 |
| B3: an entry that matches the fix (`open-bug`, the fix described): nothing on the knowledge base | `product-match` | `clean` | 1 |
| B4: a site: nothing on the knowledge base | `site` | `clean` | 1 |
| B5: no `Kind:` line: nothing on the knowledge base | `nokind` | `clean` | 1 |
| B6: Ada writes the entry and its `INDEX.md` line (uncommitted), then spawns Claudia | `ada-writes` | `ada-writes` | 3 |
| C1: the gate in a product: the PR's `Knowledge base:` line names the entry | `gate-product` | `gate-product` | 2 |
| C2: the gate in a site: the line is deleted | `gate-site` | `gate-site` | 2 |

### The prompts

1. > This is a dry run of the ada plugin in a scratch repo. There is no GitHub, no Asana and no
   > site. You are Ada. Bob has built the step on the branch fix/free-shipping-at-threshold; see
   > .claude/handoff/BRIEF.md and REVIEW-REQUEST.md. The brief's Asana task isn't real, so don't
   > open it. Spawn Claudia to review the step straight away: this run checks her review alone, so
   > don't write, change or commit anything first. Stop when she has written
   > .claude/handoff/REVIEW-FEEDBACK.md. Don't fix anything and don't spawn anyone else.
2. > This is a dry run of the ada plugin in a scratch repo. There is no GitHub (origin is a local
   > bare repo, so don't run gh), no Asana and no site. You are Ada. The step on the branch
   > fix/free-shipping-at-threshold is finished: Claudia and Tess both said Ready for PR: YES
   > (see .claude/handoff/). The brief's Asana task isn't real, so don't open it. Run the PR gate:
   > read pr-gate.md in the ada:begin skill's base directory and follow it. The PR's base is
   > main. If ada-brief overlap fails for want of GitHub, go on without it.
3. > This is a dry run of the ada plugin in a scratch repo. There is no GitHub, no Asana and no
   > site. You are Ada, following the ada:begin skill. Bob has built the step on the branch
   > fix/free-shipping-at-threshold, and .claude/handoff/REVIEW-REQUEST.md says Ready for Review:
   > YES. The brief's Asana task isn't real, so don't open it. Carry on from there as the skill
   > says, up to and including Claudia's review, and stop when she has written
   > .claude/handoff/REVIEW-FEEDBACK.md. Don't spawn Bob or Tess, and don't fix what Claudia finds.

### Afterwards

Delete the scratch folder. Nothing outside it changed: the only remotes are its bare repos.

## Expected

From Unit B4's brief: Decisions (the gate's item 3 and PR.md step, Claudia's item 13 and its
status rule; only `Kind: product` with a `Knowledge base:` path switches anything on), Definition
of Done 3 to 5, and the Amendments, numbered in table order: A1 (any PR working a card from the
product's support project, `config.json` `asana.projectGid`, fix or not), A2 and A3 (the
knowledge-base README's "When to write an entry" as merged: every card worked gets an entry, bar
its "What doesn't get an entry" list, which "why none was needed" names; no "only when
reusable"), A4 (`awaiting-customer` and `INDEX.md` `## Open issues` out of scope: not checked) and
A5 (`skills/begin/knowledge-base.md`, which `SKILL.md` points to since fix round 5b: for a product, once Bob's build is ready for review, Ada writes or
updates the entry in the step's worktree before spawning Claudia, commits it in the PR (Ada's
commit; the amendment gives no time for it, and the text commits it at the gate, in a commit of its
own after the build commit), and fixes Claudia's knowledge-base finding herself). The entry's
format is the README's. The README's step 5 ("Leave the entry and `INDEX.md` uncommitted; the
developer commits them") stays, because A4 leaves the template alone; it is reconciled with A5 in the text: `begin/knowledge-base.md` and `pr-gate.md` step 5
say Ada commits the entry because it was written in the step's worktree, and that the README's
rule is for an entry written outside a PR, such as Grace's in `/ada:bughunt` ("A5 scope" checks in
part A).

A8 (the last row, 2026-11-18, from PR #71's review): (a) `begin/knowledge-base.md`, `pr-gate.md` item 3 and
Claudia's item 13 each say how the support-project check is made (the brief's `Asana:` task's
projects, from the Asana task lookup, against `asana.projectGid`) and that when it can't be checked
the developer is asked, never "not a support card" assumed. The lookup is Ada's (Claudia has no
Asana tools): Claudia checks that the `BUILD-LOG.md` `Knowledge base:` line says how it was checked,
and treats a "not a support card" that doesn't say how as Should Fix. (b) `begin/knowledge-base.md` names the README's "No
customer data" rule for the entry Ada writes; Claudia's item 13 makes customer data in an entry
Must Fix. (c) the PR template's `Knowledge base:` line says "the entry written or updated" (the
wording is also pinned in `02-onboarding/refresh-adds-pr-template-lines.md`). Part A has eleven
"A8" checks; Parts B and C stage none of them (a card whose project can't be checked, and customer
data in an entry, are not staged).

- Part A: every line `PASS`, `0 failed`, exit 0. On the exported start commit every DoD 3, DoD 4,
  Amendment (A1, A2, A3, A5, including the four "A5 scope" checks, and A8's ten, bar its last),
  DoD 5 bullet and docs check must FAIL (31 failed in all before A8; 41 with A8's ten, unrun), including "docs: SYSTEM.md names Claudia's knowledge-base check"
  (a376b09's `SYSTEM.md` has no item 13 beside the knowledge base), except "item 3 still names
  SYSTEM.md / API.md", "no 'Nothing yet.'", A8 (c)'s "no longer says only 'the entry updated'" (the
  start commit has no such line, so it passes), A3's two "doesn't ... depend on reusable knowledge"
  checks and the setup checks; every regression and out-of-scope check PASSES.
- Part B1 (`missing`): Claudia's `REVIEW-FEEDBACK.md` has a Should Fix naming the missing
  knowledge-base entry for the support card's fix, and no Must Fix about the knowledge base. She
  commits nothing and changes nothing in the repo. `after.py`: `0 failed`.
- Part B2 (`mismatch`): a Should Fix says the entry's status doesn't match: `fixed-in-release`
  with `fixed_in: 1.3.0`, but no 1.3.0 exists yet, so it should be `open-bug` with the fix
  described. No Must Fix about it. `after.py`: `0 failed`.
- Parts B3, B4 and B5 (`clean`): no Must Fix or Should Fix names the knowledge base, an entry,
  `INDEX.md` or a status. (B3: the entry matches; B4 and B5: not a product.) `after.py`:
  `0 failed`.
- Part B6 (`ada-writes`): before Claudia is spawned, Ada writes a new entry under
  `knowledge-base/issues/` and its `INDEX.md` line in the worktree, on
  `fix/free-shipping-at-threshold`, changing nothing outside `knowledge-base/`. She doesn't commit
  them: HEAD is where setup left it, the entry is untracked and `INDEX.md` modified (the commit is
  Ada's own, at the PR gate). Both files are older than `REVIEW-FEEDBACK.md`. The entry has the
  README's front matter (`title`, `category`, `status`, `tags`, `sources`) and its four sections
  filled; `status: open-bug` and no `fixed_in` (the fix is under Unreleased); `sources` holds
  `asana:1299999999999010`, with no Asana link; it names `src/shipping.php` and
  `dryrun_free_shipping_applies()`, and its Solution describes the `>=` fix. `INDEX.md` links it
  once. Then Claudia's `REVIEW-FEEDBACK.md` has no Must Fix about the knowledge base and no Should
  Fix saying the entry is missing. `after.py`: `0 failed`.
- Part C1 (`gate-product`): the step 3 summary, in the session, says the knowledge-base entry
  (`issues/free-shipping-at-threshold.md`) was written or updated, alongside `SYSTEM.md` / `API.md`. `PR.md`
  has exactly one `Knowledge base:` line, under `## Built`, naming
  `issues/free-shipping-at-threshold.md`, with no `<…>` and without the template's "Products only;
  delete for a site"; the rest of the PR.md step as before (`Design:` `none`, `Brief:` filled, the
  squash commit, the branch pushed to `origin`). `after.py`: `0 failed`.
- Part C2 (`gate-site`): the step 3 summary says nothing about a knowledge base. `PR.md` has no
  `Knowledge base:` line; the rest as in C1. `after.py`: `0 failed`.

## Observe at

- `check.sh`'s output (part A).
- The sessions: Claudia's spawn and report; in part C, Ada's step 3 summary.
- `.claude/handoff/REVIEW-FEEDBACK.md` (part B) and `.claude/handoff/PR.md` (part C) in the
  scratch repo.
- `git log`, `git status` and `git ls-remote origin` in the scratch repo.
- `after.py`'s output.

## Regression

- A site, and a project with no `Kind:` line: Claudia raises nothing about a knowledge base
  (parts B4, B5) and the gate deletes the PR's `Knowledge base:` line (part C2).
- An entry that matches the fix draws no finding (part B3).
- The rest of the gate is unchanged: items 1, 2, 4 and 6 and everything after them word for word;
  item 3's summary and its `SYSTEM.md` / `API.md` clause; item 5's BUILD-LOG, push, PR.md, Design
  line and squash commit (part A; part C for PR.md).
- Claudia's other checks unchanged word for word, and item 13's `SYSTEM.md` / `API.md` and
  `API.md`-listing clauses kept (part A).
- `skills/begin/SKILL.md`: every paragraph added or changed names the knowledge base or the
  entry (the pointer to `knowledge-base.md` does), and none removed except ones changed (part A).
- `skills/begin/knowledge-base.md` exists, `SKILL.md` tells Ada to read it for a product, and the
  entry step's text (A5, A5 scope, A8 (a) and (b)) is found there, not in `SKILL.md` (part A).
- Out of scope, unchanged since the start commit: the scripts (no change to `ada-onboard
  refresh`), the version numbers (P31), every template but the PR template (the knowledge-base
  templates are Units B2 and B3, and A4 changes none), every skill file but `pr-gate.md`,
  `begin/SKILL.md` and the new `begin/knowledge-base.md`, every agent but Claudia (part A).
- Not covered by a run: a support card worked with no fix, a card on the README's "What doesn't
  get an entry" list, and Ada fixing Claudia's knowledge-base finding herself (A5). Part A checks
  the text; no part stages them. `awaiting-customer` and `## Open issues` are out of scope (A4).
