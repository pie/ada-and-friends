# `presstest run --clock` answers a spec's clock requests with `ada-clock` on the host, refuses anything else, and always leaves the site's clock on real time

- Area: 14 PressTest runs (`presstest`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/1203720326869832/task/1220216506739139
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-15, 151 PASS, 0 failed under bash 5.3 and bash 3.2 (see the PR)

## Precondition

- This checkout (`REPO`). The script copies `plugins/ada` to a temporary folder and puts a
  stand-in `ada-clock` in the copy's `bin/` (where `presstest` finds it) and on the `PATH`, with
  a stand-in `docker` and `curl`. The stand-in `docker` plays the container: it writes the
  clock requests into the mounted results folder, as `tests/presstest/shared/clock.js` does,
  and waits for each response. The stand-in `ada-clock` keeps its "clock" in a file and logs
  every call, one argument per bracket. Nothing runs Docker, touches a site or moves a clock.
- `git`, `mktemp`, `jq`, `tar`, `pkill`, `mkfifo`, `timeout` (GNU or busybox; sections 9 and 10 use it
  to stop a wrapper that hangs). `origin/next-release` fetched: section 7 compares a run
  without `--clock` with the released `presstest` exported from it (or from `BASE_REF`, or the
  copy `BASE_PLUGIN` names).
- Run the checkout's script by path, never the `presstest` on the `PATH` (that is the installed
  plugin).

## Steps

1. Save the block below as `check-clock.sh` in a scratch folder (not in this repo).
2. Run it under a current bash: `REPO=<this checkout> bash check-clock.sh`. It takes about a
   minute. Then under bash 3.2, with the released plugin exported first, as in
   `presstest-runs-the-projects-suite.md` step 2:
   `docker run --rm -v <this checkout>:/repo -v <scratch folder>:/c bash:3.2 bash -c 'apk add -q git jq procps >/dev/null; REPO=/repo BASE_PLUGIN=/c/base/plugins/ada bash /c/check-clock.sh'`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, and in the copy's
   `bin/presstest` make the request check accept any `when` (for instance, widen the
   `YYYY-MM-DD HH:MM` pattern to `.*`). Run `REPO=<this checkout> PLUGIN=<the copy> bash
   check-clock.sh`. Section 2 must print `FAIL` lines. Then run it once with
   `PLUGIN=<scratch folder>/base/plugins/ada` (the released plugin, which has no `--clock`):
   sections 1 to 5 and 8 to 10 must print `FAIL` lines, and sections 6 and 7 `PASS` (the released
   plugin refuses `--clock` as an unknown option with exit 2, which section 6 alone can't tell
   from the usage error; section 1 can).
4. Run `presstest-runs-the-projects-suite.md` too: nothing in it may change (Regression).

```bash
#!/usr/bin/env bash
# presstest run --clock (Unit E brief, Decisions and Definition of done): local only; ada-clock
# status real before anything runs; the clock folder, PRESSTEST_CLOCK and --workers=1; the
# bridge answering request-<n>.json with ada-clock set/reset through an argument array, and
# refusing anything else without running ada-clock; one output line per request; ada-clock reset
# then status when the container ends, however it ends, before the test session ends; exit 1
# when the clock isn't back at real time; a run without --clock exactly as before; the request
# folder is untrusted (the container writes it): a symlink planted in it never gets a host file
# overwritten, and a request that is a FIFO, a directory or a link to a FIFO is refused without
# stopping the reset.
# Prints PASS/FAIL per check; exits 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada), BASE_PLUGIN
# (the released plugin to compare with) or BASE_REF (where to export it from, default
# origin/next-release).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
W=$(mktemp -d)
mkdir -p "$W/bin" "$W/log"
cp -R "$PLUGIN" "$W/plugin"
PT=$W/plugin/bin/presstest
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
run() { d=$1; shift; (cd "$d" && PATH="$W/bin:$PATH" bash "$PT" "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
runto() { d=$1; shift; (cd "$d" && PATH="$W/bin:$PATH" timeout -s KILL 90 bash "$PT" "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc() { cat "$W/rc"; }
clear() { rm -rf "$W/log"; mkdir -p "$W/log"; }
both() { cat "$W/out" "$W/err"; }
real() { echo real > "$W/clock-state"; }
state() { cat "$W/clock-state"; }
reqs() { printf '%s\n' "$@" > "$W/requests"; }
noreqs() { : > "$W/requests"; }
# The first and last line of the call sequence (ada-clock, docker run, curl) that is exactly $1.
pos() { grep -n -x -m1 -- "$1" "$W/log/seq" 2>/dev/null | cut -d: -f1; }
lastpos() { grep -n -x -- "$1" "$W/log/seq" 2>/dev/null | tail -1 | cut -d: -f1; }
lt() { [ -n "$1" ] && [ -n "$2" ] && [ "$1" -lt "$2" ]; }
calls() { grep -c -x -F -- "$1" "$W/log/ada-clock" 2>/dev/null; }
resp() { jq -e "$2" "$W/log/response-$1.json"; }
mkrepo() {
  mkdir -p "$1/.claude/team" "$1/tests/presstest/shared" && git -C "$1" init -q
  printf '# P\n\n## Tests\n\n- **Unit tests:** x\n- **PressTest image:** `1.0.0`\n- **PressTest shared suites:** none\n- **PressTest staging:** `https://staging.shop.example.com`\n' > "$1/.claude/team/PROJECT.md"
  : > "$1/tests/presstest/checkout_test.js"; : > "$1/tests/presstest/account_test.js"; : > "$1/tests/presstest/shared/helpers.js"
  printf 'LOCAL_URL=https://shop.local\nPRESSTEST_SECRET=s3cr\nPRESSTEST_STAGING_SECRET=stg\n' > "$1/.claude/team/local.env"
}

