# The Chrome extension asks for nothing but the four local match patterns, shifts `Date` by the `ada_clock` cookie and only by it, leaves dates built from explicit values alone, and `ada-clock extension` copies it to one stable folder

- Area: 16 Clock (`ada-clock`, the Chrome extension); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219974994818873
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness. Chrome itself is `extension-in-chrome.md`)
- Last run: 2026-10-29, 256 passed, 0 failed under bash 5.2.21 and bash 3.2.57; Step 3 breaks (a) to (j) each print FAIL; see the PR

## Precondition

- This checkout (`REPO`). The script makes its own scratch `HOME` (a path with a space, no
  `.config` in it) and a scratch project whose `local.env` names a `.local` site, so it never
  touches the real `~/.config/ada-clock`, a site or the clock. A guard compares the real
  `~/.config/ada-clock` before and after.
- `git`, `mktemp`, `diff`. `python3` (the manifest checks) and `node` (the content script, run in
  a fresh JavaScript realm per "page" with `node:vm`); checks that need one that is missing print
  `SKIP`.
- Run the checkout's script by path, never an `ada-clock` on the PATH (that is the installed
  plugin).

## Steps

1. Save the block below as `check-ext.sh` in a scratch folder (not in this repo).
2. Run it under a current bash: `REPO=<this checkout> bash check-ext.sh`, and under bash 3.2:
   `docker run --rm -v <this checkout>:/repo:ro -v <scratch folder>:/c bash:3.2 bash -c 'apk add -q git python3 nodejs >/dev/null; REPO=/repo bash /c/check-ext.sh'`.
   (The `bash:3.2` image is Alpine-based; `apk add` needs network access.)
3. Break it on purpose, once per change, each in a fresh copy of `plugins/ada` in a scratch
   folder, run with `REPO=<this checkout> PLUGIN=<the copy> bash check-ext.sh`. Each must print
   `FAIL` lines: (a) in the copy's `content.js`, shift `Date` whenever its first argument is falsy
   rather than only when there are no arguments (`new Date(0)` checks fail); (b) drop
   `*://*.test/*` from the copy's `manifest.json` (the matches check fails); (c) add
   `"permissions": ["cookies"]` to it (the permissions check fails); (d) make `ada-clock extension`
   copy only `manifest.json` (the copy checks fail); (e) in the copy's `content.js`, add a
   `console.warn('ada-clock: bad value', value)` where a non-numeric value is turned away (the
   `nothing is logged to the console at all` and `console.warn` checks fail for the non-numeric
   cases); (f) remove the guard around the `document.cookie` read (the throwing-cookie checks fail
   with an error); (g) accept any non-empty value, e.g. `Number(value)` without a digits check (the
   non-numeric cases fail); (h) in the copy's `ada-clock`, move the `extension` branch after the
   `LOCAL_URL` check (the "no local.env" and "not a local site" cases fail); (i) in the copy's
   `ada-clock`, drop the check that an existing folder's manifest is ours, so any folder is
   replaced (the 4e foreign-folder cases fail: exit 0, and the folder changed); (j) in the copy's
   `ada-clock`, make `extension` ignore its arguments (the 4e argument cases fail).

