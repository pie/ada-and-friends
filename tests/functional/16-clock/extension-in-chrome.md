# In Chrome, with the extension loaded, a local site's own JavaScript sees the time in the `ada_clock` cookie, dates built from explicit values are untouched, and a site that isn't local is never changed

- Area: 16 Clock (`ada-clock`, the Chrome extension)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219974994818873
- Automate as: none (needs Chrome with the unpacked extension loaded, done by a person; Chrome for
  Testing with `--load-extension` and Playwright could drive it later). The parts that need no
  browser are in `extension-in-a-scratch-home.md`.
- Last run: 2026-10-29, parts B, D, E passed (frame sub-check of 16 and part C not run); see the PR

## Precondition

- Chrome (stable), with the person signed in to nothing on the local site but their own admin.
- An onboarded project with a running Local site, e.g. V&Me: `.claude/team/local.env` has
  `LOCAL_URL` (a `.local` site) and `LOCAL_WP_PATH`. Unit A's mu-plugin is installed
  (`ada-clock status` prints `mu-plugin: installed …`). For part C, Unit B's `local-linux` setup
  is installed on that site (`ADA_CLOCK_ADAPTER=local-linux`; `status` prints `wrapper: installed`
  and `pool: loaded`).
- The project's `PROJECT.md` (Local copies) safety checks pass and its background runners are
  frozen: the steps load pages on a copy of real data.
- No fake time is set: `ada-clock status`'s first line is `ada-clock: real`. No `ada_clock` cookie
  is left on `<LOCAL_URL>` from an earlier run (DevTools → Application → Cookies): it is a session
  cookie, and a static page such as `readme.html` doesn't expire it.
