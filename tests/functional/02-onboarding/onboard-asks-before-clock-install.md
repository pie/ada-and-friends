# `/ada:onboard` writes the clock setup in a clock-sensitive project and asks before `ada-clock install`; in a project that isn't, it writes and asks nothing about the clock

- Area: 02 Onboarding and refresh (the `onboard` skill); 16 Clock (`ada-clock install`,
  `extension`, as onboarding offers them)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219984586830313
- Automate as: none (part A is a self-checking shell script; part B is a dry run for a person)
- Last run: 29 Oct 2026, part A 0 failed (12 PASS); part B headless: B1 and B2 as expected; `PROJECT.md` drafts refused (see the PR)

The script side of this (which setup, which lines, never overwriting) is
`clock-subcommand-writes-local-env.md`. This script checks what only the skill decides: whether
it runs `ada-onboard clock` at all, and what it asks.

## Precondition

- Part A: this checkout, with the change in its working tree.
- Part B: Linux with Local installed (`~/.config/Local` is a folder), `claude` logged in, and this
  checkout. Two scratch repos, made below. Nothing is installed: every `ada-clock install`
  question is answered **No**, because on Linux it wraps Local's shared `php-fpm` (`SYSTEM.md`,
  What `ada-clock install` changes outside the repo).

## Steps

### Part A: the skill's text

1. Save the first block below as `check.sh` in a scratch folder (not in this repo) and run
   `REPO=<this checkout> bash check.sh`.
2. Break it on purpose: run it with `REPO=` an export of ff93d6a
   (`git -C <this checkout> archive ff93d6a | tar -x -C <empty folder>`). Every check FAILs.

```bash
#!/bin/bash
# The onboard skill's clock step, by its text. PASS/FAIL; exit 1 if any FAIL.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
S=$REPO/plugins/ada/skills/onboard/SKILL.md
[ -f "$S" ] || { echo "SETUP FAIL: no $S"; exit 2; }
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
# has <regex>...: some paragraph (blank-line separated) matches every regex, case-insensitive
has() { out=$(awk -v RS= '{gsub(/\n/," "); print}' "$S"); for re in "$@"; do out=$(printf '%s\n' "$out" | grep -Ei -- "$re"); done; [ -n "$out" ]; }

t "runs ada-onboard clock with LOCAL_WP_PATH and LOCAL_WP" has 'ada-onboard clock' 'LOCAL_WP_PATH' 'LOCAL_WP='
t "only when the Clock-sensitive line says yes" has 'clock-sensitive' 'yes'
t "says nothing clock-related happens when it says no" has 'clock-sensitive|clock' '\bno\b' 'nothing|skip|don.t|not'
t "asks before ada-clock install" has 'ada-clock install' 'ask'
t "names Local's php-fpm wrapper" has 'php-fpm'
t "names the pool template lines" has 'pool'
t "names the clock file in ~/.config/ada-clock" has '\.config/ada-clock'
t "says ada-clock uninstall undoes it" has 'ada-clock uninstall'
t "says the site is restarted in Local" has 'restart'
t "sudo apt install faketime is the developer's" has 'sudo apt install faketime' 'developer'
t "offers ada-clock extension only once install is done" has 'ada-clock extension' '(only|once|after)[^.]*install'
t "after a declined install, says the extension comes with the install" has 'extension' 'declin|answers? no|says no|\bno\b to' 'comes with|with the install|needs? [^.]*install|once [^.]*install|after [^.]*install'
[ "$fails" = 0 ] || { echo "$fails failed"; exit 1; }
echo "0 failed"
```

### Part B: dry runs

Save this second block as `snap.sh` in the same scratch folder. It prints what onboarding must
not touch outside the project, and the project's `local.env`, so a before and after can be
compared.

