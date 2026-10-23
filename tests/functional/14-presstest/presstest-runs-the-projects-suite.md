# `presstest` runs the project's own suite against the local site or the project's staging site, and nowhere else

- Area: 14 PressTest runs (`presstest`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/1210681019800745/task/1219403227572186
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 23 Oct 2026, 59 PASS, 0 failed under bash 5 and Docker bash 3.2; broken copy fails as it should. Real run on vandme.local the same day: product_type 3/3, failed_payment 1/1, whole suite 16 of 18 (no test customer set; the evening slot test expects 6-8PM, the site says 4-6PM)

## Precondition

- This checkout (`REPO`). The script makes its own scratch repos, a stand-in presstest.io clone
  and a stand-in `docker` under a temporary folder. It never runs Docker or touches a real site.
- `git`, `mktemp`. Nothing else.
- Run the checkout's script by path, never the `presstest` on the PATH (that is the installed
  plugin).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run it under a current bash: `REPO=<this checkout> bash check.sh`, and under bash 3.2:
   `docker run --rm -v <this checkout>:/repo -v <scratch folder>:/c bash:3.2 bash -c 'apk add -q git >/dev/null; REPO=/repo bash /c/check.sh'`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, change `(local|test)` in
   the copy's `bin/presstest` to `(local|test|com)`, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` lines.

```bash
#!/usr/bin/env bash
# presstest: suite and staging site from PROJECT.md, settings from local.env, local or staging
# only (never production, never local values on staging), the container pointed at the site, the
# result line and exit status. Prints PASS/FAIL per check; exits 1 if
# any FAIL. Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada).
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
mkrepo() {
  mkdir -p "$1/.claude/team" && git -C "$1" init -q
  printf '# P\n\n## Tests\n\n- **Unit tests:** x\n- **PressTest suite:** %s\n- **PressTest staging:** %s\n' "$2" "${3:-none}" > "$1/.claude/team/PROJECT.md"
}

# A stand-in presstest.io clone: local-test.sh records its arguments and environment and writes
# a report with STUB_PASSED passed and STUB_FAILED failed.
C=$W/clone; mkdir -p "$C/all-tests/shop/shared" "$C/playwright"
: > "$C/all-tests/shop/checkout_test.js"; : > "$C/all-tests/shop/account_test.js"; : > "$C/all-tests/shop/shared/helpers.js"
cat > "$C/local-test.sh" <<'EOF'
printf '%s\n' "$@" > "$STUB_LOG/args"
printf 'customer=%s/%s\n' "${CUSTOMER_USERNAME:-}" "${CUSTOMER_PASSWORD:-}" > "$STUB_LOG/env"
d="$(dirname "$0")/playwright/test-results/local-run"; mkdir -p "$d"
printf '{"suites":[{"status":"expected"}],"stats":{"duration":1,"expected":%s,"skipped":1,"unexpected":%s,"flaky":0}}\n' "${STUB_PASSED:-2}" "${STUB_FAILED:-0}" > "$d/results.json"
EOF
# A stand-in docker: the container maps a domain in /etc/hosts only after compose has started it
# with one, though its DNS resolves anything.
mkdir -p "$W/bin" "$W/log"
cat > "$W/bin/docker" <<'EOF'
#!/usr/bin/env bash
echo "docker $*" >> "$STUB_LOG/docker"
case "$1 $2" in
  "info "*) exit 0 ;;
  "compose "*) echo "LOCAL_SITE_DOMAIN=${LOCAL_SITE_DOMAIN:-}" >> "$STUB_LOG/docker"; echo "${LOCAL_SITE_DOMAIN:-}" > "$STUB_LOG/mapped"; exit 0 ;;
esac
if [ "$1" = exec ]; then
  case "$*" in
    *getent*) exit 0 ;;  # Docker's DNS may resolve a Local domain to ::1; that must not count
    */etc/hosts*) [ -f "$STUB_LOG/mapped" ] && case "$*" in *"d=$(cat "$STUB_LOG/mapped") "*) exit 0 ;; esac; exit 1 ;;
    *node*) exit 0 ;;
  esac
fi
exit 0
EOF
chmod +x "$W/bin/docker"
export STUB_LOG=$W/log

# 1. The suite comes from PROJECT.md: a code span, a plain word, none, or the template's prompt.
R=$W/r1; mkrepo "$R" '`shop`, in `pie/presstest.io` under `all-tests/shop/`' '`https://staging.shop.example.com` (WP Engine)'
run "$R" config
t "config exits 0" test "$(rc)" = 0
t "a code span names the suite" grep -qx 'suite: *shop' "$W/out"
R2=$W/r2; mkrepo "$R2" 'shop (front end)'; run "$R2" config
t "a plain first word names the suite" grep -qx 'suite: *shop' "$W/out"
R3=$W/r3; mkrepo "$R3" 'none'; run "$R3" run
t "a project with none is refused with exit 2" test "$(rc)" = 2
t "and told it has no suite" grep -q 'no PressTest suite' "$W/err"
R4=$W/r4; mkrepo "$R4" x
grep -F -e '- **PressTest suite:**' -e '- **PressTest staging:**' "$PLUGIN/templates/repo/.claude/team/PROJECT.md" > "$R4/.claude/team/PROJECT.md"
run "$R4" config
t "the template has both PressTest lines" test "$(wc -l < "$R4/.claude/team/PROJECT.md")" -eq 2
t "the template's unfilled prompt counts as no suite" grep -qx 'suite: *(none)' "$W/out"
t "the template's unfilled prompt counts as no staging site" grep -qx 'staging: *(none)' "$W/out"

