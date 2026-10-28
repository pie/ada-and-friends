# Under `ADA_CLOCK_ADAPTER=local-linux`, `ada-clock` finds the site's Local run ID, wraps that PHP version's `php-fpm` (never a stranger's script), adds one pool block, keeps the fake time as an offset in the site's clock file, and `uninstall` puts the binary back only when the last site using it leaves

- Area: 16 Clock (`ada-clock`); 12 Platforms (bash 3.2, POSIX `sh`)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219974872551364
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-10-28, Runs 1, 2 and 3 all 0 failed, with section 6b (see the PR)

## Precondition

- This checkout (`REPO`), and Docker. The script builds a stand-in Local install in a scratch
  `HOME`, laid out as Local for Linux lays it out: `~/.config/Local/sites.json` (paths both as
  `~/Local Sites/<site>` and absolute), `~/.config/Local/lightning-services/php-<version>+<n>/bin/linux/sbin/php-fpm`
  (copies of `true`: binaries, not scripts), `~/Local Sites/<site>/conf/php/php-fpm.d/www.conf.hbs`
  the compiled `~/.config/Local/run/<id>/conf/php/php-fpm.d/www.conf`, and the compiled
  `~/.config/Local/run/<id>/conf/php/php.ini` that names the PHP build the site last started with
  (real Local writes it; `ada-clock` reads the build from it). Two sites (`other`, `proofy`) carry
  the proof's pool lines, as V&Me does. "Restart the site in Local" is simulated by compiling
  `www.conf.hbs` into the run dir with its `{{! }}` comments dropped. It also makes stand-ins for WP-CLI, the site's time endpoint and every command that
  could change the computer's clock, as Unit A's `command-in-a-scratch-repo.md` does.
- Run it in containers (Steps), so nothing can reach the real Local install. If you run it on the
  host instead, its guard compares the real `~/.config/Local/lightning-services` (and
  `/opt/Local/...`), `~/.config/ada-clock` and every `~/Local Sites/*/.../www.conf.hbs` before and
  after, and fails if anything changed.
- `ALLOW_SYSTEM_STANDINS=1` is only for a throwaway container: with it the script may put a
  library at, or hide one from, the Debian libfaketime path, and put a stand-in `ldconfig` in
  `/sbin`. Never set it on the host.
- Run the checkout's script by path, never an `ada-clock` on the PATH (that is the installed
  plugin).

## Steps

1. Save the block below as `check-linux.sh` in a scratch folder (not in this repo).
2. **Run 1**: libfaketime installed, current bash (every section but 7 and 8):
   `docker run --rm -v <this checkout>:/repo:ro -v <scratch folder>:/c debian:stable-slim bash -c 'apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq git python3 php-cli curl faketime >/dev/null && ALLOW_SYSTEM_STANDINS=1 REPO=/repo bash /c/check-linux.sh'`
3. **Run 2**: bash 3.2 and busybox `sh`, with a harmless library standing in at the Debian
   libfaketime path (the install, wrapper, status and uninstall sections; the faked-time effects
   print `SKIP`):
   `docker run --rm -v <this checkout>:/repo:ro -v <scratch folder>:/c bash:3.2 bash -c 'apk add -q git python3 curl php >/dev/null; ALLOW_SYSTEM_STANDINS=1 STUB_LIB=1 REPO=/repo bash /c/check-linux.sh'`
4. **Run 3**: bash 3.2, no libfaketime (sections 7 and 8): the Run 2 command without
   `STUB_LIB=1`.
5. Break it on purpose, once: copy `plugins/ada` to a scratch folder, make the copy's
   `templates/clock/php-fpm-wrapper.sh` export the variables whether or not the clock file exists,
   and repeat Run 1 with `PLUGIN=/c/<the copy>` (mount it). Section 3 must print `FAIL` lines.
   Section 6b breaks the same way, one change per copy (each must print `FAIL` lines in 6b only): in
   `ada-clock`, make `need_real_binary` do nothing (its (ii) cases); skip the `SITE_ID_PATTERN` check
   after the site is found through `LOCAL_WP_PATH` (its (i) cases); change `wrapper: kept` to another
   word (its (v) case); change the `wrapper: none at` of the binary case (its (iii) case).

