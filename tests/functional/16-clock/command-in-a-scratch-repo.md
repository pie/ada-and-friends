# `ada-clock` works only on a local site, takes its settings from `local.env`, records one fake time, prints the manual steps, and installs only its own mu-plugin

- Area: 16 Clock (`ada-clock`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219974873020681
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-10-28, 224 PASS, 0 FAIL under bash 5.2 and bash 3.2 (see the PR)

## Precondition

- This checkout (`REPO`). The script makes its own scratch repos (paths with spaces), a stand-in
  WP-CLI, a stand-in site that answers `GET /wp-json/ada-clock/v1/time` and `GET /?rest_route=/ada-clock/v1/time`, and stand-ins for every
  command that could change the computer's clock (`sudo`, `timedatectl`, `systemsetup`, `date -s`).
  It never touches a real site and never changes the clock.
- `git`, `mktemp`. `python3` (the stand-in site and reading the state file), `php` (the stand-in
  `wp eval`), `curl` if `ada-clock` uses it. Checks that need one that is missing print `SKIP`.
- Run the checkout's script by path, never an `ada-clock` on the PATH (that is the installed
  plugin).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run it under a current bash: `REPO=<this checkout> bash check.sh`, and under bash 3.2:
   `docker run --rm -v <this checkout>:/repo:ro -v <scratch folder>:/c bash:3.2 bash -c 'apk add -q git python3 curl php >/dev/null; REPO=/repo bash /c/check.sh'`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, make the copy's
   `bin/ada-clock` accept any host in its local-URL test (or drop `.test` from it), and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` lines.

```bash
#!/usr/bin/env bash
# ada-clock in scratch repos, with stand-ins for WP-CLI, the site's time endpoint and every
# command that could change the computer's clock: usage; the local-URL refusal; settings from
# local.env; the setup name; wp; <when> parsing and the state file; the manual steps; status and
# reset; install's copy, overwrite and exclude rules. Never touches a real site or the clock.
# Prints PASS/FAIL/SKIP per check; exits 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
AC=$PLUGIN/bin/ada-clock
TPL=$PLUGIN/templates/clock/ada-clock.php
unset LOCAL_URL LOCAL_WP_PATH LOCAL_WP ADA_CLOCK_ADAPTER STUB_UNAME STUB_TZ
W=$(mktemp -d)
fails=0
ok()   { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()    { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not()  { ! "$@"; }
have() { command -v "$1" >/dev/null 2>&1; }
skip() { echo "SKIP $1"; }
# run <dir> <args...> (its variable is rd: a loop that calls run keeps its own d): the checkout's ada-clock from <dir>, the stand-ins first on the PATH.
run()  { rd=$1; shift; rm -f "$W/log/"*; (cd "$rd" && PATH="$W/bin:$PATH" bash "$AC" "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()   { cat "$W/rc"; }
says() { cat "$W/out" "$W/err" | grep "$@" >/dev/null; }
first_re() { head -1 "$W/out" | grep -qE "$1"; }
err_ac()   { grep -q '^ada-clock: ' "$W/err"; }
# lt <timestamp> <format>: the time in the computer's timezone (TZ as the caller has it).
lt() { date -d "@$1" "+$2" 2>/dev/null || date -r "$1" "+$2"; }
# jstate <file> <timestamp> <local> <timezone>: the state file is a JSON object with exactly the
# keys timestamp and set_at (integers), and local, timezone and setup (strings): timestamp the UTC
# time, local the time as the person gave it (the site-local text, or the ISO time without its
# offset), timezone the site's timezone or the ISO offset, setup "manual" (API.md, `ada-clock`).
jstate() {
  python3 - "$@" <<'PY'
import json, sys
f, ts, local, tz = sys.argv[1], int(sys.argv[2]), sys.argv[3], sys.argv[4]
d = json.load(open(f))
bad = []
if not isinstance(d, dict):
    bad.append('not an object')
else:
    if set(d) != {'timestamp', 'local', 'timezone', 'setup', 'set_at'}:
        bad.append('keys %s' % sorted(d))
    for k in ('timestamp', 'set_at'):
        if type(d.get(k)) is not int:
            bad.append('%s is not an integer: %r' % (k, d.get(k)))
    for k in ('local', 'timezone', 'setup'):
        if type(d.get(k)) is not str:
            bad.append('%s is not a string: %r' % (k, d.get(k)))
    for k, want in (('timestamp', ts), ('local', local), ('timezone', tz), ('setup', 'manual')):
        if d.get(k) != want:
            bad.append('%s is %r, want %r' % (k, d.get(k), want))
if bad:
    print('; '.join(bad), file=sys.stderr)
sys.exit(1 if bad else 0)
PY
}
# steps <set|reset> <os> [<timestamp>]: the manual steps for that OS (brief, Decisions, manual),
# with the time in the computer's timezone.
steps() {
  case "$2:$1" in
    Linux:set)    says -F 'timedatectl set-ntp false' && grep -hF 'date -s' "$W/out" "$W/err" | grep -qF "$(lt "$3" '%Y-%m-%d %H:%M')" ;;
    Linux:reset)  says -F 'timedatectl set-ntp true' ;;
    Darwin:set)   says -F 'Set time and date automatically' && says -F "date $(lt "$3" '%m%d%H%M%y')" ;;
    Darwin:reset) says -F 'Set time and date automatically' ;;
    MINGW*|MSYS*|CYGWIN*) says -F 'Time & language' ;;
    *) return 1 ;;
  esac
}
tz_steps() { z=$1; shift; TZ=$z steps "$@"; }
OS=$(uname -s)
PHP=1; have php && PHP=0
PY=1; have python3 && PY=0