- Known gap, not tested (the brief's Amendments, KG-2): the cookie goes stale on `wp-login.php` and
  on static files, because no PHP response refreshes it there. No step below expects the clock to be
  current on either; steps that need a current cookie use a WordPress front page or wp-admin page.
- `AC` below is this checkout's script: `bash <this checkout>/plugins/ada/bin/ada-clock`, run from
  the project's root. Never the `ada-clock` on the PATH (the installed plugin).
- Note the clock time (`date '+%F %T %Z'`) at the start of each part: it goes in the report.

The console expressions below are typed into DevTools → Console on the page named, as one line.
Two are used more than once:

- **R** (real time, nothing changed):
  `({ marker: typeof window.__adaClock, native: Function.prototype.toString.call(Date), drift: Date.now() - Math.round(performance.timeOrigin + performance.now()) })`
- **O** (the offset the page sees, in seconds; `performance` is not shifted, by the brief):
  `({ offsetS: Math.round((Date.now() - (performance.timeOrigin + performance.now())) / 1000), iso: new Date().toISOString(), marker: JSON.stringify(window.__adaClock) })`

## Steps

### A. Copy and load it (Definition of done 1, 7)

1. `AC extension`.
2. `chrome://extensions` → turn on **Developer mode** → **Load unpacked** → choose the folder it
   printed. (If it was loaded before: the card's reload button instead.)
3. On the extension's card: **Details**.

### B. The cookie set by hand, on a page the mu-plugin doesn't answer (Definition of done 2, 3, 4)

WordPress's own `readme.html` is a static file, so no response sets or expires `ada_clock` on it:
the extension is checked on its own. If `<LOCAL_URL>/readme.html` is a 404 on this site, skip B
and say so; C covers the same items through the mu-plugin.

4. Open `<LOCAL_URL>/readme.html`. Console: R.
5. Console: `document.cookie = "ada_clock=1793966100; path=/"` (2026-11-06 11:55:00 UTC). Reload
   the page (Ctrl+R).
6. Console:
   `({ drift: Date.now() - 1793966100000 - Math.round(performance.now()), fnDrift: Date.parse(Date()) - 1793966100000 - Math.round(performance.now()), iso: new Date().toISOString(), marker: JSON.stringify(window.__adaClock), y2020: [new Date(2020, 0, 1).getFullYear(), new Date(2020, 0, 1).getMonth(), new Date(2020, 0, 1).getDate(), new Date(2020, 0, 1).getHours()].join(), utc: Date.UTC(2020, 0, 1), epoch: new Date(0).getTime(), parsed: new Date("2020-01-01T00:00:00Z").getTime(), isDate: new Date() instanceof Date })`
7. Frames (`all_frames`). Console:
   `(() => { const f = document.createElement("iframe"); f.src = location.href; f.onload = () => { const w = f.contentWindow; console.log("frame:", JSON.stringify(w.__adaClock), Math.round((w.Date.now() - (w.performance.timeOrigin + w.performance.now())) / 1000)); }; document.body.appendChild(f); })()`
8. Console: `document.cookie = "ada_clock=; path=/; max-age=0"`. Reload. Console: R.
9. Console tab, all levels: look for errors from `content.js` during 4 to 8.

### C. Through Unit A and Unit B, the way it is used (the Outcome; Definition of done 2, 4)

10. `AC set "2026-11-06 11:55"`. Note its first line, and the clock file's offset it prints
    (`clock file: <path> (<offset>)`), or `cat ~/.config/ada-clock/<site ID>`.
11. Open `<LOCAL_URL>/` (a WordPress page, not `readme.html`) and reload it once. Console: O.
    DevTools → Application → Cookies → `<LOCAL_URL>`: the `ada_clock` value.
12. Open any wp-admin page (as yourself). Console: O.
13. `AC reset`. Reload the same WordPress front page. Console: R. Application → Cookies.

### D. A cookie that isn't a time (Definition of done 6)

Unit A's `manual` state is not used: under `manual` the computer's own clock is what changes, so the
browser would show the fake time without the extension, and the check couldn't tell it worked.

14. Open `<LOCAL_URL>/readme.html` (no response touches the cookie). Console, as one line each:
    `document.cookie = "ada_clock=abc; path=/"`, then reload, then R. Also look at the Console tab
    with all levels shown (Default levels: Verbose, Info, Warnings and Errors ticked).
    Repeat with `"ada_clock=12abc; path=/"`.
15. The same with an empty value: `document.cookie = "ada_clock=; path=/"`, reload, R, the Console tab.
    (Chrome keeps an empty-valued cookie, so `document.cookie` shows `ada_clock=`: check that first.)
16. A sandboxed frame, whose `document.cookie` throws. First set a real value:
    `document.cookie = "ada_clock=1793966100; path=/"`, reload. Then Console:
    `(() => { const f = document.createElement("iframe"); f.setAttribute("sandbox", "allow-scripts"); f.src = location.href; document.body.appendChild(f); })()`.
    The frame has an opaque origin, so its cookie can't be read. In the Console's context selector
    (the "top" dropdown) choose that frame (named after the page's own URL, listed under "top"), and
    run R there; look at the Console tab. Then choose "top" again and clear the cookie:
    `document.cookie = "ada_clock=; path=/; max-age=0"`. (An opaque-origin frame can't have its
    cookie read at all: if the dropdown doesn't offer the frame, say so: that sub-check is then not
    run, not passed.)

### E. A site that isn't local (Definition of done 5)

17. Open `https://example.com`. Console: `document.cookie = "ada_clock=1793966100; path=/"`.
    Reload. Console: R.
18. Console: `document.cookie = "ada_clock=; path=/; max-age=0"`.

### Clean up

19. `AC status`: first line `ada-clock: real`. No `ada_clock` cookie left on `<LOCAL_URL>` or
    `example.com` (Application → Cookies). The extension may stay loaded.

## Expected

1. Exit 0. The output names `chrome://extensions`, Developer mode, Load unpacked and the folder
   `~/.config/ada-clock/chrome-extension` (as an absolute path); the update step (run it again,
   then the extension's reload button) is in the output or the folder's `README.md`.
2. The extension's card appears with **no Errors button**, and no "Inspect views: service worker"
   line (no background worker).
3. Details → Permissions: "No special permissions" (Chrome's words for none). Site access: only
   `localhost`, `127.0.0.1`, `*.local` and `*.test` (the four patterns), nothing else.
4. R: `marker: "undefined"`, `native: "function Date() { [native code] }"`, `drift` between
   −1000 and 1000 (no cookie: nothing changed).
5. –
6. `drift` and `fnDrift` between −5000 and 5000 (`Date.now()` and `Date()` within 5 s of the
   cookie's time, counted from page load); `iso` starts `2026-11-06T11:5`; `marker`
   `{"offsetMs":<n>}`, `<n>` positive and about 1793966100000 minus the real time in ms when the
   page loaded; `y2020` `"2020,0,1,0"`; `utc` `1577836800000`; `epoch` `0`; `parsed`
   `1577836800000`; `isDate` `true`.
7. The console logs `frame: {"offsetMs":<n>} <s>`, `<s>` within 5 of `<n>` / 1000 from step 6.
8. R as in 4: real time, `marker: "undefined"`, the native `Date`.
9. No error from `content.js` or the extension.
10. First line `ada-clock: fake 2026-11-06 11:55 <tz> (local-linux), written to <path>`; an offset
    `+<n>s`.
11. O: `offsetS` within 5 of the clock file's `<n>`; `iso` is the fake time now (2026-11-06 11:55
    site-local, plus the time since step 10, in UTC); `marker` `{"offsetMs":<m>}` with `<m>` / 1000
    within 5 of `<n>`. The `ada_clock` cookie is a number within a few seconds of the fake time
    now (Unix seconds).
12. O as in 11.
13. R as in 4. No `ada_clock` cookie in Application → Cookies.
14. Each time: R: `marker: "undefined"`, `native: "function Date() { [native code] }"`, `drift`
    between −1000 and 1000 (real time, although the cookie is there). The Console tab shows nothing
    from `content.js`: no message at any level (no warning, no error, no log).
15. As 14: R as in 4, and nothing in the Console tab. `document.cookie` does contain `ada_clock=`.
16. In the frame's context: R gives `marker: "undefined"`, the native `Date`, real time. Nothing in
    the Console tab from `content.js` (no "Failed to read the 'cookie' property" message, no
    warning). The top page, whose cookie is readable, is shifted as in step 6 (a sandboxed frame
    doesn't change what the top page does).
17. R: `marker: "undefined"`, `native: "function Date() { [native code] }"`, `drift` between −1000
    and 1000, although the cookie is there: the extension doesn't run on a site that isn't local.
18. –
19. As stated.

Sources: the brief's Outcome; Decisions (Loading it, Where it runs, The time, Marker); Definition
of done 1 to 7, with its Amendments of 2026-10-29 (DoD 2 reworded: the cookie at a fake time, `set`
under `local-linux` or by hand on a static page; DoD 6 new: a value that isn't a number, an empty
value, a throwing `document.cookie`; KG-2 out of scope). API.md's `ada-clock` entry (the cookie, set to PHP's `time()` while a state file
exists and expired otherwise; `set`'s and `status`'s first lines; the clock file's offset).

## Observe at

`chrome://extensions` (the card, Details); DevTools on each page: Console (the values, errors),
Application → Cookies; the terminal for `ada-clock`'s output and the clock file.

## Regression

After `reset`, every local page is at real time with the native `Date`, and `example.com` never
changes. Unit A's `mu-plugin-on-a-local-site.md` and Unit B's `local-linux-on-a-local-site.md` are
unaffected: the extension only reads the cookie, and nothing in this script changes the site or
the computer's clock.
