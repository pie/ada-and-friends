# ada-clock for Chrome

This extension shows a local WordPress site's own JavaScript the fake time `ada-clock` set.
With it loaded, `new Date()`, `Date()` and `Date.now()` on the site give the site's time, not
the computer's. Other sites are not touched.

## Loading it

Run `ada-clock extension` from inside the project. It copies this folder to
`~/.config/ada-clock/chrome-extension/` and prints the steps:

1. Open `chrome://extensions`.
2. Turn on Developer mode (top right).
3. Choose Load unpacked and pick `~/.config/ada-clock/chrome-extension/`.

Chrome loads the copy, not this folder, because this folder's path changes with every plugin
version. After a plugin update, run `ada-clock extension` again. Then press the extension's
reload button on `chrome://extensions`.

It needs Chrome 111 or later.

## What it does

- It runs only on `localhost`, `127.0.0.1` and hosts ending in `.local` or `.test`, on any port,
  in every frame. It asks for no permissions and makes no network requests.
- It runs before the page's own scripts. It reads the `ada_clock` cookie, which `ada-clock`'s
  mu-plugin sets to PHP's time while the clock is set.
- With no cookie, or a cookie that isn't a Unix time, it changes nothing and logs nothing.
- With the cookie, `new Date()`, `Date()` and `Date.now()` run from the cookie's time. A date
  built from explicit values, such as `new Date(2020, 0, 1)` or `Date.UTC(…)`, is unchanged.
  `new Date() instanceof Date` is still true.
- It sets `window.__adaClock = { offsetMs }`, the shift in milliseconds. Check it in the console
  to see that the extension ran.

## What it doesn't shift

- `performance.now()`, timers (`setTimeout`, `setInterval`) and `Intl`.
- Web workers, and frames with no address of their own (`about:blank`, `srcdoc`).
- A page that doesn't go through WordPress's `send_headers` or `admin_init`, such as
  `wp-login.php` or a static file. It reads the cookie left by the last WordPress page, so it is
  behind by the time since that page loaded.

## Version

The manifest's `"version": "1"` doesn't follow the plugin's version and is never changed.