# The stand-ins. Logs: $W/log is emptied before each run, $W/keep is kept for the whole script.
mkdir -p "$W/bin" "$W/sitebin" "$W/log" "$W/keep" "$W/http/wp-json/ada-clock/v1" "$W/outside"
export STUB_LOG=$W/log STUB_KEEP=$W/keep STUB_BIN=$W/bin
# WP-CLI: logs each call's arguments, one per line, to $STUB_LOG/<its name>; answers `cli
# version`, `option get`, `eval` and `eval-file` (PHP with a few WordPress functions; STUB_TZ is
# the site's timezone). If ada-clock needs another WP-CLI command, add it here; never change an
# expectation to suit it.
cat > "$W/bin/wp" <<'EOF'
#!/usr/bin/env bash
n=${0##*/}
{ echo '--- call'; printf '%s\n' "$@"; } | tee -a "$STUB_KEEP/$n" >> "$STUB_LOG/$n"
a1= a2= a3= i=0
for x in "$@"; do case $x in --*) continue ;; esac; [ $i -lt 3 ] || break; i=$((i+1)); eval "a$i=\$x"; done
case "$a1 $a2" in
  "cli version") echo 'WP-CLI 2.11.0' ;;
  "option get") exec php "$STUB_BIN/wp-shim.php" eval "echo get_option('$a3');" ;;
  eval-file\ *) exec php "$STUB_BIN/wp-shim.php" file "$a2" ;;
  eval\ *) exec php "$STUB_BIN/wp-shim.php" eval "$a2" ;;
  *) echo "stand-in wp: unsupported: $*" >&2; exit 1 ;;