```bash
#!/bin/bash
# snap.sh <project>: Local's php-fpm binaries and pool templates, ~/.config/ada-clock, the
# project's local.env and the site folder's mu-plugins, by checksum. Read-only.
P=${1:?usage: snap.sh <project> [site folder]}; SITE=${2:-}
sum() { if command -v sha256sum >/dev/null; then sha256sum "$@"; else shasum -a 256 "$@"; fi; }
sums() { find "$@" -type f 2>/dev/null | sort | while IFS= read -r f; do sum "$f"; done; }
echo "== php-fpm";              sums "$HOME/.config/Local/lightning-services" -path '*/sbin/php-fpm*'
echo "== pool templates";       sums "$HOME/Local Sites" -path '*/conf/php/php-fpm.d/www.conf.hbs'
echo "== ~/.config/ada-clock";  find "$HOME/.config/ada-clock" 2>/dev/null | sort; sums "$HOME/.config/ada-clock"
echo "== local.env";            if [ -f "$P/.claude/team/local.env" ]; then sum "$P/.claude/team/local.env"; else echo none; fi
[ -n "$SITE" ] && { echo "== mu-plugins"; ls -A "$SITE/wp-content/mu-plugins" 2>&1; }
exit 0
```

#### B1: clock-sensitive, Linux with Local

1. Make the scratch project and a stand-in site folder (an empty WordPress root; no Local site
   uses it):

   ```sh
   mkdir -p ~/scratch/onboard-clock-yes/wp-content/themes/scratch ~/scratch/onboard-clock-yes-site/app/public/wp-content/mu-plugins
   cd ~/scratch/onboard-clock-yes && git init -q
   printf '/*\nTheme Name: Scratch\n*/\n' > wp-content/themes/scratch/style.css
   printf '<?php\n// Orders placed after noon on Friday are delivered the week after next.\nfunction scratch_cutoff_passed() { $now = current_datetime(); return 5 === (int) $now->format( "N" ) && (int) $now->format( "G" ) >= 12; }\n' > wp-content/themes/scratch/functions.php
   git add -A && git commit -qm init
   mkdir -p .claude/team && printf 'LOCAL_URL=https://scratch-clock.test\nSCRATCH_SECRET=keep-me\n' > .claude/team/local.env
   mkdir -p .claude && printf '{ "enabledPlugins": { "ada@pie": false } }\n' > .claude/settings.local.json
   ```

2. `bash <scratch>/snap.sh ~/scratch/onboard-clock-yes ~/scratch/onboard-clock-yes-site/app/public > <scratch>/b1-before.txt`
3. In `~/scratch/onboard-clock-yes`: `claude --plugin-dir <this checkout>/plugins/ada`, then
   `/ada:onboard`. When asked, or at the start, say: "The client is Scratch; approver me. Clock-
   sensitive behaviour: yes, Friday after noon. Keep the knowledge drafting short; it's a dry
   run." When asked for the site's WordPress root give
   `~/scratch/onboard-clock-yes-site/app/public` (as an absolute path), and for the WP-CLI
   command `wp --path=<that path>`.
4. When it asks whether to run `ada-clock install`: read the question, note what it names, and
   answer **No**. If it offers `ada-clock extension` anyway, note it (that is a FAIL) and answer
   **No**.
5. Let it finish (or stop it after the clock step). Then:

   ```sh
   bash <scratch>/snap.sh ~/scratch/onboard-clock-yes ~/scratch/onboard-clock-yes-site/app/public > <scratch>/b1-after.txt
   diff <scratch>/b1-before.txt <scratch>/b1-after.txt
   cd ~/scratch/onboard-clock-yes
   grep -n 'Clock-sensitive' .claude/team/PROJECT.md
   for k in ADA_CLOCK_ADAPTER LOCAL_WP_PATH LOCAL_WP LOCAL_URL SCRATCH_SECRET; do printf '%s %s\n' "$k" "$(grep -c "^$k=" .claude/team/local.env)"; done
   grep -n '' .claude/team/local.env
   git log --all --oneline -- .claude/team/local.env
   ```

#### B2: not clock-sensitive