```bash
#!/usr/bin/env bash
# ada-clock's local-linux setup against a stand-in Local install in a scratch HOME: run-ID lookup
# and override; install (the wrapper, its refusals, the pool block, the clock file); the wrapper's
# variables and exec; set, reset and wp; status's wrapper and pool lines and its offset rule; the
# proof's pool lines taken over by install and removed by uninstall; set, reset and uninstall before
# install or while a time is set; uninstall's last-site-out rule and its wrapper: words (kept, none at,
# the old copy deleted); a path-found run ID that breaks the ID rule; a php-fpm.real that isn't Local's
# binary; the PHP build read from the run
# folder's php.ini; missing libfaketime and ldconfig. Never touches the real Local install or the clock.
# Prints PASS/FAIL/SKIP per check (a FAIL may show what was seen); exits 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (default $REPO/plugins/ada), ALLOW_SYSTEM_STANDINS=1 (a
# throwaway container only), STUB_LIB=1 (with it: a harmless library at the Debian path).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
AC=$PLUGIN/bin/ada-clock
WT=$PLUGIN/templates/clock/php-fpm-wrapper.sh
MUT=$PLUGIN/templates/clock/ada-clock.php
DEB_LIB=/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1
SYS=${ALLOW_SYSTEM_STANDINS:-0}
unset LOCAL_URL LOCAL_WP_PATH LOCAL_WP ADA_CLOCK_ADAPTER ADA_CLOCK_LOCAL_SITE STUB_TZ \
      LD_PRELOAD FAKETIME FAKETIME_TIMESTAMP_FILE FAKETIME_NO_CACHE FAKETIME_DONT_FAKE_MONOTONIC
REAL_HOME=$HOME
W=$(mktemp -d)
fails=0
ok()   { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()    { name=$1; shift; if seen=$("$@" 2>&1); then ok 0 "$name"; else ok 1 "$name"; [ -n "$seen" ] && printf '%s\n' "$seen" | head -5 | sed 's/^/     /'; fi; }
not()  { ! "$@"; }
have() { command -v "$1" >/dev/null 2>&1; }
skip() { echo "SKIP $1"; }
is()   { [ "$1" = "$2" ] || { echo "got '$1', want '$2'"; return 1; }; }
run()  { rd=$1; shift; rm -f "$W/log/"*; (cd "$rd" && PATH="$W/bin:$PATH" bash "$AC" "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()   { cat "$W/rc"; }
says() { cat "$W/out" "$W/err" | grep "$@" >/dev/null; }
err_ac() { grep -q '^ada-clock: ' "$W/err"; }
PHP=1; have php && PHP=0
PY=1; have python3 && PY=0

# The guard: what the real Local install, clock files and site configs look like (host runs).
guard() {
  for d in "$REAL_HOME/.config/Local/lightning-services" /opt/Local/resources/extraResources/lightning-services; do
    [ -d "$d" ] && find "$d" -path '*/sbin/php-fpm*' -exec ls -l {} \; 2>/dev/null
  done
  ls -l "$REAL_HOME/.config/ada-clock" 2>/dev/null
  for f in "$REAL_HOME/Local Sites"/*/conf/php/php-fpm.d/www.conf.hbs; do [ -f "$f" ] && cksum < "$f"; done
}
G0=$(guard)
export HOME=$W/home
mkdir -p "$HOME"

# libfaketime: a harmless stand-in when asked (a container without faketime), then found as
# ada-clock should find it: the Debian path, or ldconfig -p.
if [ "$SYS" = 1 ] && [ "${STUB_LIB:-0}" = 1 ] && [ ! -e "$DEB_LIB" ]; then
  for f in /lib/libz.so.1 /usr/lib/libz.so.1 /lib/x86_64-linux-gnu/libz.so.1 /usr/lib/x86_64-linux-gnu/libz.so.1; do
    if [ -f "$f" ]; then mkdir -p "${DEB_LIB%/*}" && cp -L "$f" "$DEB_LIB"; break; fi
  done
fi
LIB=
if [ -f "$DEB_LIB" ]; then LIB=$DEB_LIB
else
  for l in ldconfig /sbin/ldconfig /usr/sbin/ldconfig; do
    LIB=$($l -p 2>/dev/null | sed -n 's/^[[:space:]]*libfaketime\.so\.1 .*=> *//p' | head -1)
    [ -n "$LIB" ] && break
  done
fi
LIBOK=1; [ -n "$LIB" ] && LIBOK=0
FAKE=1
if [ "$LIBOK" = 0 ]; then
  n=$(date +%s); f=$(LD_PRELOAD=$LIB FAKETIME=+1d date +%s 2>/dev/null)
  case $f in ''|*[!0-9]*) ;; *) d=$((f - n - 86400)); [ "${d#-}" -le 60 ] && FAKE=0 ;; esac
fi
echo "libfaketime: ${LIB:-none}; faked time takes effect: $([ "$FAKE" = 0 ] && echo yes || echo no); $(bash --version | head -1)"

# Stand-ins on the PATH (as in Unit A's script): WP-CLI that also records the libfaketime
# variables it was given; anything that could change the clock is logged and refused.
mkdir -p "$W/bin" "$W/sitebin" "$W/log" "$W/keep" "$W/http/wp-json/ada-clock/v1" "$W/orig"
export STUB_LOG=$W/log STUB_KEEP=$W/keep STUB_BIN=$W/bin
cat > "$W/bin/wp" <<'EOF'
#!/usr/bin/env bash
n=${0##*/}
{ echo '--- call'; printf '%s\n' "$@"; } | tee -a "$STUB_KEEP/$n" >> "$STUB_LOG/$n"
env | grep -E '^(LD_PRELOAD|FAKETIME[A-Z_]*)=' | sort > "$STUB_LOG/$n.env"
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
chmod +x "$W/bin/"* "$W/sitebin/site-wp"
# envof <var>: what the last stand-in WP-CLI call was given for <var>.
envof() { sed -n "s/^$1=//p" "$W/log/site-wp.env" 2>/dev/null; }

# The stand-in site's time endpoint (as in Unit A's script).
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

# The stand-in Local install. Three PHP builds: 8.2.27 (the sites'), 8.2.29 (same minor, unused)
# and 8.1.29 (one site's). Run IDs with _ and -, as Local's have.
LOC=$HOME/.config/Local; LSV=$LOC/lightning-services; CF=$HOME/.config/ada-clock; SITES="$HOME/Local Sites"
TRUEBIN=; for f in /bin/true /usr/bin/true; do [ -x "$f" ] && { TRUEBIN=$f; break; }; done
mkbin() {
  mkdir -p "$LSV/$1/bin/linux/sbin"; cp -L "$TRUEBIN" "$LSV/$1/bin/linux/sbin/php-fpm"
  printf '\nstand-in php-fpm %s\n' "$1" >> "$LSV/$1/bin/linux/sbin/php-fpm"; chmod 755 "$LSV/$1/bin/linux/sbin/php-fpm"
  cp "$LSV/$1/bin/linux/sbin/php-fpm" "$W/orig/$1"
}
mkbin php-8.2.27+1; mkbin php-8.2.29+0; mkbin php-8.1.29+0
SB27=$LSV/php-8.2.27+1/bin/linux/sbin; SB29=$LSV/php-8.2.29+0/bin/linux/sbin; SB81=$LSV/php-8.1.29+0/bin/linux/sbin
O27=$W/orig/php-8.2.27+1; O29=$W/orig/php-8.2.29+0; O81=$W/orig/php-8.1.29+0
ID_SHOP=Sh0p_bC12; ID_SHOP2=Sh0p2XyZ1; ID_OTHER=0th-rXyZ9; ID_OLD=0ldSiTe77; ID_PROOFY=Pr00fyQ12
hbs()   { printf '%s' "$SITES/$1/conf/php/php-fpm.d/www.conf.hbs"; }
wpp()   { printf '%s' "$SITES/$1/app/public"; }
rconf() { printf '%s' "$LOC/run/$1/conf/php/php-fpm.d/www.conf"; }
inif()  { printf '%s' "$LOC/run/$1/conf/php/php.ini"; }
mksite() { # <id> <name> <PHP folder the site last started with>
  mkdir -p "$(wpp "$2")/wp-content" "$SITES/$2/conf/php/php-fpm.d" "$LOC/run/$1/conf/php/php-fpm.d"
  # The compiled php.ini Local writes into the run dir, naming the PHP build the site last started with.
  printf 'zend_extension = %s/%s/bin/linux/lib/php/extensions/opcache.so\n' "$LSV" "$3" > "$(inif "$1")"
  printf '<?php\n' > "$(wpp "$2")/wp-config.php"
  printf '%s\n' '[www]' 'user = {{user}}' 'group = {{group}}' 'listen = {{port}}' 'pm = dynamic' 'pm.max_children = 5' 'clear_env = yes' > "$(hbs "$2")"
}
compile() { grep -v '^[[:space:]]*{{!' "$(hbs "$2")" > "$(rconf "$1")"; }   # <id> <name>: "restart in Local"
mksite $ID_SHOP shop php-8.2.27+1; mksite $ID_SHOP2 shop2 php-8.2.27+1; mksite $ID_OTHER other php-8.2.27+1
mksite $ID_OLD old php-8.1.29+0; mksite $ID_PROOFY proofy php-8.1.29+0
# The other and proofy sites carry the proof's pool lines, as V&Me does.
proof_lines() { printf '%s\n' '' '{{! ada-clock proof }}' "env[FAKETIME_TIMESTAMP_FILE] = \"$CF/$1\"" 'env[FAKETIME_NO_CACHE] = 1' 'env[FAKETIME_DONT_FAKE_MONOTONIC] = 1'; }
proof_lines $ID_OTHER >> "$(hbs other)"; proof_lines $ID_PROOFY >> "$(hbs proofy)"
for s in "$ID_SHOP shop" "$ID_SHOP2 shop2" "$ID_OTHER other" "$ID_OLD old" "$ID_PROOFY proofy"; do
  set -- $s; compile "$1" "$2"; cp "$(hbs "$2")" "$W/orig/hbs-$2"; cp "$(rconf "$1")" "$W/orig/run-$2"
done
entry() { printf '"%s": {"id": "%s", "name": "%s", "path": "%s", "domain": "%s.local", "services": {"nginx": {"name": "nginx", "version": "1.26.1", "type": "lightning", "role": "http"}, "php": {"name": "php", "version": "%s", "type": "lightning", "role": "php"}, "mysql": {"name": "mysql", "version": "8.0.35", "type": "lightning", "role": "db"}}}' "$1" "$1" "$2" "$3" "$2" "$4"; }
{ printf '{\n'; entry $ID_SHOP shop "~/Local Sites/shop" 8.2.27; printf ',\n'
  entry $ID_SHOP2 shop2 "~/Local Sites/shop2" 8.2.27; printf ',\n'
  entry $ID_OTHER other "$HOME/Local Sites/other" 8.2.27; printf ',\n'
  entry $ID_OLD old "~/Local Sites/old" 8.1.29; printf ',\n'
  entry $ID_PROOFY proofy "~/Local Sites/proofy" 8.1.29; printf '\n}\n'; } > "$LOC/sites.json"
if [ "$PY" = 0 ]; then python3 -c 'import json, sys; json.load(open(sys.argv[1]))' "$LOC/sites.json" || echo "STOP the stand-in sites.json isn't JSON"; fi
mkdir -p "$W/elsewhere/app/public/wp-content"; printf '<?php\n' > "$W/elsewhere/app/public/wp-config.php"

# Checks on the pool block. A marker is a comment line (;, # or {{!) naming ada-clock, not the proof's.
marks()    { grep -E '^[[:space:]]*(;|#|\{\{!)' "$1" | grep 'ada-clock' | grep -vc 'ada-clock proof'; }
inblock()  { awk '/^[[:space:]]*(;|#|\{\{!)/ && /ada-clock/ && !/ada-clock proof/ { n++; next } n == 1 { print }' "$1"; }
outblock() { awk '/^[[:space:]]*(;|#|\{\{!)/ && /ada-clock/ && !/ada-clock proof/ { n++; next } n != 1 { print }' "$1"; }
envval()   { sed -n "s/^[[:space:]]*env\[$1\][[:space:]]*=[[:space:]]*//p" | sed -e 's/[[:space:]]*$//' -e 's/^"\(.*\)"$/\1/'; }
# block_ok <file> <id>: two markers; between them env[FAKETIME_TIMESTAMP_FILE] (the site's clock
# file), env[FAKETIME_NO_CACHE] = 1 and env[FAKETIME_DONT_FAKE_MONOTONIC] = 1, each once.
block_ok() {
  m=$(marks "$1"); [ "$m" = 2 ] || { echo "$m ada-clock marker lines in $1"; return 1; }
  b=$(inblock "$1")
  is "$(printf '%s\n' "$b" | envval FAKETIME_TIMESTAMP_FILE)" "$CF/$2" || return 1
  is "$(printf '%s\n' "$b" | envval FAKETIME_NO_CACHE)" 1 || return 1
  is "$(printf '%s\n' "$b" | envval FAKETIME_DONT_FAKE_MONOTONIC)" 1
}
# strip_proof <file>: the file without the proof's pool lines (the "ada-clock proof" comment and the
# env[FAKETIME_*] lines straight after it). nonblank: without blank lines.
strip_proof() { awk '/ada-clock proof/ { skip = 3; next } skip > 0 && /^[[:space:]]*env\[FAKETIME_/ { skip--; next } { skip = 0; print }' "$1"; }
nonblank() { grep -v '^[[:space:]]*$'; }
# no_proof <file>: the proof's comment line is gone. envonce <file>: each of the three env lines once.
no_proof() { ! grep -q 'ada-clock proof' "$1" || { echo "the proof's comment line is still in $1"; return 1; }; }
envonce() {
  for v in FAKETIME_TIMESTAMP_FILE FAKETIME_NO_CACHE FAKETIME_DONT_FAKE_MONOTONIC; do
    c=$(grep -c "^[[:space:]]*env\[$v\]" "$1"); [ "$c" = 1 ] || { echo "$c env[$v] lines in $1, want 1"; return 1; }
  done
}
# appended <file> <original>: what was in the original is kept, less the proof's pool lines if it had
# them (the amendment: install takes them over), and nothing but blank lines is outside that and the
# block. With no proof lines in the original it is kept byte for byte at the start.
appended() {
  if ! grep -q 'ada-clock proof' "$2"; then
    s=$(wc -c < "$2" | tr -d ' ')
    head -c "$s" "$1" | cmp -s - "$2" || { echo "the original isn't kept at the start of $1"; return 1; }
  fi
  [ "$(outblock "$1" | nonblank)" = "$(strip_proof "$2" | nonblank)" ] || { echo "lines outside the block differ from the original (less the proof's)"; return 1; }
  no_proof "$1"
}
# plain_as <file> <original>: no ada-clock or env[FAKETIME_*] line left; the original, less the proof's
# lines if it had them (byte for byte if it had none).
plain_as() {
  ! grep -qE 'ada-clock|env\[FAKETIME_' "$1" || { echo "ada-clock lines are still in $1"; return 1; }
  [ "$(nonblank < "$1")" = "$(strip_proof "$2" | nonblank)" ] || { echo "$1 isn't the original, less the proof's lines"; return 1; }
  grep -q 'ada-clock proof' "$2" || cmp -s "$1" "$2" || { echo "$1 isn't the original byte for byte"; return 1; }
}
# is_wrapper <php-fpm>: a script whose first two lines name ada-clock (not the proof), executable, sh -n clean.
is_wrapper() {
  [ "$(head -c 2 "$1")" = '#!' ] || { echo "$1 is not a script"; return 1; }
  head -2 "$1" | grep 'ada-clock' | grep -qv 'proof' || { echo "head -2: $(head -2 "$1")"; return 1; }
  [ -x "$1" ] || { echo "$1 is not executable"; return 1; }
  sh -n "$1"
}
clock() { cat "$CF/$1" 2>/dev/null; }
# offset_near <id> <target>: the clock file is one line, +<n>s or -<n>s, n within 5 s of <target> minus now.
offset_near() {
  [ -f "$CF/$1" ] || { echo "no clock file $CF/$1"; return 1; }
  [ "$(wc -l < "$CF/$1" | tr -d ' ')" -le 1 ] || { echo "more than one line: $(cat "$CF/$1")"; return 1; }
  v=$(clock "$1"); case $v in +*s|-*s) ;; *) echo "clock file says '$v'"; return 1 ;; esac
  n=${v%s}; n=${n#+}; case ${n#-} in ''|*[!0-9]*) echo "clock file says '$v'"; return 1 ;; esac
  e=$(( $2 - $(date +%s) )); d=$(( n - e )); [ "${d#-}" -le 5 ] || { echo "clock file says $v, want about $e"; return 1; }
}
# jstate <file> <timestamp> <local> <timezone>: Unit A's state file, setup "local-linux".
jstate() {
  python3 - "$@" <<'PY'
import json, sys
f, ts, local, tz = sys.argv[1], int(sys.argv[2]), sys.argv[3], sys.argv[4]
d = json.load(open(f))
bad = []
if not isinstance(d, dict) or set(d) != {'timestamp', 'local', 'timezone', 'setup', 'set_at'}:
    bad.append('keys %r' % (sorted(d) if isinstance(d, dict) else d))
else:
    for k, want in (('timestamp', ts), ('local', local), ('timezone', tz), ('setup', 'local-linux')):
        if d.get(k) != want:
            bad.append('%s is %r, want %r' % (k, d.get(k), want))
    if type(d.get('set_at')) is not int:
        bad.append('set_at is not an integer')
if bad:
    print('; '.join(bad))
sys.exit(1 if bad else 0)
PY
}
webhook_line() { [ "$(cat "$W/out" "$W/err" | grep -ci 'webhook')" = 1 ] && cat "$W/out" "$W/err" | grep -i 'webhook' | grep -qi 'stripe'; }
os_steps() { says -E 'timedatectl|date -s|Set time and date automatically|Time & language'; }
# status's second and third lines when all is well (Q6): wrapper: installed <php-fpm>, pool: loaded <run dir www.conf>.
wline_ok() { is "$(sed -n 2p "$W/out")" "wrapper: installed $SB27/php-fpm"; }
pline_ok() { is "$(sed -n 3p "$W/out")" "pool: loaded $(rconf $ID_SHOP)"; }
# state_offset: the state file's timestamp minus set_at, as +<n>s or -<n>s. age_state <s>: the state
# file as if it had been written <s> seconds earlier.
state_offset() { python3 -c 'import json, sys; d = json.load(open(sys.argv[1])); n = d["timestamp"] - d["set_at"]; print("%s%ds" % ("+" if n >= 0 else "-", abs(n)))' "$STATE"; }
age_state() { python3 -c 'import json, sys; f = sys.argv[1]; d = json.load(open(f)); d["set_at"] -= int(sys.argv[2]); json.dump(d, open(f, "w"))' "$STATE" "$1"; }
# still_installed: shop's wrapper, pool block, clock file, mu-plugin and state file are all there.
still_installed() {
  is_wrapper "$SB27/php-fpm" && block_ok "$(hbs shop)" $ID_SHOP && [ -f "$CF/$ID_SHOP" ] &&
    [ -f "$(wpp shop)/wp-content/mu-plugins/ada-clock.php" ] && [ -f "$STATE" ] || { echo "something was removed"; return 1; }
}

# The project: its local.env names the shop site and the local-linux setup.
mkrepo() {
  mkdir -p "$1/.claude/team" && git -C "$1" init -q && printf '.claude/team/local.env\n' > "$1/.gitignore"
  git -C "$1" add -A && git -C "$1" -c user.name=t -c user.email=t@example.com commit -qm init
}
P="$W/my project"; mkrepo "$P"
printf '%s\n' "LOCAL_URL=$URL" "LOCAL_WP_PATH=\"$(wpp shop)\"" "LOCAL_WP=$W/sitebin/site-wp" "ADA_CLOCK_ADAPTER=local-linux" > "$P/.claude/team/local.env"
export STUB_ABSPATH="$(wpp shop)" STUB_HOME="$URL"
# on <site> <args...>: ada-clock for that site, from the project.
on() { s=$1; shift; LOCAL_WP_PATH=$(wpp "$s") STUB_ABSPATH=$(wpp "$s") run "$P" "$@"; }
STATE=$(wpp shop)/wp-content/ada-clock.json
nothing_changed() {
  cmp -s "$O27" "$SB27/php-fpm" || { echo "php-fpm 8.2.27 changed"; return 1; }
  { cmp -s "$O29" "$SB29/php-fpm" && [ ! -e "$SB29/php-fpm.real" ]; } || { echo "php-fpm 8.2.29 changed"; return 1; }
  [ ! -e "$SB27/php-fpm.real" ] || { echo "php-fpm.real made"; return 1; }
  cmp -s "$W/orig/hbs-shop" "$(hbs shop)" || { echo "shop's www.conf.hbs changed"; return 1; }
  [ -z "$(ls -A "$CF" 2>/dev/null)" ] || { echo "clock files: $(ls -A "$CF")"; return 1; }
  [ ! -e "$STATE" ] || { echo "state file written"; return 1; }
}

# 0. Static.
t "bash -n is clean on ada-clock" bash -n "$AC"
t "the wrapper template exists" test -f "$WT"
t "sh -n is clean on the wrapper template" sh -n "$WT"
if have dash; then t "dash -n is clean on the wrapper template" dash -n "$WT"; else skip "dash -n (no dash)"; fi
if have busybox; then t "busybox sh -n is clean on the wrapper template" busybox sh -n "$WT"; else skip "busybox sh -n (no busybox)"; fi
t "the wrapper template is an sh script" sh -c 'head -1 "$1" | grep -qE "^#!.*[/ ]sh *$"' _ "$WT"
t "the wrapper template's first two lines name ada-clock, not the proof" sh -c 'head -2 "$1" | grep ada-clock | grep -qv proof' _ "$WT"

# 1. Refusals that change nothing: a LOCAL_URL that isn't local, for every subcommand.
for c in install "set 2026-11-06T11:55:00+00:00" reset status uninstall "wp cli version"; do
  LOCAL_URL=https://example.com on shop $c
  t "local-linux, https://example.com: $c exits 2" is "$(rc)" 2
  t "local-linux, https://example.com: $c changes nothing" nothing_changed
  t "local-linux, https://example.com: $c ran no WP-CLI" not test -e "$W/log/site-wp"
  if [ "$LIBOK" = 0 ]; then t "local-linux, https://example.com: $c names the URL" grep -q '^ada-clock: .*https://example\.com' "$W/err"; fi
done

if [ "$LIBOK" = 0 ]; then
# 1b. No Local site has LOCAL_WP_PATH: exit 2 naming both, nothing written.
EW="$W/elsewhere/app/public"
for c in install "set 2026-11-06T11:55:00+00:00"; do
  LOCAL_WP_PATH=$EW STUB_ABSPATH=$EW run "$P" $c
  t "no site matches: $c exits 2" is "$(rc)" 2
  t "no site matches: $c names LOCAL_WP_PATH" grep -qF "$EW" "$W/err"
  t "no site matches: $c names sites.json" grep -qF 'sites.json' "$W/err"
  t "no site matches: $c changes nothing" nothing_changed
  t "no site matches: $c wrote no state file there" not test -e "$EW/wp-content/ada-clock.json"
done

# 1c. The PHP build comes from the compiled php.ini in the run folder (Q7); where it can't be had,
# install and status exit 2 telling the developer to start the site in Local, and change nothing.
INI=$(inif $ID_SHOP); cp "$INI" "$W/ini-shop"
q7() { # <what>
  for c in install status; do
    on shop $c
    t "$1: $c exits 2" is "$(rc)" 2
    t "$1: $c says to start the site in Local" says -E 'start.*the site in Local|the site in Local.*start'
    t "$1: $c says to run it again afterwards" says -F 'run this again'
    t "$1: $c changes nothing" nothing_changed
  done
}
rm -f "$INI"; q7 "no compiled php.ini"
printf 'zend_extension = %s/php-8.2.29+0/bin/linux/lib/php/extensions/opcache.so\n' "$LSV" > "$INI"
q7 "php.ini names a build of another version (8.2.29; sites.json says 8.2.27)"
{ cat "$W/ini-shop"; printf 'extension_dir = %s/php-8.2.29+0/bin/linux/lib/php/extensions\n' "$LSV"; } > "$INI"
q7 "php.ini names two builds"
cp "$W/ini-shop" "$INI"
mv "$LOC/run/$ID_SHOP" "$W/run-shop"; q7 "no run folder"; mv "$W/run-shop" "$LOC/run/$ID_SHOP"

# 2. install.
on shop install
t "install exits 0" is "$(rc)" 0
t "php-fpm (8.2.27) is now the ada-clock wrapper" is_wrapper "$SB27/php-fpm"
t "php-fpm.real is the original binary" cmp -s "$O27" "$SB27/php-fpm.real"
t "8.2.29's php-fpm is untouched, no php-fpm.real" sh -c 'cmp -s "$1" "$2/php-fpm" && test ! -e "$2/php-fpm.real"' _ "$O29" "$SB29"
t "8.1.29's php-fpm is untouched, no php-fpm.real" sh -c 'cmp -s "$1" "$2/php-fpm" && test ! -e "$2/php-fpm.real"' _ "$O81" "$SB81"
t "www.conf.hbs: one ada-clock block, the three env lines once each" block_ok "$(hbs shop)" $ID_SHOP
t "www.conf.hbs: the original kept, the block appended" appended "$(hbs shop)" "$W/orig/hbs-shop"
t "the clock file ~/.config/ada-clock/<run ID> says +0s" is "$(clock $ID_SHOP)" "+0s"
t "the only clock file is shop's (not shop2's, whose path starts the same)" is "$(ls -A "$CF" 2>/dev/null)" "$ID_SHOP"
t "Unit A's mu-plugin is installed too" cmp -s "$MUT" "$(wpp shop)/wp-content/mu-plugins/ada-clock.php"
t "install says to restart the site" says -i 'restart'
t "install ran no sudo" not test -s "$W/keep/clock"
cp "$(hbs shop)" "$W/after-shop"
on shop install
t "install again: exit 0" is "$(rc)" 0
t "install again: www.conf.hbs exactly as after the first (still one block)" cmp -s "$W/after-shop" "$(hbs shop)"
t "install again: php-fpm.real is still the original binary" cmp -s "$O27" "$SB27/php-fpm.real"
t "install again: php-fpm is still the wrapper" is_wrapper "$SB27/php-fpm"
t "install again: the clock file says +0s" is "$(clock $ID_SHOP)" "+0s"
printf '# a local edit\n' >> "$SB27/php-fpm"
on shop install
t "over its own edited wrapper: exit 0" is "$(rc)" 0
t "over its own edited wrapper: replaced" not grep -q 'a local edit' "$SB27/php-fpm"
t "over its own edited wrapper: php-fpm.real still the original" cmp -s "$O27" "$SB27/php-fpm.real"
cat > "$W/proof.sh" <<'EOF'
#!/bin/sh
# ada-clock proof: run only site @ID@'s php-fpm under libfaketime
REAL="$(dirname "$0")/php-fpm.real"
LIB=/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1
TS_FILE="$HOME/.config/ada-clock/@ID@"
case "$*" in *"/run/@ID@/"*)
  if [ -f "$LIB" ]; then
    mkdir -p "$(dirname "$TS_FILE")"; [ -s "$TS_FILE" ] || echo "+0s" > "$TS_FILE"
    export LD_PRELOAD="$LIB${LD_PRELOAD:+ $LD_PRELOAD}" FAKETIME_TIMESTAMP_FILE="$TS_FILE" FAKETIME_NO_CACHE=1 FAKETIME_DONT_FAKE_MONOTONIC=1
  fi ;;
esac
exec "$REAL" "$@"
EOF
sed "s/@ID@/$ID_SHOP/g" "$W/proof.sh" > "$SB27/php-fpm"; chmod 755 "$SB27/php-fpm"
on shop install
t "over the proof wrapper: exit 0" is "$(rc)" 0
t "over the proof wrapper: php-fpm is the ada-clock wrapper" is_wrapper "$SB27/php-fpm"
t "over the proof wrapper: php-fpm.real still the original" cmp -s "$O27" "$SB27/php-fpm.real"
printf '#!/bin/sh\n# my own php-fpm wrapper\nexec "$(dirname "$0")/php-fpm.real" "$@"\n' > "$SB27/php-fpm"; chmod 755 "$SB27/php-fpm"; cp "$SB27/php-fpm" "$W/mine"
on shop install
t "an unrelated php-fpm script: exit 2" is "$(rc)" 2
t "an unrelated php-fpm script: ada-clock: on stderr" err_ac
t "an unrelated php-fpm script: left alone" cmp -s "$W/mine" "$SB27/php-fpm"
t "an unrelated php-fpm script: php-fpm.real left alone" cmp -s "$O27" "$SB27/php-fpm.real"
rm -f "$SB27/php-fpm.real"
on shop install
t "an unrelated script, no php-fpm.real: exit 2" is "$(rc)" 2
t "an unrelated script, no php-fpm.real: left alone, none made" sh -c 'cmp -s "$1" "$2/php-fpm" && test ! -e "$2/php-fpm.real"' _ "$W/mine" "$SB27"
cp "$O27" "$SB27/php-fpm"
on shop install
t "the binary back as php-fpm: install wraps it again" is_wrapper "$SB27/php-fpm"
t "the binary back as php-fpm: it is php-fpm.real" cmp -s "$O27" "$SB27/php-fpm.real"
# Second site on the same version; then ADA_CLOCK_LOCAL_SITE from the environment and from local.env.
on shop2 install
t "shop2: exit 0" is "$(rc)" 0
t "shop2: its own clock file says +0s" is "$(clock $ID_SHOP2)" "+0s"
t "shop2: its own block" block_ok "$(hbs shop2)" $ID_SHOP2
t "shop2: shop's www.conf.hbs is untouched" cmp -s "$W/after-shop" "$(hbs shop)"
t "shop2: php-fpm.real still the original binary" cmp -s "$O27" "$SB27/php-fpm.real"
cp "$(hbs shop2)" "$W/after-shop2"
LOCAL_WP_PATH="$W/elsewhere/app/public" STUB_ABSPATH="$W/elsewhere/app/public" ADA_CLOCK_LOCAL_SITE=$ID_OTHER run "$P" install
t "ADA_CLOCK_LOCAL_SITE in the environment, no path match: exit 0" is "$(rc)" 0
t "ADA_CLOCK_LOCAL_SITE: the named site's clock file says +0s" is "$(clock $ID_OTHER)" "+0s"
t "ADA_CLOCK_LOCAL_SITE: the named site's block (absolute path in sites.json)" block_ok "$(hbs other)" $ID_OTHER
t "the proof's pool lines are gone, one block (E1, the amendment): no proof comment line" no_proof "$(hbs other)"
t "the proof's pool lines are gone: each env line once in the file" envonce "$(hbs other)"
t "the proof's pool lines are gone: the rest of the file as it was" appended "$(hbs other)" "$W/orig/hbs-other"
OLDP="$W/old project"; mkrepo "$OLDP"
printf '%s\n' "LOCAL_URL=$URL" "LOCAL_WP_PATH=\"$(wpp shop2)\"" "LOCAL_WP=$W/sitebin/site-wp" "ADA_CLOCK_ADAPTER=local-linux" "ADA_CLOCK_LOCAL_SITE=$ID_OLD" > "$OLDP/.claude/team/local.env"
STUB_ABSPATH=$(wpp shop2) run "$OLDP" install
t "ADA_CLOCK_LOCAL_SITE in local.env beats a path match: exit 0" is "$(rc)" 0
t "ADA_CLOCK_LOCAL_SITE in local.env: old's clock file, not a second shop2 one" sh -c 'test "$(cat "$1/$2")" = +0s && test "$(cat "$1/$3")" = +0s' _ "$CF" $ID_OLD $ID_SHOP2
t "ADA_CLOCK_LOCAL_SITE in local.env: old's block" block_ok "$(hbs old)" $ID_OLD
t "ADA_CLOCK_LOCAL_SITE in local.env: shop2's www.conf.hbs untouched" cmp -s "$W/after-shop2" "$(hbs shop2)"
t "old runs PHP 8.1.29: that php-fpm is the wrapper" is_wrapper "$SB81/php-fpm"
t "old runs PHP 8.1.29: its php-fpm.real is the 8.1.29 binary" cmp -s "$O81" "$SB81/php-fpm.real"
t "8.2.29 still untouched" cmp -s "$O29" "$SB29/php-fpm"
for s in "$ID_SHOP shop" "$ID_SHOP2 shop2" "$ID_OTHER other" "$ID_OLD old"; do set -- $s; compile "$1" "$2"; done
t "the restart compiled other's www.conf: each env line once, not twice" envonce "$(rconf $ID_OTHER)"

# 3. The wrapper, with a stand-in php-fpm.real that records what it was started with.
mv "$SB27/php-fpm.real" "$W/real27"
cat > "$SB27/php-fpm.real" <<'EOF'
#!/bin/sh
{ echo "pid=$$"; for a in "$@"; do echo "arg=$a"; done
  for v in LD_PRELOAD FAKETIME_TIMESTAMP_FILE FAKETIME_NO_CACHE FAKETIME_DONT_FAKE_MONOTONIC; do eval "x=\${$v-UNSET}"; echo "$v=$x"; done
  echo "now=$(date +%s)"; } > "$PROBE"
[ "${PROBE_MODE:-}" = loop ] && exec python3 "$PROBE_PY"
exit 0
EOF
chmod 755 "$SB27/php-fpm.real"
export PROBE=$W/probe PROBE_PY=$W/loop.py PROBE_GO=$W/go
cat > "$PROBE_PY" <<'EOF'
import os, time
out = open(os.environ['PROBE'], 'a')
out.write('t1=%d\n' % int(time.time())); out.flush()
for i in range(150):
    if os.path.exists(os.environ['PROBE_GO']):
        break
    time.sleep(0.1)
out.write('t2=%d\n' % int(time.time())); out.close()
EOF
# fpm <run id> [<sh>]: start php-fpm as Local does (the arguments name the run dir); F_PID its PID.
fpm() { rm -f "$PROBE"; a="$HOME/.config/Local/run/$1/conf/php"
  ${2:+$2} "$SB27/php-fpm" --nodaemonize --fpm-config "$a/php-fpm.conf" -c "$a/php.ini" --prefix "$SITES/x y" & F_PID=$!; wait "$F_PID"; }
args_ok() { a="$HOME/.config/Local/run/$1/conf/php"
  is "$(sed -n 's/^arg=//p' "$PROBE" 2>/dev/null)" "$(printf '%s\n' --nodaemonize --fpm-config "$a/php-fpm.conf" -c "$a/php.ini" --prefix "$SITES/x y")"; }
pv() { sed -n "s/^$1=//p" "$PROBE" 2>/dev/null; }
has_lib() { case $(pv LD_PRELOAD) in *libfaketime*) return 0 ;; esac; echo "LD_PRELOAD=$(pv LD_PRELOAD)"; return 1; }
none_set() { for v in LD_PRELOAD FAKETIME_TIMESTAMP_FILE FAKETIME_NO_CACHE FAKETIME_DONT_FAKE_MONOTONIC; do is "$v=$(pv $v)" "$v=UNSET" || return 1; done; }
near() { case "$1$2" in *[!0-9-]*|'') echo "not numbers: '$1' '$2'"; return 1 ;; esac; d=$(( $1 - $2 )); [ "${d#-}" -le "$3" ] || { echo "$1 is not within $3 s of $2"; return 1; }; }
fpm $ID_SHOP
t "wrapper, a site with a clock file: execs php-fpm.real (same PID)" is "$(pv pid)" "$F_PID"
t "wrapper, a site with a clock file: the arguments passed on unchanged" args_ok $ID_SHOP
t "wrapper, a site with a clock file: LD_PRELOAD has libfaketime" has_lib
t "wrapper, a site with a clock file: FAKETIME_TIMESTAMP_FILE is its clock file" is "$(pv FAKETIME_TIMESTAMP_FILE)" "$CF/$ID_SHOP"
t "wrapper, a site with a clock file: FAKETIME_NO_CACHE=1" is "$(pv FAKETIME_NO_CACHE)" 1
t "wrapper, a site with a clock file: FAKETIME_DONT_FAKE_MONOTONIC=1" is "$(pv FAKETIME_DONT_FAKE_MONOTONIC)" 1
fpm $ID_OTHER
t "wrapper, a run ID with a hyphen: its own clock file" is "$(pv FAKETIME_TIMESTAMP_FILE)" "$CF/$ID_OTHER"
fpm N0Cl0ck99
t "wrapper, a site with no clock file: none of the four variables" none_set
t "wrapper, a site with no clock file: the arguments unchanged" args_ok N0Cl0ck99
t "wrapper, a site with no clock file: execs php-fpm.real (same PID)" is "$(pv pid)" "$F_PID"
t "wrapper, a site with no clock file: no clock file made for it" not test -e "$CF/N0Cl0ck99"
rm -f "$PROBE"; "$SB27/php-fpm" -t
t "wrapper, no run dir in the arguments: none of the four variables" none_set
if have dash; then
  fpm $ID_SHOP dash
  t "wrapper under dash: its clock file" is "$(pv FAKETIME_TIMESTAMP_FILE)" "$CF/$ID_SHOP"
  t "wrapper under dash: the arguments unchanged" args_ok $ID_SHOP
fi
if [ "$FAKE" = 0 ]; then
  printf '+86400s\n' > "$CF/$ID_SHOP"; fpm $ID_SHOP
  t "wrapper, clock file +86400s: php-fpm.real runs a day ahead" near "$(pv now)" $(( $(date +%s) + 86400 )) 60
  rm -f "$PROBE" "$PROBE_GO"
  PROBE_MODE=loop "$SB27/php-fpm" --fpm-config "$HOME/.config/Local/run/$ID_SHOP/conf/php/php-fpm.conf" & p=$!
  for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do grep -q '^t1=' "$PROBE" 2>/dev/null && break; sleep 0.25; done
  printf '+172800s\n' > "$CF/$ID_SHOP"; touch "$PROBE_GO"; wait "$p"
  t "a running process sees the clock file's new offset (no cache): before, a day ahead" near "$(pv t1)" $(( $(date +%s) + 86400 )) 60
  t "a running process sees the clock file's new offset (no cache): after, two days ahead" near "$(pv t2)" $(( $(date +%s) + 172800 )) 60
  printf '+0s\n' > "$CF/$ID_SHOP"; rm -f "$PROBE_GO"
else
  skip "the faked time taking effect, and no cache (not a working libfaketime here)"
fi
if [ "$SYS" = 1 ]; then
  mv "$LIB" "$LIB.away"; fpm $ID_SHOP; mv "$LIB.away" "$LIB"
  t "wrapper, libfaketime gone: none of the four variables" none_set
  t "wrapper, libfaketime gone: execs php-fpm.real, the arguments unchanged" args_ok $ID_SHOP
else
  skip "the wrapper without libfaketime (needs ALLOW_SYSTEM_STANDINS=1 in a container)"
fi
mv "$W/real27" "$SB27/php-fpm.real"

# 4. set, reset and wp.
rm -f "$STATE"
on shop set "2026-11-06T11:55:00+00:00"
t "set ISO: exit 0" is "$(rc)" 0
t "set ISO: first line ada-clock: fake 2026-11-06 11:55:00 +00:00 (local-linux), written to ..." sh -c 'head -1 "$1" | grep -qF "ada-clock: fake 2026-11-06 11:55:00 +00:00 (local-linux), written to "' _ "$W/out"
if [ "$PY" = 0 ]; then t "set ISO: the state file, setup local-linux" jstate "$STATE" 1793966100 "2026-11-06 11:55:00" "+00:00"; fi
t "set ISO: the clock file is +<n>s, 1793966100 minus now" offset_near $ID_SHOP 1793966100
t "set: one warning line about webhooks, naming Stripe" webhook_line
t "set: says to reload pages loaded before the jump" says -i 'reload'
t "set: (E2) no steps to change the computer's clock" not os_steps
t "set on shop: shop2's clock file still +0s" is "$(clock $ID_SHOP2)" "+0s"
on shop wp cli version
t "wp: runs LOCAL_WP and passes its output back" grep -qx 'WP-CLI 2.11.0' "$W/out"
t "wp: LD_PRELOAD has libfaketime" sh -c 'case "$1" in *libfaketime*) exit 0 ;; esac; exit 1' _ "$(envof LD_PRELOAD)"
t "wp: FAKETIME_TIMESTAMP_FILE is the site's clock file" is "$(envof FAKETIME_TIMESTAMP_FILE)" "$CF/$ID_SHOP"
t "wp: FAKETIME_NO_CACHE=1" is "$(envof FAKETIME_NO_CACHE)" 1
t "wp: FAKETIME_DONT_FAKE_MONOTONIC=1" is "$(envof FAKETIME_DONT_FAKE_MONOTONIC)" 1
ADA_CLOCK_ADAPTER=manual on shop wp cli version
t "wp under manual: none of them" not test -s "$W/log/site-wp.env"
before=$(clock $ID_SHOP)
ADA_CLOCK_ADAPTER=manual on shop set "2026-11-06T11:55:00+00:00"
t "set under manual: exit 0" is "$(rc)" 0
t "set under manual: still prints the computer-clock steps (so the E2 checks aren't vacuous)" os_steps
t "set under manual: no webhook line (Q9: local-linux only)" sh -c '[ "$(cat "$1" "$2" | grep -ci webhook)" = 0 ]' _ "$W/out" "$W/err"
t "set under manual: the clock file is not touched" is "$(clock $ID_SHOP)" "$before"
rm -f "$STATE"
if [ "$PHP" = 0 ]; then
  rm -f "$STATE"
  STUB_TZ=America/New_York on shop set "2026-11-06 11:55"
  t "set site-local: exit 0" is "$(rc)" 0
  t "set site-local: first line ada-clock: fake 2026-11-06 11:55 America/New_York (local-linux), written to ..." sh -c 'head -1 "$1" | grep -qF "ada-clock: fake 2026-11-06 11:55 America/New_York (local-linux), written to "' _ "$W/out"
  if [ "$PY" = 0 ]; then t "set site-local: the state file" jstate "$STATE" 1793984100 "2026-11-06 11:55" America/New_York; fi
  t "set site-local: the clock file is 1793984100 minus now" offset_near $ID_SHOP 1793984100
  STUB_TZ=America/New_York on shop set "2026-11-07 11:55"
  t "set again straight after: the offset is from real now, not added on" offset_near $ID_SHOP 1794070500
else
  skip "set with a site-local time (no php for the stand-in wp eval)"
fi
on shop set "2020-01-01T00:00:00Z"
t "set in the past: exit 0" is "$(rc)" 0
t "set in the past: a -<n>s offset" sh -c 'case "$(cat "$1")" in -*s) exit 0 ;; esac; exit 1' _ "$CF/$ID_SHOP"
t "set in the past: 1577836800 minus now" offset_near $ID_SHOP 1577836800
on shop reset
t "reset: exit 0" is "$(rc)" 0
t "reset: the clock file is kept, and says +0s" is "$(clock $ID_SHOP)" "+0s"
t "reset: the state file is deleted" not test -e "$STATE"
t "reset: first line ada-clock: real, deleted ..." sh -c 'head -1 "$1" | grep -q "^ada-clock: real, deleted "' _ "$W/out"
t "reset: (E2) no steps to change the computer's clock" not os_steps
on shop reset
t "reset with no state: exit 0, the clock file still +0s" sh -c '[ "$1" = 0 ] && [ "$2" = +0s ]' _ "$(rc)" "$(clock $ID_SHOP)"
t "reset with no state: (E2) no steps to change the computer's clock" not os_steps

# 5. status's wrapper and pool lines (the site's PHP is "restarted": its www.conf compiled).
if [ "$HTTP" = 0 ] && [ "$PY" = 0 ]; then
  hasline() { grep -q "$1" "$W/out" || { echo "no line $1 in: $(cat "$W/out")"; return 1; }; }
  noextra() { not grep -qE '^(wrapper|pool):' "$W/out"; }
  rm -f "$STATE"; on shop set "2026-11-06T11:55:00+00:00"; serve 1793966100 true; on shop status
  t "status after set ISO: first line exactly ada-clock: fake 2026-11-06 11:55:00 +00:00 (local-linux)" is "$(head -1 "$W/out")" 'ada-clock: fake 2026-11-06 11:55:00 +00:00 (local-linux)'
  t "status, wrapper and pool fine: line 2 is wrapper: installed <php-fpm path> (Q6)" wline_ok
  t "status, wrapper and pool fine: line 3 is pool: loaded <run dir www.conf> (Q6)" pline_ok
  t "status, wrapper and pool fine, site at the fake time: exit 0" is "$(rc)" 0
  if [ "$PHP" = 0 ]; then
    rm -f "$STATE"; STUB_TZ=America/New_York on shop set "2026-11-06 11:55"; serve 1793984100 true; on shop status
    t "status after set site-local: first line exactly ada-clock: fake 2026-11-06 11:55 America/New_York (local-linux)" is "$(head -1 "$W/out")" 'ada-clock: fake 2026-11-06 11:55 America/New_York (local-linux)'
    t "status after set site-local: exit 0" is "$(rc)" 0
    rm -f "$STATE"; on shop set "2026-11-06T11:55:00+00:00"; serve 1793966100 true
  fi
  # The offset rule (E3, Q2): the fake clock runs on, so an hour after set the site should be an hour
  # past the state's timestamp. Aging the state file stands in for the hour. manual is unchanged.
  rm -f "$STATE"; on shop set "2026-11-06T11:55:00+00:00"; age_state 3600
  serve $((1793966100 + 3600)) true; on shop status
  t "local-linux, an hour after set, the site an hour past the timestamp: exit 0 (offset rule)" is "$(rc)" 0
  t "local-linux, an hour after set: first line still the time that was set" is "$(head -1 "$W/out")" 'ada-clock: fake 2026-11-06 11:55:00 +00:00 (local-linux)'
  serve 1793966100 true; on shop status
  t "local-linux, an hour after set, the site still at the timestamp: exit 1" is "$(rc)" 1
  serve $((1793966100 + 7200)) true; on shop status
  t "local-linux, an hour after set, the site two hours past the timestamp: exit 1" is "$(rc)" 1
  rm -f "$STATE"; ADA_CLOCK_ADAPTER=manual on shop set "2026-11-06T11:55:00+00:00"; age_state 3600
  serve 1793966100 true; ADA_CLOCK_ADAPTER=manual on shop status
  t "manual, an hour after set, the site at the timestamp: exit 0 (unchanged)" is "$(rc)" 0
  serve $((1793966100 + 3600)) true; ADA_CLOCK_ADAPTER=manual on shop status
  t "manual, an hour after set, the site an hour past the timestamp: exit 1 (unchanged)" is "$(rc)" 1
  # install while a time is set writes the state's offset, not +0s (E5, Q3).
  rm -f "$STATE"; on shop set "2026-11-06T11:55:00+00:00"; age_state 3600; W1=$(state_offset)
  on shop install
  t "install while a time is set: exit 0" is "$(rc)" 0
  t "install while a time is set: the clock file is the state's timestamp minus set_at ($W1), not +0s (E5)" is "$(clock $ID_SHOP)" "$W1"
  rm -f "$STATE"; on shop set "2026-11-06T11:55:00+00:00"; serve 1793966100 true
  cp "$SB27/php-fpm.real" "$SB27/php-fpm"; on shop status
  t "the binary copied over php-fpm (a Local update): status says wrapper: missing" hasline '^wrapper: missing'
  t "... and to run ada-clock install" says -F 'ada-clock install'
  t "... exit 1" is "$(rc)" 1
  t "... the line names php-fpm and what to run" says -F "wrapper: missing $SB27/php-fpm, run ada-clock install"
  on shop install; on shop set "2026-11-06T11:55:00+00:00"; on shop status
  t "after install: the wrapper back" is_wrapper "$SB27/php-fpm"
  t "after install: the binary as php-fpm.real" cmp -s "$O27" "$SB27/php-fpm.real"
  t "after install (and set again): wrapper: installed <php-fpm path>" wline_ok
  t "after install (and set again): exit 0" is "$(rc)" 0
  mv "$SB27/php-fpm.real" "$SB27/php-fpm"; on shop status
  t "php-fpm.real moved back over php-fpm: wrapper: missing, exit 1" sh -c 'grep -q "^wrapper: missing" "$1" && [ "$2" = 1 ]' _ "$W/out" "$(rc)"
  on shop install; on shop set "2026-11-06T11:55:00+00:00"
  t "and install puts it back: the wrapper" is_wrapper "$SB27/php-fpm"
  t "and install puts it back: the binary as php-fpm.real" cmp -s "$O27" "$SB27/php-fpm.real"
  cp "$W/orig/run-shop" "$(rconf $ID_SHOP)"; on shop status
  t "the run dir's www.conf without the env lines: status says pool: needs restart" hasline '^pool: needs restart'
  t "... exit 1" is "$(rc)" 1
  t "... the line names the run dir's www.conf" says -F "pool: needs restart $(rconf $ID_SHOP)"
  rm -f "$(rconf $ID_SHOP)"; on shop status
  t "the run dir's www.conf not compiled at all: pool: needs restart, exit 1" sh -c 'grep -q "^pool: needs restart" "$1" && [ "$2" = 1 ]' _ "$W/out" "$(rc)"
  compile $ID_SHOP shop; on shop status
  t "after the restart: pool: loaded <run dir www.conf>" pline_ok
  t "after the restart: exit 0" is "$(rc)" 0
  cp "$SB27/php-fpm.real" "$SB27/php-fpm"; cp "$W/orig/run-shop" "$(rconf $ID_SHOP)"; on shop status
  t "both wrong: both lines, exit 1" sh -c 'grep -q "^wrapper: missing" "$1" && grep -q "^pool: needs restart" "$1" && [ "$2" = 1 ]' _ "$W/out" "$(rc)"
  on shop install; compile $ID_SHOP shop; on shop set "2026-11-06T11:55:00+00:00"
  serve "$(date +%s)" true; on shop status
  t "wrapper and pool fine, the site at real time while set: exit 1 (Unit A's rule)" is "$(rc)" 1
  on shop reset; serve "$(date +%s)" false; on shop status
  t "after reset: first line exactly ada-clock: real" is "$(head -1 "$W/out")" 'ada-clock: real'
  t "after reset: wrapper: installed <php-fpm path>" wline_ok
  t "after reset: pool: loaded <run dir www.conf>" pline_ok
  t "after reset: exit 0" is "$(rc)" 0
  cp "$SB27/php-fpm.real" "$SB27/php-fpm"; on shop status
  t "real time, wrapper missing: wrapper: missing, exit 1" sh -c 'grep -q "^wrapper: missing" "$1" && [ "$2" = 1 ]' _ "$W/out" "$(rc)"
  on shop install; compile $ID_SHOP shop
  ADA_CLOCK_ADAPTER=manual on shop status
  t "status under manual: no wrapper: or pool: line" noextra
else
  skip "status (needs python3 for the stand-in endpoint)"
fi

# 6. uninstall: shop, shop2 and other share 8.2.27; old is on 8.1.29.
LOCAL_URL=https://example.com on shop uninstall
t "uninstall for https://example.com: exit 2, nothing removed" sh -c '[ "$1" = 2 ] && [ -f "$2" ]' _ "$(rc)" "$CF/$ID_SHOP"
# Q5: uninstall refuses while a fake time is set, under any setup, and removes nothing.
on shop set "2026-11-06T11:55:00+00:00"
before=$(clock $ID_SHOP)
on shop uninstall
t "uninstall while a time is set: exit 2" is "$(rc)" 2
t "uninstall while a time is set: says to run ada-clock reset first" says -F 'ada-clock reset'
t "uninstall while a time is set: nothing removed" still_installed
t "uninstall while a time is set: the clock file is as set" is "$(clock $ID_SHOP)" "$before"
ADA_CLOCK_ADAPTER=manual on shop uninstall
t "uninstall under manual while a time is set: exit 2, the mu-plugin still there" sh -c '[ "$1" = 2 ] && test -f "$2"' _ "$(rc)" "$(wpp shop)/wp-content/mu-plugins/ada-clock.php"
on shop reset
t "after reset: uninstall is allowed again (the clock file is +0s)" is "$(clock $ID_SHOP)" "+0s"
on shop uninstall
t "uninstall shop: exit 0" is "$(rc)" 0
t "uninstall shop: www.conf.hbs exactly as before install" cmp -s "$W/orig/hbs-shop" "$(hbs shop)"
t "uninstall shop: its clock file gone" not test -e "$CF/$ID_SHOP"
t "uninstall shop: its mu-plugin gone" not test -e "$(wpp shop)/wp-content/mu-plugins/ada-clock.php"
t "uninstall shop: says to restart the site" says -i 'restart'
t "uninstall shop: the wrapper stays (shop2 and other still use 8.2.27)" is_wrapper "$SB27/php-fpm"
t "uninstall shop: php-fpm.real still the binary" cmp -s "$O27" "$SB27/php-fpm.real"
t "uninstall shop: the others' clock files stay" sh -c 'test -f "$1/$2" && test -f "$1/$3" && test -f "$1/$4"' _ "$CF" $ID_SHOP2 $ID_OTHER $ID_OLD
on shop2 uninstall
t "uninstall shop2: exit 0, www.conf.hbs as before, clock file gone" sh -c '[ "$1" = 0 ] && cmp -s "$2" "$3" && test ! -e "$4"' _ "$(rc)" "$W/orig/hbs-shop2" "$(hbs shop2)" "$CF/$ID_SHOP2"
t "uninstall shop2: the wrapper stays (other still uses 8.2.27)" is_wrapper "$SB27/php-fpm"
LOCAL_WP_PATH="$W/elsewhere/app/public" STUB_ABSPATH="$W/elsewhere/app/public" ADA_CLOCK_LOCAL_SITE=$ID_OTHER run "$P" uninstall
t "uninstall other (the last on 8.2.27): exit 0" is "$(rc)" 0
t "uninstall other: www.conf.hbs as before, less the proof's pool lines (E1): no ada-clock or env[FAKETIME_*] line" plain_as "$(hbs other)" "$W/orig/hbs-other"
t "uninstall other: its clock file gone" not test -e "$CF/$ID_OTHER"
t "uninstall other: the binary is php-fpm again" cmp -s "$O27" "$SB27/php-fpm"
t "uninstall other: no php-fpm.real left" not test -e "$SB27/php-fpm.real"
t "uninstall other: 8.1.29 still wrapped (old's clock file is for that version)" is_wrapper "$SB81/php-fpm"
# uninstall also removes the proof's pool lines from a site that only has them (E1): proofy has the
# proof's lines and the proof's clock file, no ada-clock block.
mkdir -p "$CF"; printf '+0s\n' > "$CF/$ID_PROOFY"
on proofy uninstall
t "uninstall a site with only the proof's pool lines: exit 0" is "$(rc)" 0
t "uninstall a site with only the proof's pool lines: they are gone, the rest as it was" plain_as "$(hbs proofy)" "$W/orig/hbs-proofy"
t "uninstall a site with only the proof's pool lines: its clock file gone" not test -e "$CF/$ID_PROOFY"
t "uninstall a site with only the proof's pool lines: 8.1.29 still wrapped (old uses it)" is_wrapper "$SB81/php-fpm"
STUB_ABSPATH=$(wpp shop2) run "$OLDP" uninstall
t "uninstall old: exit 0, www.conf.hbs as before" sh -c '[ "$1" = 0 ] && cmp -s "$2" "$3"' _ "$(rc)" "$W/orig/hbs-old" "$(hbs old)"
t "uninstall old: 8.1.29's binary is php-fpm again, no php-fpm.real" sh -c 'cmp -s "$1" "$2/php-fpm" && test ! -e "$2/php-fpm.real"' _ "$O81" "$SB81"
t "no clock files left" is "$(ls -A "$CF" 2>/dev/null)" ""
# Q4: set before install exits 2 "run ada-clock install first"; reset before install deletes the state
# file, writes no clock file, and says there is none.
on shop set "2026-11-06T11:55:00+00:00"
t "set before install: exit 2" is "$(rc)" 2
t "set before install: says to run ada-clock install" says -F 'ada-clock install'
t "set before install: ada-clock: on stderr" err_ac
t "set before install: changes nothing (no state file, no clock file)" nothing_changed
printf '{"timestamp": 1793966100, "local": "2026-11-06 11:55:00", "timezone": "+00:00", "setup": "local-linux", "set_at": %s}\n' "$(date +%s)" > "$STATE"
on shop reset
t "reset before install: exit 0" is "$(rc)" 0
t "reset before install: the state file is deleted" not test -e "$STATE"
t "reset before install: no clock file made" is "$(ls -A "$CF" 2>/dev/null)" ""
t "reset before install: says there is no clock file" says -Ei 'no clock file|clock file.*(none|missing|not found|does not exist|not exist)'
on shop uninstall
t "uninstall with nothing installed: changes nothing" nothing_changed
mkdir -p "$(wpp shop)/wp-content/mu-plugins"; printf '<?php\n/*\n * Plugin Name: Something else\n */\n' > "$(wpp shop)/wp-content/mu-plugins/ada-clock.php"; cp "$(wpp shop)/wp-content/mu-plugins/ada-clock.php" "$W/other-mu"
on shop uninstall
t "uninstall: another plugin's ada-clock.php is left alone" cmp -s "$W/other-mu" "$(wpp shop)/wp-content/mu-plugins/ada-clock.php"
rm -f "$(wpp shop)/wp-content/mu-plugins/ada-clock.php"
ADA_CLOCK_ADAPTER=manual on shop install
ADA_CLOCK_ADAPTER=manual on shop uninstall
t "uninstall under manual: exit 0, the mu-plugin gone" sh -c '[ "$1" = 0 ] && test ! -e "$2"' _ "$(rc)" "$(wpp shop)/wp-content/mu-plugins/ada-clock.php"
t "uninstall under manual: Local's php-fpm and the pool untouched" nothing_changed

# 6b. Refusals and words of install and uninstall that the checks above don't reach (API.md's uninstall
# row; the brief's last-site-out rule). Each case starts from nothing installed and ends back there.
# (i) A site found through LOCAL_WP_PATH whose run ID breaks [A-Za-z0-9_-] (S102): install exits 2
# naming the ID, and writes nothing. The site is added to sites.json for this case only.
ID_BAD=Bad.id
cp "$LOC/sites.json" "$W/sites.json.orig"
mksite $ID_BAD bad php-8.2.27+1; cp "$(hbs bad)" "$W/orig/hbs-bad"
{ sed '$d' "$W/sites.json.orig" | sed '$s/$/,/'; entry $ID_BAD bad "~/Local Sites/bad" 8.2.27; printf '\n}\n'; } > "$LOC/sites.json"
if [ "$PY" = 0 ]; then python3 -c 'import json, sys; json.load(open(sys.argv[1]))' "$LOC/sites.json" || echo "STOP the stand-in sites.json isn't JSON"; fi
on bad install
t "a path-found run ID that breaks the rule ($ID_BAD): install exits 2" is "$(rc)" 2
t "a path-found run ID that breaks the rule: ada-clock: on stderr" err_ac
t "a path-found run ID that breaks the rule: the message names the ID" grep -qF "$ID_BAD" "$W/err"
t "a path-found run ID that breaks the rule: no clock file, php-fpm unchanged, no php-fpm.real" nothing_changed
t "a path-found run ID that breaks the rule: its www.conf.hbs untouched" cmp -s "$W/orig/hbs-bad" "$(hbs bad)"
t "a path-found run ID that breaks the rule: no clock file for it" not test -e "$CF/$ID_BAD"
t "a path-found run ID that breaks the rule: no mu-plugin or state file in its site" sh -c 'test ! -e "$1/wp-content/mu-plugins/ada-clock.php" && test ! -e "$1/wp-content/ada-clock.json"' _ "$(wpp bad)"
cp "$W/sites.json.orig" "$LOC/sites.json"; rm -rf "$LOC/run/$ID_BAD" "$SITES/bad"
# (ii) php-fpm is ada-clock's wrapper but php-fpm.real isn't Local's binary (a script, a link, or
# missing): install and uninstall both exit 2 and change nothing.
on shop install
t "6b setup: install exits 0" is "$(rc)" 0
cp "$SB27/php-fpm" "$W/wrap"; cp "$(hbs shop)" "$W/after-shop-6b"; cp "$O27" "$W/realbin"
shop_kept() { cmp -s "$W/after-shop-6b" "$(hbs shop)" && [ -f "$CF/$ID_SHOP" ] && [ -f "$(wpp shop)/wp-content/mu-plugins/ada-clock.php" ] || { echo "shop's pool block, clock file or mu-plugin changed"; return 1; }; }
real_kept() { # <script|link|missing>: php-fpm.real is as the case left it
  case $1 in
    script)  cmp -s "$W/wrap" "$SB27/php-fpm.real" ;;
    link)    [ -L "$SB27/php-fpm.real" ] && cmp -s "$W/realbin" "$SB27/php-fpm.real" ;;
    missing) [ ! -e "$SB27/php-fpm.real" ] && [ ! -L "$SB27/php-fpm.real" ] ;;
  esac || { echo "php-fpm.real is not as left ($1)"; return 1; }
}
for k in script link missing; do
  rm -f "$SB27/php-fpm.real"
  case $k in script) cp "$W/wrap" "$SB27/php-fpm.real" ;; link) ln -s "$W/realbin" "$SB27/php-fpm.real" ;; esac
  for c in install uninstall; do
    on shop $c
    t "php-fpm is the wrapper, php-fpm.real is a $k: $c exits 2" is "$(rc)" 2
    t "php-fpm is the wrapper, php-fpm.real is a $k: $c, ada-clock: on stderr" err_ac
    t "php-fpm is the wrapper, php-fpm.real is a $k: $c leaves php-fpm alone" cmp -s "$W/wrap" "$SB27/php-fpm"
    t "php-fpm is the wrapper, php-fpm.real is a $k: $c leaves php-fpm.real alone" real_kept $k
    t "php-fpm is the wrapper, php-fpm.real is a $k: $c leaves the pool block, clock file and mu-plugin" shop_kept
  done
done
rm -f "$SB27/php-fpm.real"; cp "$W/realbin" "$SB27/php-fpm.real"
# (iii) php-fpm is Local's binary again (a Local update) while another site has a clock file: uninstall
# says wrapper: none at <php-fpm>, never "kept", and keeps php-fpm.real; the last site out deletes it and says so.
on shop2 install
t "6b setup: shop2 install exits 0" is "$(rc)" 0
cp "$O27" "$SB27/php-fpm"
on shop uninstall
t "php-fpm a binary again, shop2 still has a clock file: uninstall exits 0" is "$(rc)" 0
t "php-fpm a binary again, shop2 still uses it: says wrapper: none at <php-fpm>" grep -qxF "wrapper: none at $SB27/php-fpm" "$W/out"
t "php-fpm a binary again, shop2 still uses it: no wrapper: kept line" not grep -q '^wrapper: kept' "$W/out"
t "php-fpm a binary again, shop2 still uses it: php-fpm.real kept" cmp -s "$O27" "$SB27/php-fpm.real"
t "php-fpm a binary again, shop2 still uses it: php-fpm still the binary" cmp -s "$O27" "$SB27/php-fpm"
t "php-fpm a binary again, shop2 still uses it: shop's own pool block and clock file are gone" sh -c 'cmp -s "$1" "$2" && test ! -e "$3"' _ "$W/orig/hbs-shop" "$(hbs shop)" "$CF/$ID_SHOP"
t "php-fpm a binary again, shop2 still uses it: shop2's clock file stays" test -f "$CF/$ID_SHOP2"
on shop2 uninstall
t "the last site out, php-fpm a binary: uninstall exits 0" is "$(rc)" 0
t "the last site out, php-fpm a binary: says it deleted the old php-fpm.real" says -Ei 'delet(ed|ing).*(old|php-fpm\.real)'
t "the last site out, php-fpm a binary: php-fpm.real is gone" not test -e "$SB27/php-fpm.real"
t "the last site out, php-fpm a binary: php-fpm still the binary" cmp -s "$O27" "$SB27/php-fpm"
t "the last site out, php-fpm a binary: no wrapper: kept line" not grep -q '^wrapper: kept' "$W/out"
t "after (iii): Local is as at the start" nothing_changed
# (iv) php-fpm missing: uninstall says wrapper: none at <php-fpm> and leaves php-fpm.real.
on shop install
t "6b setup: install exits 0" is "$(rc)" 0
rm -f "$SB27/php-fpm"
on shop uninstall
t "php-fpm missing: uninstall exits 0" is "$(rc)" 0
t "php-fpm missing: says wrapper: none at <php-fpm>" grep -qxF "wrapper: none at $SB27/php-fpm" "$W/out"
t "php-fpm missing: no wrapper: kept line" not grep -q '^wrapper: kept' "$W/out"
t "php-fpm missing: php-fpm.real left" cmp -s "$O27" "$SB27/php-fpm.real"
t "php-fpm missing: php-fpm not made" not test -e "$SB27/php-fpm"
t "php-fpm missing: shop's pool block and clock file are gone" sh -c 'cmp -s "$1" "$2" && test ! -e "$3"' _ "$W/orig/hbs-shop" "$(hbs shop)" "$CF/$ID_SHOP"
rm -f "$SB27/php-fpm.real"; cp "$O27" "$SB27/php-fpm"
# (v) The wrapper shared with another site: uninstall says wrapper: kept <php-fpm>, still used by <id>.
on shop install; on shop2 install
on shop uninstall
t "the wrapper shared with shop2: uninstall exits 0" is "$(rc)" 0
t "the wrapper shared with shop2: says wrapper: kept <php-fpm>, still used by <shop2's run ID>" grep -qxF "wrapper: kept $SB27/php-fpm, still used by $ID_SHOP2" "$W/out"
t "the wrapper shared with shop2: no wrapper: none at line" not grep -q '^wrapper: none' "$W/out"
t "the wrapper shared with shop2: php-fpm still the wrapper, php-fpm.real the binary" sh -c 'cmp -s "$1" "$2/php-fpm.real"' _ "$O27" "$SB27"
t "the wrapper shared with shop2: php-fpm is still the wrapper" is_wrapper "$SB27/php-fpm"
on shop2 uninstall
t "the last site out, php-fpm the wrapper: the binary is php-fpm again, no php-fpm.real" sh -c 'cmp -s "$1" "$2/php-fpm" && test ! -e "$2/php-fpm.real"' _ "$O27" "$SB27"
t "the last site out, php-fpm the wrapper: no wrapper: kept line" not grep -q '^wrapper: kept' "$W/out"
t "after 6b: Local is as at the start" nothing_changed
else
  skip "sections 1b to 6 (no libfaketime here: Run 1 or Run 2 covers them)"
fi

# 7. Missing libfaketime: install and set exit 2 naming the package, and change nothing.
if [ "$LIBOK" = 1 ]; then
  on shop install
  t "no libfaketime: install exits 2" is "$(rc)" 2
  t "no libfaketime: install says sudo apt install faketime" grep -qF 'sudo apt install faketime' "$W/err"
  t "no libfaketime: install changes nothing in Local" nothing_changed
  on shop set "2026-11-06T11:55:00+00:00"
  t "no libfaketime: set exits 2" is "$(rc)" 2
  t "no libfaketime: set says sudo apt install faketime" grep -qF 'sudo apt install faketime' "$W/err"
  t "no libfaketime: set writes no state or clock file" nothing_changed
  ADA_CLOCK_ADAPTER=manual on shop install
  t "no libfaketime: install under manual still works" is "$(rc)" 0
  ADA_CLOCK_ADAPTER=manual on shop uninstall
  # 8. Found through ldconfig -p (a container only: stand-ins for ldconfig wherever it may be called from).
  SO=; for f in /lib/libz.so.1 /usr/lib/libz.so.1; do [ -f "$f" ] && { SO=$f; break; }; done
  if [ "$SYS" = 1 ] && [ -n "$SO" ]; then
    mkdir -p "$W/lib"; cp -L "$SO" "$W/lib/libfaketime.so.1"; export STUB_LDLIB=$W/lib/libfaketime.so.1
    printf '#!/bin/sh\ncase "$*" in *-p*) printf "2 libs found in cache /etc/ld.so.cache\\n\\tlibz.so.1 (libc6,x86-64) => /lib/libz.so.1\\n\\tlibfaketime.so.1 (libc6,x86-64) => %%s\\n" "$STUB_LDLIB" ;; esac\nexit 0\n' > "$W/bin/ldconfig"; chmod 755 "$W/bin/ldconfig"
    for l in /sbin/ldconfig /usr/sbin/ldconfig /usr/bin/ldconfig; do
      [ -e "$l" ] && mv "$l" "$l.ada-away"; mkdir -p "${l%/*}"; cp "$W/bin/ldconfig" "$l"
    done
    on shop install
    t "libfaketime listed by ldconfig -p: install exits 0" is "$(rc)" 0
    on shop wp cli version
    t "libfaketime listed by ldconfig -p: wp's LD_PRELOAD is that path" sh -c 'case "$1" in *"$2"*) exit 0 ;; esac; exit 1' _ "$(envof LD_PRELOAD)" "$STUB_LDLIB"
    on shop uninstall
    t "and uninstall puts the binary back" sh -c 'cmp -s "$1" "$2/php-fpm" && test ! -e "$2/php-fpm.real"' _ "$O27" "$SB27"
    for l in /sbin/ldconfig /usr/sbin/ldconfig /usr/bin/ldconfig; do rm -f "$l"; [ -e "$l.ada-away" ] && mv "$l.ada-away" "$l"; done
    rm -f "$W/bin/ldconfig"
  else
    skip "libfaketime through ldconfig -p (needs ALLOW_SYSTEM_STANDINS=1 and a libz to stand in)"
  fi
else
  skip "sections 7 and 8 (libfaketime is installed here: Run 3 covers them)"
fi

# 9. Nothing ran sudo or tried to set the clock; the real Local install is as it was.
t "nothing ran sudo or tried to set the clock" not test -s "$W/keep/clock"
t "the real Local install, clock files and site configs are untouched" is "$(guard)" "$G0"

echo
echo "$fails failed"
[ "$fails" = 0 ]
```