esac
EOF
cp "$W/bin/wp" "$W/sitebin/site-wp"
cat > "$W/bin/wp-shim.php" <<'EOF'
<?php
// A few WordPress functions for the stand-in `wp eval`. STUB_TZ is the site's timezone.
function wp_timezone_string() { $t = getenv( 'STUB_TZ' ); return $t ? $t : 'America/New_York'; }
function wp_timezone() { return new DateTimeZone( wp_timezone_string() ); }
function get_option( $name, $default = false ) {
	$tz = wp_timezone_string();
	if ( 'timezone_string' === $name ) { return preg_match( '/^[+-]/', $tz ) ? '' : $tz; }
	if ( 'gmt_offset' === $name ) { return wp_timezone()->getOffset( new DateTime() ) / 3600; }
	if ( 'home' === $name || 'siteurl' === $name ) { return (string) getenv( 'STUB_HOME' ); }
	return $default;
}
function home_url( $path = '' ) { return rtrim( get_option( 'home' ), '/' ) . $path; }
function wp_date( $format, $ts = null, $tz = null ) {
	$d = new DateTime( '@' . ( null === $ts ? time() : $ts ) );
	$d->setTimezone( $tz ? $tz : wp_timezone() );
	return $d->format( $format );
}
function current_time( $type, $gmt = 0 ) {
	if ( 'timestamp' === $type || 'U' === $type ) { return $gmt ? time() : time() + wp_timezone()->getOffset( new DateTime() ); }
	if ( 'mysql' === $type ) { $type = 'Y-m-d H:i:s'; }
	return $gmt ? gmdate( $type ) : wp_date( $type );
}
define( 'ABSPATH', rtrim( (string) getenv( 'STUB_ABSPATH' ), '/' ) . '/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'DAY_IN_SECONDS', 86400 ); define( 'YEAR_IN_SECONDS', 31536000 );
if ( 'eval' === $argv[1] ) { eval( $argv[2] ); exit; }
$f = $argv[2];
if ( '-' === $f ) { $f = tempnam( sys_get_temp_dir(), 'wpe' ); file_put_contents( $f, stream_get_contents( STDIN ) ); }
include $f;
EOF
# Anything that could change the clock is logged to $STUB_KEEP/clock and refused.
for c in sudo systemsetup; do
  printf '#!/usr/bin/env bash\necho "%s $*" >> "$STUB_KEEP/clock"\nexit 1\n' "$c" > "$W/bin/$c"
done
cat > "$W/bin/timedatectl" <<'EOF'
#!/usr/bin/env bash
case "$*" in *set-*) echo "timedatectl $*" >> "$STUB_KEEP/clock"; exit 1 ;; esac
for d in /usr/bin/timedatectl /bin/timedatectl; do [ -x "$d" ] && exec "$d" "$@"; done
exit 1
EOF
cat > "$W/bin/date" <<'EOF'
#!/usr/bin/env bash
for x in "$@"; do case $x in -s|--set|--set=*) echo "date $*" >> "$STUB_KEEP/clock"; exit 1 ;; esac; done
case ${1:-} in [0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]*) echo "date $*" >> "$STUB_KEEP/clock"; exit 1 ;; esac
for d in /bin/date /usr/bin/date; do [ -x "$d" ] && exec "$d" "$@"; done
exit 127
EOF
# uname: says STUB_UNAME for `uname` and `uname -s` when it is set.
cat > "$W/bin/uname" <<'EOF'
#!/usr/bin/env bash
if [ -n "${STUB_UNAME:-}" ]; then case ${1:-} in -s|'') echo "$STUB_UNAME"; exit 0 ;; esac; fi
for d in /bin/uname /usr/bin/uname; do [ -x "$d" ] && exec "$d" "$@"; done
exit 127
EOF
chmod +x "$W/bin/"* "$W/sitebin/site-wp"

# The stand-in site: GET /wp-json/ada-clock/v1/time and GET /?rest_route=/ada-clock/v1/time (the
# URL `status` calls) answer what serve() last wrote.
H=$W/http; PORT=$((18000 + RANDOM % 1000)); SRV=
serve() {
  printf '{"now":%s,"fake":%s,"timezone":"America/New_York"}\n' "$1" "$2" > "$H/wp-json/ada-clock/v1/time"
  cp "$H/wp-json/ada-clock/v1/time" "$H/index.html"
}
serve "$(date +%s)" false
HTTP=1
if [ "$PY" = 0 ]; then
  (cd "$H" && exec python3 -m http.server "$PORT" --bind 127.0.0.1) >/dev/null 2>&1 &
  SRV=$!
  for i in 1 2 3 4 5 6 7 8 9 10; do
    python3 -c 'import sys, urllib.request; urllib.request.urlopen(sys.argv[1], timeout=2)' "http://127.0.0.1:$PORT/wp-json/ada-clock/v1/time" 2>/dev/null && { HTTP=0; break; }
    sleep 0.5
  done
fi
cleanup() { if [ -n "$SRV" ]; then kill "$SRV" 2>/dev/null; wait "$SRV" 2>/dev/null; fi; rm -rf "$W"; }
trap cleanup EXIT
URL=http://127.0.0.1:$PORT