1. Make the scratch project. Its theme has no date or time logic, so "Clock-sensitive: no" is
   the true answer (B1's Friday cut-off is not copied):

   ```sh
   mkdir -p ~/scratch/onboard-clock-no/wp-content/themes/scratch
   cd ~/scratch/onboard-clock-no && git init -q
   printf '/*\nTheme Name: Scratch\n*/\n' > wp-content/themes/scratch/style.css
   printf '<?php\n// Adds the shop name to the footer.\nfunction scratch_footer_text() { return esc_html( get_bloginfo( "name" ) ); }\n' > wp-content/themes/scratch/functions.php
   git add -A && git commit -qm init
   mkdir -p .claude/team && printf 'LOCAL_URL=https://scratch-clock.test\nSCRATCH_SECRET=keep-me\n' > .claude/team/local.env
   mkdir -p .claude && printf '{ "enabledPlugins": { "ada@pie": false } }\n' > .claude/settings.local.json
   ```

2. `bash <scratch>/snap.sh ~/scratch/onboard-clock-no > <scratch>/b2-before.txt`
3. `claude --plugin-dir <this checkout>/plugins/ada`, `/ada:onboard`, saying: "The client is
   Scratch; approver me. Clock-sensitive behaviour: no. Keep the knowledge drafting short; it's a
   dry run."
4. Let it finish. Then `snap.sh` to `b2-after.txt`, `diff` the two, and
   `grep -n 'Clock-sensitive' .claude/team/PROJECT.md`.

#### Afterwards

`rm -rf ~/scratch/onboard-clock-yes ~/scratch/onboard-clock-yes-site ~/scratch/onboard-clock-no`.
If any `ada-clock install` ran despite the No, that is a FAIL: undo it with the checkout's
`ada-clock uninstall` from the project (after `ada-clock reset`), and restart any Local site
whose `php-fpm` it wrapped.

## Expected

From the brief (Decisions, Onboarding; DoD 1; the Amendment of 29 Oct 2026 on the extension),
`SYSTEM.md` (What `ada-clock install` changes outside the repo) and Bob's approved Builder plan,
item 4:

- Part A: every line `PASS`, `0 failed`, exit 0; on ff93d6a, every line `FAIL`.
- B1:
  - `PROJECT.md`'s Clock-sensitive line says yes. Onboarding runs `ada-onboard clock
    LOCAL_WP_PATH=… LOCAL_WP=…` and tells you the setup it chose, `local-linux`.
  - `local.env`: `ADA_CLOCK_ADAPTER`, `LOCAL_WP_PATH`, `LOCAL_WP` once each, the adapter
    `local-linux`, the other two what you gave; `LOCAL_URL` and `SCRATCH_SECRET` once each and
    unchanged (the first two lines are as written in step 1).
  - Before any `ada-clock install`, it asks, and the question names: Local's `php-fpm` for that
    PHP build is wrapped, shared by every Local site on it (which stay on real time); lines added
    to the site's pool template; a clock file in `~/.config/ada-clock/`; the mu-plugin in
    `wp-content/mu-plugins/`; that `ada-clock uninstall` undoes them; that the site is restarted
    in Local afterwards; that libfaketime is needed first, `sudo apt install faketime`, run by
    you, not by Claude.
  - After the No, no `ada-clock install` runs, and `ada-clock extension` is neither offered nor
    run. Onboarding says the extension comes with the install: it is offered once `ada-clock
    install` is done (the extension works only from the cookie the mu-plugin sets).
  - `diff` of the snapshots: only the `local.env` line differs. `mu-plugins` is still empty.
  - `git log --all -- .claude/team/local.env` prints nothing: it is in no commit.
- B2:
  - `PROJECT.md`'s Clock-sensitive line says no. No `ada-onboard clock`, no `ada-clock` command,
    no question or offer about the clock or the extension.
  - `diff` of the snapshots is empty: `local.env` byte-identical.

## Observe at

- Part A: stdout and `echo $?`.
- Part B: the session's messages and tool calls (Ctrl-O shows the transcript), the `diff`
  output, `local.env`, `git log`.

## Regression

- Onboarding's other steps (files, gitignore, settings, check, the drafting, the hand-over
  list) still run as before; `ada-onboard settings` is unchanged by this step.
- `local.env` is never committed (B1, last command).