# The stand-in ada-clock. Its clock is the file $STUB_STATE: "real" or "fake <when>". It logs
# each call (one [argument] each) and the folder it ran in, and adds "ada-clock <subcommand>"
# to the call sequence. status prints the real first line (ada-clock's header), then more lines,
# and exits STUB_STATUS_RC; STUB_STATUS_LINE replaces the first line. set prints a first line
# and a warning, or STUB_SET_LINE and exits STUB_SET_RC when that is set. reset makes the clock
# real unless STUB_RESET_STUCK is set.
cat > "$W/bin/ada-clock" <<'EOF'
#!/usr/bin/env bash
{ printf 'ada-clock'; for a in "$@"; do printf ' [%s]' "$a"; done; printf '\n'; } >> "$STUB_LOG/ada-clock"
pwd -P >> "$STUB_LOG/ada-clock-cwd"
echo "ada-clock ${1:-}" >> "$STUB_LOG/seq"
state=$(cat "$STUB_STATE" 2>/dev/null); [ -n "$state" ] || state=real
case "${1:-}" in
  status)
    if [ -n "${STUB_STATUS_LINE:-}" ]; then echo "$STUB_STATUS_LINE"
    elif [ "$state" = real ]; then echo 'ada-clock: real'
    else echo "ada-clock: fake ${state#fake } Europe/London (local-linux)"; fi
    echo 'wrapper: installed /stand-in/php-fpm'
    echo 'site time: 2026-11-15 12:00:00 UTC (0 s from this computer)'
    exit "${STUB_STATUS_RC:-0}" ;;
  set)
    if [ -n "${STUB_SET_RC:-}" ]; then echo "${STUB_SET_LINE:-ada-clock: refused}"; echo 'a second line'; exit "$STUB_SET_RC"; fi
    echo "fake $2" > "$STUB_STATE"
    echo "ada-clock: fake $2 Europe/London (local-linux), written to /stand-in/ada-clock.json"
    echo 'Payment webhooks fail while the clock is moved.'
    exit 0 ;;
  reset)
    [ -z "${STUB_RESET_STUCK:-}" ] && echo real > "$STUB_STATE"
    echo 'ada-clock: reset, removed /stand-in/ada-clock.json'
    exit 0 ;;
  *) echo "ada-clock: the stand-in doesn't do ${1:-nothing}"; exit 2 ;;
esac
EOF
cp "$W/bin/ada-clock" "$W/plugin/bin/ada-clock"

# The stand-in docker. A run of node is the pre-flight check. The run of playwright test
# records its arguments and what it is given with -e (NAME=value, or NAME and its value), finds
# the host side of PRESSTEST_CLOCK_DIR through the /app/test-results mount, and, one line of
# $STUB_REQUESTS at a time, writes request-<n>.json there (to a temporary name first) and waits
# up to 5 s for response-<n>.json, which it keeps. KILL exits 137 at once, as `docker kill`
# makes `docker run` do; HANG waits for $STUB_LOG/stop (30 s at most), then exits 137; NAME
# <file> writes a reset request under that file name; LINK <name> <target> plants a symlink
# <name> -> <target> in the folder (before the next request); FIFO, DIR and FIFOLINK make the next
# request-<n>.json a named pipe, a directory, or a symlink to a named pipe, instead of a file.
# Then a report with STUB_PASSED passed and
# STUB_FAILED failed, unless STUB_NO_REPORT.
cat > "$W/bin/docker" <<'EOF'
#!/usr/bin/env bash
echo "docker $*" >> "$STUB_LOG/docker"
case "$1" in
  run) ;;
  *) exit 0 ;;
