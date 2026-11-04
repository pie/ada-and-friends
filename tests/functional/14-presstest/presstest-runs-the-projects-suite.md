# `presstest` runs the project's own tests in the runner image against the local site or the project's staging site, and nowhere else

- Area: 14 PressTest runs (`presstest`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/1204538801343792/task/1220130039368040
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 4 Nov 2026, 114 PASS, 0 failed under bash 5 (with node); under Docker bash 3.2 all PASS but the Basic auth checks, which SKIP there (no node); the broken copy fails as it should. Real runs the same day on annamason.local and pds-printing.local (companion 1.3.0): a session started and ended, wordpress-core 5 passed, 1 skipped, its 3 test users deleted and 1 email captured; and on example.com as staging

## Precondition

- This checkout (`REPO`). The script makes its own scratch repos, a stand-in `docker` and a
  stand-in `curl` under a temporary folder. It never runs Docker or touches a real site.
- `git`, `mktemp`, `jq`, Python 3 (for `ada-onboard`). `node`, if installed, for the Basic auth checks; without it they are skipped.
- Run the checkout's script by path, never the `presstest` on the PATH (that is the installed
  plugin).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run it under a current bash: `REPO=<this checkout> bash check.sh`, and under bash 3.2:
   `docker run --rm -v <this checkout>:/repo -v <scratch folder>:/c bash:3.2 bash -c 'apk add -q git jq python3 >/dev/null; REPO=/repo bash /c/check.sh'`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, change `(local|test)` in
   the copy's `bin/presstest` to `(local|test|com)`, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` lines.