# The project (its root path has a space) and its site, outside the project's work tree, as on
# Local ("Local Sites/<site>/app/public").
mkrepo() {
  mkdir -p "$1/.claude/team" && git -C "$1" init -q && printf '.claude/team/local.env\n' > "$1/.gitignore"
  git -C "$1" add -A && git -C "$1" -c user.name=t -c user.email=t@example.com commit -qm init
}
P="$W/my project"; mkrepo "$P"; mkdir -p "$P/sub/dir"
S="$W/Local Sites/shop/app/public"; mkdir -p "$S/wp-content"; printf '<?php\n' > "$S/wp-config.php"
STATE="$S/wp-content/ada-clock.json"; MU="$S/wp-content/mu-plugins/ada-clock.php"
export STUB_ABSPATH="$S" STUB_HOME="$URL"
cat > "$P/.claude/team/local.env" <<EOF
# the shop's local site
LOCAL_URL=$URL
LOCAL_WP_PATH="$S"
LOCAL_WP=$W/sitebin/site-wp   # a stand-in, like vme-wp
touch $W/executed
PRESSTEST_SECRET='s3cr#t'
EOF
# envrepo <dir> <local.env lines...>: another project with the given local.env.
envrepo() { r=$1; shift; mkrepo "$r"; printf '%s\n' "$@" > "$r/.claude/team/local.env"; }

# 0. Static.
t "bash -n is clean" bash -n "$AC"
if [ "$PHP" = 0 ]; then t "php -l is clean on the mu-plugin" php -l "$TPL"; else skip "php -l (no php)"; fi

# 1. No command: the header is the usage. Exit 2, ada-clock: on stderr pointing at the header, for
# any site, and no WP-CLI.
LOCAL_URL=https://example.com run "$P"
t "no command exits 2" test "$(rc)" = 2
t "no command: ada-clock: on stderr, pointing at the header" grep -qi '^ada-clock: .*header' "$W/err"
t "no command: runs no WP-CLI" not test -e "$W/log/site-wp"
run "$P"
t "no command, with a local site: exits 2" test "$(rc)" = 2
t "no command, with a local site: ada-clock: on stderr, pointing at the header" grep -qi '^ada-clock: .*header' "$W/err"

# 2. Every subcommand refuses a site that isn't local, before anything runs or is written.
for c in "set 2026-11-06T11:55:00+00:00" status reset install "wp cli version"; do
  LOCAL_URL=https://example.com run "$P" $c
  t "https://example.com: $c exits 2" test "$(rc)" = 2
  t "https://example.com: $c says ada-clock: and names the URL" grep -q '^ada-clock: .*https://example\.com' "$W/err"
  t "https://example.com: $c ran no WP-CLI" not test -e "$W/log/site-wp"
  t "https://example.com: $c wrote no state file" not test -e "$STATE"
  t "https://example.com: $c installed no mu-plugin" not test -e "$MU"
done
for u in http://localhost.example.com http://vandme.local.example.com https://x.test.example.com \
         http://127.0.0.1.example.com http://shop.localhost https://example.com/.local \
         'https://example.com/?x=.test' http://x.local@example.com https://notlocal; do
  LOCAL_URL=$u run "$P" wp cli version
  t "$u is refused with exit 2" test "$(rc)" = 2
  t "$u: no WP-CLI ran" not test -e "$W/log/site-wp"
done
envrepo "$W/no url" "LOCAL_WP_PATH=\"$S\"" "LOCAL_WP=$W/sitebin/site-wp"
run "$W/no url" wp cli version
t "no LOCAL_URL at all is refused with exit 2" test "$(rc)" = 2
for u in http://localhost:10003 http://vandme.local https://x.test http://localhost http://127.0.0.1:8080 https://shop.local:8443; do
  LOCAL_URL=$u run "$P" wp cli version
  t "$u passes the local check" test "$(rc)" = 0
  t "$u: wp runs LOCAL_WP and passes its output back" grep -qx 'WP-CLI 2.11.0' "$W/out"
done