## Expected

Every line `PASS`, `0 failed`, exit 0, in each run; `SKIP` only where the run's line in Steps
says so (Run 1 skips 7 and 8; Run 2 the faked-time effects, 7 and 8; Run 3 sections 1b to 6, and
`dash`). The source of each expectation is the brief (Decisions; Definition of done 1, 3, 4, 6, 7,
8, 9, 10, 11; the Amendments row of 28 Oct, agreed by Neil: `install` and `uninstall` remove the
proof's pool lines, one block), Ada's rulings and approved Builder answers in `BRIEF.md` (E2: no
computer-clock steps under `local-linux`; E3/Q2: `status` expects now plus `timestamp` minus
`set_at`; E5/Q3: `install` writes the state's offset, else `+0s`; Q4: `set` exits 2 before `install`,
`reset` writes no clock file; Q5: `uninstall` exits 2 while a state file exists; Q6: `status`'s
`wrapper: installed <path>` and `pool: loaded <path>`; Q7: the PHP build from the run folder's
compiled `php.ini`; Q9: the webhook line under `local-linux` only), Unit A's `ada-clock` entry in
`API.md` (state file keys, first lines, exit codes) and `API.md`'s Every script (exit 2 with
`ada-clock:` on stderr). With the broken copies (Step 5), section 3's "a site with no clock file"
checks print `FAIL`, and so do the matching checks of section 6b. Section 6b's sources: API.md's
`uninstall` row (exit 2, nothing changed, when `php-fpm.real` is missing or not Local's binary; a
stale `php-fpm.real` deleted unless a site still uses it), SYSTEM.md's "The wrapper" row (a script,
a link or a missing file exits 2, untouched), and the brief's last-site-out rule (`wrapper: kept
<path>, still used by <ids>`). The `wrapper: none at <php-fpm>` words and the run ID rule are from
Claudia's review feedback and `ada-clock`'s settings in API.md.

## Observe at

The script's output and exit code.

## Regression

Unit A's `command-in-a-scratch-repo.md` still `0 failed` under both bashes: unset and `manual`
behave as before. Sites on another PHP version, and another build of the same version, are never
touched (section 2); a site with no clock file runs the real binary with nothing added (section 3).