```bash
#!/usr/bin/env bash
# presstest: image version, shared suites and staging site from PROJECT.md, settings from
# local.env, local or staging only (never production, never local values on staging), the
# project's tests mounted into the image, the test session started and always ended, the result
# line and exit status. Prints PASS/FAIL per check; exits 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
PT=$PLUGIN/bin/presstest
W=$(mktemp -d)
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
run() { d=$1; shift; (cd "$d" && PATH="$W/bin:$PATH" bash "$PT" "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc() { cat "$W/rc"; }
clear() { rm -rf "$W/log"; mkdir -p "$W/log"; }
# The arguments after the image name: what playwright is asked to run, one per line.
targets() { sed -n '/^playwright$/,$p' "$W/log/args" | sed -n '3,$p' | tr '\n' ' '; }
# PROJECT.md with the image line $2, the shared suites line $3 and the staging line $4.
mkrepo() {
  mkdir -p "$1/.claude/team" "$1/tests/presstest/shared" && git -C "$1" init -q
  printf '# P\n\n## Tests\n\n- **Unit tests:** x\n- **PressTest image:** %s\n- **PressTest shared suites:** %s\n- **PressTest staging:** %s\n' \
    "$2" "${3:-none}" "${4:-none}" > "$1/.claude/team/PROJECT.md"
  : > "$1/tests/presstest/checkout_test.js"; : > "$1/tests/presstest/account_test.js"; : > "$1/tests/presstest/shared/helpers.js"
}

# A stand-in docker. `image inspect` finds the image when STUB_IMAGE is set; `pull` exits
# STUB_PULL_RC. A `run` of node is the pre-flight check; a `run` of playwright test records its
# arguments, the values of the variables it passes in with -e NAME, and writes a report with
# STUB_PASSED passed and STUB_FAILED failed into the mounted results folder, then exits
# STUB_PLAYWRIGHT_RC.
mkdir -p "$W/bin" "$W/log"
cat > "$W/bin/docker" <<'EOF'
#!/usr/bin/env bash
echo "docker $*" >> "$STUB_LOG/docker"
case "$1" in
  info) exit 0 ;;
  image) [ -n "${STUB_IMAGE:-}" ]; exit $? ;;
  pull) echo pulled >> "$STUB_LOG/pull"; exit "${STUB_PULL_RC:-0}" ;;
  run) ;;
  *) exit 0 ;;
esac
case "$*" in
  *" node -e "*)
    echo "$*" > "$STUB_LOG/preflight"
    # With STUB_STATUS set and node here, run the real check with a stand-in fetch that answers
    # STUB_STATUS and the WWW-Authenticate header STUB_AUTH. Otherwise answer STUB_NODE_RC
    # (3: the site asks for a Basic auth password; 1: no answer).
    if [ -n "${STUB_STATUS:-}" ] && command -v node >/dev/null 2>&1; then
      js='' url=''; for a in "$@"; do js=$url; url=$a; done
      node -e 'globalThis.fetch = async () => ({status: Number(process.env.STUB_STATUS), headers: {get: n => (n.toLowerCase() === "www-authenticate" && process.env.STUB_AUTH) || null}});'"$js" "$url"
      exit $?
    fi
    exit "${STUB_NODE_RC:-0}" ;;
esac
printf '%s\n' "$@" > "$STUB_LOG/args"
results='' prev=''
for a in "$@"; do
  if [ "$prev" = -e ]; then
    case "$a" in *=*) ;; *) printf '%s=%s\n' "$a" "${!a:-}" >> "$STUB_LOG/env" ;; esac
  fi
  if [ "$prev" = -v ]; then
    case "$a" in *:/app/test-results) results=${a%:/app/test-results} ;; esac
  fi
  prev=$a
done
[ -n "${STUB_NO_REPORT:-}" ] && exit 1
mkdir -p "$results/run"
printf '{"suites":[{"status":"expected"}],"stats":{"duration":1,"expected":%s,"skipped":1,"unexpected":%s,"flaky":0}}\n' "${STUB_PASSED:-2}" "${STUB_FAILED:-0}" > "$results/run/results.json"
[ -n "${PRESSTEST_STORAGE_STATE:-}" ] && : > "$results/run/session-state.json"
exit "${STUB_PLAYWRIGHT_RC:-0}"
EOF
# A stand-in curl: a POST starts session 7 (or answers STUB_SESSION_CODE), a DELETE ends it. It
# records each call's method and URL, its arguments, and the headers it was given in a file.
cat > "$W/bin/curl" <<'EOF'
#!/usr/bin/env bash
echo "curl $*" >> "$STUB_LOG/curl-args"
method='' url='' prev=''
for a in "$@"; do
  [ "$prev" = -X ] && method=$a
  [ "$prev" = -H ] && case "$a" in @*) cat "${a#@}" >> "$STUB_LOG/curl-headers" ;; esac
  case "$a" in http*) url=$a ;; esac
  prev=$a
done
echo "$method $url" >> "$STUB_LOG/curl"
[ -n "${STUB_CURL_DOWN:-}" ] && { echo 'curl: (7) Failed to connect' >&2; exit 7; }
if [ "$method" = POST ]; then
  code=${STUB_SESSION_CODE:-201}
  if [ "$code" = 201 ]; then printf '{"id":7,"cookie":{"value":"cookie-v"}}\n201'; else printf '{"message":"Test data is off"}\n%s' "$code"; fi
else
  printf '{"summary":{"deleted":{"user":1}}}'
fi
EOF
chmod +x "$W/bin/docker" "$W/bin/curl"
export STUB_LOG=$W/log STUB_IMAGE=1

# 1. The image version and the shared suites come from PROJECT.md: code spans, words, none, or
#    the template's prompt.
R=$W/r1; mkrepo "$R" '`1.0.0`, ghcr.io/pie/presstest-runner' '`wordpress-core`, `woocommerce-core`' '`https://staging.shop.example.com` (WP Engine)'
run "$R" config
t "config exits 0" test "$(rc)" = 0
t "a code span names the image version" grep -qx 'image: *ghcr.io/pie/presstest-runner:1.0.0' "$W/out"
t "every code span names a shared suite" grep -qx 'shared: *wordpress-core woocommerce-core' "$W/out"
t "config counts the project's tests" grep -q '(2 files)' "$W/out"
R2=$W/r2; mkrepo "$R2" '1.2.3 (pinned)' 'wordpress-core, woocommerce-core'; run "$R2" config
t "a plain first word names the image version" grep -qx 'image: *ghcr.io/pie/presstest-runner:1.2.3' "$W/out"
t "plain words name the shared suites" grep -qx 'shared: *wordpress-core woocommerce-core' "$W/out"
R3=$W/r3; mkrepo "$R3" 'none'; run "$R3" run
t "a project with image none is refused with exit 2" test "$(rc)" = 2
t "and told it has no image version" grep -q 'no PressTest image version' "$W/err"
R4=$W/r4; mkrepo "$R4" x
grep -F -e '- **PressTest image:**' -e '- **PressTest shared suites:**' -e '- **PressTest staging:**' "$PLUGIN/templates/repo/.claude/team/PROJECT.md" > "$R4/.claude/team/PROJECT.md"
run "$R4" config
t "the template has the three PressTest lines" test "$(wc -l < "$R4/.claude/team/PROJECT.md")" -eq 3
t "the template's unfilled prompt counts as no image" grep -qx 'image: *(none)' "$W/out"
t "the template's unfilled prompt counts as no shared suites" grep -qx 'shared: *(none)' "$W/out"
t "the template's unfilled prompt counts as no staging site" grep -qx 'staging: *(none)' "$W/out"
R6=$W/r6; mkrepo "$R6" '`latest`'; run "$R6" config
t "an image that isn't a version is refused" test "$(rc)" = 2
R7=$W/r7; mkrepo "$R7" '`1.0.0`' '`../etc`'; run "$R7" config
t "a shared suite that isn't a name is refused" test "$(rc)" = 2
R8=$W/r8; mkrepo "$R8" '`1.0.0`' '`project`'; run "$R8" config
t "a shared suite can't take the project's own folder" test "$(rc)" = 2