# 3. Settings come from local.env, parsed not executed, overridable by the environment.
t "local.env is not executed" not test -e "$W/executed"
t "LOCAL_WP comes from local.env, without its trailing comment" test -e "$W/log/site-wp"
envrepo "$W/env over" "LOCAL_URL=https://example.com" "LOCAL_WP_PATH=\"$S\"" "LOCAL_WP=$W/sitebin/site-wp"
run "$W/env over" wp cli version
t "local.env's LOCAL_URL is used (example.com refused)" test "$(rc)" = 2
LOCAL_URL=http://vandme.local run "$W/env over" wp cli version
t "the environment's LOCAL_URL overrides local.env" test "$(rc)" = 0
run "$P" wp option get 'blog name'
t "wp passes its arguments through as they are" test "$(tail -3 "$W/log/site-wp" 2>/dev/null)" = "$(printf '%s\n' option get 'blog name')"
LOCAL_WP="$W/sitebin/site-wp --stub-flag" run "$P" wp eval 'echo "a b";'
t "a LOCAL_WP with arguments runs them, then the wp arguments" test "$(tail -3 "$W/log/site-wp" 2>/dev/null)" = "$(printf '%s\n' --stub-flag eval 'echo "a b";')"
if [ "$PHP" = 0 ]; then t "and its output comes back" grep -qx 'a b' "$W/out"; fi
run "$P/sub/dir" wp cli version
t "from a subfolder, local.env is still found" test "$(rc)" = 0
LOCAL_URL=http://localhost LOCAL_WP_PATH="$S" LOCAL_WP=$W/sitebin/site-wp run "$W/outside" status
t "outside a git repo, exit 2" test "$(rc)" = 2

# 4. The setup: only manual is known; any other value exits 2 naming it, and runs nothing.
for c in "set 2026-11-06T11:55:00+00:00" status reset install "wp cli version"; do
  ADA_CLOCK_ADAPTER=foo run "$P" $c
  t "ADA_CLOCK_ADAPTER=foo: $c exits 2" test "$(rc)" = 2
  t "ADA_CLOCK_ADAPTER=foo: $c names manual" grep -q 'manual' "$W/err"
  t "ADA_CLOCK_ADAPTER=foo: $c ran no WP-CLI and wrote nothing" test ! -e "$W/log/site-wp" -a ! -e "$STATE" -a ! -e "$MU"
done
envrepo "$W/foo setup" "LOCAL_URL=$URL" "LOCAL_WP_PATH=\"$S\"" "LOCAL_WP=$W/sitebin/site-wp" "ADA_CLOCK_ADAPTER=foo"
run "$W/foo setup" wp cli version
t "ADA_CLOCK_ADAPTER=foo in local.env exits 2" test "$(rc)" = 2
ADA_CLOCK_ADAPTER=manual run "$W/foo setup" wp cli version
t "ADA_CLOCK_ADAPTER=manual from the environment overrides it" test "$(rc)" = 0

# 5. install, site outside the project: copies the template, replaces only its own copy.
XP="$P/.git/info/exclude"; xp_before=$(cat "$XP" 2>/dev/null | cksum)
if [ "$HTTP" = 0 ]; then run "$P" status; tail -n +2 "$W/out" > "$W/st-before"; fi
run "$P" install
t "install exits 0" test "$(rc)" = 0
t "install puts the template in wp-content/mu-plugins/" cmp -s "$TPL" "$MU"
t "install says to log in to the local admin again" says -iE 'log ?in'
t "a site outside the project: .git/info/exclude unchanged" test "$(cat "$XP" 2>/dev/null | cksum)" = "$xp_before"
if [ "$HTTP" = 0 ]; then
  run "$P" status
  t "status says the mu-plugin is installed" grep -q '^mu-plugin: installed ' "$W/out"
  t "status's later lines change once the mu-plugin is installed" not cmp -s "$W/st-before" <(tail -n +2 "$W/out")
fi
[ -f "$MU" ] && printf '\n// a local edit\n' >> "$MU"
run "$P" install
t "run again over its own (edited) copy: exit 0" test "$(rc)" = 0
t "and the copy is the template again" cmp -s "$TPL" "$MU"
mkdir -p "${MU%/*}"; printf '<?php\n/*\n * Plugin Name: Something else\n */\n' > "$MU"; cp "$MU" "$W/other.php"
run "$P" install
t "a different ada-clock.php there: exit 2" test "$(rc)" = 2
t "with ada-clock: on stderr" err_ac
t "and that file is left alone" cmp -s "$W/other.php" "$MU"
rm -f "$MU"; run "$P" install

