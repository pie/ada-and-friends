# P41, outbound HTTP through the WordPress HTTP API, is in the standards Bob and Claudia are preloaded with

- Area: 09 Roles (the P rules Bob builds to and Claudia reviews against)
- Automate as: none (part A is a self-checking shell script; part B is read by a person)
- Last run: 19 Nov 2026, part A 59 PASS exit 0; part B round-3 runs re-judged against the amended Expected: first review 3 of 4 as expected, 1 of 4 (run 1) still fails case 8 (handler the only fix); second review 2 of 2 as expected (see the PR)

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
   `plugins/ada/skills/standards/SKILL.md` delete P41's **SDK text**: what it says to do with a
   third-party SDK or library that brings its own HTTP client (record it in `STANDARDS.md`; the
   optional handler advice), whether it is a bullet or a paragraph or two, but not the
   third-party plugin paragraph, the Severity line or the Why. Run `REPO=<scratch>/copy bash check.sh`. Then delete the copy.

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
# The SDK text alone: from the first paragraph that mentions STANDARDS.md, PSR-18 or a handler, up to
# the third-party plugin paragraph, the Severity line or the Why (whichever comes first), so it is
# empty when the SDK text is missing and every check on it then FAILs. It holds no numbered item.
perl -00ne 'if (!$on && /STANDARDS\.md|PSR-18|handler/i) { $on=1 } if ($on) { last if /^(A third.party plugin|Severity|Why:)/i; print }' "$T/p41" | tr '\n' ' ' | tr -s ' ' > "$T/sdk"
# The Severity line alone: from "Severity" to the Why.
perl -00ne 'if (/^Severity/i) { $on=1 } last if /^Why:/; print if $on' "$T/p41" | tr '\n' ' ' | tr -s ' ' > "$T/sev"
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
t "P41: the SDK text is a record in STANDARDS.md" sh -c "grep -q 'STANDARDS\.md' '$T/sdk' && grep -qiE 'record' '$T/sdk'"
t "P41: recorded as an S rule that narrows P41" sh -c "grep -qE '(^|[^[:alnum:]])S rule' '$T/sdk' && grep -qi 'narrow' '$T/sdk'"
t "P41: the record names which SDK or library" has 'which (SDK|library)|the (SDK|library)' sdk
t "P41: the record says what it bypasses" has 'bypass' sdk
t "P41: the record lists local blocks, pre_http_request stubs and HTTP logging" sh -c "grep -qi 'local block' '$T/sdk' && grep -q 'pre_http_request' '$T/sdk' && grep -qi 'logging' '$T/sdk'"
t "P41: the record says how it's kept from sending locally" has 'local copy|locally' sdk
t "P41: a handler is optional advice" has 'optional|not required|isn.t required|need not' sdk
t "P41: a library recorded in STANDARDS.md is not a finding" sh -c "grep -qiE '(isn.t|is not|not) a finding' '$T/sdk' && grep -qi 'record' '$T/sdk'"
t "P41: ... even if it could take a handler" sh -c "grep -qi 'even if' '$T/sdk' && grep -qi 'handler' '$T/sdk'"
t "P41: ... routing it through the HTTP API is a Should fix at most" sh -c "grep -qiE 'should.fix' '$T/sdk' && grep -qi 'at most' '$T/sdk' && grep -qiE 'through the (WordPress )?HTTP API' '$T/sdk'"
t "P41: the handler advice names PSR-18" has 'PSR-18' sdk
t "P41: the handler advice names a Guzzle handler" has 'Guzzle handler' sdk
t "P41: the handler advice names wp_remote_request()" has 'wp_remote_request\(\)' sdk
t "P41: the handler advice names wp_safe_remote_request()" has 'wp_safe_remote_request\(\)' sdk
t "P41: the safe variant is for a URL or host from outside the code" sh -c "grep -qi 'outside' '$T/sdk' && grep -qi 'host' '$T/sdk'"
t "P41: no step order for an SDK ('in this order')" sh -c "! grep -qi 'in this order' '$T/p41f'"
t "P41: no numbered list of steps for an SDK" sh -c "! grep -qE '^[[:space:]]*[0-9]+\. ' '$T/p41'"
t "P41: an SDK is not required to take a handler" sh -c "! grep -qiE 'give it (a handler|one)|must (have|accept|take|be given) a handler|neither a handler' '$T/p41f'"
t "P41: third-party plugins recorded the same way" has 'third.party plugin'
# Why
t "P41: has a Why" grep -q '^Why:' "$T/p41"
t "Why: local blocks" has 'local (safety )?block' why
t "Why: pre_http_request stubs" has 'pre_http_request' why
t "Why: HTTP logging" has 'logging' why
t "Why: the generic case, a plugin kept inactive" sh -c "grep -qi 'plugin' '$T/why' && grep -qi 'inactive' '$T/why'"
# Severity
t "Severity: a Severity line" test -s "$T/sev"
t "Severity: Must fix when the project's own code calls another client directly" sh -c "grep -qi 'must fix' '$T/sev' && grep -qi 'directly' '$T/sev'"
t "Severity: Must fix when a library or plugin that sends around the HTTP API isn't recorded" sh -c "grep -qi 'record' '$T/sev' && grep -q 'STANDARDS\.md' '$T/sev'"
t "Severity: says 'through' or 'around' the HTTP API" has '(through|around) the (WordPress )?HTTP API' sev
t "Severity: names no single function" sh -c "! grep -qE 'wp_(safe_)?remote_[a-z]+\(\)' '$T/sev'"
t "Severity: no handler in it" sh -c "! grep -qi 'handler' '$T/sev'"
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