esac
case "$*" in *" node -e "*) echo "$*" > "$STUB_LOG/preflight"; exit 0 ;; esac
echo "docker run" >> "$STUB_LOG/seq"
printf '%s\n' "$@" > "$STUB_LOG/args"
results='' prev=''
for a in "$@"; do
  if [ "$prev" = -e ]; then
    case "$a" in *=*) printf '%s\n' "$a" >> "$STUB_LOG/env" ;; *) printf '%s=%s\n' "$a" "${!a:-}" >> "$STUB_LOG/env" ;; esac
  fi
  if [ "$prev" = -v ]; then case "$a" in *:/app/test-results) results=${a%:/app/test-results} ;; esac; fi
  prev=$a
done
dir=$(sed -n 's/^PRESSTEST_CLOCK_DIR=//p' "$STUB_LOG/env" 2>/dev/null | tail -1)
host=''
case "$dir" in /app/test-results/?*) host=$results/${dir#/app/test-results/} ;; esac
if [ -n "$host" ] && [ -d "$host" ]; then echo "$host" > "$STUB_LOG/clockdir"; fi
n=0
if [ -n "$host" ] && [ -s "${STUB_REQUESTS:-}" ]; then
  while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in
      KILL) exit 137 ;;
      HANG) i=0; while [ ! -e "$STUB_LOG/stop" ] && [ "$i" -lt 300 ]; do sleep 0.1; i=$((i+1)); done; exit 137 ;;
      NAME\ *) printf '{"action":"reset"}' > "$host/${line#NAME }"; sleep 1.5; continue ;;
      LINK\ *) read -r _ lname ltarget <<< "$line"; ln -s "$ltarget" "$host/$lname"; continue ;;
    esac
    n=$((n+1))
    case "$line" in
      FIFO) mkfifo "$host/request-$n.json" ;;
      DIR) mkdir "$host/request-$n.json" ;;
      FIFOLINK) mkfifo "$host/.fifo-$n"; ln -s "$host/.fifo-$n" "$host/request-$n.json" ;;
      *)
        printf '%s' "$line" > "$host/.request-$n.tmp"
        mv "$host/.request-$n.tmp" "$host/request-$n.json" ;;
    esac
    i=0
    while [ ! -e "$host/response-$n.json" ] && [ "$i" -lt 50 ]; do sleep 0.1; i=$((i+1)); done
    if [ -e "$host/response-$n.json" ]; then
      sleep 0.3
      cp "$host/response-$n.json" "$STUB_LOG/response-$n.json"
      echo "$i" > "$STUB_LOG/wait-$n"
      [ -e "$host/request-$n.json" ] && echo left > "$STUB_LOG/request-left-$n"
    else
      echo missing > "$STUB_LOG/response-$n.missing"
    fi
  done < "$STUB_REQUESTS"
fi
echo "$n" > "$STUB_LOG/sent"
[ -n "${STUB_NO_REPORT:-}" ] && exit 1
mkdir -p "$results/run"
printf '{"suites":[{"status":"expected"}],"stats":{"duration":1,"expected":%s,"skipped":0,"unexpected":%s,"flaky":0}}\n' "${STUB_PASSED:-2}" "${STUB_FAILED:-0}" > "$results/run/results.json"
[ "${STUB_FAILED:-0}" = 0 ]
EOF
# The stand-in curl: a POST starts session 7, a DELETE ends it; each adds "curl <method>" to
# the call sequence.
cat > "$W/bin/curl" <<'EOF'
#!/usr/bin/env bash
method='' url='' prev=''
for a in "$@"; do
  [ "$prev" = -X ] && method=$a
  case "$a" in http*) url=$a ;; esac
  prev=$a
done
echo "$method $url" >> "$STUB_LOG/curl"
echo "curl $method" >> "$STUB_LOG/seq"
if [ "$method" = POST ]; then printf '{"id":7,"cookie":{"value":"cookie-v"}}\n201'
else printf '{"id":7,"status":"ended","summary":{"deleted":{"user":1},"outstanding":0}}'; fi
EOF
chmod +x "$W/bin/ada-clock" "$W/plugin/bin/ada-clock" "$W/bin/docker" "$W/bin/curl"
export STUB_LOG=$W/log STUB_STATE=$W/clock-state STUB_REQUESTS=$W/requests
R=$W/r; mkrepo "$R"
RP=$(cd "$R" && pwd -P)
CLOCKDIR=$R/.claude/presstest/run/clock
FRI='ada-clock: fake 2026-11-20 15:00 Europe/London (local-linux), written to /stand-in/ada-clock.json'
SUN='ada-clock: fake 2026-11-22 10:00 Europe/London (local-linux), written to /stand-in/ada-clock.json'
RESET='ada-clock: reset, removed /stand-in/ada-clock.json'

