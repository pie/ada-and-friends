# P41, outbound HTTP through the WordPress HTTP API, is in the standards Bob and Claudia are preloaded with

- Area: 09 Roles (the P rules Bob builds to and Claudia reviews against)
- Automate as: none (part A is a self-checking shell script; part B is read by a person)
- Last run: 18 Nov 2026, part A 42 PASS and the broken copy FAILs as expected; part B run, both reviews as expected (see the PR)

## Precondition

- This checkout, with the change in its working tree, and `origin/next-release` fetched
  (`git fetch origin next-release`): part A compares with it.
- No scratch repo for part A: it only reads this checkout and `origin/next-release`.
- Part B: Claude Code, and a scratch project that is not a client's.

## Steps

### Part A: the text checks

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. Break it on purpose, once: copy the checkout (`cp -R <this checkout> <scratch>/copy`, which
   keeps its `.git`, so `origin/next-release` can still be read), and in the copy's
   `plugins/ada/skills/standards/SKILL.md` delete P41's **SDK bullet**: the bullet that says what
   to do with a third-party SDK that can't be changed (give it a handler, or record an exception),
   with any numbered items under it. Run `REPO=<scratch>/copy bash check.sh`. Then delete the copy.

```bash
#!/bin/bash
# Text checks for P41. PASS/FAIL per check; exit 1 if any FAIL, 2 if it can't start.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
cd "$REPO" || exit 2
S=plugins/ada/skills/standards/SKILL.md
PJ=plugins/ada/.claude-plugin/plugin.json
MJ=.claude-plugin/marketplace.json
git rev-parse --verify -q "$BASE^{commit}" >/dev/null || { echo "no $BASE: git fetch origin first"; exit 2; }
T=$(mktemp -d) || exit 2
trap 'rm -rf "$T"' EXIT
fails=0
t() { name=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $name"; else echo "FAIL $name"; fails=$((fails+1)); fi; }
has() { grep -qiE -- "$1" "$T/${2:-p41f}"; }
# P41's text, from its title line to the next rule or heading; flattened so a phrase can wrap.
awk '/^\*\*P41\./{p=1;print;next} p&&(/^\*\*P[0-9]+\./||/^#/){exit} p' "$S" > "$T/p41"
tr '\n' ' ' < "$T/p41" | tr -s ' ' > "$T/p41f"
awk '/^Why:/{p=1} p&&/^[[:space:]]*$/{exit} p' "$T/p41" | tr '\n' ' ' | tr -s ' ' > "$T/why"
awk '/^#/{h=$0} /^\*\*P41\./{print h; exit}' "$S" > "$T/section"
# The SDK text alone: from "can't be changed" (the SDK bullet) to the third-party plugin paragraph.
# Empty when the SDK bullet is missing, so every check on it then FAILs.
perl -0ne 'if (/(can.{1,3}t be changed.*)/i) { $s=$1; $s =~ s/third.party plugin.*//i; print $s }' "$T/p41f" > "$T/sdk"
awk '/^## Unreleased/{p=1;next} /^## /{exit} p' CHANGELOG.md | tr '\n' ' ' > "$T/unrel"
# The released files, to compare with.
git show "$BASE:$S" > "$T/base-skill" 2>/dev/null
git show "$BASE:CHANGELOG.md" > "$T/base-changelog" 2>/dev/null
git show "$BASE:$PJ" > "$T/base-pj" 2>/dev/null
git show "$BASE:$MJ" > "$T/base-mj" 2>/dev/null
git grep -hoE '^\*\*P[0-9]+\.' "$BASE" -- plugins/ada/skills | sort > "$T/base-nums"
{ cat "$T/base-nums"; echo '**P41.'; } | sort > "$T/want-nums"
grep -rhoE '^\*\*P[0-9]+\.' plugins/ada/skills | sort > "$T/nums"

# Where it is
t "P41 appears once in the standards skill" test "$(grep -c '^\*\*P41\.' "$S")" = 1
t "no other skill numbers a rule P41" test "$(grep -rhoE '\*\*P41\.' plugins/ada/skills | wc -l | tr -d ' ')" = 1
t "P41 is in Data and boundaries" grep -qx '## Data and boundaries' "$T/section"
t "P41 comes after P28" awk '/^\*\*P28\./{a=NR} /^\*\*P41\./{b=NR} END{exit !(a && b > a)}' "$S"
# Its shape (P40's): bold title sentence, the rule, bullets, then Why
t "P41: bold title sentence" grep -qE '^\*\*P41\. [^*]+\.\*\*' "$T/p41"
t "P41: has bullets" grep -qE '^- ' "$T/p41"
t "P41: Why: comes after the bullets" awk '/^Why:/{w=1;next} w&&/^[[:space:]]*([-*]|[0-9]+\.) /{bad=1} END{exit !(w && !bad)}' "$T/p41"
# The rule
t "P41: names wp_remote_ functions" has 'wp_remote_(get|post|request)\(\)'
t "P41: names wp_safe_remote_" has 'wp_safe_remote_'
t "P41: safe variants when the URL or host is from outside the code" sh -c "grep -qiE 'outside (the|its|your|the project.s) code' '$T/p41f' && grep -qi 'host' '$T/p41f'"
t "P41: examples of a URL from outside (setting, input, webhook)" has 'admin setting|customer input|webhook'
# What a reviewer looks for
t "P41: Guzzle" has 'Guzzle'
t "P41: curl_init" has 'curl_init'
t "P41: curl_exec" has 'curl_exec'
t "P41: file_get_contents" has 'file_get_contents'
t "P41: fopen" has 'fopen'
t "P41: SimpleXMLElement or simplexml_load_file" has 'SimpleXMLElement|simplexml_load_file'
t "P41: fsockopen" has 'fsockopen'
t "P41: stream_socket_client" has 'stream_socket_client'
t "P41: a vendor SDK with its own client" sh -c "grep -q 'SDK' '$T/p41f' && grep -qiE 'own (HTTP )?client' '$T/p41f'"
# An SDK that can't be changed, and third-party plugins
t "P41: SDK given a handler" has 'handler' sdk
t "P41: SDK given a PSR-18 client" has 'PSR-18' sdk
t "P41: the SDK's handler sends through wp_remote_request()" has 'wp_remote_request\(\)' sdk
t "P41: otherwise an exception in the project's STANDARDS.md" sh -c "grep -qi 'exception' '$T/p41f' && grep -q 'STANDARDS\.md' '$T/p41f'"
t "P41: as an S rule that narrows P41" grep -qE '(^|[^[:alnum:]])S rule' "$T/p41f"
t "P41: the exception says what it bypasses" has 'bypass'
t "P41: the SDK's exception says how it's kept from sending locally" has 'local copy|locally' sdk
t "P41: third-party plugins recorded the same way" has 'third.party plugin'
# Why
t "P41: has a Why" grep -q '^Why:' "$T/p41"
t "Why: local blocks" has 'local (safety )?block' why
t "Why: pre_http_request stubs" has 'pre_http_request' why
t "Why: HTTP logging" has 'logging' why
t "Why: the generic case, a plugin kept inactive" sh -c "grep -qi 'plugin' '$T/why' && grep -qi 'inactive' '$T/why'"
t "P41: names no client" sh -c "! grep -qiE '(^|[^[:alnum:]])v ?(&|and) ?me([^[:alnum:]]|$)|vandme|klaviyo' '$T/p41f'"
# The rest of the standards: nothing renumbered, nothing removed or changed
t "skills: rule numbers are the released ones plus P41" cmp -s "$T/nums" "$T/want-nums"
t "standards skill: only lines added" sh -c "test -s '$T/base-skill' && ! diff '$T/base-skill' '$S' | grep -q '^<'"
t "no rule list elsewhere ends at P40" sh -c "! grep -rnE '(to|-|–|through|until) ?P40([^0-9]|$)' plugins README.md DEVELOPING.md .claude/team"
# CHANGELOG and versions (P31)
t "CHANGELOG: P41 under Unreleased" grep -q 'P41' "$T/unrel"
t "CHANGELOG: the P41 entry names no client" sh -c "! grep -qiE '(^|[^[:alnum:]])v ?(&|and) ?me([^[:alnum:]]|$)|vandme|klaviyo' '$T/unrel'"
t "CHANGELOG: release headings unchanged" sh -c "grep '^## [0-9]' '$T/base-changelog' > '$T/a'; grep '^## [0-9]' CHANGELOG.md > '$T/b'; test -s '$T/a' && cmp -s '$T/a' '$T/b'"
t "plugin.json: version unchanged" sh -c "grep '\"version\"' '$T/base-pj' > '$T/c'; grep '\"version\"' '$PJ' > '$T/d'; test -s '$T/c' && cmp -s '$T/c' '$T/d'"
t "marketplace.json: version lines unchanged" sh -c "test -s '$T/base-mj' && grep -i 'version' '$T/base-mj' > '$T/e'; grep -i 'version' '$MJ' > '$T/f'; cmp -s '$T/e' '$T/f'"

[ "$fails" = 0 ] || exit 1
```

