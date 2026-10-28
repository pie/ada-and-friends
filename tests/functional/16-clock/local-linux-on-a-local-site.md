# On Linux with Local, `ada-clock` under `local-linux` moves one site's page loads, WP-CLI and database to the fake time with no restart, and back on `reset`; a second site on the same PHP stays on real time; a Local update is caught by `status`; `uninstall` undoes it all

- Area: 16 Clock (`ada-clock`)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219974872551364
- Automate as: none (the Steps block is a self-checking shell script run against real Local sites; it pauses for a person to restart sites in Local)
- Last run: 2026-10-28, all PASS, 0 failed on V&Me and test.local (see the PR)

## Precondition

- Linux, Local, and `faketime` installed (`/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1`).
- An onboarded project with a running Local site, e.g. V&Me (run ID `rh2ziZudA`): its
  `.claude/team/local.env` has `LOCAL_URL`, `LOCAL_WP_PATH` and `LOCAL_WP` (`vme-wp`). The block
  sets `ADA_CLOCK_ADAPTER=local-linux` itself.
- V&Me's `PROJECT.md` (Local copies) safety checks pass and its background runners are frozen:
  the block loads pages, which can start WP-Cron on a copy of real data.
- No `wp-content/ada-clock.json` in the site (one left over means an earlier run didn't finish).
- For the Definition of Done as written, the proof's wrapper is installed: `head -2` of the
  site's `php-fpm` says `ada-clock proof`. The block says what it found and goes on either way.
- A **second** running Local site on the **same PHP version** (V&Me is on 8.2.27; Local's
  `test.local`, run ID `QQehHWkGz`, is one), with no `ada-clock` on it: no
  `~/.config/ada-clock/<its run ID>`, no `wp-content/mu-plugins/ada-clock.php`. The block adds
  and then removes `ada-clock` on it; it needs no WP-CLI for it.
- This checkout (`REPO`); `curl`, `python3` 3.9 or later (`zoneinfo`). The computer's clock is
  never changed.

## Steps

1. Save the block below as `check-local-linux.sh` in a scratch folder (not in either repo).
2. From the project's root (e.g. `~/Repos/vandme`):
   `REPO=<this checkout> SECOND_URL=http://test.local SECOND_WP_PATH="$HOME/Local Sites/test/app/public" bash <scratch>/check-local-linux.sh`
3. It pauses four times. Each time, in Local, restart the site(s) it names (Stop site, then Start
   site), wait until Local shows it running, and press Enter.
4. If it stops part way (`STOP`, Ctrl-C or a crash), it removes its probe pages and resets the
   fake time. To undo the rest: `ada-clock uninstall` from the project, and
   `LOCAL_URL=<second URL> LOCAL_WP_PATH=<second path> ada-clock uninstall` (both with
   `ADA_CLOCK_ADAPTER=local-linux`, the checkout's script by path), then restart both sites.
5. Afterwards, Unit A's `mu-plugin-on-a-local-site.md` with `ADA_CLOCK_ADAPTER=manual` (it puts
   the mu-plugin back, which phase 6 removed).

```bash
#!/usr/bin/env bash
# ada-clock's local-linux setup on real Local sites: install over the proof; set and reset with no
# restart (page loads, the cookie, the endpoint, WP-CLI, MySQL); a second site on the same PHP
# version stays real; a Local update simulated; pool: needs restart; uninstall, last site out.
# Run from the onboarded project's root. Pauses four times for a restart in Local. Prints
# PASS/FAIL per check (a FAIL shows what was seen); exits 1 if any FAIL, 2 if a precondition fails.
# Env: REPO (this checkout); SECOND_URL, SECOND_WP_PATH (the second site); SECOND_WP (optional).
set -u
AC=${REPO:?set REPO to this checkout}/plugins/ada/bin/ada-clock
: "${SECOND_URL:?set SECOND_URL to a second Local site on the same PHP version}" "${SECOND_WP_PATH:?set SECOND_WP_PATH}"
cd "$(git rev-parse --show-toplevel)" || exit 2
export ADA_CLOCK_ADAPTER=local-linux
unset ADA_CLOCK_LOCAL_SITE
fails=0
ok()   { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()    { name=$1; shift; if seen=$("$@" 2>&1); then ok 0 "$name"; else ok 1 "$name"; [ -n "$seen" ] && printf '%s\n' "$seen" | head -5 | sed 's/^/     /'; fi; }
not()  { ! "$@"; }
is()   { [ "$1" = "$2" ] || { echo "got '$1', want '$2'"; return 1; }; }
stop() { echo "STOP $*"; exit 2; }
pause() { printf '\n>>> %s\n>>> Press Enter when Local shows it running. ' "$*"; read -r _ < /dev/tty || stop "no terminal to pause on"; echo; }
val()  { sed -n "s/^$1=//p" .claude/team/local.env | head -1 | sed -e 's/[[:space:]][[:space:]]*#.*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"; }
URL=${LOCAL_URL:-$(val LOCAL_URL)}; URL=${URL%/}
WPP=${LOCAL_WP_PATH:-$(val LOCAL_WP_PATH)}; WPP=${WPP%/}
SURL=${SECOND_URL%/}; SWPP=${SECOND_WP_PATH%/}
ac()  { bash "$AC" "$@"; }
ac2() { LOCAL_URL=$SURL LOCAL_WP_PATH=$SWPP LOCAL_WP=${SECOND_WP:-false} bash "$AC" "$@"; }
STATE=$WPP/wp-content/ada-clock.json; MU=$WPP/wp-content/mu-plugins/ada-clock.php
SMU=$SWPP/wp-content/mu-plugins/ada-clock.php; CF=$HOME/.config/ada-clock
command -v curl >/dev/null && command -v python3 >/dev/null || stop "needs curl and python3"
[ -n "$URL" ] && [ -d "$WPP/wp-content" ] || stop "LOCAL_URL or LOCAL_WP_PATH not set, or no wp-content there"
[ -d "$SWPP/wp-content" ] || stop "no wp-content in SECOND_WP_PATH ($SWPP)"

# site <wp path>: run ID, PHP version and site folder from Local's sites.json, tab-separated.
site() {
  python3 - "$1" <<'PY'
import json, os, sys
want = os.path.realpath(sys.argv[1])
d = json.load(open(os.path.expanduser('~/.config/Local/sites.json')))
hits = [(s.get('id', k), s.get('services', {}).get('php', {}).get('version', ''), os.path.realpath(os.path.expanduser(s.get('path', ''))))
        for k, s in d.items() if os.path.join(os.path.realpath(os.path.expanduser(s.get('path', ''))), 'app', 'public') == want]
if len(hits) != 1:
    sys.exit(1)
print('\t'.join(hits[0]))
PY
}
IFS=$'\t' read -r ID VER SDIR <<EOF
$(site "$WPP")
EOF
IFS=$'\t' read -r SID SVER SSDIR <<EOF
$(site "$SWPP")
EOF
[ -n "${ID:-}" ] || stop "no Local site in ~/.config/Local/sites.json has $WPP as its app/public"
[ -n "${SID:-}" ] || stop "no Local site in ~/.config/Local/sites.json has $SWPP as its app/public"
[ "$ID" != "$SID" ] || stop "SECOND_WP_PATH is the same site"
[ "$VER" = "$SVER" ] || stop "the second site runs PHP $SVER and this one $VER: pick a second site on $VER"
SB=
for d in "$HOME/.config/Local/lightning-services/php-$VER" "$HOME/.config/Local/lightning-services/php-$VER+"*; do
  [ -d "$d/bin/linux/sbin" ] || continue
  [ -z "$SB" ] || stop "more than one Local folder for PHP $VER"
  SB=$d/bin/linux/sbin
done
[ -n "$SB" ] || stop "no ~/.config/Local/lightning-services/php-$VER folder"
FPM=$SB/php-fpm; REAL=$SB/php-fpm.real
HBS="$SDIR/conf/php/php-fpm.d/www.conf.hbs"; SHBS="$SSDIR/conf/php/php-fpm.d/www.conf.hbs"
[ -f "$HBS" ] && [ -f "$SHBS" ] || stop "a site's conf/php/php-fpm.d/www.conf.hbs is missing"
[ -e "$STATE" ] && stop "a state file is left from an earlier run: $STATE"
[ -e "$CF/$SID" ] || [ -e "$SMU" ] && stop "the second site already has ada-clock on it"
if [ "$(head -c 2 "$FPM")" = '#!' ]; then
  head -2 "$FPM" | grep -q 'ada-clock' || stop "php-fpm is a script that isn't ada-clock's: $(head -2 "$FPM" | tail -1)"
  [ -f "$REAL" ] || stop "php-fpm is a wrapper but there is no php-fpm.real"
  BIN=$REAL
else
  BIN=$FPM
fi
CK=$(cksum < "$BIN")
SAVE=$(mktemp -d "${TMPDIR:-/tmp}/ada-clock-local-linux.XXXXXX")
cp "$HBS" "$SAVE/site.www.conf.hbs"; cp "$SHBS" "$SAVE/second.www.conf.hbs"
[ "$BIN" = "$REAL" ] && cp "$FPM" "$SAVE/php-fpm.before"
TZS=$(ac wp eval 'echo wp_timezone_string();' 2>/dev/null | tail -1)
[ -n "$TZS" ] || stop "ada-clock wp eval doesn't answer: check LOCAL_WP and that the site is running"
echo "site $URL, run ID $ID, PHP $VER, timezone $TZS; second $SURL, run ID $SID; $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo "php-fpm at the start: $(if [ "$BIN" = "$FPM" ]; then echo 'the binary'; else head -2 "$FPM" | tail -1; fi)"
echo "copies of the files before the run: $SAVE"

# T: 2026-11-06 11:55 on the site's clock, as UTC. sday <timestamp>: that day in the site's timezone.
pyt() {
  python3 - "$TZS" "$@" <<'PY'
import datetime, sys, zoneinfo
tz = sys.argv[1]
if tz[:1] in '+-':
    h, m = tz[1:].split(':')
    z = datetime.timezone((-1 if tz[0] == '-' else 1) * datetime.timedelta(hours=int(h), minutes=int(m)))
else:
    z = zoneinfo.ZoneInfo(tz)
if sys.argv[2] == 'T':
    print(int(datetime.datetime(2026, 11, 6, 11, 55, tzinfo=z).timestamp()))
else:
    print(datetime.datetime.fromtimestamp(int(sys.argv[2]), z).strftime('%Y-%m-%d'))
PY
}
T=$(pyt T) || stop "python3 can't work out the fake time for $TZS (needs 3.9+ with zoneinfo)"

# Probe pages: time() and whether libfaketime is loaded in the PHP serving the request; with ?wp=1,
# also WordPress's wp_date() and MySQL's CURDATE() after the mu-plugin's SET timestamp.
PN=ada-clock-probe-$$-$RANDOM.php
cat > "$WPP/$PN" <<'EOF'
<?php
// Written by ada-clock's local-linux check, which deletes it when it ends.
$out = array( time(), false !== strpos( (string) @file_get_contents( '/proc/self/maps' ), 'libfaketime' ) ? 'faketime' : 'none' );
if ( ! empty( $_GET['wp'] ) ) {
	require __DIR__ . '/wp-load.php';
	global $wpdb;
	$out[] = wp_date( 'D Y-m-d H:i' );
	$out[] = $wpdb->get_var( 'SELECT CURDATE()' );
}
header( 'Content-Type: text/plain' );
echo implode( '|', $out );
EOF
cp "$WPP/$PN" "$SWPP/$PN"
O=$(mktemp)
cleanup() { [ -e "$STATE" ] && ac reset >/dev/null 2>&1; rm -f "$WPP/$PN" "$SWPP/$PN" "$O"; echo "probe pages removed; copies of the files before the run: $SAVE"; }
trap cleanup EXIT
# probe <url> [wp]: sets PT (time), PM (faketime|none), PD (wp_date), PC (CURDATE).
probe() { PT= PM= PD= PC=; IFS='|' read -r PT PM PD PC <<EOF
$(curl -sk "$1/$PN${2:+?wp=1}")
EOF
}
near() { case "$1$2" in *[!0-9-]*|'') echo "not numbers: '$1' '$2'"; return 1 ;; esac; d=$(( $1 - $2 )); [ "${d#-}" -le "$3" ] || { echo "$1 is $d s from $2"; return 1; }; }
fakenow() { echo $(( T + $(date +%s) - S )); }
# ep: the endpoint's now and fake, as "EN EF".
ep() { read -r EN EF <<EOF
$(curl -sk "$URL/?rest_route=/ada-clock/v1/time" | python3 -c 'import json, sys
try:
    d = json.load(sys.stdin); print(d["now"], "true" if d["fake"] is True else "false")
except Exception:
    print("none none")')
EOF
}
cookie() { curl -sk -o /dev/null -D - "$URL/" | tr -d '\r' | grep -i '^set-cookie: *ada_clock=' | tail -1 | sed 's/^[^=]*=\([^;]*\).*$/\1/'; }
starts() { case $1 in "$2"*) return 0 ;; esac; echo "'$1' doesn't start '$2'"; return 1; }

# The pool block and the wrapper (as in local-linux-in-a-scratch-home.md).
marks()    { grep -E '^[[:space:]]*(;|#|\{\{!)' "$1" | grep 'ada-clock' | grep -vc 'ada-clock proof'; }
inblock()  { awk '/^[[:space:]]*(;|#|\{\{!)/ && /ada-clock/ && !/ada-clock proof/ { n++; next } n == 1 { print }' "$1"; }
outblock() { awk '/^[[:space:]]*(;|#|\{\{!)/ && /ada-clock/ && !/ada-clock proof/ { n++; next } n != 1 { print }' "$1"; }
envval()   { sed -n "s/^[[:space:]]*env\[$1\][[:space:]]*=[[:space:]]*//p" | sed -e 's/[[:space:]]*$//' -e 's/^"\(.*\)"$/\1/'; }
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
no_proof() { ! grep -q 'ada-clock proof' "$1" || { echo "the proof's comment line is still in $1"; return 1; }; }
envonce() {
  for v in FAKETIME_TIMESTAMP_FILE FAKETIME_NO_CACHE FAKETIME_DONT_FAKE_MONOTONIC; do
    c=$(grep -c "^[[:space:]]*env\[$v\]" "$1"); [ "$c" = 1 ] || { echo "$c env[$v] lines in $1, want 1"; return 1; }
  done
}
# appended <file> <original>: the original is kept, less the proof's pool lines if it had them (the
# amendment: install takes them over), and nothing but blank lines is outside that and the block.
# With no proof lines in the original it is kept byte for byte at the start.
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
# status's second and third lines when all is well (Q6).
RCONF=$HOME/.config/Local/run/$ID/conf/php/php-fpm.d/www.conf
lines_ok() { is "$(sed -n 2p "$O")" "wrapper: installed $FPM" && is "$(sed -n 3p "$O")" "pool: loaded $RCONF"; }
state_offset() { python3 -c 'import json, sys; d = json.load(open(sys.argv[1])); n = d["timestamp"] - d["set_at"]; print("%s%ds" % ("+" if n >= 0 else "-", abs(n)))' "$STATE"; }
is_wrapper() {
  [ "$(head -c 2 "$1")" = '#!' ] || { echo "$1 is not a script"; return 1; }
  head -2 "$1" | grep 'ada-clock' | grep -qv 'proof' || { echo "head -2: $(head -2 "$1")"; return 1; }
  [ -x "$1" ] || { echo "$1 is not executable"; return 1; }
  sh -n "$1"
}
real_ok() { is "$(cksum < "$REAL" 2>/dev/null)" "$CK"; }
offset_near() {
  v=$(cat "$CF/$ID" 2>/dev/null); case $v in +*s|-*s) ;; *) echo "clock file says '$v'"; return 1 ;; esac
  n=${v%s}; n=${n#+}; e=$(( $1 - $(date +%s) )); d=$(( n - e )); [ "${d#-}" -le 5 ] || { echo "clock file says $v, want about $e"; return 1; }
}

echo "== Phase 1: install on this site (DoD 1)"
ac install > "$O" 2>&1; t "install exits 0" is $? 0
t "head -2 php-fpm: the ada-clock wrapper" is_wrapper "$FPM"
t "php-fpm.real is the same binary as before" real_ok
t "www.conf.hbs: one ada-clock block, the three env lines" block_ok "$HBS" "$ID"
t "www.conf.hbs: everything before it as it was, less the proof's pool lines (E1, the amendment)" appended "$HBS" "$SAVE/site.www.conf.hbs"
t "www.conf.hbs: each env line once in the file (the proof's are gone, not doubled)" envonce "$HBS"
t "the clock file says +0s" is "$(cat "$CF/$ID" 2>/dev/null)" "+0s"
t "install says to restart the site" grep -qi 'restart' "$O"
cp "$HBS" "$SAVE/site.after-install"
ac install > "$O" 2>&1; t "install again exits 0" is $? 0
t "run twice: www.conf.hbs exactly as after the first (still one block)" cmp "$SAVE/site.after-install" "$HBS"
t "run twice: still the wrapper, the same binary as php-fpm.real" sh -c '[ "$(head -c 2 "$1")" = "#!" ] && [ "$(cksum < "$2")" = "$3" ]' _ "$FPM" "$REAL" "$CK"
pause "Restart BOTH sites in Local: this one ($URL) and the second ($SURL)."

echo "== Phase 2: set, with no restart (DoD 2, 3, 5, 10)"
probe "$SURL"
t "second site, no clock file: a page load at real time" near "$PT" "$(date +%s)" 10
t "second site, no clock file: libfaketime not loaded in its PHP" is "$PM" none
probe "$URL"
t "this site, +0s: libfaketime loaded in its PHP" is "$PM" faketime
t "this site, +0s: a page load at real time" near "$PT" "$(date +%s)" 10
ac status > "$O" 2>&1; rc=$?
t "status before set: ada-clock: real, exit 0" sh -c '[ "$(head -1 "$1")" = "ada-clock: real" ] && [ "$2" = 0 ]' _ "$O" "$rc"
ac set "2026-11-06 11:55" > "$O" 2>&1; rc=$?; S=$(date +%s)
t "set exits 0" is "$rc" 0
t "set: one webhook warning line, naming Stripe" sh -c '[ "$(grep -ci webhook "$1")" = 1 ] && grep -i webhook "$1" | grep -qi stripe' _ "$O"
t "set: the clock file is an offset, $T minus now" offset_near "$T"
ac status > "$O" 2>&1; rc=$?
t "status: first line exactly ada-clock: fake 2026-11-06 11:55 $TZS (local-linux)" is "$(head -1 "$O")" "ada-clock: fake 2026-11-06 11:55 $TZS (local-linux)"
t "status: exit 0" is "$rc" 0
t "status: line 2 wrapper: installed <php-fpm>, line 3 pool: loaded <run dir www.conf> (Q6)" lines_ok
ep
t "endpoint: now is the fake time, no restart" near "$EN" "$(fakenow)" 15
t "endpoint: fake true" is "$EF" true
t "a page response's ada_clock cookie is the fake time" near "$(cookie)" "$(fakenow)" 15
probe "$URL" wp
t "page load: PHP's time() is the fake time" near "$PT" "$(fakenow)" 15
t "page load: wp_date says Fri 2026-11-06 11:5x" starts "$PD" "Fri 2026-11-06 11:5"
t "page load: SELECT CURDATE() says 2026-11-06" is "$PC" 2026-11-06
t "ada-clock wp eval wp_date: Fri 2026-11-06 11:5x" starts "$(ac wp eval 'echo wp_date("D Y-m-d H:i");' 2>/dev/null | tail -1)" "Fri 2026-11-06 11:5"
t "ada-clock wp eval time(): the fake time" near "$(ac wp eval 'echo time();' 2>/dev/null | tail -1)" "$(fakenow)" 15
probe "$SURL"
t "second site, while this one is set: a page load at real time" near "$PT" "$(date +%s)" 10
t "second site, while this one is set: libfaketime not loaded" is "$PM" none
sleep 5; ep
t "five seconds on, the fake clock has run on five seconds (no drift)" near "$EN" "$(fakenow)" 15

echo "== Phase 3: reset, with no restart (DoD 4)"
ac reset > "$O" 2>&1; t "reset exits 0" is $? 0
t "the clock file says +0s" is "$(cat "$CF/$ID" 2>/dev/null)" "+0s"
t "the state file is gone" not test -e "$STATE"
ep
t "endpoint: real time, fake false" sh -c 'd=$(( $1 - $(date +%s) )); [ "${d#-}" -le 10 ] && [ "$2" = false ]' _ "$EN" "$EF"
probe "$URL" wp
t "page load: real time" near "$PT" "$(date +%s)" 10
t "page load: SELECT CURDATE() is today on the site's clock" is "$PC" "$(pyt "$(date +%s)")"
t "ada-clock wp eval time(): real time" near "$(ac wp eval 'echo time();' 2>/dev/null | tail -1)" "$(date +%s)" 10
ac status > "$O" 2>&1; rc=$?
t "status: ada-clock: real, exit 0" sh -c '[ "$(head -1 "$1")" = "ada-clock: real" ] && [ "$2" = 0 ]' _ "$O" "$rc"

echo "== Phase 4: a Local update replaces the wrapper (DoD 6)"
ac set "2026-11-06 11:55" >/dev/null 2>&1; S=$(date +%s)
cp "$REAL" "$FPM"
ac status > "$O" 2>&1; rc=$?
t "status says wrapper: missing" grep -q '^wrapper: missing' "$O"
t "status says to run ada-clock install" grep -qF 'ada-clock install' "$O"
t "status exits 1" is "$rc" 1
pause "Restart this site ($URL) in Local."
probe "$URL"
t "this site now runs on real time" near "$PT" "$(date +%s)" 10
t "libfaketime is not loaded" is "$PM" none
ac status > /dev/null 2>&1; t "status still exits 1" is $? 1
ac install > "$O" 2>&1; t "install exits 0" is $? 0
t "install puts the wrapper back" is_wrapper "$FPM"
t "the same binary as php-fpm.real" real_ok
t "install while a time is set: the clock file is the state's offset, $(state_offset) (E5, Q3)" is "$(cat "$CF/$ID" 2>/dev/null)" "$(state_offset)"
ac reset >/dev/null 2>&1
pause "Restart this site ($URL) in Local."
probe "$URL"
t "after the restart: libfaketime loaded again, real time (+0s)" sh -c '[ "$1" = faketime ] && d=$(( $2 - $(date +%s) )) && [ "${d#-}" -le 10 ]' _ "$PM" "$PT"
ac status > "$O" 2>&1; rc=$?
t "status: ada-clock: real, exit 0" sh -c '[ "$(head -1 "$1")" = "ada-clock: real" ] && [ "$2" = 0 ]' _ "$O" "$rc"
t "status: wrapper: installed <php-fpm>, pool: loaded <run dir www.conf>" lines_ok

echo "== Phase 5: install on the second site"
cp "$FPM" "$SAVE/wrapper.before-second"
ac2 install > "$O" 2>&1; t "second site: install exits 0" is $? 0
t "second site: its clock file says +0s" is "$(cat "$CF/$SID" 2>/dev/null)" "+0s"
t "second site: one block in its www.conf.hbs" block_ok "$SHBS" "$SID"
t "second site: the rest of its www.conf.hbs as it was" appended "$SHBS" "$SAVE/second.www.conf.hbs"
t "second site: php-fpm is the same wrapper, php-fpm.real the same binary" sh -c 'cmp -s "$1" "$2" && [ "$(cksum < "$3")" = "$4" ]' _ "$SAVE/wrapper.before-second" "$FPM" "$REAL" "$CK"
ac2 status > "$O" 2>&1; rc=$?
t "second site, not restarted: status says pool: needs restart" grep -q '^pool: needs restart' "$O"
t "second site, not restarted: status exits 1" is "$rc" 1

echo "== Phase 6: uninstall, this site then the second (DoD 9)"
ac set "2026-11-06 11:55" >/dev/null 2>&1
ac uninstall > "$O" 2>&1; rc=$?
t "uninstall while a time is set: exit 2, says to run ada-clock reset first (Q5)" sh -c '[ "$1" = 2 ] && grep -qF "ada-clock reset" "$2"' _ "$rc" "$O"
t "uninstall while a time is set: nothing removed" sh -c 'test -f "$1" && test -f "$2" && test -f "$3"' _ "$CF/$ID" "$MU" "$STATE"
ac reset >/dev/null 2>&1
ac uninstall > "$O" 2>&1; t "uninstall exits 0" is $? 0
t "www.conf.hbs as before install, less the proof's pool lines (E1): no ada-clock or env[FAKETIME_*] line" plain_as "$HBS" "$SAVE/site.www.conf.hbs"
t "the clock file is gone" not test -e "$CF/$ID"
t "the mu-plugin is gone" not test -e "$MU"
t "uninstall says to restart the site" grep -qi 'restart' "$O"
t "the wrapper stays: the second site still uses it" is_wrapper "$FPM"
t "php-fpm.real still the binary" real_ok
ac2 uninstall > "$O" 2>&1; t "second site: uninstall exits 0" is $? 0
t "second site: www.conf.hbs exactly as before" cmp "$SAVE/second.www.conf.hbs" "$SHBS"
t "second site: its clock file and mu-plugin gone" sh -c 'test ! -e "$1" && test ! -e "$2"' _ "$CF/$SID" "$SMU"
t "the last site out: php-fpm is the binary again" is "$(cksum < "$FPM")" "$CK"
t "no php-fpm.real left" not test -e "$REAL"
pause "Restart BOTH sites in Local: $URL and $SURL."
probe "$URL"
t "this site: real time, libfaketime not loaded" sh -c '[ "$1" = none ] && d=$(( $2 - $(date +%s) )) && [ "${d#-}" -le 10 ]' _ "$PM" "$PT"
probe "$SURL"
t "second site: real time, libfaketime not loaded" sh -c '[ "$1" = none ] && d=$(( $2 - $(date +%s) )) && [ "${d#-}" -le 10 ]' _ "$PM" "$PT"

echo
echo "$fails failed"
[ "$fails" = 0 ]
```

## Expected

Every line `PASS`, `0 failed`, exit 0. The source of each expectation is the brief (Outcome,
Decisions, Definition of done 1 to 6, 9 and 10; the Amendments row of 28 Oct, agreed by Neil:
`install` and `uninstall` remove the proof's pool lines; Ada's rulings and the approved Builder
answers: E5/Q3 `install` writes the state's offset, Q5 `uninstall` exits 2 while a time is set, Q6
`status`'s `wrapper: installed` and `pool: loaded` lines) and Unit A's `ada-clock` entry in `API.md`
(the `status` first line, the endpoint, the `ada_clock` cookie, the state file). "Give or take the
seconds since" in the Definition of Done is read as within 15 seconds of the fake time run on
from `set`.

## Observe at

The script's output; `head -2 <Local's PHP>/bin/linux/sbin/php-fpm`; the sites' `www.conf.hbs`;
`~/.config/ada-clock/`; Local's site list (both sites running after each pause).

## Regression

Unit A's `mu-plugin-on-a-local-site.md`, run after this with `ADA_CLOCK_ADAPTER=manual`, still
passes on the same site. Other Local sites on the same PHP version, with no clock file, stay on
real time with libfaketime not loaded (phase 2). The computer's clock is untouched.