# 1. A clock run that passes (DoD 1, 2): status first; the folder, the variables and one worker;
#    each request answered by ada-clock with its arguments as an array; one line per request;
#    reset then status after the container, before the session ends; the clock real at the end.
clear; real
reqs '{"action":"set","when":"2026-11-20 15:00"}' '{"action":"set","when":"2026-11-22 10:00"}' '{"action":"reset"}'
run "$R" run --clock -b firefox checkout_test.js
t "a passing clock run exits 0" test "$(rc)" = 0
t "ada-clock status is the first thing run" test "$(sed -n 1p "$W/log/seq" 2>/dev/null)" = 'ada-clock status'
t "status comes before the test session starts" lt "$(pos 'ada-clock status')" "$(pos 'curl POST')"
t "status comes before the container starts" lt "$(pos 'ada-clock status')" "$(pos 'docker run')"
t "the clock folder .claude/presstest/run/clock exists when the container starts" test "$(cat "$W/log/clockdir" 2>/dev/null)" = "$CLOCKDIR"
t "the container gets PRESSTEST_CLOCK=1" grep -qx 'PRESSTEST_CLOCK=1' "$W/log/env"
t "the container gets PRESSTEST_CLOCK_DIR=/app/test-results/run/clock" grep -qx 'PRESSTEST_CLOCK_DIR=/app/test-results/run/clock' "$W/log/env"
t "playwright test runs with --workers=1" grep -qx -- '--workers=1' "$W/log/args"
t "the named file still runs" grep -qx 'project/checkout_test.js' "$W/log/args"
t "on the named browser" grep -qx -- '--project=firefox' "$W/log/args"
t "request 1 (set Friday) is answered exit 0 with ada-clock's first line" resp 1 ".exit == 0 and .line == \"$FRI\""
t "request 2 (set Sunday) is answered exit 0 with ada-clock's first line" resp 2 ".exit == 0 and .line == \"$SUN\""
t "request 3 (reset) is answered exit 0 with ada-clock's first line" resp 3 ".exit == 0 and .line == \"$RESET\""
t "a response is exactly {exit, line}" resp 1 'keys == ["exit","line"]'
t "set is run as ada-clock [set] [2026-11-20 15:00]: an argument array" test "$(calls 'ada-clock [set] [2026-11-20 15:00]')" = 1
t "and as ada-clock [set] [2026-11-22 10:00]" test "$(calls 'ada-clock [set] [2026-11-22 10:00]')" = 1
t "each request is answered within 2 s" test "$(cat "$W/log/wait-1" "$W/log/wait-2" "$W/log/wait-3" 2>/dev/null | awk '$1 <= 20' | wc -l)" -eq 3
t "each request is deleted once answered" not ls "$W/log/request-left-"*
t "ada-clock always runs from the project repo" test -s "$W/log/ada-clock-cwd" -a -z "$(grep -v -x -F -e "$RP" -e "$R" "$W/log/ada-clock-cwd" 2>/dev/null)"
t "the wrapper prints one line per clock request" test "$(both | grep -c 'clock request')" -eq 3
t "ada-clock reset runs after the container ends" lt "$(lastpos 'docker run')" "$(lastpos 'ada-clock reset')"
t "then ada-clock status" lt "$(lastpos 'ada-clock reset')" "$(lastpos 'ada-clock status')"
t "the clock is reset before the test session ends" lt "$(lastpos 'ada-clock reset')" "$(pos 'curl DELETE')"
t "the output shows ada-clock reset" grep -q 'ada-clock reset' "$W/out" "$W/err"
t "and status's first line, ada-clock: real" grep -q 'ada-clock: real$' "$W/out" "$W/err"
t "only status's first line is printed" not grep -q 'wrapper: installed' "$W/out" "$W/err"
t "the clock is real at the end" test "$(state)" = real
t "the result line is still last" test "$(tail -1 "$W/out")" = 'presstest: passed 2'
n=$(cat "$W/log/ada-clock" 2>/dev/null | wc -l)
mkdir -p "$CLOCKDIR"; printf '{"action":"set","when":"2026-11-27 15:00"}' > "$CLOCKDIR/request-99.json"; sleep 1.5
t "after the run, the loop has stopped: a new request isn't answered" not test -e "$CLOCKDIR/response-99.json"
t "and ada-clock isn't run" test "$(cat "$W/log/ada-clock" 2>/dev/null | wc -l)" -eq "$n"
rm -f "$CLOCKDIR/request-99.json"