# 6. install, site inside the project: both paths in .git/info/exclude, never in git status.
P5="$W/site repo"; SI="$P5/site root"; mkrepo "$P5"
mkdir -p "$SI/wp-content"; printf '<?php\n' > "$SI/wp-config.php"
git -C "$P5" add -A && git -C "$P5" -c user.name=t -c user.email=t@example.com commit -qm site
printf '%s\n' "LOCAL_URL=$URL" "LOCAL_WP_PATH=\"$SI\"" "LOCAL_WP=$W/sitebin/site-wp" > "$P5/.claude/team/local.env"
X5="$P5/.git/info/exclude"
STUB_ABSPATH="$SI" run "$P5" install
t "inside: install exits 0" test "$(rc)" = 0
t "inside: the mu-plugin is installed" cmp -s "$TPL" "$SI/wp-content/mu-plugins/ada-clock.php"
t "inside: the mu-plugin's path is in .git/info/exclude" grep -qF 'mu-plugins/ada-clock.php' "$X5"
t "inside: the state file's path is in .git/info/exclude" grep -qF 'wp-content/ada-clock.json' "$X5"
t "inside: git ignores the mu-plugin" git -C "$P5" check-ignore -q "site root/wp-content/mu-plugins/ada-clock.php"
t "inside: git ignores the state file" git -C "$P5" check-ignore -q "site root/wp-content/ada-clock.json"
t "inside: .gitignore is not changed" git -C "$P5" diff --quiet HEAD -- .gitignore
STUB_ABSPATH="$SI" run "$P5" set "2026-11-06T11:55:00+00:00"
t "inside: set exits 0" test "$(rc)" = 0
t "inside: git status shows neither file" test -z "$(git -C "$P5" status --porcelain --untracked-files=all)"
git -C "$P5" add -A
t "inside: git add -A stages neither file" test -z "$(git -C "$P5" diff --cached --name-only)"
git -C "$P5" reset -q
STUB_ABSPATH="$SI" run "$P5" install
t "inside: run again, exit 0" test "$(rc)" = 0
t "inside: run again, each path is in .git/info/exclude once" test "$(grep -cF 'mu-plugins/ada-clock.php' "$X5"):$(grep -cF 'wp-content/ada-clock.json' "$X5")" = 1:1
STUB_ABSPATH="$SI" run "$P5" reset

# 7. set with an ISO time and offset: no WP-CLI needed (LOCAL_WP points nowhere). The state keeps
# the time as given (seconds only if given) and the offset as its timezone (Z is +00:00).
for c in "2026-11-06T11:55:00+00:00|1793966100|2026-11-06 11:55:00|+00:00" \
         "2026-11-06T11:55:00+05:30|1793946300|2026-11-06 11:55:00|+05:30" \
         "2026-11-06T11:55:00-05:00|1793984100|2026-11-06 11:55:00|-05:00" \
         "2026-11-06T11:55+00:00|1793966100|2026-11-06 11:55|+00:00" \
         "2026-11-06T11:55Z|1793966100|2026-11-06 11:55|+00:00" \
         "2026-11-06T11:55:00Z|1793966100|2026-11-06 11:55:00|+00:00" \
         "2026-11-06T11:55:30Z|1793966130|2026-11-06 11:55:30|+00:00"; do
  IFS='|' read -r w ts lo zn <<EOF
$c
EOF
  rm -f "$STATE"
  LOCAL_WP=$W/nowhere/wp run "$P" set "$w"
  t "set $w without WP-CLI exits 0" test "$(rc)" = 0
  if [ "$PY" = 0 ]; then t "set $w: the state's keys, timestamp $ts, local '$lo', timezone $zn, manual" jstate "$STATE" "$ts" "$lo" "$zn"; fi
  t "set $w: says ada-clock: fake $lo $zn (manual), written to" says -F "ada-clock: fake $lo $zn (manual), written to"
  t "set $w: the steps for $OS, in the computer's time" steps set "$OS" "$ts"
done
rm -f "$STATE"
TZ=Asia/Tokyo LOCAL_WP=$W/nowhere/wp run "$P" set "2026-11-06T11:55:00+00:00"
t "TZ=Asia/Tokyo: the steps give the Tokyo time" tz_steps Asia/Tokyo set "$OS" 1793966100

