# In a product repo `/ada:bughunt` checks the knowledge base first and stops on an entry that answers the report; otherwise it hunts as before; either way it records the card in an entry (new or updated) in the main checkout's knowledge base, left uncommitted, even after a fix was tried in a bughunt worktree, and the README says every bughunt is recorded; in a site repo none of it happens; `/ada:begin` is unchanged

- Area: 10 Investigation (`skills/bughunt/SKILL.md`: 1. Intake and the step after the findings;
  `templates/handoff/FINDINGS.md`: Known issue?; `templates/knowledge-base/README.md`: When to
  write an entry, with What doesn't get an entry)
- Asana: https://app.asana.com/0/0/1220058513997551/f
- Automate as: none (part A is a self-checking shell script; parts B to F are dry runs with
  `claude --plugin-dir` in scratch repos, each with a self-checking after-script)
- Last run: 18 Nov 2026, part A 0 failed (51 passed; scope checks rescoped to Unit B3's own commit); dry runs not re-run (see the PR)

## Precondition

- Part A: this checkout, with the change in its working tree, on a branch whose history holds
  the commit it started from (`origin/add/knowledge-base`, 3260617 when this was written). Nothing
  else.
- Parts B to F:
  - `python3` (3.8 or later), `git` 2.28 or later, and `claude` logged in. Asana isn't needed:
    the reports say the tasks aren't real.
  - An empty scratch folder outside any repo, `S`, holding this script's blocks: `check.sh`,
    `setup.sh` and `after.py`. Then, with `REPO` this checkout:

    ```sh
    REPO=<this checkout> S=<the scratch folder> bash <the scratch folder>/setup.sh
    ```

    It prints `made: …/product …/product-nokind …/site`. `product` has `Kind: product`,
    `Knowledge base: knowledge-base/`, a knowledge base with one entry (guests get "Security
    verification was not successful" when joining, status `workaround`), and a free-shipping bug
    in `src/shipping.php` that no entry covers. `product-nokind` is an older project: no `Kind:`
    or `Knowledge base:` line and no `knowledge-base/` folder. `site` has `Kind: site`,
    `Knowledge base: none` and no knowledge base. In each, `ada@pie` is off in
    `.claude/settings.local.json`, so only this checkout's plugin loads.
  - The made-up Asana GIDs: the known-issues project `1299999999999200` (in `PROJECT.md`), and
    the tasks `1299999999999001` to `…004` (one per report).

## Steps

### Part A: the text

1. Save the first block as `check.sh` in the scratch folder and run
   `REPO=<this checkout> bash <scratch>/check.sh`.
2. Break it on purpose: export the commit the branch started from
   (`git -C <this checkout> merge-base HEAD origin/add/knowledge-base`, 3260617 when this was
   written) with `git -C <this checkout> archive -o <scratch>/base.tar <that commit>`, unpack it
   into an empty folder with `tar -xf <scratch>/base.tar -C <empty folder>`, and run
   `REPO=<that folder> BASE_REPO=<this checkout> bash <scratch>/check.sh`.

```bash
#!/bin/bash
# Grace and a product's knowledge base, by the text of the bughunt skill, the FINDINGS template,
# the knowledge-base README, SYSTEM.md, API.md and the CHANGELOG; and /ada:begin unchanged. PASS/FAIL; exit 1 if any FAIL,
# 2 on a setup error.
# Env: REPO (the checkout under test), BASE_REPO (a git checkout to read BASE from; default REPO),
# BASE (the commit the change started from; default the merge-base of BASE_REPO's HEAD and
# origin/add/knowledge-base, else 3260617).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE_REPO=${BASE_REPO:-$REPO}
BASE=${BASE:-$(git -C "$BASE_REPO" merge-base HEAD origin/add/knowledge-base 2>/dev/null || echo 3260617)}
SK=plugins/ada/skills/bughunt/SKILL.md
FT=plugins/ada/templates/handoff/FINDINGS.md
RM=plugins/ada/templates/knowledge-base/README.md
for f in "$SK" "$FT" "$RM" CHANGELOG.md .claude/team/SYSTEM.md .claude/team/API.md; do
  [ -f "$REPO/$f" ] || { echo "SETUP FAIL: no $REPO/$f"; exit 2; }
done
command -v python3 >/dev/null 2>&1 || { echo "SETUP FAIL: no python3"; exit 2; }
W=$(mktemp -d "${TMPDIR:-/tmp}/bughunt-kb.XXXXXX") || exit 2
trap 'rm -rf "$W"' EXIT
git -C "$BASE_REPO" show "$BASE:$SK" > "$W/old-bughunt.md" 2>/dev/null \
  || { echo "SETUP FAIL: no $SK at $BASE in $BASE_REPO"; exit 2; }
# B3 is this unit's own commit on add/knowledge-base (the squash of #70) and B3P its parent. The
# "unchanged" scope checks (DoD 6, the README outside its section, out of scope) compare B3P with
# B3, so they still mean "this unit changed none of it" on a later branch, where later units (B4)
# change begin, the README and so on.
B3=${B3:-a376b09}
B3P=$(git -C "$BASE_REPO" rev-parse --verify -q "$B3^") \
  && git -C "$BASE_REPO" cat-file -e "$B3:$SK" 2>/dev/null \
  || { echo "SETUP FAIL: no commit $B3 (Unit B3's) with its parent in $BASE_REPO"; exit 2; }
echo "info: BASE $BASE; Unit B3 $B3 (parent $B3P)"
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
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
hasf() { flat "$1" | grep -qF -- "$2"; }
hasnot() { ! has "$@"; }
lacks() { ! flat "$1" | grep -qEi -- "$2"; }
# between <file> <start regex> <stop regex>: from the first line matching start to the line
# before the next line matching stop
between() { awk -v a="$2" -v z="$3" 'p && $0 ~ z { exit } $0 ~ a { p = 1 } p' "$1"; }
# nomore <regex>: the bughunt skill matches the regex (any case) no more often than at BASE
cnt() { grep -Eoi -- "$1" "$2" | wc -l; }
nomore() { test "$(cnt "$1" "$S")" -le "$(cnt "$1" "$W/old-bughunt.md")"; }
S=$REPO/$SK
between "$S" '^### 1\. Intake' '^##' > "$W/s1.md"
between "$S" '^### 6\. ' '^## Never' > "$W/after6.md"
between "$S" '^## The hunt' '^## Never' > "$W/hunt.md"
between "$REPO/$FT" '^## Known issue' '^## ' > "$W/known.md"
between "$REPO/.claude/team/SYSTEM.md" '^## What fires when' '^## [^W]' > "$W/fires.md"
between "$REPO/$RM" '^## When to write an entry' '^## ' > "$W/when.md"
awk '/^## Unreleased/{p=1;next} p&&/^## /{exit} p' "$REPO/CHANGELOG.md" > "$W/unrel.md"
t "setup: bughunt 1. Intake found" test -s "$W/s1.md"
t "setup: bughunt 6. Write the findings found" test -s "$W/after6.md"
t "setup: FINDINGS template's Known issue? section found" test -s "$W/known.md"
t "setup: the knowledge-base README's When to write an entry found" test -s "$W/when.md"

KB='knowledge.base|INDEX\.md|issues/'
# DoD 1: Intake checks the knowledge base first, and stops on an entry that answers the report
t "DoD 1: Intake looks in INDEX.md and issues/" has "$W/s1.md" 'INDEX\.md' 'issues/' 'search|grep|scan|look|check'
kbline=$(grep -nEi -- "$KB" "$W/s1.md" | head -1 | cut -d: -f1)
kiline=$(grep -nEi -- 'known-issues project' "$W/s1.md" | head -1 | cut -d: -f1)
t "DoD 1: the knowledge-base check comes before the known-issues project" test -n "$kbline" -a -n "$kiline" -a "${kbline:-0}" -lt "${kiline:-0}"
t "DoD 1: an entry with a fix or workaround that fits ends the hunt" has "$W/s1.md" 'entry' 'fix' 'workaround' 'end|stop|early'
# Decisions: no other searches, no worktree rules (no more of them in bughunt than at BASE)
t "Decisions: no support-project search (config.json, projectGid, support project)" nomore 'config\.json|projectGid|support project'
t "Decisions: no recent-fix search (gh pr list, --since, 60 days)" nomore 'gh pr list|--since|60.days'
t "Decisions: no worktree rule (ada-worktree new, EnterWorktree)" nomore 'ada-worktree new|EnterWorktree'
# DoD 2: no Gmail
t "DoD 2: nothing in skills/bughunt mentions Gmail" sh -c "! grep -rqi gmail '$REPO/plugins/ada/skills/bughunt'"
t "DoD 2: the FINDINGS template doesn't mention Gmail" sh -c "! grep -qi gmail '$REPO/$FT'"
# DoD 3: every bughunt in a product ends with an entry written or updated, with the card's
# asana: source, in the main checkout's knowledge base, left uncommitted
t "DoD 3: after the findings, write or update an entry and INDEX.md" has "$W/after6.md" 'entry' 'INDEX\.md' 'write|add|update'
t "DoD 3: the card goes in the entry's sources as asana:<task id>" has "$W/hunt.md" 'asana:' 'sources?'
t "DoD 3: an entry that ended the hunt at Intake gets the card's source too" has "$W/hunt.md" 'Intake|early|ended|ends' 'asana:|sources|last_seen'
t "DoD 3: an entry on the same problem is updated, not duplicated" has "$W/after6.md" 'update' 'existing|matching|already|same (problem|issue|bug|symptom)' 'duplicat|rather than|instead of|not a new'
t "DoD 3: 'not a bug' results are recorded too (configuration or how-to)" has "$W/hunt.md" 'not a bug' 'configuration|how-to'
t "DoD 3: a cause not yet found is recorded too (unresolved)" has "$W/hunt.md" 'entry|record' 'unresolved'
t "DoD 3: no 'only when something reusable was learnt' condition in the hunt" hasnot "$W/hunt.md" 'entry' '(no|nothing) (reusable|worth)|only (when|if)[^.]{0,60}(reusable|worth keeping)'
t "DoD 3: the entry goes in the main checkout, never a bughunt worktree" has "$W/hunt.md" 'main (checkout|working tree)' 'never|not' 'worktree'
t "DoD 3: the entry and INDEX.md are left uncommitted" has "$W/after6.md" 'entry|INDEX\.md' 'uncommitted|(not|n.t|never) commit'
# Ada's decision for step 7 (2026-11-16, in the spawn; no Amendments row yet): after a fix was
# tried in a bughunt worktree, ExitWorktree first; without it, the entry's text and path to the developer
t "DoD 3: after a fix tried in a bughunt worktree, return to the main checkout with ExitWorktree first" has "$W/after6.md" 'ExitWorktree' 'main checkout' 'fix|worktree' 'first|before'
t "DoD 3: without ExitWorktree, give the developer the entry's text and path instead" has "$W/after6.md" 'ExitWorktree' "isn.t available|not available|unavailable" 'text' 'path'
# DoD 4: the knowledge-base README's When to write an entry: every bughunt and support card, no skip
t "DoD 4: When to write an entry says every bughunt and support card is recorded" has "$W/when.md" 'every|all|each' 'bughunt' 'support'
t "DoD 4: When to write an entry no longer says to skip" lacks "$W/when.md" 'skip'
t "DoD 4: nor to write only when the investigation produces reusable information" lacks "$W/when.md" 'only create or update an entry when'
t "DoD 4: What doesn't get an entry no longer has the old-bugs-already-fixed exception" lacks "$W/when.md" 'old bugs already fixed|covered by the changelog|diagnostic value'
# DoD 5: the FINDINGS template's Known issue? section has the knowledge-base line
t "DoD 5: Known issue? has a knowledge-base line naming the entry that matched" has "$W/known.md" 'knowledge.base' 'entry' 'match'
t "DoD 5: the same line names the entry written or updated, uncommitted" has "$W/known.md" 'knowledge.base' 'written|updated|wrote' 'uncommitted|commit'
# DoD 6: /ada:begin unchanged by Unit B3: its commit changes no file in skills/begin and adds none
bgchanged=$(git -C "$BASE_REPO" diff --name-only "$B3P" "$B3" -- plugins/ada/skills/begin)
t "DoD 6: /ada:begin unchanged by Unit B3's commit (every file in skills/begin; none added)" test -z "$bgchanged"
[ -n "$bgchanged" ] && printf '      changed or added: %s\n' $bgchanged
# DoD 7: every new step in the hunt says products only
t "DoD 7: the switch is PROJECT.md's Kind: product and Knowledge base: lines" has "$W/hunt.md" 'Kind' 'product' 'Knowledge base'
python3 - "$W/hunt.md" > "$W/notprod.txt" <<'PY'
import re, sys
kb = re.compile(r'knowledge.base|INDEX\.md|issues/', re.I)
prod = re.compile(r'products? only|only (for|in|when|if)\b[^.]{0,40}\bproducts?\b|\b(for|in) a product\b|Kind\W{0,6}product|product repo', re.I)
items, buf, fence = [], None, False
heading, lead = '', ''
def flush():
    global buf, lead
    if buf is not None:
        buf['heading'], buf['lead'] = heading, lead
        items.append(buf)
        if not buf['list']:
            lead = buf['text']
    buf = None
for line in open(sys.argv[1], encoding='utf-8').read().split('\n'):
    if line.strip().startswith('```'):
        fence = not fence
        if buf is not None:
            buf['text'] += ' ' + line.strip()
        continue
    if fence:
        if buf is not None:
            buf['text'] += ' ' + line.strip()
        continue
    if not line.strip():
        flush(); continue
    if line.startswith('#'):
        flush(); heading, lead = line, ''; continue
    m = re.match(r'^(\s*)([-*]|\d+\.)\s', line)
    if m:
        flush(); buf = {'ind': len(m.group(1)), 'text': line.strip(), 'list': True}
    elif buf is None:
        buf = {'ind': -1, 'text': line.strip(), 'list': False}
    else:
        buf['text'] += ' ' + line.strip()
flush()
bad = 0
for i, it in enumerate(items):
    if not kb.search(it['text']):
        continue
    ctx = [it['text'], it['heading']]
    if it['list']:
        ind = it['ind']
        for prev in reversed(items[:i]):
            if not prev['list'] or prev['heading'] != it['heading']:
                break
            if prev['ind'] < ind:
                ctx.append(prev['text']); ind = prev['ind']
        if it['lead'].rstrip().endswith(':'):
            ctx.append(it['lead'])
    if not any(prod.search(c) for c in ctx):
        bad += 1
        print('      not products-only: ' + it['text'][:110])
sys.exit(1 if bad else 0)
PY
t "DoD 7: every item in The hunt that names the knowledge base says products only" test ! -s "$W/notprod.txt"
cat "$W/notprod.txt"
# DoD 8: CHANGELOG
awk '/^- /{ if (b != "") print b; b = $0; next } /^[[:space:]]*$/{ if (b != "") print b; b = ""; next } { sub(/^[[:space:]]+/, ""); b = b " " $0 } END { if (b != "") print b }' "$W/unrel.md" > "$W/bullets.txt"
t "DoD 8: a CHANGELOG Unreleased bullet with a bold lead names bughunt or Grace and the knowledge base" sh -c "grep '^- \*\*' '$W/bullets.txt' | grep -Ei 'bughunt|Grace' | grep -Eqi 'knowledge.base'"
t "DoD 8: no 'Nothing yet.' left under Unreleased" sh -c "! grep -qx 'Nothing yet\.' '$W/unrel.md'"
# Constraints: SYSTEM.md and API.md
t "docs: SYSTEM.md What fires when names bughunt and the knowledge base" has "$W/fires.md" 'bughunt' 'knowledge.base|INDEX\.md'
t "docs: an API.md line on bughunt names the knowledge base" sh -c "grep -i 'bughunt' '$REPO/.claude/team/API.md' | grep -Eqi 'knowledge.base|INDEX\.md'"

# Regression: what sits next to the change
# The three places: the Repo row may change (Ada's decision: it allows the uncommitted entry and
# INDEX.md in the main checkout); the rest of the table and the production rules may not.
between "$W/old-bughunt.md" '^## The three places' '^## The hunt' | grep -v '^| \*\*Repo\*\* |' > "$W/places-old"
between "$S" '^## The three places' '^## The hunt' | grep -v '^| \*\*Repo\*\* |' > "$W/places-new"
t "regression: the three places (but its Repo row) and the production rules unchanged since BASE" cmp -s "$W/places-old" "$W/places-new"
grep '^| \*\*Repo\*\* |' "$S" > "$W/repo-row"
t "regression: the Repo row is still there and still allows experimental changes on a local bughunt/<slug> branch, never pushed" grep -qF 'Experimental changes on a local `bughunt/<slug>` branch, never pushed.' "$W/repo-row"
t "regression: the Repo row also allows the uncommitted entry and INDEX.md in the main checkout" has "$W/repo-row" 'entry' 'INDEX\.md' 'uncommitted' 'main checkout'
between "$W/old-bughunt.md" '^### 2\. ' '^### 6\. ' > "$W/s25-old"; between "$S" '^### 2\. ' '^### 6\. ' > "$W/s25-new"
t "regression: hunt steps 2 to 5 unchanged since BASE" cmp -s "$W/s25-old" "$W/s25-new"
cmp -s "$W/s25-old" "$W/s25-new" || diff "$W/s25-old" "$W/s25-new" | sed -n '1,12s/^/      /p'
between "$W/old-bughunt.md" '^## Never' '^THE-END$' > "$W/never-old"; between "$S" '^## Never' '^THE-END$' > "$W/never-new"
t "regression: the Never list unchanged since BASE" cmp -s "$W/never-old" "$W/never-new"
t "regression: Intake still reads the Asana task and comments" hasf "$W/s1.md" 'Read the Asana task and its comments.'
t "regression: Intake still searches the known-issues project named in PROJECT.md" hasf "$W/s1.md" 'known-issues project named in `PROJECT.md`'
t "regression: Intake still reads the SYSTEM.md sections for the area" hasf "$W/s1.md" 'Read the `SYSTEM.md` sections for the area'
t "regression: step 6 still leaves FINDINGS.md in the bughunt worktree for /ada:begin" hasf "$W/after6.md" '`/ada:begin` finds this worktree with `ada-worktree list`'
t "regression: customer remedies are still written up, not done" hasf "$W/after6.md" 'Customer remedies (a credit, a re-sent order, a refund) are **written up, not done**.'
t "regression: FINDINGS Known issue? keeps the known-issues project line" grep -qF 'Known-issues project (PROJECT.md) checked' "$W/known.md"
# The README outside its When to write an entry section (that section ends the file, and holds
# What doesn't get an entry) is unchanged.
outside() { awk 'p && /^## / { p = 0 } /^## When to write an entry/ { p = 1 } !p' "$1"; }
git -C "$BASE_REPO" show "$B3P:$RM" > "$W/readme-old.md"; git -C "$BASE_REPO" show "$B3:$RM" > "$W/readme-b3.md"
outside "$W/readme-old.md" > "$W/readme-out-old"; outside "$W/readme-b3.md" > "$W/readme-out-new"
t "regression: Unit B3 left the knowledge-base README unchanged outside its When to write an entry section (which holds What doesn't get an entry)" cmp -s "$W/readme-out-old" "$W/readme-out-new"
t "regression: FINDINGS template keeps its headings" sh -c "for h in '## The claim' '## Known issue?' '## Timeline' '## Cause' '## Reproduced locally' '## Scope' '## For the customer' '## Code fix' '## Open questions'; do grep -qxF \"\$h\" '$REPO/$FT' || exit 1; done"
# Out of scope (Unit B2, Unit B4, P31): Unit B3's commit changed nothing else (the README is checked above)
changed=$(git -C "$BASE_REPO" diff --name-only "$B3P" "$B3" -- plugins/ada/bin plugins/ada/.claude-plugin/plugin.json .claude-plugin/marketplace.json plugins/ada/templates/knowledge-base plugins/ada/templates/repo plugins/ada/skills/onboard plugins/ada/skills/pickup plugins/ada/agents .github | grep -vxF "$RM")
t "out of scope: Unit B3's commit changed no script, version, other knowledge-base template file, other skill or agent" test -z "$changed"
[ -n "$changed" ] && printf '      changed: %s\n' $changed
newbin=$(git -C "$BASE_REPO" diff --name-only --diff-filter=A "$B3P" "$B3" -- plugins/ada/bin)
t "out of scope: Unit B3's commit added no script to plugins/ada/bin" test -z "$newbin"
echo "$fails failed"; [ "$fails" = 0 ]
```

### The setup script for parts B to F

Save this as `setup.sh` in the scratch folder.

```bash
#!/bin/bash
# setup.sh: makes the scratch repos for the dry runs in $S: product (Kind product, with a
# knowledge base holding one entry), product-nokind (no Kind or Knowledge base line and no
# knowledge base, as an older project) and site (Kind site). Env: REPO (this checkout), S (an
# empty scratch folder outside any repo).
set -eu
: "${REPO:?this checkout}" "${S:?the scratch folder}"
ago() { python3 -c 'import datetime,sys; print((datetime.datetime.now()-datetime.timedelta(days=int(sys.argv[1]))).strftime("%Y-%m-%dT%H:%M:%S"))' "$1"; }
commit() { d=$(ago "$1"); shift; GIT_AUTHOR_DATE=$d GIT_COMMITTER_DATE=$d git commit -q "$@"; }

make_repo() { # make_repo <dir> <kind line> <knowledge base line>
  R=$S/$1
  mkdir -p "$R/src" "$R/.claude/team"
  cd "$R"
  git init -q -b main
  git config user.name "Dry Run"; git config user.email "dry-run@example.com"
  bash "$REPO/plugins/ada/bin/ada-onboard" gitignore >/dev/null
  git check-ignore -q .claude/handoff/x || printf '.claude/handoff/\n' >> .gitignore
  echo '{"enabledPlugins":{"ada@pie":false}}' > .claude/settings.local.json
  git check-ignore -q .claude/settings.local.json || printf '.claude/settings.local.json\n' >> .gitignore
  cat > .claude/team/PROJECT.md <<EOF
# Dry run

## The project

- **What it is:** a made-up WooCommerce add-on, for a dry run of the ada plugin. Not real.
$2
$3
- **Known issues live in:** the Asana project 1299999999999200 (a dry run: it doesn't exist).

## Code and branches

- **Code:** \`src/\`
- **Branch from:** \`main\`. **PR into:** \`main\`. Releases are tagged on \`main\`.
- **Branch names:** \`<type>/<slug>\`

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

None: there is no site. Run the code with \`php\` if it's installed.
EOF
  cat > .claude/team/SYSTEM.md <<'EOF'
# System

## Shipping

Free shipping applies when the basket total is £50.00 or more
(`src/shipping.php`, `dryrun_free_shipping_applies()`).

## Joining

Guests join the waiting list from the join page; the form carries a nonce
(`src/join.php`, `dryrun_join_form()`).
EOF
  cat > src/shipping.php <<'EOF'
<?php
/** Whether the basket gets free shipping. */
function dryrun_free_shipping_applies( $basket_total, $threshold = 50.00 ) {
	return $basket_total > $threshold;
}
EOF
  cat > src/join.php <<'EOF'
<?php
/** The join form; the nonce is checked on submit. */
function dryrun_join_form() {
	return '<form method="post">' . wp_nonce_field( 'dryrun_join', '_wpnonce', true, false ) . '</form>';
}
EOF
  printf '# Changelog\n\n## 1.2.0\n\n- Waiting list for guests.\n' > CHANGELOG.md
  git add -A
  commit 120 -m "Dry-run add-on 1.2.0"
}

add_kb() { # the product's knowledge base, with one entry (a workaround)
  cd "$S/$1"
  bash "$REPO/plugins/ada/bin/ada-onboard" kb >/dev/null
  cat > knowledge-base/issues/guest-join-security-verification.md <<'EOF'
---
title: Guests get "Security verification was not successful" when joining
category: conflict
status: workaround
tags: [nonce, caching, guests, join]
plugin_versions: [1.2.0]
first_seen: 2026-08-02
last_seen: 2026-09-14
sources:
  - asana:1299999999999050
---

## Symptoms
A guest submits the join form and sees "Security verification was not successful. Please try
again." Logged-in customers can join.

## Cause
A page cache serves the join page with an expired nonce (`src/join.php`,
`dryrun_join_form()`), so the check on submit fails for guests.

## Solution
Exclude the join page from the page cache. No fix in the add-on yet.

## How to diagnose
Ask whether a page cache is active, and whether the error happens only for guests. View the
join page's source as a guest: an old `_wpnonce` value that doesn't change means a cached page.
EOF
  python3 - <<'PY'
p = 'knowledge-base/INDEX.md'
s = open(p, encoding='utf-8').read()
s = s.replace('## <Area, e.g. Emails>', '## Joining\n\n- 🩹 [Guests get "Security verification was not successful" when joining](issues/guest-join-security-verification.md)')
open(p, 'w', encoding='utf-8').write(s)
PY
  git add knowledge-base
  commit 30 -m "Knowledge base"
}

make_repo product '- **Kind:** `product`' '- **Knowledge base:** `knowledge-base/`'
add_kb product
make_repo product-nokind '' ''
make_repo site '- **Kind:** `site`' '- **Knowledge base:** `none`'
for r in product product-nokind site; do (cd "$S/$r" && bash "$REPO/plugins/ada/bin/ada-handoff-init" >/dev/null); done
echo "made: $S/product $S/product-nokind $S/site"
```

### The after-script for parts B to F

Save this as `after.py` in the scratch folder. It reads the session's tool calls from the
transcripts under `~/.claude/projects/` written since the mark file, and the files Grace left.

```python
#!/usr/bin/env python3
# after.py: run from anywhere once Grace has written FINDINGS.md and you have quit.
#   python3 after.py <MODE> <scratch repo> <mark file> <this checkout>
# MODE: early (part B), entry (part D), fix (part F: a fix tried in a bughunt worktree),
# nokb (part E: a site, or no Kind line).
# Expected values from BRIEF.md (Decisions, DoD 3 and 5), the knowledge-base README's format, and
# Ada's decision for step 7 (ExitWorktree first, after a fix tried in a bughunt worktree).
# PASS/FAIL lines; exit 1 if any FAIL. Python 3.8+.
import glob, json, os, re, subprocess, sys, time

mode, R, mark, REPO = sys.argv[1], os.path.abspath(sys.argv[2]), sys.argv[3], sys.argv[4]
assert mode in ('early', 'entry', 'fix', 'nokb'), 'MODE: early, entry, fix or nokb'
TASK = {'early': '1299999999999001', 'entry': '1299999999999002', 'nokb': '1299999999999003',
        'fix': '1299999999999004'}[mode]
KNOWN = '1299999999999200'
OLD_ENTRY = 'guest-join-security-verification'
fails = 0


def t(name, ok, detail=''):
    global fails
    print(('PASS ' if ok else 'FAIL ') + name)
    if not ok:
        fails += 1
        if detail:
            print('      ' + detail)


def run_git(args, cwd=R):
    return subprocess.run(['git'] + list(args), cwd=cwd, capture_output=True, text=True).stdout


def section(text, head):
    out, on = [], False
    for line in text.split('\n'):
        if line.startswith('## '):
            if on:
                break
            on = line.startswith('## ' + head)
            continue
        if on:
            out.append(line)
    return '\n'.join(out)


# The worktrees: the bughunt ones
wts, cur = [], {}
for line in run_git(['worktree', 'list', '--porcelain']).split('\n') + ['']:
    if not line:
        if cur:
            wts.append(cur)
        cur = {}
    elif ' ' in line:
        k, v = line.split(' ', 1)
        cur[k] = v
bughunts = [w['worktree'] for w in wts if w.get('branch', '').startswith('refs/heads/bughunt/')]
print('info: bughunt worktrees: ' + (', '.join(bughunts) or 'none'))

# The session's tool calls since the mark, from the transcripts (subagents included; and, for a
# session moved into a bughunt worktree, that worktree's project folder)
proj = os.path.expanduser('~/.claude/projects/')


def enc(p):
    return re.sub(r'[^A-Za-z0-9]', '-', p)


files = glob.glob(os.path.join(proj, enc(R), '**', '*.jsonl'), recursive=True) + \
    glob.glob(os.path.join(proj, enc(R) + '--claude-worktrees-*', '**', '*.jsonl'), recursive=True)
for b in bughunts:
    files += glob.glob(os.path.join(proj, enc(b), '**', '*.jsonl'), recursive=True)
files = sorted(set(files))
since = time.strftime('%Y-%m-%dT%H:%M:%S', time.gmtime(os.path.getmtime(mark)))
calls = []  # (time, tool name, the input as JSON, the Bash command)
for f in files:
    with open(f, encoding='utf-8', errors='replace') as fh:
        for line in fh:
            try:
                d = json.loads(line)
            except ValueError:
                continue
            m, ts = d.get('message'), d.get('timestamp') or ''
            if ts < since or not isinstance(m, dict) or not isinstance(m.get('content'), list):
                continue
            for c in m['content']:
                if isinstance(c, dict) and c.get('type') == 'tool_use':
                    i = c.get('input') or {}
                    cmd = str(i.get('command', '')) if isinstance(i, dict) else ''
                    calls.append((ts, c.get('name') or '', json.dumps(i), cmd))
calls.sort()
print('info: %d tool calls since the mark, from %d transcript files' % (len(calls), len(files)))
t("setup: the session's tool calls were found", len(calls) > 0, 'no transcript under ' + os.path.join(proj, enc(R)))
KBRE = re.compile(r'knowledge-base|INDEX\.md')


def first(pred):
    return next((i for i, c in enumerate(calls) if pred(c)), None)


kb_i = first(lambda c: KBRE.search(c[2]))
known_i = first(lambda c: KNOWN in c[2])
t('no prod-wp call', not [c for c in calls if 'prod-wp' in c[3]])
commits = [c for c in calls if re.search(r'\bgit\b.*\bcommit\b', c[3])]
mark_local = time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(os.path.getmtime(mark)))
new_commits = run_git(['log', '--all', '--format=%h %s', '--since=' + mark_local]).strip()
t('no commit by Grace (no git commit call, no new commit in the repo)', not commits and not new_commits,
  (commits[0][3][:150] if commits else '') + ' ' + new_commits)

# FINDINGS.md: in the bughunt worktree if Grace made one (step 6, as today), else the checkout
findings_dir = bughunts[0] if len(bughunts) == 1 else R
F = os.path.join(findings_dir, '.claude', 'handoff', 'FINDINGS.md')
fx = open(F, encoding='utf-8').read() if os.path.isfile(F) else ''
t('FINDINGS.md written (%s)' % F, bool(fx) and '<short title>' not in fx.split('\n')[0])
# Only Grace's own lines count, not lines left as the template has them.
tset = set(l.strip() for l in open(os.path.join(REPO, 'plugins/ada/templates/handoff/FINDINGS.md'), encoding='utf-8') if l.strip())


def own(x):
    return '\n'.join(l for l in x.split('\n') if l.strip() and l.strip() not in tset)


known = own(section(fx, 'Known issue'))
print('info: Known issue?: ' + ' / '.join(l.strip() for l in known.split('\n'))[:400])
KB_NONE = re.compile(r'knowledge.base[^\n]*(none|no (entry|match)|nothing)', re.I)


def parse(path):
    """An entry's front matter and body."""
    e = open(path, encoding='utf-8').read()
    parts = e.split('---', 2)
    fm = parts[1] if e.startswith('---') and len(parts) == 3 else ''
    return e, fm, (parts[2] if len(parts) == 3 else e)


def val(fm, k):
    m = re.search(r'^%s:\s*(.*?)\s*(#.*)?$' % k, fm, re.M)
    return m.group(1).strip('\'"') if m else ''


def sources(fm):
    return re.findall(r'^\s*-\s*(\S+)\s*$', fm.split('sources:')[-1], re.M)


def no_emails(e):
    return [a for a in re.findall(r'[\w.+-]+@[\w-]+(?:\.[\w-]+)+', e) if not re.search(r'@(pie\.co\.de|example\.com)$', a)]


def kb_status(where):
    """git status --porcelain of knowledge-base/, as {path: code}, untracked files listed singly."""
    out = run_git(['status', '--porcelain', '--untracked-files=all', '--', 'knowledge-base'], cwd=where)
    return {l[3:]: l[:2] for l in out.split('\n') if l.strip()}


if mode in ('early', 'entry', 'fix'):
    t('product: the knowledge base was checked', kb_i is not None)
    t('product: before the known-issues project (Asana %s), if that was searched at all' % KNOWN,
      kb_i is not None and (known_i is None or kb_i < known_i),
      'first knowledge-base call #%s, first known-issues call #%s' % (kb_i, known_i))

if mode == 'early':
    t('early: Known issue? names the matching entry', OLD_ENTRY in known)
    t('early: Status says known issue', re.search(r'^Status:[^|\n]*known issue[^|\n]*$', fx, re.M | re.I) is not None)
    t('early: the hunt ended early: no bughunt worktree', not bughunts)
    t('early: no code changed', run_git(['status', '--porcelain', '--', 'src']) == '')
    st_ = kb_status(R)
    print('info: knowledge-base/ status: %s' % st_)
    OLD_PATH = 'knowledge-base/issues/%s.md' % OLD_ENTRY
    t('early: the matching entry updated in the checkout and left uncommitted (modified)', st_.get(OLD_PATH, '').strip() == 'M', str(st_))
    t('early: nothing else in knowledge-base/ changed (INDEX.md unchanged, no new entry)', set(st_) <= {OLD_PATH},
      ' '.join(sorted(set(st_) - {OLD_PATH})))
    e, fm, body = parse(os.path.join(R, OLD_PATH))
    srcs = sources(fm)
    t('early: sources gained asana:%s' % TASK, 'asana:' + TASK in srcs, ' '.join(srcs))
    t('early: sources still have asana:1299999999999050', 'asana:1299999999999050' in srcs, ' '.join(srcs))
    t('early: every source is asana:<gid>', bool(srcs) and all(re.match(r'asana:\d+$', s) for s in srcs), ' '.join(srcs))
    t('early: last_seen widened, later than 2026-09-14 (%s)' % val(fm, 'last_seen'), val(fm, 'last_seen') > '2026-09-14')
    t('early: plugin_versions still has 1.2.0 (%s)' % val(fm, 'plugin_versions'), '1.2.0' in val(fm, 'plugin_versions'))
    t('early: the four sections still filled', all(section(body, h).strip() for h in ('Symptoms', 'Cause', 'Solution', 'How to diagnose')))
    t('early: no customer email address', not no_emails(e), ' '.join(no_emails(e)))

if mode == 'fix':
    # Part F: a fix was tried in a bughunt/<slug> worktree; the entry still lands in the main checkout.
    # If ExitWorktree wasn't available, the first three FAIL: see "If ExitWorktree isn't available" in the script.
    enter_i = first(lambda c: c[1] == 'EnterWorktree')
    exit_i = first(lambda c: c[1] == 'ExitWorktree')
    t('fix: Grace made exactly one bughunt/<slug> worktree (the fix was tried)', len(bughunts) == 1, ', '.join(bughunts))
    t('fix: the scratch fix is in that worktree (src changed there)',
      len(bughunts) == 1 and run_git(['status', '--porcelain', '--', 'src'], cwd=bughunts[0]) != '')
    t('fix: no code changed in the main checkout', run_git(['status', '--porcelain', '--', 'src']) == '')
    t('fix: EnterWorktree, then ExitWorktree, were called (in that order)',
      enter_i is not None and exit_i is not None and enter_i < exit_i, 'EnterWorktree #%s, ExitWorktree #%s' % (enter_i, exit_i))
    kbw = [i for i, c in enumerate(calls) if c[1] in ('Write', 'Edit', 'MultiEdit') and KBRE.search(json.loads(c[2]).get('file_path', ''))]
    print('info: Write/Edit calls on knowledge-base files: %d' % len(kbw))
    t('fix: every Write or Edit of a knowledge-base file came after ExitWorktree',
      exit_i is not None and all(i > exit_i for i in kbw))
    wk = kb_status(bughunts[0]) if len(bughunts) == 1 else None
    t("fix: nothing changed in the worktree's knowledge-base/ (it can fail: there is a worktree)",
      wk == {}, str(wk))
    t('fix: FINDINGS.md is in the bughunt worktree, for /ada:begin',
      len(bughunts) == 1 and os.path.isfile(os.path.join(bughunts[0], '.claude', 'handoff', 'FINDINGS.md')))

if mode in ('entry', 'fix'):
    st_ = kb_status(R)
    print('info: knowledge-base/ status in the main checkout: %s' % st_)
    if mode == 'entry':
        t('entry: no bughunt worktree (no fix was tried, and the entry needs none)', not bughunts)
        t('entry: no code changed', run_git(['status', '--porcelain', '--', 'src']) == '')
    ne = sorted(p for p, c in st_.items() if c == '??' and p.startswith('knowledge-base/issues/') and p.endswith('.md'))
    t("entry: exactly one new entry in the main checkout's knowledge-base/issues/, untracked (uncommitted)", len(ne) == 1, ' '.join(ne))
    t('entry: INDEX.md changed and uncommitted', st_.get('knowledge-base/INDEX.md', '').strip() == 'M')
    t('entry: nothing else in knowledge-base/ changed',
      set(st_) <= set(ne) | {'knowledge-base/INDEX.md'}, ' '.join(sorted(set(st_) - set(ne) - {'knowledge-base/INDEX.md'})))
    if len(ne) == 1:
        e, fm, body = parse(os.path.join(R, ne[0]))
        name = os.path.basename(ne[0])
        st = val(fm, 'status')
        t('entry: status open-bug, workaround or unresolved (not fixed, not confirmed by the customer) (%s)' % st,
          st in ('open-bug', 'workaround', 'unresolved'))
        t('entry: category from the README list (%s)' % val(fm, 'category'),
          val(fm, 'category') in ('bug', 'conflict', 'configuration', 'how-to', 'feature', 'licensing', 'support-process', 'development'))
        t('entry: title and tags filled', bool(val(fm, 'title')) and val(fm, 'tags') not in ('', '[]'))
        t('entry: sources has asana:%s' % TASK, re.search(r'^\s*-\s*asana:%s\s*$' % TASK, fm, re.M) is not None)
        srcs = sources(fm)
        t('entry: every source is asana:<gid>', bool(srcs) and all(re.match(r'asana:\d+$', s) for s in srcs), ' '.join(srcs))
        t('entry: no Gmail', 'gmail' not in e.lower())
        for s in ('Symptoms', 'Cause', 'Solution', 'How to diagnose'):
            t('entry: ## %s present and not empty' % s, section(body, s).strip() != '')
        t('entry: no customer email address', not no_emails(e), ' '.join(no_emails(e)))
        idx = open(os.path.join(R, 'knowledge-base', 'INDEX.md'), encoding='utf-8').read()
        links = [l for l in idx.split('\n') if '](issues/%s)' % name in l]
        t('entry: INDEX.md links it exactly once', len(links) == 1)
        icon = {'open-bug': '\U0001F41E', 'workaround': '\U0001FA79', 'unresolved': '\u2753'}.get(st, '?')
        t('entry: its INDEX.md line has the status icon %s' % icon, len(links) == 1 and icon in links[0])
        t('entry: INDEX.md has no placeholder area left', not [h for h in idx.split('\n') if h.startswith('## ') and '<' in h])
        t('entry: INDEX.md still links the joining entry once', idx.count('](issues/%s.md)' % OLD_ENTRY) == 1)
        t('entry: Known issue? names the entry file', name[:-3] in known)
        t('entry: Known issue? says no existing entry matched', KB_NONE.search(known) is not None and OLD_ENTRY not in known)

if mode == 'nokb':
    t('nokb: no call names the knowledge base or INDEX.md', kb_i is None, calls[kb_i][2][:150] if kb_i is not None else '')
    t('nokb: Known issue? names no knowledge-base entry', OLD_ENTRY not in known)
    t('nokb: no knowledge-base/ folder made or changed anywhere',
      not os.path.exists(os.path.join(R, 'knowledge-base')) and
      all(not os.path.exists(os.path.join(b, 'knowledge-base')) for b in bughunts))

print('%d failed' % fails)
sys.exit(1 if fails else 0)
```

### Running a dry run

Each part is the same steps, with the repo, the report and the after-script's mode in its table
row. `<X>` is the part's letter.

0. Parts B, D and F all run in `product`, in that order, and each starts from the knowledge base
   as `setup.sh` left it. Before D, revert B's change to the existing entry:
   `git -C <scratch>/product checkout -- knowledge-base`. Before F, revert it again and remove
   D's new entry: `git -C <scratch>/product checkout -- knowledge-base`, then
   `git -C <scratch>/product clean -fdq -- knowledge-base`. (A shipping entry left from D would
   answer F's report and end the hunt before any fix.) Archive each part's handoff files first
   (step 5). Run E1 and E2 in their own repos, any time.
1. `touch <scratch>/mark-<X>`
2. `cd <scratch>/<repo>` and start `claude --plugin-dir <this checkout>/plugins/ada`. Give the
   report word for word.
3. Approve reads: `grep`, `git log`, `git show`, reading files. In parts B, D and F also approve
   Grace's writes to `knowledge-base/` in the scratch repo's main checkout and to
   `.claude/handoff/` (in part F, `FINDINGS.md` goes in the bughunt worktree). In part F also
   approve `ada-worktree new`, `EnterWorktree`, `ExitWorktree`, and edits under `src/` inside the
   bughunt worktree. Decline anything else and note it as a FAIL: a `prod-wp` call, an Asana call,
   an edit under `src/` in the main checkout, a new worktree in any part but F, a `git commit`, a
   push.
4. When Grace has written `FINDINGS.md` and the entry (or, in part F's fallback below, given you
   its text) and stopped, quit.
5. `python3 <scratch>/after.py <mode> <scratch>/<repo> <scratch>/mark-<X> <this checkout>`,
   then archive the run's handoff files:
   `(cd <scratch>/<repo> && bash <this checkout>/plugins/ada/bin/ada-handoff-init --archive dry-run-<X>)`.
   In part F, `FINDINGS.md` is in the bughunt worktree, so archive there too:
   `(cd <the bughunt worktree> && bash <this checkout>/plugins/ada/bin/ada-handoff-init --archive dry-run-F)`.

| Part | Repo | Mode | Report |
|---|---|---|---|
| B: an entry that answers the report ends the hunt, and gets the card's source | `product` | `early` | 1 |
| D: no entry answers it: the hunt runs, then a new entry and INDEX.md in the checkout, uncommitted | `product` | `entry` | 2 |
| F: no entry answers it, Grace tries a fix in a bughunt worktree: the entry and INDEX.md still land in the main checkout, uncommitted, and nothing changes in the worktree's `knowledge-base/` | `product` | `fix` | 4 |
| E1: a site | `site` | `nokb` | 3 |
| E2: an older project with no `Kind:` line and no knowledge base | `product-nokind` | `nokb` | 3 |

### The reports

Say them in the session word for word. Reports 1 to 3 each tell Grace not to try a fix, so step 4
makes no worktree and she stays in the checkout she started in. Report 4 asks for one.

1. > /ada:bughunt This is a dry run of the ada plugin in a scratch repo. There is no production
   > and no local site, so don't use prod-wp. The Asana task is 1299999999999001; it isn't real,
   > so don't open it: this is the whole report. A guest customer says that when they try to
   > join, the page says "Security verification was not successful. Please try again." Find out
   > what's going on and write FINDINGS.md. Don't try a fix.
2. > /ada:bughunt This is a dry run of the ada plugin in a scratch repo. There is no production
   > and no local site, so don't use prod-wp. The Asana task is 1299999999999002; it isn't real,
   > so don't open it: this is the whole report. A customer says their basket of exactly £50.00
   > was charged shipping, though the settings say free shipping from £50. Find out why from the
   > code and write FINDINGS.md. Don't try a fix.
3. > /ada:bughunt This is a dry run of the ada plugin in a scratch repo. There is no production
   > and no local site, so don't use prod-wp. The Asana task is 1299999999999003; it isn't real,
   > so don't open it: this is the whole report. A guest customer says that when they try to
   > join, the page says "Security verification was not successful. Please try again." Find out
   > what's going on and write FINDINGS.md. Don't try a fix.
4. > /ada:bughunt This is a dry run of the ada plugin in a scratch repo. There is no production
   > and no local site, so don't use prod-wp. The Asana task is 1299999999999004; it isn't real,
   > so don't open it: this is the whole report. A customer says their basket of exactly £50.00
   > was charged shipping, though the settings say free shipping from £50. Find out why from the
   > code. Confirm the cause by trying a fix, as the skill says: make a scratch change in a
   > bughunt worktree (there is no remote and nothing to push), and don't commit it. Then write
   > FINDINGS.md.

### If `ExitWorktree` isn't available (part F)

Step 7 says that if Grace tried a fix and `ExitWorktree` isn't available, she writes nothing
outside the worktree and gives the developer the entry's text and the path to save it at in the
main checkout. `after.py fix` can't pass that path (its `ExitWorktree`, entry and `INDEX.md`
checks FAIL, as they should: the entry is not in the checkout). Treat it as a **manual
observation**, recorded as such and never as a PASS. Check by hand:

1. Grace's last message says `ExitWorktree` isn't available (or the transcript shows no
   `ExitWorktree` tool), and gives the entry's text and a path inside the main checkout's
   `knowledge-base/issues/` (not inside the worktree).
2. `git -C <scratch>/product status --porcelain --untracked-files=all -- knowledge-base` prints
   nothing, and the same in the bughunt worktree prints nothing: she wrote the entry and
   `INDEX.md` nowhere.
3. No `git commit`.

### Afterwards

Delete the scratch folder. Nothing outside it changed: the scratch repos have no remote.

## Expected

From the brief (Decisions; Definition of done; the last three 2026-11-16 Amendments rows), Ada's
decision for step 7 (given when the tests were fixed: after a fix tried in a bughunt worktree,
`ExitWorktree` first, or the entry's text and path if it isn't available) and the
knowledge-base README's entry format:

- Part A: every line `PASS`, `0 failed`, exit 0. On the exported start commit `24 failed`, exit
  1: every DoD 1, 3 (but its "no reusable condition" check), 4 and 5 check (DoD 4 including the
  "old bugs already fixed" one), DoD 7's `Kind:` line check, DoD 8's bullet check, both docs
  checks and the Repo row's "uncommitted entry and `INDEX.md`" check FAIL; the setup checks, the
  three Decisions checks, DoD 2, DoD 6, DoD 7's products-only check (nothing to check yet),
  DoD 8's "Nothing yet.", the three-places check (its Repo row aside), the Repo row's "still
  allows the local branch" check and every other regression and out-of-scope check PASS.
- Part B (`early`): Grace checks `knowledge-base/` (`INDEX.md` or `issues/`) before she searches
  the known-issues project `1299999999999200` (if she searches it at all), finds
  `guest-join-security-verification.md` (status `workaround`, which fits) and ends the hunt
  there, as a known Asana bug does: `FINDINGS.md` `Status:` says known issue, and "Known issue?"
  names the entry. Every bughunt is recorded, so she updates that entry in the checkout: its
  `sources` gain `asana:1299999999999001` and keep `asana:1299999999999050`, every source is
  `asana:<gid>`, `last_seen` is later than 2026-09-14 (the day of the report), `plugin_versions`
  still has 1.2.0, its four sections still filled, no customer data. The entry is left modified
  and uncommitted; `INDEX.md` and everything else in `knowledge-base/` unchanged (an update adds
  no INDEX.md line). No `bughunt/` worktree, no code change, no commit. `after.py`: every line
  `PASS`, `0 failed`.
- Part D (`entry`): the knowledge base is checked before the known-issues project, and no entry
  matches, so the hunt runs as before. After the findings she writes one new entry in the main
  checkout's `knowledge-base/issues/`, never in a bughunt worktree, in the README's format: status `open-bug`, `workaround`
  or `unresolved`; `sources` only `asana:<gid>` lines, including `asana:1299999999999002`; no
  Gmail; Symptoms, Cause, Solution and How to diagnose filled; no customer data. `INDEX.md`
  links it exactly once, with its status's icon, under a named area, and still links the
  joining entry once. Both are left uncommitted: the entry untracked, `INDEX.md` modified, no
  commit, nothing else in `knowledge-base/` changed. No `bughunt/` worktree and no code change.
  "Known issue?" in `FINDINGS.md` says no entry matched and names the entry file written.
  `after.py`: every line `PASS`, `0 failed`.
- Part F (`fix`): as part D (KB checked first, no entry matches, the hunt runs), except that Grace
  tries a fix, so she makes exactly one `bughunt/<slug>` worktree with `ada-worktree new` and
  enters it with `EnterWorktree`; the scratch change to `src/shipping.php` is in that worktree
  and not in the main checkout, uncommitted. At step 7 she returns to the main checkout with
  `ExitWorktree` (keeping the worktree and its branch) before writing anything under
  `knowledge-base/`. The one new entry (as in part D, `sources` including
  `asana:1299999999999004`) and the modified `INDEX.md` are in the **main checkout's**
  `knowledge-base/`, untracked and modified respectively, nothing else there changed. In the
  worktree, `git status` on `knowledge-base/` prints nothing: the entry, `INDEX.md` and every
  other file there are as committed. `FINDINGS.md` is in the worktree (step 6, for `/ada:begin`),
  its "Known issue?" saying no entry matched and naming the entry file. No commit anywhere, no
  `prod-wp` call. `after.py`: every line `PASS`, `0 failed`. If `ExitWorktree` isn't available:
  the manual observation above, not a PASS.
- Part E1 and E2 (`nokb`): nothing Grace runs names `knowledge-base` or `INDEX.md`; "Known
  issue?" names no knowledge-base entry; no `knowledge-base/` folder appears; no commit. The hunt
  otherwise runs as before (the known-issues project, the code, `FINDINGS.md`). `after.py`:
  every line `PASS`, `0 failed`.

## Observe at

- `check.sh`'s output (part A).
- The sessions (Ctrl-O for the transcript: each search, and its order; in part F the
  `EnterWorktree` and `ExitWorktree` calls and where the entry was written).
- `.claude/handoff/FINDINGS.md` in the scratch repo (in part F, in the bughunt worktree); earlier
  runs' under `.claude/handoff/archive/dry-run-<X>/`.
- `git status knowledge-base` and `git log` in the scratch repo (in part F, in the worktree too:
  `git worktree list` gives its path).
- `after.py`'s output.

## Regression

- A site, and a project with no `Kind:` line, hunt exactly as before: no knowledge-base step
  (parts E1 and E2).
- The rest of the bughunt skill is unchanged: the three places table apart from its Repo row
  (which may now allow the uncommitted entry and `INDEX.md` in the main checkout, and must still
  allow the local `bughunt/<slug>` branch, never pushed), the production rules, steps 2 to 5, the
  Never list; Intake still reads the Asana task, searches the known-issues project and reads
  `SYSTEM.md`; step 6 still leaves `FINDINGS.md` in the bughunt worktree for `/ada:begin`, and
  customer remedies are still written up, not done (part A; part F for `FINDINGS.md` in the
  worktree).
- No support-project, recent-fix or worktree steps added to bughunt (part A, Decisions).
- The `FINDINGS.md` template keeps its headings and the known-issues project line (part A).
- `/ada:begin` unchanged, every file in `skills/begin` (part A, DoD 6).
- Out of scope, unchanged since the start commit: the scripts, the version numbers (P31), the
  knowledge-base template (Unit B2), the onboard and pickup skills, the agents and the repo
  templates (part A).
- The rest of the knowledge-base README, outside its "When to write an entry" section (which
  holds "What doesn't get an entry"), unchanged (part A).
- Not covered by a run: a "not a bug" result (`configuration` or `how-to`) and a cause not found
  (`unresolved`). Part A checks the skill says each; the parts don't stage them. Part B covers
  updating a matching entry rather than duplicating it; part F covers the entry written in the
  main checkout after a fix was tried in a `bughunt/<slug>` worktree.