# 2. Requests that aren't set with a YYYY-MM-DD HH:MM when, or reset, are refused with a
#    response, and ada-clock isn't run (DoD 5). Nothing in a request or its file name reaches a
#    shell.
clear; real; rm -f "$W/x" "$W/y" "$W/z"
tmpx=absent; [ -e /tmp/x ] && tmpx=present
reqs '{"action":"set","when":"2026-11-13 15:00; touch /tmp/x"}' \
  "{\"action\":\"set\",\"when\":\"2026-11-13 15:00; touch $W/x\"}" \
  "{\"action\":\"set\",\"when\":\"\$(touch $W/y)\"}" \
  "{\"action\":\"set\",\"when\":\"2026-11-13 15:00\`touch $W/z\`\"}" \
  '{"action":"wp","when":"2026-11-13 15:00"}' \
  '{"action":"status"}' \
  '{"action":"install"}' \
  '{"action":"RESET"}' \
  '{"action":"set"}' \
  '{"action":"set","when":"2026-11-13 15:00\n2026-11-13 16:00"}' \
  '{"action":"set","when":"2026-11-13 15:00\n"}' \
  '{"action":"set","when":"2026-11-13T15:00Z"}' \
  '{"action":"set","when":"2026-11-13 15:00:00"}' \
  '{"action":"set","when":" 2026-11-13 15:00"}' \
  '{"action":"set","when":["2026-11-13 15:00"]}' \
  '{"action":"set","when":202611131500}' \
  '{"action":"reset","extra":1}' \
  'set 2026-11-13 15:00' \
  '{"action":"set\nreset"}' \
  '{"action":"set","when":"2026-11-13 15:00"}{"action":"reset"}' \
  '{"action":"set","when":"２０２６-11-13 15:00"}' \
  '{"action":"set","when":"2026-11-20 15:00"}' \
  '{"action":"reset"}' \
  'NAME request-$(touch pwned).json' \
  'NAME request-1;touch pwned2.json'
run "$R" run --clock
bad=21
i=1
while [ "$i" -le "$bad" ]; do
  t "bad request $i: $(sed -n "${i}p" "$W/requests") gets a refusal (exit not 0, a line)" resp "$i" '(.exit | type == "number") and .exit != 0 and (.line | type == "string") and (.line | length) > 0'
  i=$((i+1))
done
t "ada-clock set ran once, for the one good set" test "$(grep -c '^ada-clock \[set\]' "$W/log/ada-clock" 2>/dev/null)" = 1
t "with exactly its when" test "$(calls 'ada-clock [set] [2026-11-20 15:00]')" = 1
t "no refused when reached ada-clock" not grep -q '2026-11-13' "$W/log/ada-clock" 2>/dev/null
t "ada-clock ran nothing but status, set and reset" test -z "$(grep -v -e '^ada-clock \[status\]$' -e '^ada-clock \[set\] ' -e '^ada-clock \[reset\]$' "$W/log/ada-clock" 2>/dev/null)"
t "reset ran twice: the good request and the end of the run (not for the badly named files)" test "$(calls 'ada-clock [reset]')" = 2
t "the good set after the refusals is answered exit 0" resp $((bad+1)) ".exit == 0 and .line == \"$FRI\""
t "the good reset is answered exit 0" resp $((bad+2)) '.exit == 0'
t "no ; touch ran" not test -e "$W/x"
t "no \$( ) ran" not test -e "$W/y"
t "no backticks ran" not test -e "$W/z"
t "no command in a request file's name ran" test -z "$(find "$W" "$REPO" -maxdepth 6 \( -name pwned -o -name 'pwned2.json' -o -name pwned2 \) 2>/dev/null)"
if [ "$tmpx" = absent ]; then t "/tmp/x doesn't exist" not test -e /tmp/x; else echo "SKIP /tmp/x existed before the run, so the DoD's own check can't be made here (the $W/x check stands in)"; fi
t "no request is left unanswered" not ls "$W/log/"response-*.missing
t "the clock is real at the end" test "$(state)" = real

# 3. ada-clock's own failure comes back as the response: its exit and first line, JSON-escaped.
clear; real
reqs '{"action":"set","when":"2026-11-20 15:00"}'
LINE='ada-clock: "2026-11-20 15:00" can'"'"'t be set \ here'
STUB_SET_RC=2 STUB_SET_LINE=$LINE run "$R" run --clock
t "a failing ada-clock set is answered with its exit 2" resp 1 '.exit == 2'
t "and its first line, quotes and backslash intact" test "$(jq -r .line "$W/log/response-1.json" 2>/dev/null)" = "$LINE"