```bash
#!/usr/bin/env bash
# The Chrome extension (Unit C) without a browser: the folder's files; the manifest's matches, world,
# timing and lack of permissions; content.js run in a fresh JavaScript realm per "page" (node's vm),
# with document.cookie as given, for the Date replacement, the marker and the cookie parsing; and
# ada-clock extension's copy into a scratch HOME. Never touches the real HOME, a site or the clock.
# Prints PASS/FAIL/SKIP per check (a FAIL may show what was seen); exits 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
AC=$PLUGIN/bin/ada-clock
EXT=$PLUGIN/extras/ada-clock-extension
unset LOCAL_URL LOCAL_WP_PATH LOCAL_WP ADA_CLOCK_ADAPTER ADA_CLOCK_LOCAL_SITE XDG_CONFIG_HOME
REAL_HOME=$HOME
W=$(mktemp -d)
trap 'rm -rf "$W"' EXIT
fails=0
ok()   { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()    { name=$1; shift; if seen=$("$@" 2>&1); then ok 0 "$name"; else ok 1 "$name"; [ -n "$seen" ] && printf '%s\n' "$seen" | head -5 | sed 's/^/     /'; fi; }
not()  { ! "$@"; }
have() { command -v "$1" >/dev/null 2>&1; }
skip() { echo "SKIP $1"; }
is()   { [ "$1" = "$2" ] || { echo "got '$1', want '$2'"; return 1; }; }
# sub <output file> <exit code> <what>: print another checker's lines, count its FAILs as ours, and
# fail once more if it stopped early (a non-zero exit with no FAIL line).
sub()  { cat "$1"; n=$(grep -c '^FAIL ' "$1"); fails=$((fails + n)); [ "$2" = 0 ] || [ "$n" != 0 ] || ok 1 "the $3 checker ran to the end (exit $2)"; }

# The guard: the real ~/.config/ada-clock (clock files, an installed copy of the extension).
guard() { ls -lR "$REAL_HOME/.config/ada-clock" 2>/dev/null | cksum; }
G0=$(guard)
export HOME="$W/home dir"
mkdir -p "$HOME"

# 0. Static, and the folder's files (brief, Decisions: manifest.json, one content script, README.md).
t "bash -n is clean on ada-clock" bash -n "$AC"
t "the extension folder is in the plugin: extras/ada-clock-extension" test -d "$EXT"
t "it has manifest.json" test -f "$EXT/manifest.json"
t "it has README.md" test -f "$EXT/README.md"
t "it has exactly one content script (one .js file)" is "$(find "$EXT" -type f -name '*.js' | wc -l | tr -d ' ')" 1
t "the content script is content.js" test -f "$EXT/content.js"
if have node; then t "node --check is clean on content.js" node --check "$EXT/content.js"; else skip "node --check (no node)"; fi

# 1. The manifest (brief, Where it runs; Definition of done 7: no permissions beyond the matches).
cat > "$W/manifest.py" <<'PY'
import json, os, re, sys
d = sys.argv[1]
def ok(c, name, seen=None):
    print(('PASS ' if c else 'FAIL ') + name + ('' if c or seen is None else '\n     seen: %r' % (seen,)))
try:
    m = json.load(open(os.path.join(d, 'manifest.json')))
except Exception as e:
    ok(False, 'manifest.json is JSON', str(e))
    sys.exit(0)
ok(isinstance(m, dict), 'manifest.json is a JSON object', type(m).__name__)
if not isinstance(m, dict):
    sys.exit(0)
ok(m.get('manifest_version') == 3, 'manifest_version is 3 (Manifest V3)', m.get('manifest_version'))
ok(isinstance(m.get('name'), str) and m['name'].strip() != '', 'it has a name (Chrome will not load it without one)', m.get('name'))
v = m.get('version')
ok(isinstance(v, str) and re.match(r'^(0|[1-9][0-9]{0,4})(\.(0|[1-9][0-9]{0,4})){0,3}$', v) is not None,
   'its version is one to four dot-separated integers (Chrome will not load it otherwise)', v)
for k in ('permissions', 'optional_permissions', 'host_permissions', 'optional_host_permissions'):
    ok(m.get(k, []) == [], 'no %s' % k, m.get(k))
ok('background' not in m, 'no background worker', m.get('background'))
cs = m.get('content_scripts')
ok(isinstance(cs, list) and len(cs) == 1 and isinstance(cs[0], dict), 'exactly one content_scripts entry', cs)
if isinstance(cs, list) and len(cs) == 1 and isinstance(cs[0], dict):
    c = cs[0]
    want = sorted(['*://localhost/*', '*://127.0.0.1/*', '*://*.local/*', '*://*.test/*'])
    mt = c.get('matches')
    ok(isinstance(mt, list) and len(mt) == 4 and sorted(mt) == want,
       'matches are exactly *://localhost/*, *://127.0.0.1/*, *://*.local/* and *://*.test/*', mt)
    ok(c.get('js') == ['content.js'], 'its js is ["content.js"]', c.get('js'))
    ok('css' not in c, 'no css', c.get('css'))
    ok(c.get('run_at') == 'document_start', 'run_at is document_start', c.get('run_at'))
    ok(c.get('world') == 'MAIN', "world is MAIN (the page's own JavaScript sees the change)", c.get('world'))
    ok(c.get('all_frames') is True, 'all_frames is true', c.get('all_frames'))
PY
if have python3; then python3 "$W/manifest.py" "$EXT" > "$W/m.out" 2>&1; sub "$W/m.out" $? manifest; else skip "the manifest checks (no python3)"; fi

# 2. No network requests (brief, Where it runs). A static look: any match is shown for a person to judge.
net() { ! grep -nE '(^|[^[:alnum:]_.])fetch[[:space:]]*\(|XMLHttpRequest|WebSocket|EventSource|sendBeacon|importScripts|(^|[^[:alnum:]_.])import[[:space:]]*\(|chrome\.runtime|new[[:space:]]+Image[[:space:]]*\(' "$EXT/content.js"; }
t "content.js makes no network requests (no fetch, XHR, WebSocket, EventSource, beacon, import() or chrome.runtime)" net

# 3. content.js in a fresh realm per page (brief, The time and Marker; Definition of done 2 to 4, and 6: a value that isn't a number, an empty one, a document.cookie that throws).
cat > "$W/harness.js" <<'JS'
'use strict';
// content.js in a fresh JavaScript realm per page (node's vm), as Chrome runs it in a page's main
// world at document_start: window is the realm's global, document.cookie as given, console a stub
// that records every call. Prints PASS/FAIL lines; exits 1 if any FAIL.
const fs = require('fs');
const vm = require('vm');
const src = fs.readFileSync(process.argv[2], 'utf8');
const T = 1793966100;        // 2026-11-06 11:55:00 UTC: a future time, a positive offset
const TP = 1582977600;       // 2020-02-29 12:00:00 UTC: a past time (a leap day), a negative offset
const J2020 = 1577836800000; // 2020-01-01 00:00:00 UTC, in ms
let fails = 0;
const show = v => { try { return v === undefined ? 'undefined' : JSON.stringify(v); } catch (e) { return String(v); } };
function ok(c, name, ...s) {
  if (c) { console.log('PASS ' + name); return; }
  fails++;
  console.log('FAIL ' + name + (s.length > 0 ? '\n     seen: ' + show(s[0]) : ''));
}

function page(cookie, cookieThrows) {
  const ctx = vm.createContext({});
  vm.runInContext('globalThis.window = globalThis; globalThis.self = globalThis; globalThis.top = globalThis; globalThis.parent = globalThis;', ctx);
  const doc = { readyState: 'loading', URL: 'http://shop.local/', domain: 'shop.local' };
  if (cookieThrows) {
    Object.defineProperty(doc, 'cookie', { configurable: true, get() {
      throw new Error("Failed to read the 'cookie' property from 'Document': The document is sandboxed and lacks the 'allow-same-origin' flag.");
    } });
  } else {
    doc.cookie = cookie;
  }
  ctx.document = doc;
  // Every console call content.js makes is recorded, not printed (a page's DevTools console, as far
  // as a node run can show it): 'error: <args>', 'warn: <args>', 'log: <args>' and so on.
  const logs = [];
  const con = {};
  ['log', 'info', 'warn', 'error', 'debug', 'trace', 'dir', 'table', 'assert', 'group', 'groupCollapsed', 'groupEnd', 'count', 'time', 'timeEnd', 'timeLog', 'clear']
    .forEach(m => { con[m] = (...a) => { logs.push(m + ': ' + a.map(String).join(' ')); }; });
  ctx.console = con;
  ctx.location = { hostname: 'shop.local', host: 'shop.local', protocol: 'http:', origin: 'http://shop.local', href: 'http://shop.local/' };
  const run = code => vm.runInContext(code, ctx);
  const p = { run, native: run('Date'), error: null, logs };
  p.before = Date.now();
  try { vm.runInContext(src, ctx, { filename: 'content.js' }); } catch (e) { p.error = String((e && e.message) || e); }
  p.after = Date.now();
  return p;
}

// shifted: the cookie is there, so Date is a subclass whose no-argument constructor, Date() and
// Date.now() add offset = cookie seconds x 1000 - Date.now() at that moment; explicit values untouched.
function shifted(label, cookie, ts) {
  const p = page(cookie);
  const L = label + ': ';
  const want = ts * 1000;
  const wantIso = new Date(want).toISOString();
  ok(p.error === null, L + 'content.js runs without an error', p.error);
  if (p.error !== null) return;
  ok(p.logs.filter(l => /^(error|warn):/.test(l)).length === 0, L + 'nothing goes to console.error or console.warn', p.logs);
  const near = (v, slack) => typeof v === 'number' && Math.abs(v - want) <= slack;
  let v = p.run('Date.now()');
  ok(near(v, 5000), L + 'Date.now() is within 5 s of ' + wantIso, v);
  v = p.run('new Date().getTime()');
  ok(near(v, 5000), L + 'new Date() is within 5 s of ' + wantIso, v);
  v = p.run('new Date().toISOString()');
  ok(near(Date.parse(v), 5000), L + 'new Date().toISOString() is within 5 s of ' + wantIso, v);
  v = p.run('Date()');
  ok(typeof v === 'string' && near(Date.parse(v), 6000), L + 'Date() called as a function gives a string within 5 s of ' + wantIso + ' (it has whole seconds)', v);
  const m = p.run('window.__adaClock');
  ok(m !== null && typeof m === 'object' && typeof m.offsetMs === 'number', L + 'window.__adaClock is { offsetMs: <number> }', m);
  if (m && typeof m.offsetMs === 'number') {
    const lo = want - p.after - 5, hi = want - p.before + 5;
    ok(m.offsetMs >= lo && m.offsetMs <= hi, L + 'offsetMs is the cookie seconds x 1000 minus Date.now() when content.js ran (' + lo + ' to ' + hi + ')', m.offsetMs);
  }
  const N = p.native;
  ok(p.run('new Date(2020, 0, 1).getTime()') === new N(2020, 0, 1).getTime(), L + 'new Date(2020, 0, 1) is unchanged', p.run('new Date(2020, 0, 1).getTime()'));
  ok(p.run('new Date(2020, 1, 29, 12, 30, 15, 250).getTime()') === new N(2020, 1, 29, 12, 30, 15, 250).getTime(), L + 'new Date(2020, 1, 29, 12, 30, 15, 250) is unchanged');
  ok(p.run('new Date(2020, 0).getTime()') === new N(2020, 0).getTime(), L + 'new Date(2020, 0) (two values) is unchanged');
  ok(p.run('new Date(0).getTime()') === 0, L + 'new Date(0) is the epoch, unchanged', p.run('new Date(0).getTime()'));
  ok(p.run('new Date(' + J2020 + ').getTime()') === J2020, L + 'new Date(1577836800000) is unchanged');
  ok(p.run('new Date("2020-01-01T00:00:00Z").getTime()') === J2020, L + 'new Date("2020-01-01T00:00:00Z") is unchanged');
  ok(p.run('new Date(new Date(5)).getTime()') === 5, L + 'new Date(<a date>) copies it, unchanged');
  ok(p.run('isNaN(new Date(undefined).getTime())') === true, L + 'new Date(undefined) is an invalid date, as with the native Date (an explicit value)');
  ok(p.run('Date.UTC(2020, 0, 1)') === J2020, L + 'Date.UTC(2020, 0, 1) is 1577836800000, unchanged', p.run('Date.UTC(2020, 0, 1)'));
  ok(p.run('Date.parse("2020-01-01T00:00:00Z")') === J2020, L + 'Date.parse("2020-01-01T00:00:00Z") is unchanged');
  ok(p.run('Date') !== N, L + 'Date is replaced');
  ok(p.run('new Date() instanceof Date') === true, L + 'new Date() instanceof Date is true');
  ok(p.run('new Date(2020, 0, 1) instanceof Date') === true, L + 'new Date(2020, 0, 1) instanceof Date is true');
  ok(p.run('new Date()') instanceof N, L + 'new Date() is an instance of the native Date (the replacement is a subclass)');
  ok(p.run('Object.prototype.toString.call(new Date())') === '[object Date]', L + 'new Date() is a real Date object ([object Date])');
  ok(p.run('new Date().getUTCFullYear()') === new Date(want).getUTCFullYear(), L + 'new Date().getUTCFullYear() is the fake year, ' + new Date(want).getUTCFullYear());
  const a = p.run('Date.now()');
  const until = Date.now() + 300;
  while (Date.now() < until) { /* wait 300 ms of real time */ }
  const b = p.run('Date.now()');
  ok(b - a >= 290 && b - a <= 1500, L + 'the fake time runs on: 300 ms later, Date.now() has moved about 300 ms', b - a);
}

// unchanged: no usable ada_clock cookie, so content.js changes nothing: Date native, no marker, real time,
// no error. strict is for the inputs Definition of done 6 names (a value that isn't a number, an empty
// one, a document.cookie that throws): the console gets no call at all, not even console.log. Without
// strict, console.error and console.warn are still required to stay silent (P12).
function unchanged(label, cookie, strict, cookieThrows) {
  const p = page(cookie, cookieThrows);
  const L = label + ': ';
  ok(p.error === null, L + 'content.js runs without an error (nothing thrown into the page)', p.error);
  ok(p.run('Date') === p.native, L + 'Date is the native one (nothing changed)');
  ok(/\[native code\]/.test(p.run('Function.prototype.toString.call(Date)')), L + "Date's source is '[native code]'", p.run('Function.prototype.toString.call(Date)'));
  ok(p.run('typeof window.__adaClock') === 'undefined', L + 'window.__adaClock is undefined', p.run('window.__adaClock'));
  const v = p.run('Date.now()');
  ok(typeof v === 'number' && Math.abs(v - Date.now()) <= 1000, L + 'Date.now() is real time', v);
  const w = p.run('new Date().getTime()');
  ok(typeof w === 'number' && Math.abs(w - Date.now()) <= 1000, L + 'new Date() is real time', w);
  const noisy = p.logs.filter(l => /^(error|warn):/.test(l));
  ok(noisy.length === 0, L + 'nothing goes to console.error or console.warn', noisy);
  if (strict) ok(p.logs.length === 0, L + 'nothing is logged to the console at all', p.logs);
}

// The harness itself: its console stub records calls, and its throwing document.cookie throws, so the
// checks above can fail (they would pass for nothing if the stub never saw a call).
(function () {
  const p = page('');
  p.run('console.warn("probe", 1); console.error("probe"); console.log("probe")');
  ok(p.logs.length === 3 && /^warn: probe 1$/.test(p.logs[0]) && /^error: probe$/.test(p.logs[1]), 'harness: the console stub records console.warn, console.error and console.log calls made in the page', p.logs);
  const q = page('', true);
  ok(q.run('(() => { try { void document.cookie; return false; } catch (e) { return true; } })()') === true, 'harness: the throwing document.cookie does throw when read');
})();

shifted('ada_clock among other cookies, a future time', 'wordpress_test_cookie=WP%20Cookie%20check; ada_clock=' + T + '; wp-settings-time-1=1700000000', T);
shifted('ada_clock the only cookie', 'ada_clock=' + T, T);
shifted('ada_clock the last cookie, a past time (a negative offset)', 'wp-settings-1=x; ada_clock=' + TP, TP);
shifted('ada_clock 11 digits, the longest allowed (99999999999, the year 5138)', 'ada_clock=99999999999', 99999999999);
unchanged('no cookies', '');
unchanged('other cookies only', 'wordpress_test_cookie=WP%20Cookie%20check; wp-settings-1=x');
unchanged('only cookies whose names contain ada_clock (not_ada_clock, xada_clock, ada_clock_x)', 'not_ada_clock=' + T + '; xada_clock=' + T + '; ada_clock_x=' + T);
// Definition of done 6 (amendment, 2026-10-29): a value that isn't a number, an empty value, or a
// document.cookie read that throws leaves Date native, sets no window.__adaClock and logs nothing.
unchanged('ada_clock not a number (abc)', 'ada_clock=abc', true);
unchanged('ada_clock not a number (12abc: digits then letters)', 'ada_clock=12abc', true);
unchanged('ada_clock not a number (NaN)', 'ada_clock=NaN', true);
unchanged('ada_clock not a number (null)', 'ada_clock=null', true);
unchanged('ada_clock not a number, among other cookies', 'wp-settings-1=x; ada_clock=abc; wordpress_test_cookie=WP%20Cookie%20check', true);
// Too long for a Date (PR #47 review): more than 11 digits leaves Date native rather than making every
// new Date() an Invalid Date. 12 digits still fits a Date; 14 does not.
unchanged('ada_clock 12 digits (999999999999)', 'ada_clock=999999999999', true);
unchanged('ada_clock 14 digits, past the last Date (99999999999999)', 'ada_clock=99999999999999', true);
unchanged('ada_clock empty', 'ada_clock=', true);
unchanged('ada_clock empty, among other cookies', 'wp-settings-1=x; ada_clock=; wordpress_test_cookie=WP%20Cookie%20check', true);
unchanged('document.cookie throws, as in a sandboxed frame without allow-same-origin', '', true, true);
console.log('content.js checks: ' + fails + ' failed');
process.exit(fails ? 1 : 0);
JS
if have node && [ -f "$EXT/content.js" ]; then
  node "$W/harness.js" "$EXT/content.js" > "$W/h.out" 2>&1; sub "$W/h.out" $? content.js
else
  skip "content.js in a fresh realm (no node, or no content.js)"
fi

# 4. ada-clock extension (brief, Loading it; Definition of done 1), in a scratch HOME with no .config,
# from a scratch project whose local.env names a .local site.
P="$W/my project"; mkdir -p "$P/.claude/team" "$W/site/wp-content"
git -C "$P" init -q; printf '.claude/team/local.env\n' > "$P/.gitignore"
printf '%s\n' "LOCAL_URL=http://shop.local" "LOCAL_WP_PATH=\"$W/site\"" > "$P/.claude/team/local.env"
git -C "$P" add -A; git -C "$P" -c user.name=t -c user.email=t@example.com commit -qm init
D="$HOME/.config/ada-clock/chrome-extension"
run()  { (cd "$P" && bash "$1" extension) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()   { cat "$W/rc"; }
says() { cat "$W/out" "$W/err" | grep "$@" >/dev/null; }
tree() { (cd "$1" && find . -type f | sort | while read -r f; do printf '%s %s\n' "$(cksum < "$f")" "$f"; done); }
x0=$(tree "$EXT")

# 4a. First run.
t "precondition: the scratch HOME has no .config" not test -e "$HOME/.config"
run "$AC"
t "extension exits 0" is "$(rc)" 0
t "it copies the folder to ~/.config/ada-clock/chrome-extension/, exactly" diff -r "$EXT" "$D"
t "it prints chrome://extensions" says -F 'chrome://extensions'
t "it prints Developer mode" says -i 'developer mode'
t "it prints Load unpacked" says -i 'load unpacked'
t "it prints the folder's path, $D" says -F "$D"
upd() { cat "$W/out" "$W/err" "$D/README.md" 2>/dev/null | grep -qi 'reload' || { echo "no 'reload' in the output or the copied README.md"; return 1; }; }
t "the update step (run it again, then the extension's reload button) is in the output or the README" upd
t "nothing else is written in HOME" is "$(cd "$HOME" && find . -mindepth 1 -maxdepth 3 | sort | tr '\n' ' ')" "./.config ./.config/ada-clock ./.config/ada-clock/chrome-extension "
t "nothing is written into the project (git status clean)" is "$(git -C "$P" status --porcelain --untracked-files=all)" ""
t "the plugin's own folder is unchanged" is "$(tree "$EXT")" "$x0"

# 4b. Run again, with the copy edited by hand and a site's clock file beside it (Unit B's).
printf '\n// edited by hand\n' >> "$D/content.js"
printf '+0s\n' > "$HOME/.config/ada-clock/Sh0p_bC12"
run "$AC"
t "run again: exit 0" is "$(rc)" 0
t "run again: the copy is the plugin's folder again (the hand edit replaced)" diff -r "$EXT" "$D"
t "run again: a site's clock file beside it is left alone" is "$(cat "$HOME/.config/ada-clock/Sh0p_bC12")" "+0s"

# 4c. A plugin update: another copy of the plugin, its content.js changed, run from that copy.
V2="$W/plugin v2"; cp -R "$PLUGIN" "$V2"
printf '\n// version 2\n' >> "$V2/extras/ada-clock-extension/content.js"
run "$V2/bin/ada-clock"
t "after a plugin update: exit 0" is "$(rc)" 0
t "after a plugin update: the copy is the running plugin's folder (found through the script's own location)" diff -r "$V2/extras/ada-clock-extension" "$D"
t "after a plugin update: the folder printed is still $D" says -F "$D"
t "after a plugin update: the clock file is still left alone" is "$(cat "$HOME/.config/ada-clock/Sh0p_bC12")" "+0s"

# 4d. No settings (Ada's decision on the brief: extension reads no settings and runs before the LOCAL_URL check).
# Each case is a fresh HOME with no .config, and a project with no .claude/team/local.env, or one whose
# LOCAL_URL isn't a local site (every other subcommand refuses that; extension must not).
n4=0
nosettings() {  # nosettings <name> <project dir>
  n4=$((n4 + 1)); H4="$W/home 4d$n4"; mkdir -p "$H4"   # a path that names no setting, so the 'does not mention' check is fair
  (cd "$2" && HOME="$H4" bash "$AC" extension) > "$W/out" 2> "$W/err"; echo $? > "$W/rc"
  D4="$H4/.config/ada-clock/chrome-extension"
  t "$1: extension exits 0" is "$(rc)" 0
  t "$1: it copies the folder to ~/.config/ada-clock/chrome-extension/, exactly" diff -r "$EXT" "$D4"
  t "$1: it prints 'extension: installed'" says -F 'extension: installed'
  t "$1: it prints chrome://extensions, Developer mode, Load unpacked and the folder's path" bash -c 'cat "$1" "$2" | grep -qF chrome://extensions && cat "$1" "$2" | grep -qi "developer mode" && cat "$1" "$2" | grep -qi "load unpacked" && cat "$1" "$2" | grep -qF "$3"' _ "$W/out" "$W/err" "$D4"
  t "$1: it does not mention LOCAL_URL or local.env (no setting was needed)" not says -E 'LOCAL_URL|local\.env'
  t "$1: nothing else is written in HOME" is "$(cd "$H4" && find . -mindepth 1 -maxdepth 3 | sort | tr '\n' ' ')" "./.config ./.config/ada-clock ./.config/ada-clock/chrome-extension "
}
PN="$W/project with no local.env"; mkdir -p "$PN"
git -C "$PN" init -q
nosettings "no local.env at all (no .claude folder)" "$PN"
t "no local.env: nothing is written into the project (no .claude, no local.env)" is "$(cd "$PN" && find . -path ./.git -prune -o -mindepth 1 -print | tr '\n' ' ')" ""
mkdir -p "$PN/.claude/team"
nosettings "an empty .claude/team and no local.env" "$PN"
t "an empty .claude/team: local.env is still not created" not test -e "$PN/.claude/team/local.env"
PX="$W/project not local"; mkdir -p "$PX/.claude/team"; git -C "$PX" init -q
printf '%s\n' "LOCAL_URL=https://example.com" > "$PX/.claude/team/local.env"
nosettings "LOCAL_URL is not a local site (https://example.com)" "$PX"
t "LOCAL_URL not local: local.env is as it was" is "$(cat "$PX/.claude/team/local.env")" "LOCAL_URL=https://example.com"

# 4e. Refusals (Ada's decision on the brief: an existing copy is replaced only when its manifest is
# ours, otherwise exit 2; and extension takes no argument). Each case is a fresh HOME with a clock file
# beside the extension's folder. A refusal exits 2, says why on stderr, installs or replaces nothing,
# and leaves everything under ~/.config as it was (no temp folder left behind).
snap() {  # snap <path>: every folder, and every file with its checksum, under the path (or the file's own checksum)
  if [ -d "$1" ]; then (cd "$1" && find . | sort | while read -r f; do if [ -f "$f" ]; then printf '%s %s\n' "$(cksum < "$f")" "$f"; else printf 'd %s\n' "$f"; fi; done)
  elif [ -f "$1" ]; then printf 'file %s\n' "$(cksum < "$1")"; else echo nothing; fi
}
n5=0
refusal() {  # refusal <name> <setup function> [argument ...]
  case_name=$1; setup=$2; shift 2
  n5=$((n5 + 1)); H5="$W/home 4e$n5"; mkdir -p "$H5/.config/ada-clock"
  D5="$H5/.config/ada-clock/chrome-extension"
  printf '+0s\n' > "$H5/.config/ada-clock/Sh0p_bC12"
  "$setup" "$D5"
  before=$(snap "$H5/.config")
  (cd "$P" && HOME="$H5" bash "$AC" extension "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"
  t "$case_name: exits 2" is "$(rc)" 2
  t "$case_name: it says why on stderr" test -s "$W/err"
  t "$case_name: it does not print installed or replaced" not says -E 'extension: (installed|replaced)'
  t "$case_name: nothing under ~/.config changed (the folder or file at the path, the clock file, no temp folder left)" is "$(snap "$H5/.config")" "$before"
  t "$case_name: nothing else is written in HOME" is "$(cd "$H5" && find . -mindepth 1 -maxdepth 1 | tr '\n' ' ')" "./.config "
}
no_copy()          { :; }
foreign_manifest() { mkdir -p "$1"; printf '%s\n' '{ "manifest_version": 3, "name": "Someone else", "version": "2.0" }' > "$1/manifest.json"; printf 'mine\n' > "$1/notes.txt"; }
no_manifest()      { mkdir -p "$1/sub"; printf 'mine\n' > "$1/notes.txt"; printf 'x\n' > "$1/sub/a.js"; }
empty_manifest()   { mkdir -p "$1"; : > "$1/manifest.json"; }
a_file()           { printf 'a file, not a folder\n' > "$1"; }
ours_edited()      { cp -R "$EXT" "$1"; printf '\n// edited by hand\n' >> "$1/content.js"; }
refusal "a folder that is not ours (another extension's manifest.json, and a file of its own)" foreign_manifest
refusal "a folder that is not ours (no manifest.json at all, a subfolder)" no_manifest
refusal "a folder that is not ours (an empty manifest.json)" empty_manifest
refusal "a file at the path" a_file
refusal "an argument, no copy yet" no_copy now
refusal "an argument, our own copy there (edited by hand)" ours_edited now

# 5. The real HOME.
t "the real ~/.config/ada-clock is untouched" is "$(guard)" "$G0"

echo
echo "$fails failed"
[ "$fails" = 0 ]
```