### Part B: a dry run

1. Make a scratch plugin in a new git repo, `acme-sync`, with one commit holding only a
   `README.md`. Onboard it with this checkout's script (`<this checkout>/plugins/ada/bin/ada-onboard
   files`), and turn `ada@pie` off in its `.claude/settings.local.json` (`DEVELOPING.md`).
2. On a branch `add/acme-sync`, add these files and commit them:
   - `vendor/acme/sdk/src/Client.php`: a class `Acme\Sdk\Client` whose `send()` posts with
     `curl_init()` / `curl_exec()` and has no way to pass it a client or handler.
   - `acme-sync.php`, a plugin file with one function for each case, numbered in comments:
     1. `wp_remote_get( 'https://api.example.com/v1/status' )`, a URL written in the code;
     2. `( new \GuzzleHttp\Client() )->post( 'https://api.example.com/v1/orders', array( 'json' => $order ) )`;
     3. `curl_init( 'https://api.example.com/v1/ping' )` then `curl_exec()`;
     4. `file_get_contents( get_option( 'acme_feed_url' ) )`, a URL from an admin setting;
     5. `wp_remote_post( get_option( 'acme_webhook_url' ), array( 'body' => $payload ) )`;
     6. `file_get_contents( __DIR__ . '/data/regions.json' )`, a file in the plugin;
     7. `( new \Acme\Sdk\Client( ACME_KEY ) )->send( $order )`.
   - `assets/acme.js`: `fetch( '/wp-json/acme/v1/status' )`.
3. Run `claude --plugin-dir <this checkout>/plugins/ada` in the scratch repo and ask for a Claudia
   review of the branch.
4. Add an S rule to the scratch project's `.claude/team/STANDARDS.md` that narrows P41 for the
   Acme SDK: it sends with its own curl client and can't take a handler; it bypasses local blocks,
   `pre_http_request` stubs and HTTP logging; it is kept from sending locally because `ACME_KEY`
   is not defined on local copies. Commit it and ask for the review again.

## Expected

- Part A: every line `PASS`, exit 0. The broken copy prints `FAIL P41: SDK given a handler` and
  `FAIL P41: SDK given a PSR-18 client`, `FAIL P41: the SDK's handler sends through
  wp_remote_request()` and `FAIL P41: the SDK's exception says how it's kept from sending
  locally`, and exits 1 (other SDK lines, such as the exception's, may FAIL too). The last two
  look only at the SDK text, so they FAIL when the SDK bullet is gone even though the rule's
  opening sentence still names `wp_remote_request()` and the Why still says "local copy".
- Part B, first review: P41 is cited as **Must Fix** for cases 2 (Guzzle), 3 (curl) and 4
  (`file_get_contents()` on a URL, to be `wp_safe_remote_get()` as the URL is an admin setting),
  for case 5 (to be `wp_safe_remote_post()`, the URL being an admin setting), and for case 7 (an
  SDK with its own client, no handler and no recorded exception: give it a handler through
  `wp_remote_request()` or record it in `STANDARDS.md`). Cases 1 and 6 and `assets/acme.js` are
  not cited under P41.
- Part B, second review: case 7 is no longer a P41 finding (the review may name the S rule);
  cases 2 to 5 still are.

## Observe at

- Part A: stdout and `echo $?`.
- Part B: `.claude/handoff/REVIEW-FEEDBACK.md` in the scratch repo, after each review.

## Regression

- P numbers never change: the skills' rule numbers are the released ones plus P41, and no line of
  the released standards skill is removed or changed (part A checks both).
- Nothing else in the plugin lists the P rules up to P40 (part A, `no rule list elsewhere ends at
  P40`).
- No version number changes (P31): `plugin.json`, `marketplace.json` and the CHANGELOG's release
  headings are as on `origin/next-release` (part A).
- P40's rule text is unchanged: the rule-text checks of P40's own test,
  `tests/functional/09-roles/standards-p40-asset-versions.md` part A, still pass. Leave out its
  CHANGELOG check (`P40 under Unreleased`): P40 is released, so that check fails on
  `origin/next-release` too and says nothing about this change.