# 4. However the run ends, the clock is reset and checked (DoD 3, 4).
clear; real; reqs '{"action":"set","when":"2026-11-20 15:00"}'
STUB_FAILED=1 run "$R" run --clock
t "a failing test: the run exits 1" test "$(rc)" = 1
t "a failing test: ada-clock reset runs after the container" lt "$(lastpos 'docker run')" "$(lastpos 'ada-clock reset')"
t "a failing test: then status" lt "$(lastpos 'ada-clock reset')" "$(lastpos 'ada-clock status')"
t "a failing test: the clock is real" test "$(state)" = real
t "a failing test: ada-clock: real is printed" grep -q 'ada-clock: real$' "$W/out" "$W/err"
clear; real; reqs '{"action":"set","when":"2026-11-20 15:00"}'
STUB_NO_REPORT=1 run "$R" run --clock
t "no report: the run exits 1" test "$(rc)" = 1
t "no report: the clock is reset and real" test "$(state)" = real
clear; real; reqs '{"action":"set","when":"2026-11-20 15:00"}' KILL
run "$R" run --clock
t "container killed (exit 137) after a set: the run doesn't exit 0" not test "$(rc)" = 0
t "container killed: ada-clock reset runs" lt "$(lastpos 'ada-clock set')" "$(lastpos 'ada-clock reset')"
t "container killed: then status" lt "$(lastpos 'ada-clock reset')" "$(lastpos 'ada-clock status')"
t "container killed: the clock is real" test "$(state)" = real
t "container killed: ada-clock: real is printed" grep -q 'ada-clock: real$' "$W/out" "$W/err"
clear; real; reqs '{"action":"set","when":"2026-11-20 15:00"}' HANG
(cd "$R" && PATH="$W/bin:$PATH" exec bash "$PT" run --clock) > "$W/out" 2> "$W/err" &
pid=$!
i=0; while ! grep -q '^fake' "$W/clock-state" && [ "$i" -lt 100 ]; do sleep 0.1; i=$((i+1)); done
sleep 0.5
t "SIGTERM test: the clock was set before the signal" grep -q '^fake' "$W/clock-state"
kill -TERM "$pid" 2>/dev/null; sleep 0.3; touch "$W/log/stop"
wait "$pid" 2>/dev/null
sleep 0.5
t "the wrapper stopped with SIGTERM mid-run still runs ada-clock reset" lt "$(lastpos 'ada-clock set')" "$(lastpos 'ada-clock reset')"
t "SIGTERM: then status" lt "$(lastpos 'ada-clock reset')" "$(lastpos 'ada-clock status')"
t "SIGTERM: the clock is real" test "$(state)" = real
n=$(cat "$W/log/ada-clock" 2>/dev/null | wc -l)
mkdir -p "$CLOCKDIR"; printf '{"action":"set","when":"2026-11-27 15:00"}' > "$CLOCKDIR/request-98.json"; sleep 1.5
t "SIGTERM: the loop has stopped" not test -e "$CLOCKDIR/response-98.json"
t "SIGTERM: and runs nothing more" test "$(cat "$W/log/ada-clock" 2>/dev/null | wc -l)" -eq "$n"
rm -f "$CLOCKDIR/request-98.json"
clear; real; reqs '{"action":"set","when":"2026-11-20 15:00"}'
STUB_RESET_STUCK=1 run "$R" run --clock
t "a reset that leaves the clock fake: the run exits 1 though the tests passed" test "$(rc)" = 1
t "and prints status's first line" grep -q 'ada-clock: fake 2026-11-20 15:00 Europe/London (local-linux)' "$W/out" "$W/err"
real

# 5. The clock must be real before anything runs (DoD 8): otherwise exit 2, the line printed,
#    no session, no container, no set or reset, the clock as it was.
for case in fake status2 status1 notreal; do
  clear; real; noreqs
  case "$case" in
    fake) echo 'fake 2026-11-13 15:00' > "$W/clock-state"; line='ada-clock: fake 2026-11-13 15:00 Europe/London (local-linux)'; run "$R" run --clock ;;
    status2) line='ada-clock: LOCAL_WP_PATH is not set (.claude/team/local.env)'; STUB_STATUS_RC=2 STUB_STATUS_LINE=$line run "$R" run --clock ;;
    status1) line='ada-clock: real'; STUB_STATUS_RC=1 run "$R" run --clock ;;
    notreal) line='ada-clock: real time unknown'; STUB_STATUS_LINE=$line run "$R" run --clock ;;
  esac
  t "pre-check $case: exit 2" test "$(rc)" = 2
  t "pre-check $case: prints status's first line" grep -q -F -- "$line" "$W/out" "$W/err"
  t "pre-check $case: no container" not grep -q -x 'docker run' "$W/log/seq"
  t "pre-check $case: no test session" not test -e "$W/log/curl"
  t "pre-check $case: no set or reset" not grep -q -e '\[set\]' -e '\[reset\]' "$W/log/ada-clock"
done
echo 'fake 2026-11-13 15:00' > "$W/clock-state"; clear; run "$R" run --clock
t "pre-check: a clock already set is left as it was" test "$(state)" = 'fake 2026-11-13 15:00'
real

# 6. --clock is local only (DoD 7): with --env staging, in either order, exit 2, a usage
#    message naming --clock, and nothing runs.
for args in 'run --clock --env staging' 'run --env staging --clock' 'run --clock --env staging checkout_test.js'; do
  clear
  # shellcheck disable=SC2086
  run "$R" $args
  t "'$args' exits 2" test "$(rc)" = 2
  t "'$args' says why, naming --clock" grep -q -- '--clock' "$W/err"
  t "'$args': no docker" not test -e "$W/log/docker"
  t "'$args': no session" not test -e "$W/log/curl"
  t "'$args': no ada-clock" not test -e "$W/log/ada-clock"
done