## Expected

Every line `PASS`, `0 failed`, exit 0, under both bashes. `SKIP` only for a missing `python3` or
`node`. There are no `WARN` lines: the three inputs the brief once left open (a value that isn't a
number, an empty one, a `document.cookie` that throws) are Definition of done 6 since the
2026-10-29 amendment, and each is a `PASS`/`FAIL` check on `Date`, `window.__adaClock`, and the
console. `ada-clock extension` needs no settings (Ada's decision, not an amendment): it succeeds
with no `.claude/team/local.env` and with a `LOCAL_URL` that isn't local (section 4d). It refuses,
with exit 2 and nothing changed, a folder at its path that isn't its own, a file at that path, and any
argument (section 4e).

Out of scope (the brief's Amendments, KG-2): `ada_clock` going stale on `wp-login.php` and on static
files, because no PHP response refreshes it there. No check here expects the clock to be current on
either.

Sources: Ada's agreed decision on the brief (4e: replace an existing copy only when its manifest is
ours, otherwise exit 2; no argument), and the brief's Decisions (Where it lives; Loading it; Where it runs: `document_start`,
`"world": "MAIN"`, `all_frames`, the four patterns only, no other permissions, no background worker,
no network requests; The time: the offset rule, the subclass, the no-argument constructor, `Date()`
and `Date.now()` shifted, explicit values untouched, nothing changed without the cookie; Marker:
`window.__adaClock = { offsetMs }`) and its Definition of done 1, 2 (the 5-second window), 3, 4
(no cookie: real time, no marker), 6 (a value that isn't a number, an empty value, a `document.cookie`
that throws: `Date` native, no marker, nothing logged) and 7 (no permissions). API.md's `ada-clock` entry for the cookie's name and value
(Unix seconds). The fake times are 2026-11-06 11:55:00 UTC (a positive offset) and 2020-02-29
12:00:00 UTC (a past leap day, a negative offset).

## Observe at

The script's output and exit code.

## Regression

The real `~/.config/ada-clock` is untouched (the guard). Unit A's `command-in-a-scratch-repo.md`
and Unit B's `local-linux-in-a-scratch-home.md` still `0 failed`; the latter's section 6c runs
`uninstall` with this folder beside the clock files.
