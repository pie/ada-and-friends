# On a Local site, `ada-clock` installs its mu-plugin, and while a fake time is set the site's database, `ada_clock` cookie and time endpoint follow PHP's clock and logins last ten years; on a site that isn't local the mu-plugin does nothing

- Area: 16 Clock (`ada-clock`)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219974873020681
- Automate as: none (the Steps block is a self-checking shell script run against a real Local site; the browser steps are for a person)
- Last run: 2026-10-28, block 32 PASS, 0 FAIL, 0 SKIP on V&Me local; browser steps 4, 5 not verified (see the PR)

## Precondition

- An onboarded project with a running Local WordPress site, e.g. V&Me: its
  `.claude/team/local.env` has `LOCAL_URL` (a `.local` site), `LOCAL_WP_PATH` and, for Local,
  `LOCAL_WP` (V&Me's is `vme-wp`). The site runs MySQL 8 (Local's default).
- The project's own `PROJECT.md` (Local copies) safety checks pass and its background runners are
  frozen: the block loads the front page, which can start WP-Cron on a copy of real data.
- No `wp-content/ada-clock.json` in the site (one left over means an earlier run didn't finish;
  run `ada-clock reset` and find out why before going on).
- This checkout (`REPO`); `curl`, `python3` (3.9 or later, for `zoneinfo`: it works out the times,
  so the block runs on Linux and macOS alike). The block never changes the computer's clock (the
  `manual` setup only prints the steps); the optional steps at the end do.

## Steps

1. Save the block below as `check-site.sh` in a scratch folder (not in either repo).
2. From the project's root (e.g. `~/Repos/vandme`): `REPO=<this checkout> bash <scratch>/check-site.sh`.
3. The browser steps after the block (a person, logged in to the local admin as themselves).