# 7. Without --clock, nothing changes (DoD 9): no ada-clock, no clock variables (even when the
#    shell has them), no --workers=1; and every docker and curl call, and the output, exactly as
#    the released presstest makes them.
clear; real; noreqs
(cd "$R" && PRESSTEST_CLOCK=1 PRESSTEST_CLOCK_DIR=/app/test-results/run/clock PATH="$W/bin:$PATH" bash "$PT" run) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"
t "a plain run exits 0" test "$(rc)" = 0
t "a plain run calls no ada-clock" not test -e "$W/log/ada-clock"
t "a plain run passes no clock variable, even one set in the shell" not grep -q '^PRESSTEST_CLOCK' "$W/log/env"
t "a plain run has no --workers=1" not grep -qx -- '--workers=1' "$W/log/args"
t "a plain run prints no clock lines" not grep -q -e 'clock request' -e 'ada-clock' "$W/out" "$W/err"
BASE_PLUGIN=${BASE_PLUGIN:-}
if [ -z "$BASE_PLUGIN" ]; then
  mkdir -p "$W/base"
  if git -C "$REPO" archive "${BASE_REF:-origin/next-release}" plugins/ada 2>/dev/null | tar -x -C "$W/base" 2>/dev/null; then
    BASE_PLUGIN=$W/base/plugins/ada
  fi
fi
if [ -f "$BASE_PLUGIN/bin/presstest" ]; then
  PT_NEW=$PT
  for args in 'run' 'run -b firefox checkout_test.js' 'run --env staging checkout_test.js'; do
    for side in new base; do
      if [ "$side" = base ]; then PT=$BASE_PLUGIN/bin/presstest; else PT=$PT_NEW; fi
      clear; rm -rf "$R/.claude/presstest"
      # shellcheck disable=SC2086
      run "$R" $args
      rm -rf "$W/log-$side"; cp -R "$W/log" "$W/log-$side"; cp "$W/out" "$W/log-$side/stdout"; cp "$W/err" "$W/log-$side/stderr"; cp "$W/rc" "$W/log-$side/rc"
    done
    PT=$PT_NEW
    t "no --clock: '$args' calls docker and curl, and prints, exactly as before" diff -r "$W/log-base" "$W/log-new"
  done
else
  ok 1 "no released presstest to compare with: set BASE_PLUGIN to an export of origin/next-release's plugins/ada"
fi

# 8. The header and the usage text document --clock.
t "presstest's header comment documents --clock" grep -q -- '--clock' <(awk 'NR>1 && !/^#/ {exit} {print}' "$PT")
(cd "$R" && PATH="$W/bin:$PATH" bash "$PT") > "$W/usage" 2>&1
(cd "$R" && PATH="$W/bin:$PATH" bash "$PT" help) >> "$W/usage" 2>&1
t "the usage text shows --clock" grep -q -- '--clock' "$W/usage"

# 9. The request folder is untrusted: the container writes it as the host user, so test code can
#    plant a symlink where the bridge might write its response. Whatever name it plants (the
#    bridge's temporary name for a response, guessable or not), no host file is created or
#    overwritten through it, and the request is still answered (Outcome: the bridge is a
#    narrow door; DoD 5).
clear; real
printf 'canary: must stay as it is\n' > "$W/canary"
cp "$R/.claude/team/PROJECT.md" "$W/project.before"
cksum < "$W/canary" > "$W/canary.sum"
rm -f "$W/nothere"
reqs "LINK .response-1.tmp $W/canary" \
  "LINK response-1.json.tmp $W/canary" \
  "LINK .response-1.json $W/canary" \
  "LINK .response-1.json.tmp $W/canary" \
  "LINK response-1.tmp $W/canary" \
  "LINK .response-1.tmp.$$ $W/canary" \
  "LINK .response-2.tmp $R/.claude/team/PROJECT.md" \
  "LINK response-2.json.tmp $R/.claude/team/PROJECT.md" \
  "LINK .response-3.tmp $W/nothere" \
  "LINK response-3.json.tmp $W/nothere" \
  '{"action":"set","when":"2026-11-20 15:00"}' \
  '{"action":"set","when":"2026-11-22 10:00"}' \
  '{"action":"reset"}'
run "$R" run --clock
t "planted symlinks: the run exits 0" test "$(rc)" = 0
t "planted symlinks: a file linked at a temporary name for a response isn't overwritten" test "$(cksum < "$W/canary")" = "$(cat "$W/canary.sum")"
t "planted symlinks: the project's own PROJECT.md isn't overwritten" cmp -s "$W/project.before" "$R/.claude/team/PROJECT.md"
t "planted symlinks: a dangling link doesn't make the bridge create its target" not test -e "$W/nothere"
t "planted symlinks: request 1 is still answered with ada-clock's first line" resp 1 ".exit == 0 and .line == \"$FRI\""
t "planted symlinks: request 2 is still answered" resp 2 ".exit == 0 and .line == \"$SUN\""
t "planted symlinks: request 3 is still answered" resp 3 ".exit == 0 and .line == \"$RESET\""
t "planted symlinks: the clock is real at the end" test "$(state)" = real
rm -rf "$R/.claude/presstest"
cp "$W/project.before" "$R/.claude/team/PROJECT.md"   # put back what a failing bridge may have overwritten