# 8. set with a site-local time: the site's timezone for that date, through LOCAL_WP.
if [ "$PHP" = 0 ]; then
  for c in "America/New_York|2026-11-06 11:55|1793984100" "America/New_York|2026-11-06 11:55:30|1793984130" \
           "America/New_York|2026-10-30 12:00|1793376000" "+05:30|2026-11-06 11:55|1793946300"; do
    tz=${c%%|*}; r=${c#*|}; w=${r%|*}; ts=${r#*|}; rm -f "$STATE"
    STUB_TZ=$tz run "$P" set "$w"
    t "site $tz, set $w: exit 0" test "$(rc)" = 0
    if [ "$PY" = 0 ]; then t "site $tz, set $w: the state's keys, timestamp $ts, local '$w', timezone $tz, manual" jstate "$STATE" "$ts" "$w" "$tz"; fi
    t "site $tz, set $w: WP-CLI ran through LOCAL_WP" test -e "$W/log/site-wp"
    t "site $tz, set $w: the steps for $OS, in the computer's time" steps set "$OS" "$ts"
  done
  t "set says to reload pages loaded before the jump" says -i 'reload'
  rm -f "$STATE"
  TZ=Asia/Tokyo STUB_TZ=America/New_York run "$P" set "2026-11-06 11:55"
  t "site New York, computer Tokyo: the steps give 2026-11-07 01:55" tz_steps Asia/Tokyo set "$OS" 1793984100
  rm -f "$STATE"
  ADA_CLOCK_ADAPTER=manual run "$P" set "2026-11-06 11:55"
  t "ADA_CLOCK_ADAPTER=manual set exits 0" test "$(rc)" = 0
else
  skip "set with a site-local time (no php for the stand-in wp eval)"
fi

# 9. Anything else exits 2 and writes nothing.
rm -f "$STATE"
for w in "next friday" "2026-11-06" "2026-11-06T11:55:00" "2026-13-01 10:00" "2026-02-30 10:00" \
         "2026-11-06 24:30" "06/11/2026 11:55" ""; do
  run "$P" set "$w"
  t "set '$w' exits 2" test "$(rc)" = 2
  t "set '$w' says ada-clock:" err_ac
  t "set '$w' wrote no state file" not test -e "$STATE"
done
run "$P" set
t "set with no time exits 2" test "$(rc)" = 2

# 10. status and reset, against the stand-in site's time.
if [ "$HTTP" = 0 ] && [ "$PHP" = 0 ] && [ "$PY" = 0 ]; then
  # A port nothing listens on: the endpoint does not answer.
  DEAD=$(python3 -c 'import socket; s = socket.socket(); s.bind(("127.0.0.1", 0)); print(s.getsockname()[1])')
  rm -f "$STATE"; N=$(date +%s)
  serve "$N" false; run "$P" status
  t "no state: status says exactly ada-clock: real" test "$(head -1 "$W/out")" = 'ada-clock: real'
  t "no state, site at the real time: exit 0" test "$(rc)" = 0
  for d in 100:0 -100:0 300:1 -300:1; do
    serve $(( $(date +%s) + ${d%:*} )) false; run "$P" status
    t "no state, site ${d%:*}s from the real time: exit ${d#*:}" test "$(rc)" = "${d#*:}"
  done
  LOCAL_URL=http://127.0.0.1:$DEAD run "$P" status
  t "no state, the endpoint doesn't answer: exit 2" test "$(rc)" = 2
  t "no state, the endpoint doesn't answer: ada-clock: on stderr" err_ac
  serve "$(date +%s)" true; run "$P" status
  t "no state, but the endpoint says fake: exit 2" test "$(rc)" = 2
  t "no state, but the endpoint says fake: ada-clock: on stderr" err_ac
  T=1793984100
  run "$P" set "2026-11-06 11:55"
  serve "$(date +%s)" true; run "$P" status
  t "after set: status says ada-clock: fake 2026-11-06 11:55 <tz> (manual)" first_re '^ada-clock: fake 2026-11-06 11:55 [^ ]+ \(manual\)$'
  t "after set, clock not changed: exit 1" test "$(rc)" = 1
  for d in 0:0 100:0 -100:0 140:1 -140:1; do
    serve $((T + ${d%:*})) true; run "$P" status
    t "after set, site ${d%:*}s from the fake time: exit ${d#*:}" test "$(rc)" = "${d#*:}"
  done
  serve "$(date +%s)" false; run "$P" status
  t "state present, but the endpoint says not fake: exit 2" test "$(rc)" = 2
  t "state present, but the endpoint says not fake: ada-clock: on stderr" err_ac
  LOCAL_URL=http://127.0.0.1:$DEAD run "$P" status
  t "state present, the endpoint doesn't answer: exit 2" test "$(rc)" = 2
  t "state present, the endpoint doesn't answer: ada-clock: on stderr" err_ac
  # ISO time with seconds: the first line shows them, and the given offset.
  rm -f "$STATE"; LOCAL_WP=$W/nowhere/wp run "$P" set "2026-11-06T11:55:00+00:00"
  serve 1793966100 true; run "$P" status
  t "after set ISO with seconds: first line is exactly ada-clock: fake 2026-11-06 11:55:00 +00:00 (manual)" test "$(head -1 "$W/out")" = 'ada-clock: fake 2026-11-06 11:55:00 +00:00 (manual)'
  t "after set ISO with seconds, site at that time: exit 0" test "$(rc)" = 0
  rm -f "$STATE"; run "$P" set "2026-11-06 11:55"
  LOCAL_URL=https://example.com run "$P" reset
  t "reset for example.com exits 2" test "$(rc)" = 2
  t "and leaves the state file" test -e "$STATE"
  run "$P" reset
  t "reset exits 0" test "$(rc)" = 0
  t "reset deletes the state file" not test -e "$STATE"
  t "reset prints the steps to restore automatic time on $OS" steps reset "$OS"
  serve "$(date +%s)" false; run "$P" status
  t "after reset: ada-clock: real" test "$(head -1 "$W/out")" = 'ada-clock: real'
  t "after reset: exit 0" test "$(rc)" = 0
else
  skip "status and reset against a site (needs python3 and php)"
fi

# 11. The steps for macOS and Windows, through a stand-in uname.
for u in Darwin MINGW64_NT-10.0-19045; do
  for c in set reset; do
    if [ "$c" = set ]; then rm -f "$STATE"; STUB_UNAME=$u LOCAL_WP=$W/nowhere/wp run "$P" set "2026-11-06T11:55:00+00:00"
    else STUB_UNAME=$u run "$P" reset; fi
    if steps "$c" "$u" 1793966100; then ok 0 "$u: $c prints that OS's steps"
    elif says -F timedatectl; then skip "$u: $c (ada-clock doesn't take the OS from uname; check on that OS)"
    else ok 1 "$u: $c prints that OS's steps"; fi
  done
done

# 12. Nothing ran sudo or tried to set the clock; the plain wp never ran while LOCAL_WP was set.
t "nothing ran sudo or tried to set the clock" not test -s "$W/keep/clock"
t "with LOCAL_WP set, every WP-CLI call went through it" not test -e "$W/keep/wp"
# Unset LOCAL_WP means wp --path=<LOCAL_WP_PATH>, the path as one argument.
envrepo "$W/plain wp" "LOCAL_URL=$URL" "LOCAL_WP_PATH=\"$S\""
run "$W/plain wp" wp cli version
t "no LOCAL_WP: wp runs, exit 0" test "$(rc)" = 0
t "no LOCAL_WP: wp gets --path=<LOCAL_WP_PATH> as one argument" grep -qxF -- "--path=$S" "$W/log/wp"
t "no LOCAL_WP: then the wp arguments" test "$(tail -2 "$W/log/wp" 2>/dev/null)" = "$(printf '%s\n' cli version)"

echo
echo "$fails failed"
[ "$fails" = 0 ]
```

## Expected

Every line `PASS`, `0 failed`, exit 0, under both bashes. The source of each expectation is the
brief (Decisions and Definition of done), `API.md` (Every script: exit 2 with `<script>:
<message>` on stderr, run from inside a git repo) and `SYSTEM.md` (Known gotchas: works from a
subfolder). `SKIP` lines only where a tool is missing, or for macOS and Windows when `ada-clock`
doesn't read `uname` (then those steps are checked on that OS). With the broken copy, the refused
URL checks print `FAIL`.

## Observe at

The script's output and exit code.

## Regression

`presstest config` in an onboarded repo still reads the same `local.env` (`LOCAL_URL`,
`PRESSTEST_*`), and `tests/functional/14-presstest/presstest-runs-the-projects-suite.md` still
passes if `bin/presstest` changed. The computer's clock is untouched (`timedatectl` shows NTP as it
was).