```bash
#!/usr/bin/env bash
# ada-clock on a real Local site, manual setup, the computer's clock not changed: install; status,
# set and reset; the mu-plugin's database timestamp, ada_clock cookie, time endpoint and login
# lifetime; and none of them when home_url() isn't local. Run from the onboarded project's root.
# Prints PASS/FAIL/SKIP per check (a FAIL shows what was seen); exits 1 if any FAIL. Leaves the
# mu-plugin installed and no state file. Env: REPO (this checkout).
set -u
AC=${REPO:?set REPO to this checkout}/plugins/ada/bin/ada-clock
TPL=$REPO/plugins/ada/templates/clock/ada-clock.php
cd "$(git rev-parse --show-toplevel)" || exit 2
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if seen=$("$@" 2>&1); then ok 0 "$name"; else ok 1 "$name"; [ -n "$seen" ] && printf '     %s\n' "$seen"; fi; }
not() { ! "$@"; }
stop() { echo "STOP $*"; exit 2; }
ac()  { bash "$AC" "$@"; }
# val <key>: LOCAL_URL or LOCAL_WP_PATH from local.env (no other line is read, nothing printed).
val() { sed -n "s/^$1=//p" .claude/team/local.env | head -1 | sed -e 's/[[:space:]][[:space:]]*#.*$//' -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"; }
URL=${LOCAL_URL:-$(val LOCAL_URL)}; URL=${URL%/}
WPP=${LOCAL_WP_PATH:-$(val LOCAL_WP_PATH)}
STATE="$WPP/wp-content/ada-clock.json"; MU="$WPP/wp-content/mu-plugins/ada-clock.php"
php_last() { ac wp "$@" 2>/dev/null | tail -1; }

# Preconditions.
command -v curl >/dev/null && command -v python3 >/dev/null || stop "needs curl and python3"
[ -n "$URL" ] && [ -d "$WPP/wp-content" ] || stop "LOCAL_URL or LOCAL_WP_PATH not set, or no wp-content there"
[ -e "$STATE" ] && stop "a state file is left from an earlier run: $STATE"
TZS=$(php_last eval 'echo wp_timezone_string();')
[ -n "$TZS" ] || stop "ada-clock wp eval doesn't answer: check LOCAL_WP and that the site is running"
echo "site $URL, timezone $TZS, $(date '+%Y-%m-%d %H:%M:%S %Z')"

# The fake time used: 2026-11-06 11:55 on the site's clock, as UTC.
T=$(python3 - "$TZS" <<'PY'
import datetime, sys, zoneinfo
tz = sys.argv[1]
if tz[:1] in '+-':
    h, m = tz[1:].split(':')
    z = datetime.timezone((-1 if tz[0] == '-' else 1) * datetime.timedelta(hours=int(h), minutes=int(m)))
else:
    z = zoneinfo.ZoneInfo(tz)
print(int(datetime.datetime(2026, 11, 6, 11, 55, tzinfo=z).timestamp()))
PY
) || stop "python3 can't work out the fake time for timezone $TZS (needs python3.9+ with zoneinfo)"
lt() { date -d "@$1" "+$2" 2>/dev/null || date -r "$1" "+$2"; }

# jstate <timestamp> <local> <timezone>: the state file is a JSON object with exactly the keys
# timestamp and set_at (integers), and local, timezone and setup (strings): timestamp the UTC time,
# local the site-local time as given, timezone the site's timezone, setup "manual" (API.md).
jstate() {
  python3 - "$STATE" "$@" <<'PY'
import json, sys
d = json.load(open(sys.argv[1])); ts, local, tz = int(sys.argv[2]), sys.argv[3], sys.argv[4]
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
    print('; '.join(bad))
sys.exit(1 if bad else 0)
PY
}
# db [<wp-cli args>]: "set" if NOW() stayed at the request's start while SYSDATE() moved on
# (SET timestamp ran on load), "unset" if NOW() kept up with SYSDATE(), else what was seen.
DBQ='global $wpdb; $r = $_SERVER["REQUEST_TIME"]; $t0 = time(); sleep( 3 ); echo $r, " ", $t0, " ", $wpdb->get_var( "SELECT UNIX_TIMESTAMP(NOW())" ), " ", $wpdb->get_var( "SELECT UNIX_TIMESTAMP(SYSDATE())" );'
db() {
  read -r r t0 n s <<EOF
$(php_last "$@" eval "$DBQ")
EOF
  case "${r:-x}${t0:-x}${n:-x}${s:-x}" in *[!0-9]*) echo "other: request $r, time() $t0, NOW() $n, SYSDATE() $s"; return ;; esac
  if [ "$n" -ge "$r" ] && [ "$n" -le "$t0" ] && [ $((s - n)) -ge 3 ]; then echo set
  elif [ $((s - n)) -le 1 ] && [ $((n - t0)) -ge 2 ]; then echo unset
  else echo "other: request $r, time() $t0, NOW() $n, SYSDATE() $s"; fi
}
is() { [ "$1" = "$2" ] || { echo "$1"; return 1; }; }
# ep <true|false>: GET the endpoint, no auth: 200, exactly now/fake/timezone, now an integer
# within 10 s of this computer's clock, fake as given, timezone the site's.
ep() {
  python3 - "$1" "$TZS" "$(date +%s)" "$(curl -sk -w '\n%{http_code}' "$URL/wp-json/ada-clock/v1/time")" <<'PY'
import json, sys
fake, tz, now, raw = sys.argv[1] == 'true', sys.argv[2], int(sys.argv[3]), sys.argv[4]
body, code = raw.rsplit('\n', 1)
try:
    d = json.loads(body)
except ValueError:
    d = {}
good = code == '200' and set(d) == {'now', 'fake', 'timezone'} and type(d['now']) is int \
  and abs(d['now'] - now) <= 10 and d['fake'] is fake and d['timezone'] == tz
if not good: print(code, body[:300])
sys.exit(0 if good else 1)
PY
}
# cookie: GET / with no cookies; C is the response's Set-Cookie line for ada_clock, B and A the
# times just before and after.
cookie() { B=$(date +%s); C=$(curl -sk -o /dev/null -D - "$URL/" | tr -d '\r' | grep -i '^set-cookie: *ada_clock=' | tail -1); A=$(date +%s); }
cv() { printf '%s' "$C" | sed 's/^[^=]*=\([^;]*\).*$/\1/'; }
live() {
  v=$(cv); case $v in ''|*[!0-9]*) echo "no ada_clock=<number>: $C"; return 1 ;; esac
  [ "$v" -ge "$B" ] && [ "$v" -le "$A" ] || { echo "$v is not between $B and $A: $C"; return 1; }
  printf '%s' "$C" | grep -qi 'path=/' || { echo "no path=/: $C"; return 1; }
  printf '%s' "$C" | grep -qi 'httponly' && { echo "HttpOnly: $C"; return 1; }
  printf '%s' "$C" | grep -qiE 'expires=|max-age=' && { echo "not a session cookie: $C"; return 1; }
  return 0
}
expired() {
  [ -n "$C" ] || { echo "no Set-Cookie for ada_clock"; return 1; }
  printf '%s' "$C" | grep -qi 'max-age=0' && return 0
  e=$(printf '%s' "$C" | sed -n 's/.*[Ee]xpires=\([^;]*\).*/\1/p' | tr '-' ' ')
  [ -n "$e" ] && [ "$(python3 -c 'import sys; from email.utils import parsedate_to_datetime as p; print(int(p(sys.argv[1]).timestamp()))' "$e" 2>/dev/null || echo 9999999999)" -lt "$(date +%s)" ] && return 0
  echo "not expired: $C"; return 1
}
LOGIN='echo apply_filters( "auth_cookie_expiration", 2 * DAY_IN_SECONDS, 1, false ), " ", apply_filters( "auth_cookie_expiration", 14 * DAY_IN_SECONDS, 1, true );'
years() { lo=$1 hi=$2; shift 2; for v in "$@"; do case $v in ''|*[!0-9]*) echo "not a number: $*"; return 1 ;; esac; [ "$v" -ge "$lo" ] && [ "$v" -le "$hi" ] || { echo "$v s"; return 1; }; done; }
ROUTE='echo isset( rest_get_server()->get_routes()["/ada-clock/v1/time"] ) ? "yes" : "no";'
steps() {
  case "$(uname -s):$1" in
    Linux:set)    grep -qF 'timedatectl set-ntp false' "$O" && grep -F 'date -s' "$O" | grep -qF "$(lt "$2" '%Y-%m-%d %H:%M')" ;;
    Linux:reset)  grep -qF 'timedatectl set-ntp true' "$O" ;;
    Darwin:set)   grep -qF 'Set time and date automatically' "$O" && grep -qF "date $(lt "$2" '%m%d%H%M%y')" "$O" ;;
    Darwin:reset) grep -qF 'Set time and date automatically' "$O" ;;
    *) grep -qF 'Time & language' "$O" ;;
  esac
}
O=$(mktemp); trap '[ -e "$STATE" ] && ac reset >/dev/null 2>&1; rm -f "$O"' EXIT
inside=1; case "$WPP/" in "$(pwd)/"*) inside=0 ;; esac
X=$(git rev-parse --git-path info/exclude); x_before=$(cat "$X" 2>/dev/null | cksum)

# 1. install.
ac install > "$O" 2>&1; t "install exits 0" is $? 0
t "the mu-plugin is the template" cmp "$TPL" "$MU"
t "install says to log in to the local admin again" grep -qiE 'log ?in' "$O"
if [ "$inside" = 0 ]; then
  t "site inside the project: the mu-plugin's path is in .git/info/exclude" grep -qF 'mu-plugins/ada-clock.php' "$X"
  t "site inside the project: the state file's path is in .git/info/exclude" grep -qF 'wp-content/ada-clock.json' "$X"
  t "site inside the project: the mu-plugin is not in git status" is "$(git status --porcelain --untracked-files=all -- "$MU")" ""
else
  t "site outside the project: .git/info/exclude unchanged" is "$(cat "$X" 2>/dev/null | cksum)" "$x_before"
fi

# 2. No state: real time, no SET, the endpoint says not fake; logins last ten years anyway.
ac status > "$O" 2>&1; rc=$?
t "status, no state: first line ada-clock: real" is "$(head -1 "$O")" 'ada-clock: real'
t "status, no state: exit 0" is "$rc" 0
t "status says the mu-plugin is installed" grep -q '^mu-plugin: installed ' "$O"
t "endpoint, no state: fake false" ep false
t "no state: the mu-plugin runs no SET" is "$(db)" unset
t "a login lasts about ten years (remember me off and on)" years 299592000 331128000 $(php_last eval "$LOGIN")
t "the route is registered" is "$(php_last eval "$ROUTE")" yes

# 3. set "2026-11-06 11:55" (the clock not changed).
ac set "2026-11-06 11:55" > "$O" 2>&1; t "set exits 0" is $? 0
t "the state file has exactly its five keys: timestamp $T (2026-11-06 11:55 $TZS), local '2026-11-06 11:55', timezone $TZS, manual" jstate "$T" "2026-11-06 11:55" "$TZS"
t "set prints this OS's steps with $(lt "$T" '%Y-%m-%d %H:%M') (the computer's time)" steps set "$T"
t "set says to reload pages loaded before the jump" grep -qi 'reload' "$O"
ac status > "$O" 2>&1; rc=$?
t "status after set: ada-clock: fake 2026-11-06 11:55 <tz> (manual)" grep -qE '^ada-clock: fake 2026-11-06 11:55 [^ ]+ \(manual\)$' <(head -1 "$O")
t "status after set, clock not changed: exit 1" is "$rc" 1
t "endpoint with state: fake true, now PHP's time()" ep true
cookie
t "a page response sets ada_clock to PHP's time(), path /, session, not HttpOnly" live
t "with state: SET timestamp ran on load (NOW() at the request's start, SYSDATE() moving on)" is "$(db)" set

# 4. The same mu-plugin, home_url() https://example.com, state present: inert.
EX='--exec=define( "WP_HOME", "https://example.com" );'
if [ "$(php_last "$EX" eval 'echo home_url();')" = https://example.com ]; then
  t "home example.com: no SET" is "$(db "$EX")" unset
  t "home example.com: no ten-year login filter" years 0 31536000 $(php_last "$EX" eval "$LOGIN")
  t "home example.com: no route" is "$(php_last "$EX" eval "$ROUTE")" no
else
  echo "SKIP home example.com: WP_HOME through --exec didn't change home_url(); see Escalate"
fi

# 5. reset.
ac reset > "$O" 2>&1; t "reset exits 0" is $? 0
t "reset deletes the state file" not test -e "$STATE"
t "reset prints the steps to restore automatic time" steps reset
ac status > "$O" 2>&1; rc=$?
t "status after reset: ada-clock: real" is "$(head -1 "$O")" 'ada-clock: real'
t "status after reset: exit 0" is "$rc" 0
cookie
t "after reset the next response expires ada_clock" expired
t "endpoint after reset: fake false" ep false
t "after reset: no SET" is "$(db)" unset
[ "$inside" = 0 ] || t "site outside the project: .git/info/exclude still unchanged" is "$(cat "$X" 2>/dev/null | cksum)" "$x_before"

echo
echo "$fails failed"
[ "$fails" = 0 ]
```