# 2. Settings come from local.env, parsed not executed; other keys and stray lines are ignored;
#    a # inside a value is kept; the environment overrides; secrets are never printed.
cat > "$R/.claude/team/local.env" <<EOF
# my site
LOCAL_URL=https://shop.local
LOCAL_WP="php /x/wp-cli.phar --path=/x"
touch $W/executed
PRESSTEST_SECRET='s3cr#t'
PRESSTEST_CUSTOMER_USERNAME=cust@example.com
PRESSTEST_CUSTOMER_PASSWORD="pa ss#1"
PRESSTEST_STAGING_SECRET=stg-token   # from vmestg
PRESSTEST_STAGING_USERNAME=<admin login, optional>
PRESSTEST_STAGING_CUSTOMER_USERNAME=stg-cust@example.com
PRESSTEST_STAGING_CUSTOMER_PASSWORD=stg-pass
EOF
run "$R" config
t "local.env is not executed" not test -e "$W/executed"
t "config shows the local site" grep -qx 'local: *https://shop.local' "$W/out"
t "config shows the staging site" grep -qx 'staging: *https://staging.shop.example.com' "$W/out"
t "config shows the secret as (set)" grep -qx '  secret: *(set)' "$W/out"
t "config never prints a secret" not grep -q 's3cr\|pa ss\|stg-' "$W/out"
(cd "$R" && LOCAL_URL=http://other.test PRESSTEST_SHARED_SUITES='' PATH="$W/bin:$PATH" bash "$PT" config) > "$W/out" 2>&1
t "an unfilled <example> counts as unset" grep -qx '  login: *(not set)' <(sed -n '/^staging/,$p' "$W/out")
t "the environment overrides local.env" grep -qx 'local: *http://other.test' "$W/out"
t "the environment can empty the shared suites" grep -qx 'shared: *(none)' "$W/out"

# 3. list shows the project's test files only.
run "$R" list
t "list exits 0" test "$(rc)" = 0
t "list shows the test files" test "$(cat "$W/out")" = "$(printf 'account_test.js\ncheckout_test.js')"

# 4. Only a local site: anything else is refused before Docker runs.
for u in https://shop.example.com http://shop.local.example.com ftp://shop.local; do
  clear
  (cd "$R" && LOCAL_URL=$u PATH="$W/bin:$PATH" bash "$PT" run) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"
  t "$u is refused with exit 2" test "$(rc)" = 2
  t "$u: nothing ran" not test -e "$W/log/docker"
done
run "$R" run ../../etc/x_test.js
t "a path outside the tests is refused" test "$(rc)" = 2
run "$R" run missing_test.js
t "a file not in tests/presstest is refused" test "$(rc)" = 2
run "$R" run -b netscape
t "an unknown browser is refused" test "$(rc)" = 2

# 5. A run mounts the project's tests into the image, maps the site, passes the settings through
#    the environment, and reports.
clear
run "$R" run
t "run exits 0 when everything passes" test "$(rc)" = 0
t "an image already here isn't pulled" not test -e "$W/log/pull"
t "the pre-flight check maps the site's domain to the host" grep -q -- '--add-host shop.local:host-gateway' "$W/log/preflight"
t "the tests run in the pinned image" grep -qx 'ghcr.io/pie/presstest-runner:1.0.0' "$W/log/args"
t "the domain is mapped for the tests" grep -qx 'shop.local:host-gateway' "$W/log/args"
t "the tests run as this user" grep -qx "$(id -u):$(id -g)" "$W/log/args"
t "the project's tests are mounted read-only" grep -qx "$R/tests/presstest:/all-tests/project:ro" "$W/log/args"
t "the results go to .claude/presstest" grep -qx "$R/.claude/presstest:/app/test-results" "$W/log/args"
t "the project's tests and the shared suites run, on chromium" test "$(targets)" = 'project wordpress-core woocommerce-core --project=chromium '
t "the site reaches the tests" grep -qx 'URL=https://shop.local' "$W/log/env"
t "the customer login reaches the tests, # and all" grep -qx 'CUSTOMER_PASSWORD=pa ss#1' "$W/log/env"
t "the self-signed certificate is accepted on a local site" grep -qx 'IGNORE_HTTPS_ERRORS=1' "$W/log/env"
t "no secret is on docker's command line" not grep -q 's3cr\|pa ss\|cookie-v' "$W/log/docker"
t "the last line says passed 2" test "$(tail -1 "$W/out")" = 'presstest: passed 2'
clear
run "$R" run -b firefox checkout_test.js
t "a named file runs on its own, without the shared suites" test "$(targets)" = 'project/checkout_test.js --project=firefox '
STUB_FAILED=1 run "$R" run
t "a failing test exits 1" test "$(rc)" = 1
t "the last line says failed 1 of 4" grep -q '^presstest: failed 1 of 4' <(tail -1 "$W/out")
STUB_NO_REPORT=1 run "$R" run
t "no report exits 1" test "$(rc)" = 1
t "and says so" test "$(tail -1 "$W/out")" = 'presstest: failed, no report written'
STUB_PASSED=0 STUB_PLAYWRIGHT_RC=1 run "$R" run
t "Playwright failing with no test failed (no tests found) exits 1" test "$(rc)" = 1
t "and says so" test "$(tail -1 "$W/out")" = 'presstest: failed, Playwright exited 1 with no test failed (see above)'
STUB_PASSED=0 run "$R" run
t "a run where no test passed exits 1" test "$(rc)" = 1
t "and says no test ran" test "$(tail -1 "$W/out")" = 'presstest: failed, no test ran (see above)'
clear
STUB_IMAGE='' run "$R" run
t "a missing image is pulled" test -e "$W/log/pull"
STUB_IMAGE='' STUB_PULL_RC=1 run "$R" run
t "an image that can't be pulled is refused with exit 2" test "$(rc)" = 2
t "and told how to log in" grep -q 'docker login ghcr.io' "$W/err"
R9=$W/r9; mkrepo "$R9" '`1.0.0`'; rm "$R9/tests/presstest/"*_test.js; cp "$R/.claude/team/local.env" "$R9/.claude/team/"
run "$R9" run
t "nothing to run is refused with exit 2" test "$(rc)" = 2

# 6. The test session: started with the token, which is never on a command line; always ended,
#    even when a test fails; its cookie file deleted; none without a token.
clear
run "$R" run
t "the session is started on the site" grep -qx 'POST https://shop.local/wp-json/presstest-companion/v1/sessions' "$W/log/curl"
t "with the token, # and all" grep -qx 'X-Presstest-Token: s3cr#t' "$W/log/curl-headers"
t "the token is never on curl's command line" not grep -q 's3cr' "$W/log/curl-args"
t "the session reaches the tests" grep -qx 'PRESSTEST_SESSION_ID=7' "$W/log/env"
t "so does its cookie" grep -qx 'PRESSTEST_SESSION_CREDENTIALS=cookie-v' "$W/log/env"
t "the session is ended" grep -qx 'DELETE https://shop.local/wp-json/presstest-companion/v1/sessions/7' "$W/log/curl"
t "and what it removed is printed" grep -q '^presstest: ended test session 7: {"deleted":{"user":1}}' "$W/out"
t "the session's cookie file is deleted" not test -e "$R/.claude/presstest/run/session-state.json"
clear
STUB_FAILED=1 run "$R" run
t "the session is ended when a test fails" grep -q '^DELETE ' "$W/log/curl"
clear
STUB_NO_REPORT=1 run "$R" run
t "the session is ended when no report is written" grep -q '^DELETE ' "$W/log/curl"
clear
STUB_SESSION_CODE=403 run "$R" run
t "a refused session doesn't stop the run" test "$(rc)" = 0
t "it says why" grep -q 'test session not started (HTTP 403): Test data is off' "$W/err"
t "and nothing is ended" not grep -q '^DELETE ' "$W/log/curl"
t "and no session reaches the tests" grep -qx 'PRESSTEST_SESSION_ID=' "$W/log/env"
clear
STUB_CURL_DOWN=1 run "$R" run
t "a site curl can't reach doesn't stop the run" test "$(rc)" = 0
R10=$W/r10; mkrepo "$R10" '`1.0.0`'; echo 'LOCAL_URL=https://shop.local' > "$R10/.claude/team/local.env"
clear
run "$R10" run
t "without a token, no session is started" not test -e "$W/log/curl"
t "and the tests still run" test "$(rc)" = 0

# 7. Staging: the project's URL and the staging settings, never the local ones; production and a
#    project without staging are refused.
clear
run "$R" run --env staging checkout_test.js
t "a staging run exits 0" test "$(rc)" = 0
t "it says it is running on staging" grep -q '^presstest: staging, https://staging.shop.example.com' "$W/out"
t "the tests get the staging site" grep -qx 'URL=https://staging.shop.example.com' "$W/log/env"
t "no domain is mapped for staging" not grep -q host-gateway "$W/log/args"
t "the certificate is checked on staging" grep -qx 'IGNORE_HTTPS_ERRORS=' "$W/log/env"
t "the session uses the staging token, without its comment" grep -qx 'X-Presstest-Token: stg-token' "$W/log/curl-headers"
t "the local token is not sent to staging" not grep -q 's3cr' "$W/log/curl-headers"
t "an unfilled <example> is not sent" grep -qx 'USERNAME=' "$W/log/env"
t "the staging customer is passed, not the local one" grep -qx 'CUSTOMER_USERNAME=stg-cust@example.com' "$W/log/env"
for c in 3 1; do
  clear
  STUB_NODE_RC=$c run "$R" run --env staging
  t "a site that answers $c is refused with exit 2" test "$(rc)" = 2
  t "a site that answers $c: nothing ran" not test -e "$W/log/args"
  t "a site that answers $c: no session started" not test -e "$W/log/curl"
done
STUB_NODE_RC=3 run "$R" run --env staging
t "a password-protected site is named as such" grep -q 'password-protected (Basic auth)' "$W/err"
STUB_NODE_RC=1 run "$R" run --env staging
t "a site with no answer is told apart from one with a password" not grep -q 'password-protected' "$W/err"
# The check itself, run by node with a stand-in fetch: only a Basic auth challenge stops the run.
if command -v node >/dev/null 2>&1; then
  for c in '401|Basic realm="x"' '401|Digest realm="a", Basic realm="b"'; do
    clear
    STUB_STATUS=${c%%|*} STUB_AUTH=${c#*|} run "$R" run --env staging
    t "$c is refused as password-protected" grep -q 'password-protected' "$W/err"
    t "$c: nothing ran" not test -e "$W/log/args"
  done
  for c in '401|Bearer realm="x"' '401|' '403|Basic realm="x"' '200|'; do
    clear
    STUB_STATUS=${c%%|*} STUB_AUTH=${c#*|} run "$R" run --env staging
    t "$c is not taken for a password: the tests run" test -e "$W/log/args"
  done
else
  echo "SKIP the Basic auth check itself (no node here)"
fi
for e in production live ''; do
  clear
  run "$R" run --env "$e"
  t "--env '$e' is refused with exit 2" test "$(rc)" = 2
  t "--env '$e': nothing ran" not test -e "$W/log/docker"
done
cp "$R/.claude/team/local.env" "$R2/.claude/team/local.env"
run "$R2" run --env staging
t "a project with staging none is refused with exit 2" test "$(rc)" = 2
t "and told it has no staging site" grep -q 'no staging site' "$W/err"
R5=$W/r5; mkrepo "$R5" '`1.0.0`' none '`https://shop.local`'
cp "$R/.claude/team/local.env" "$R5/.claude/team/local.env"
run "$R5" run --env staging
t "a local URL on the staging line is refused" test "$(rc)" = 2
t "and told why" grep -q 'not a local one' "$W/err"

# 8. The results are git-ignored by onboarding.
t "ada-onboard ignores .claude/presstest/" grep -qx '.claude/presstest/' <(cd "$R" && bash "$PLUGIN/bin/ada-onboard" gitignore --list 2>/dev/null)

echo
echo "$fails failed"
rm -rf "$W"
[ "$fails" = 0 ]
```

## Expected

Every line `PASS`, `0 failed`, exit 0, under both bashes. Where node isn't installed (the
`bash:3.2` image), the Basic auth checks print one `SKIP` line instead. With the broken copy, the
`https://shop.example.com is refused` checks print `FAIL`.

## Observe at

The script's output and exit code.

## Regression

The Tess run step (`agents/tess.md`, Run mode step 2) and `presstest config` in a real onboarded
repo with a running Local site with the Presstest Companion plugin (1.3.0 or later), and
`presstest run --env staging` on a project with a staging line: `presstest run` starts and ends
a test session (`presstest: ended test session <id>`) and ends with `presstest: passed <n>` or
`presstest: failed <n> of <total>`.