# 10. However a request is shaped, the run ends and the clock is back on real time (Outcome: back to
#     real time when the run ends, however it ends; DoD 3, 4, 5). A FIFO, a directory and a link
#     to a FIFO named request-<n>.json each get a refusal (exit not 0, a line), run no
#     ada-clock set, and don't stop the bridge answering the request after them. A wrapper that
#     hangs on one is stopped after 90 s by `timeout`, and the checks below then fail.
clear; real
reqs FIFO DIR FIFOLINK '{"action":"set","when":"2026-11-20 15:00"}'
runto "$R" run --clock
pkill -9 -f "$CLOCKDIR/request-" 2>/dev/null
t "FIFO, directory and link to a FIFO: the wrapper ends by itself (exit below 124: not stopped by timeout)" lt "$(rc)" 124
t "FIFO request 1 gets a refusal" resp 1 '(.exit | type == "number") and .exit != 0 and (.line | type == "string") and (.line | length) > 0'
t "directory request 2 gets a refusal" resp 2 '(.exit | type == "number") and .exit != 0 and (.line | type == "string") and (.line | length) > 0'
t "link-to-FIFO request 3 gets a refusal" resp 3 '(.exit | type == "number") and .exit != 0 and (.line | type == "string") and (.line | length) > 0'
t "the good request after them is still answered" resp 4 ".exit == 0 and .line == \"$FRI\""
t "ada-clock set ran once, for the good request" test "$(grep -c '^ada-clock \[set\]' "$W/log/ada-clock" 2>/dev/null)" = 1
t "ada-clock reset runs after the container ends" lt "$(lastpos 'docker run')" "$(lastpos 'ada-clock reset')"
t "then ada-clock status" lt "$(lastpos 'ada-clock reset')" "$(lastpos 'ada-clock status')"
t "the clock is real at the end" test "$(state)" = real
t "ada-clock: real is printed" grep -q 'ada-clock: real$' "$W/out" "$W/err"
rm -rf "$R/.claude/presstest"
real

pkill -9 -f "$W/plugin/bin/presstest" 2>/dev/null
echo
echo "$fails failed"
rm -rf "$W"
[ "$fails" = 0 ]
```

## Expected

Every line `PASS` (one `SKIP` only if `/tmp/x` already existed), `0 failed`, exit 0, under both
bashes. From the Unit E brief (Decisions; Definition of done; Amendments, 15 Nov: one line per
clock request):

- `ada-clock status` runs before anything else, and anything but exit 0 with first line
  `ada-clock: real` stops the run with exit 2, printing that line, with no session, no container
  and the clock as it was.
- `--clock` with `--env staging` is a usage error (exit 2), and nothing runs.
- The container gets `PRESSTEST_CLOCK=1` and `PRESSTEST_CLOCK_DIR=/app/test-results/run/clock`,
  which is `.claude/presstest/run/clock/` on the host, made before it starts, and Playwright gets
  `--workers=1`.
- Each `request-<n>.json` is answered with `response-<n>.json`, `{"exit": <code>, "line": "<first
  line>"}`, and then deleted. Only `{"action": "set", "when": "YYYY-MM-DD HH:MM"}` and
  `{"action": "reset"}` run `ada-clock`, with an argument array; anything else gets a refusal and
  runs nothing.
- When the container ends (passing, failing, no report, killed) and when the wrapper is stopped
  with SIGTERM, the loop stops, `ada-clock reset` runs, then `ada-clock status`, whose first line
  is printed, before the test session ends. A clock not back at `ada-clock: real` makes the run
  exit 1.
- A run without `--clock` makes exactly the released wrapper's calls and output.
- The request folder is untrusted. A symlink planted in it (at a temporary name for a response, to
  a host file or to nothing) never gets that file overwritten or created, and the request is still
  answered. A `request-<n>.json` that is a FIFO, a directory or a link to a FIFO is refused with a
  response, runs no `ada-clock set`, doesn't stop the bridge answering the next request or the run
  ending, and the clock is reset and real at the end.

With the broken copy, section 2 prints `FAIL` lines. With the released plugin as `PLUGIN`,
sections 1 to 5 and 8 to 10 print `FAIL` lines, and sections 6 and 7 pass.

## Observe at

The script's output and exit code.

## Regression

`presstest-runs-the-projects-suite.md` passes unchanged under both bashes (a run without
`--clock`, the session, the settings, `API.md`'s line range for the header). On a real Local
site with Docker: vandme's `tests/functional/00-test-helper/clock-runs-on-the-local-site.md`.