Then in the browser (a person, logged in to the local admin as themselves; nobody else's login):

4. **Login after install.** Log out of the local admin and log in again. In DevTools, Application →
   Cookies → `<LOCAL_URL>`, the `wordpress_logged_in_…` cookie's value has `|` (shown as `%7C`)
   between its parts; the second part is a Unix time. `date -d @<that number>` is about ten years
   from now (2036). With "Remember Me" ticked, the cookie's own Expires is about ten years out too.
5. **Admin responses.** Run `ada-clock set "2026-11-06 11:55"`, then open any wp-admin page. In
   DevTools, Network → the page's document → Response headers: `Set-Cookie: ada_clock=<number>;
   path=/`, the number within a few seconds of `date +%s`; Application → Cookies shows `ada_clock`
   as a Session cookie, HttpOnly unticked. Run `ada-clock reset`, reload: the response expires it
   and it is gone from Cookies.
6. **Optional, changes the computer's clock** (only with the project's runners frozen, and back
   to automatic time straight after): run `ada-clock set "2026-11-06 11:55"` and follow the steps
   it prints. `ada-clock status`: first line `ada-clock: fake 2026-11-06 11:55 <tz> (manual)`,
   **exit 0**; `curl -sk <LOCAL_URL>/wp-json/ada-clock/v1/time` shows `now` within a minute or two
   of the fake time and `"fake":true`. Then `ada-clock reset`, follow its steps: `status` is
   `ada-clock: real`, exit 0, and `timedatectl` shows NTP active again.
7. **MariaDB**, if a site on MariaDB is to hand: run the block against it; the `SET timestamp`
   checks must pass there too. Otherwise record "MariaDB not verified".

## Expected

Every block line `PASS` (or the `SKIP` for `WP_HOME`, recorded with the reason), `0 failed`, exit
0; the browser steps as written. The source is the brief: Decisions (`status`, `install`, the
mu-plugin's database, logins, cookie and endpoint items) and its Definition of done.

## Observe at

The block's output and exit code; `wp-content/ada-clock.json` and `wp-content/mu-plugins/` in the
site; DevTools (Application → Cookies, Network → response headers) for the browser steps.

## Regression

After the run the site behaves as before for everything but logins: with no state file the
endpoint says `"fake":false`, no `SET timestamp` runs, and responses expire `ada_clock`. The
project's `git status` is as it was (the mu-plugin and state file never show), and its
`.gitignore` is unchanged. To remove the mu-plugin afterwards: delete
`wp-content/mu-plugins/ada-clock.php` (and its lines in `.git/info/exclude`, if any were added).