1. Make a scratch plugin in a new git repo, `acme-sync`, on `main`, whose first commit holds a
   `README.md` and the SDK as a dependency would arrive, with its own manifest:
   - `vendor/acme/sdk/composer.json`: `{ "name": "acme/sdk", "description": "Acme API client", "version": "1.0.0", "autoload": { "psr-4": { "Acme\\Sdk\\": "src/" } } }`.
   - `vendor/acme/sdk/src/Client.php`: a class `Acme\Sdk\Client` whose `send()` posts with
     `curl_init()` / `curl_exec()` and has no way to pass it a client or handler.
   - `vendor/acme/sdk/src/HandlerClient.php`: a class `Acme\Sdk\HandlerClient`, from the same
     package, that takes a Guzzle handler in its constructor and posts through it.

   The SDK is on `main` so that the review branch does not add it: it reads as a dependency, not as
   project code. Onboard the repo with this checkout's script (`<this checkout>/plugins/ada/bin/ada-onboard
   files`), and turn `ada@pie` off in its `.claude/settings.local.json` (`DEVELOPING.md`).
2. On a branch `add/acme-sync` off `main`, add these files and commit them (the branch does not
   touch `vendor/`):
   - `acme-sync.php`, a plugin file with one function for each case, numbered in comments:
     1. `wp_remote_get( 'https://api.example.com/v1/status' )`, a URL written in the code;
     2. `( new \GuzzleHttp\Client() )->post( 'https://api.example.com/v1/orders', array( 'json' => $order ) )`;
     3. `curl_init( 'https://api.example.com/v1/ping' )` then `curl_exec()`;
     4. `file_get_contents( get_option( 'acme_feed_url' ) )`, a URL from an admin setting;
     5. `wp_remote_post( get_option( 'acme_webhook_url' ), array( 'body' => $payload ) )`;
     6. `file_get_contents( __DIR__ . '/data/regions.json' )`, a file in the plugin;
     7. `( new \Acme\Sdk\Client( ACME_KEY ) )->send( $order )`;
     8. `( new \Acme\Sdk\HandlerClient( ACME_KEY ) )->send( $order )`, given no handler, so it
        uses its own curl.
   - `assets/acme.js`: `fetch( '/wp-json/acme/v1/status' )`.
3. Write `.claude/handoff/REVIEW-REQUEST.md` in the scratch repo, saying the branch adds
   `acme-sync.php` and `assets/acme.js` and does not change `vendor/acme/sdk`, which is a
   dependency already on `main`. Run `claude --plugin-dir <this checkout>/plugins/ada` in the
   scratch repo and ask for a Claudia review of the branch.
4. Add an S rule to the scratch project's `.claude/team/STANDARDS.md` that narrows P41 for the
   Acme SDK package (both classes): it sends with its own curl client; it bypasses local blocks,
   `pre_http_request` stubs and HTTP logging; it is kept from sending locally because `ACME_KEY`
   is not defined on local copies. Commit it and ask for the review again.

## Expected

- Part A: every line `PASS`, exit 0. The broken copy exits 1 and prints `FAIL` for every check on
  the SDK text: `the SDK text is a record in STANDARDS.md`, `recorded as an S rule that narrows
  P41`, `the record says what it bypasses`, `the record lists local blocks, pre_http_request
  stubs and HTTP logging`, `the record says how it's kept from sending locally`, `a handler is
  optional advice`, `a library recorded in STANDARDS.md is not a finding`, `... even if it could take a
  handler`, `... routing it through the HTTP API is a Should fix at most`, `the handler advice names PSR-18`, `... a Guzzle handler`, `... wp_remote_request()`,
  `... wp_safe_remote_request()` and `the safe variant is for a URL or host from outside the
  code` (other SDK lines, such as `names which SDK or library`, may FAIL too). They look only at
  the SDK text, so they FAIL when it is gone even though the rule's opening sentence still names
  `wp_remote_request()` and `wp_safe_remote_`, the third-party plugin paragraph still names
  `STANDARDS.md` and the Why still says "local copy". The Severity, no-step-order and no-handler-
  required checks still PASS in the broken copy.
- Part B, first review: P41 is cited as **Must Fix** for cases 2 (Guzzle), 3 (curl) and 4
  (`file_get_contents()` on a URL, to be `wp_safe_remote_get()` as the URL is an admin setting),
  for case 5 (to be `wp_safe_remote_post()`, the URL being an admin setting), and for cases 7 and
  8 (an SDK with its own client, not recorded in `STANDARDS.md`). For 7 the fix named is the
  record in `STANDARDS.md` (which SDK, what it bypasses, how it's kept from sending locally), not
  a code change, and the review does not ask for a handler (the SDK can't take one). For 8, given
  no handler, the fix named is that record too, and routing it through the HTTP API (giving it a
  handler) may be offered as an alternative, since a library given a handler no longer sends
  around the HTTP API and so needs no record. Case 8 fails the check only if the handler is
  presented as the sole fix, the record not offered. Where the review names a function for the
  handler, any `wp_remote_*()` function is accepted, or its `wp_safe_remote_*()` variant when the
  URL or host comes from outside the code; the test does not require `wp_remote_request()` by
  name. Cases 1 and 6 and `assets/acme.js` are not cited under P41, and no P41 finding is raised
  on the files under `vendor/acme/sdk`, which the branch does not change.
- Part B, second review: cases 7 and 8 are no longer P41 findings (the review may name the S
  rule); in particular case 8, though its SDK could take a handler, is not a Must fix, and routing
  it through the HTTP API (a handler, with any `wp_remote_*()` function) is at most a Should fix;
  cases 2 to 5 still are findings.

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