# 2. Settings come from local.env, parsed not executed; other keys and stray lines are ignored;
#    a # inside a value is kept; the environment overrides; secrets are never printed.
cat > "$R/.claude/team/local.env" <<EOF
# my site
LOCAL_URL=https://shop.local
LOCAL_WP="php /x/wp-cli.phar --path=/x"
touch $W/executed
PRESSTEST_DIR=$C
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
(cd "$R" && LOCAL_URL=http://other.test PATH="$W/bin:$PATH" bash "$PT" config) > "$W/out" 2>&1
t "an unfilled <example> counts as unset" grep -qx '  login: *(not set)' <(sed -n '/^staging/,$p' "$W/out")
t "the environment overrides local.env" grep -qx 'local: *http://other.test' "$W/out"

# 3. list shows the suite's test files only.
run "$R" list
t "list exits 0" test "$(rc)" = 0
t "list shows the test files" test "$(cat "$W/out")" = "$(printf 'account_test.js\ncheckout_test.js')"

# 4. Only a local site: anything else is refused before Docker or local-test.sh runs.
for u in https://shop.example.com http://shop.local.example.com ftp://shop.local; do
  rm -f "$W/log/"*
  (cd "$R" && LOCAL_URL=$u PATH="$W/bin:$PATH" bash "$PT" run) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"
  t "$u is refused with exit 2" test "$(rc)" = 2
  t "$u: nothing ran" not test -e "$W/log/args"
done
run "$R" run ../../etc/x_test.js
t "a path outside the suite is refused" test "$(rc)" = 2
run "$R" run missing_test.js
t "a file not in the suite is refused" test "$(rc)" = 2
run "$R" run -b netscape
t "an unknown browser is refused" test "$(rc)" = 2

# 5. A run points the container at the site, passes the settings, and reports.
rm -f "$W/log/"*
run "$R" run
t "run exits 0 when everything passes" test "$(rc)" = 0
t "the container is started with the site's domain" grep -qx 'LOCAL_SITE_DOMAIN=shop.local' "$W/log/docker"
t "only the playwright container, rebuilt, with the local compose file" grep -q 'compose -f docker-compose.yml -f docker-compose.local.yml up -d --build --no-deps playwright' "$W/log/docker"
t "test-results exists before Docker could make it" test -d "$C/playwright/test-results"
t "local-test.sh gets the site" grep -qx 'https://shop.local' "$W/log/args"
t "local-test.sh gets the whole suite" test "$(sed -n '4p' "$W/log/args")" = shop
t "local-test.sh gets the secret, # and all" grep -qx 's3cr#t' "$W/log/args"
t "the customer login reaches local-test.sh" grep -qx 'customer=cust@example.com/pa ss#1' "$W/log/env"
t "the last line says passed 2" test "$(tail -1 "$W/out")" = 'presstest: passed 2'
rm -f "$W/log/docker"
run "$R" run -b firefox checkout_test.js
t "a second run doesn't restart the container" not grep -q compose "$W/log/docker"
t "a named file runs on its own" test "$(sed -n '4p' "$W/log/args")" = shop/checkout_test.js
t "the browser is passed on" test "$(sed -n '6p' "$W/log/args")" = firefox
STUB_FAILED=1 run "$R" run
t "a failing test exits 1" test "$(rc)" = 1
t "the last line says failed 1 of 4" test "$(tail -1 "$W/out")" = 'presstest: failed 1 of 4'

# 6. Staging: the project's URL and the staging settings, never the local ones; the container is
#    only started if it isn't running; production and a project without staging are refused.
rm -f "$W/log/"*
run "$R" run --env staging checkout_test.js
t "a staging run exits 0" test "$(rc)" = 0
t "it says it is running on staging" grep -qx 'presstest: staging, https://staging.shop.example.com' "$W/out"
t "local-test.sh gets the staging site" grep -qx 'https://staging.shop.example.com' "$W/log/args"
t "local-test.sh gets the staging secret, without its comment" grep -qx 'stg-token' "$W/log/args"
t "an unfilled <example> is not sent" not grep -q '<admin' "$W/log/args"
t "the local secret is not sent to staging" not grep -q 's3cr' "$W/log/args"
t "the staging customer is passed, not the local one" grep -qx 'customer=stg-cust@example.com/stg-pass' "$W/log/env"
t "a running container is left alone" not grep -q compose "$W/log/docker"
for e in production live ''; do
  rm -f "$W/log/"*
  run "$R" run --env "$e"
  t "--env '$e' is refused with exit 2" test "$(rc)" = 2
  t "--env '$e': nothing ran" not test -e "$W/log/args"
done
cp "$R/.claude/team/local.env" "$R2/.claude/team/local.env"
run "$R2" run --env staging
t "a project with staging none is refused with exit 2" test "$(rc)" = 2
t "and told it has no staging site" grep -q 'no staging site' "$W/err"
R5=$W/r5; mkrepo "$R5" '`shop`' '`https://shop.local`'
cp "$R/.claude/team/local.env" "$R5/.claude/team/local.env"
run "$R5" run --env staging
t "a local URL on the staging line is refused" test "$(rc)" = 2
t "and told why" grep -q 'not a local one' "$W/err"

# 7. A clone without the suite, or no clone, is a settings error.
(cd "$R" && PRESSTEST_DIR=$W/nowhere PATH="$W/bin:$PATH" bash "$PT" run) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"
t "a missing clone is refused with exit 2" test "$(rc)" = 2

echo
echo "$fails failed"
rm -rf "$W"
[ "$fails" = 0 ]
```

## Expected

Every line `PASS`, `0 failed`, exit 0, under both bashes. With the broken copy, the
`https://shop.example.com is refused` checks print `FAIL`.

## Observe at

The script's output and exit code.

## Regression

The Tess run step (`agents/tess.md`, Run mode step 2) and `presstest config` in a real onboarded
repo with a running Local site, and `presstest run --env staging` on a project with a staging line:
`presstest run` ends with `presstest: passed <n>` or
`presstest: failed <n> of <total>`.
