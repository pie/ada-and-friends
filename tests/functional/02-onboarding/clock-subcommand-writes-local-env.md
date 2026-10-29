# `ada-onboard clock` picks the clock setup and writes it to `local.env` without overwriting a value already there

- Area: 02 Onboarding and refresh (`ada-onboard`); 12 Platforms (bash 3.2, a CRLF `local.env`)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219984586830313
- Automate as: none (the Steps block is a self-checking shell script)
- Last run: 29 Oct 2026, 0 failed (61 PASS) under bash 5.2 and Docker bash 3.2; both breaks fail as they should (see the PR)

## Precondition

- This checkout (`REPO`), with the change in its working tree.
- `git`, `mktemp`, `cmp`, `head -c`. The script makes its own scratch repos and scratch `HOME`s
  under `$TMPDIR` and never reads or writes the real `~/.config/Local`, `~/.config/ada-clock` or
  any project's `local.env`.
- It runs on Linux or a Mac. On Linux, "Linux with `~/.config/Local`" expects `local-linux`; on any
  other system it expects `manual` (the `info:` line says which).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`.
3. bash 3.2 (S100), with `S` the folder holding `check.sh`:

   ```sh
   docker run --rm -v <this checkout>/plugins/ada:/plugin:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 >/dev/null && REPO=/s PLUGIN=/plugin bash /s/check.sh'
   ```

4. Break it on purpose, twice:
   - Export the code from before this step and run against it:
     `mkdir /tmp/base && git -C <this checkout> archive ff93d6a plugins/ada | tar -x -C /tmp/base`,
     then `REPO=<this checkout> PLUGIN=/tmp/base/plugins/ada bash check.sh`. There is no `clock`
     subcommand there: the cases FAIL.
   - In a scratch copy of `plugins/ada`, edit `bin/ada-onboard`'s `clock` so the folder it looks
     for is `.config/LocalX`, and run with `PLUGIN=<the copy>`. Case 1 (and 6, 7, 8, 11a) FAIL;
     the faked-Mac cases 2 to 4 still PASS.

```bash
#!/bin/bash
# ada-onboard clock: the setup it picks and the local.env lines it writes, never overwriting.
# Env: REPO (this checkout), PLUGIN (default $REPO/plugins/ada). PASS/FAIL per check; exit 1 if any.
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
ONB=$PLUGIN/bin/ada-onboard
[ -f "$ONB" ] || { echo "SETUP FAIL: no $ONB"; exit 2; }
W=$(mktemp -d "${TMPDIR:-/tmp}/onboard-clock.XXXXXX") || exit 2
if [ "$(uname -s)" = Linux ]; then REAL=local-linux; else REAL=manual; fi
echo "info: $(bash --version | head -1)"
echo "info: $(uname -s): Linux with ~/.config/Local expects $REAL"
fails=0
pass() { echo "PASS $1"; }
fail() { echo "FAIL $1"; fails=$((fails+1)); }
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then pass "$tn"; else fail "$tn"; fi; }
L() { printf '%s\n' "$@"; }

# A local.env value as ada-clock's parser reads it (API.md, ada-clock; bin/ada-clock's parser):
# the last line for the key wins; "..." or '...' quotes come off, with an optional " # comment"
# after them; otherwise the value ends at whitespace followed by #, trailing space trimmed; a
# <...> value is empty; a CR at the end of a line is dropped.
RE_SKIP='^[[:space:]]*(#|$)'
RE_KV='^[[:space:]]*([A-Z_]+)[[:space:]]*=[[:space:]]*(.*)$'
RE_DQ='^"([^"]*)"([[:space:]]+#.*)?[[:space:]]*$'
RE_SQ="^'([^']*)'([[:space:]]+#.*)?[[:space:]]*\$"
envval() {
  v=''
  if [ -f "$1" ]; then
    while IFS= read -r line || [ -n "$line" ]; do
      line=${line%$'\r'}
      [[ $line =~ $RE_SKIP ]] && continue
      [[ $line =~ $RE_KV ]] || continue
      [ "${BASH_REMATCH[1]}" = "$2" ] || continue
      val=${BASH_REMATCH[2]}
      if [[ $val =~ $RE_DQ ]] || [[ $val =~ $RE_SQ ]]; then val=${BASH_REMATCH[1]}
      else val=${val%%[[:space:]]#*}; val=${val%"${val##*[![:space:]]}"}; fi
      case $val in '<'*'>') val='' ;; esac
      v=$val
    done < "$1"
  fi
  printf '%s' "$v"
}
is() { test "$(envval "$ENV" "$1")" = "$2"; }            # KEY reads as value
endsnl() { [ -s "$1" ] && [ -z "$(tail -c 1 "$1")" ]; }    # last byte is a newline
nlines() { { wc -l < "$1"; } 2>/dev/null | tr -d ' '; }
prefix() { c=$(wc -c < "$2" | tr -d ' '); head -c "$c" "$1" | cmp -s - "$2"; }  # $1 starts with $2
outhas() { [ "$RC" = 0 ] && printf '%s\n' "$OUT" | grep -Fxq -- "$1"; }

n=0
newrepo() {  # sets R and ENV; .claude/team exists, local.env doesn't
  n=$((n+1)); R=$W/repo$n; mkdir -p "$R/.claude/team"
  git -C "$R" init -q && git -C "$R" -c user.name=t -c user.email=t@example.com commit -q --allow-empty -m init
  ENV=$R/.claude/team/local.env
}
keep() { cp "$ENV" "$W/orig$n"; ORIG=$W/orig$n; }
oc() {  # oc <home> <PATH> <args...>: run clock in $R; sets OUT and RC
  h=$1; p=$2; shift 2
  OUT=$(cd "$R" && HOME=$h PATH=$p bash "$ONB" clock "$@" 2>"$W/err"); RC=$?
}
same() {  # same <name> <expected stdout>: exit 0 and exactly that stdout
  if [ "$RC" = 0 ] && [ "$OUT" = "$2" ]; then pass "$1"; else
    fail "$1"; echo "      exit $RC; stdout:"; printf '%s\n' "$OUT" | sed 's/^/        /'
    sed 's/^/      stderr: /' "$W/err"; fi
}
refused() {  # refused <name> <args...>: exit 2, local.env untouched
  name=$1; shift
  newrepo; printf 'LOCAL_URL=https://scratch.test\n' > "$ENV"; keep
  oc "$HL" "$PATH" "$@"
  if [ "$RC" = 2 ] && cmp -s "$ENV" "$ORIG"; then pass "$name"; else fail "$name (exit $RC)"; fi
}

HL=$W/home-linux-local; mkdir -p "$HL/.config/Local"          # Local installed
HM=$W/home-no-local;    mkdir -p "$HM/.config"                # no ~/.config/Local
HF=$W/home-local-file;  mkdir -p "$HF/.config"; : > "$HF/.config/Local"   # a file, not a folder
FAKE=$W/fakebin; mkdir -p "$FAKE"; printf '#!/bin/sh\necho Darwin\n' > "$FAKE/uname"; chmod +x "$FAKE/uname"
P=$PATH; PF=$FAKE:$PATH
WPP='/home/dev/Local Sites/scratch/app/public'
WPC="wp --path='/home/dev/Local Sites/scratch/app/public'"
A1="LOCAL_WP_PATH=$WPP"; A2="LOCAL_WP=$WPC"
ADDED3=$(L "added    ADA_CLOCK_ADAPTER=$REAL" "added    LOCAL_WP_PATH=$WPP" "added    LOCAL_WP=$WPC" "setup: $REAL")
MAC3=$(L "added    ADA_CLOCK_ADAPTER=manual" "added    LOCAL_WP_PATH=$WPP" "added    LOCAL_WP=$WPC" "setup: manual")

# 1. Linux with ~/.config/Local, no local.env
newrepo; R1=$R; oc "$HL" "$P" "$A1" "$A2"
same "1 Linux with ~/.config/Local, no local.env: three added, setup: $REAL" "$ADDED3"
t "1 local.env created" test -f "$ENV"
t "1 reads ADA_CLOCK_ADAPTER=$REAL" is ADA_CLOCK_ADAPTER "$REAL"
t "1 reads LOCAL_WP_PATH (with its space)" is LOCAL_WP_PATH "$WPP"
t "1 reads LOCAL_WP (with its quotes)" is LOCAL_WP "$WPC"
t "1 three lines" test "$(nlines "$ENV")" = 3
t "1 ends in a newline" endsnl "$ENV"
cp "$ENV" "$W/r1.env" 2>/dev/null || : > "$W/r1.env"

# 9. The same repo again: nothing is overwritten, whatever values are given
oc "$HL" "$P" "$A1" "$A2"
same "9 second run, same values: all kept" "$(L "kept     ADA_CLOCK_ADAPTER" "kept     LOCAL_WP_PATH" "kept     LOCAL_WP" "setup: $REAL")"
t "9 second run: local.env byte-identical" cmp -s "$ENV" "$W/r1.env"
oc "$HM" "$P" "LOCAL_WP_PATH=/srv/other/app/public" "LOCAL_WP=other-wp"
same "9 third run, other values, faked Mac: all kept, setup still $REAL" "$(L "kept     ADA_CLOCK_ADAPTER" "kept     LOCAL_WP_PATH" "kept     LOCAL_WP" "setup: $REAL")"
t "9 third run: local.env byte-identical" cmp -s "$ENV" "$W/r1.env"

# 2-4. A faked Mac and its near relations: manual
newrepo; oc "$HM" "$P" "$A1" "$A2"
same "2 faked Mac (no ~/.config/Local, no ADA_CLOCK_ADAPTER): setup: manual" "$MAC3"
t "2 reads ADA_CLOCK_ADAPTER=manual" is ADA_CLOCK_ADAPTER manual
newrepo; oc "$HL" "$PF" "$A1" "$A2"
same "3 uname says Darwin, ~/.config/Local there: setup: manual" "$MAC3"
newrepo; oc "$HF" "$P" "$A1" "$A2"
same "4 ~/.config/Local is a file, not a folder: setup: manual" "$MAC3"

# 5. Values already set are kept; the rest of the file is untouched
newrepo
L '# Scratch settings' 'LOCAL_URL=https://scratch.test' 'SECRET_TOKEN=keep-me' 'ADA_CLOCK_ADAPTER=manual' \
  'LOCAL_WP_PATH="/home/x/Local Sites/keep/app/public"  # mine' > "$ENV"; keep
oc "$HL" "$P" "$A1" "$A2"
same "5 two set, one missing: kept, kept, added; setup: manual (the kept one)" \
  "$(L "kept     ADA_CLOCK_ADAPTER" "kept     LOCAL_WP_PATH" "added    LOCAL_WP=$WPC" "setup: manual")"
t "5 the old lines are untouched (only appended to)" prefix "$ENV" "$ORIG"
t "5 one line added" test "$(nlines "$ENV")" = 6
t "5 still reads ADA_CLOCK_ADAPTER=manual" is ADA_CLOCK_ADAPTER manual
t "5 still reads the kept LOCAL_WP_PATH" is LOCAL_WP_PATH "/home/x/Local Sites/keep/app/public"
t "5 reads the added LOCAL_WP" is LOCAL_WP "$WPC"
t "5 other keys unchanged" sh -c "grep -Fxq 'SECRET_TOKEN=keep-me' '$ENV' && grep -Fxq 'LOCAL_URL=https://scratch.test' '$ENV'"

# 6. Empty and <placeholder> values count as missing; the appended line wins
newrepo
L 'LOCAL_URL=https://scratch.test' 'ADA_CLOCK_ADAPTER=' "LOCAL_WP_PATH=\"<the site's app/public>\"" \
  'LOCAL_WP=<the command that runs WP-CLI>' > "$ENV"; keep
oc "$HL" "$P" "$A1" "$A2"
same "6 empty and placeholder values: three added" "$ADDED3"
t "6 the old lines are untouched" prefix "$ENV" "$ORIG"
t "6 reads ADA_CLOCK_ADAPTER=$REAL" is ADA_CLOCK_ADAPTER "$REAL"
t "6 reads the new LOCAL_WP_PATH" is LOCAL_WP_PATH "$WPP"
t "6 reads the new LOCAL_WP" is LOCAL_WP "$WPC"

# 7. No newline at the end of the file
newrepo; printf 'LOCAL_URL=https://scratch.test' > "$ENV"; keep
oc "$HL" "$P" "$A1" "$A2"
same "7 no trailing newline: three added" "$ADDED3"
t "7 line 1 intact (not joined to an added line)" test "$(sed -n 1p "$ENV")" = 'LOCAL_URL=https://scratch.test'
t "7 four lines" test "$(nlines "$ENV")" = 4
t "7 reads LOCAL_URL unchanged" is LOCAL_URL https://scratch.test
t "7 reads ADA_CLOCK_ADAPTER=$REAL" is ADA_CLOCK_ADAPTER "$REAL"

# 8. A CRLF local.env (Windows checkout)
newrepo; printf 'LOCAL_URL=https://scratch.test\r\nLOCAL_WP_PATH=/srv/crlf/app/public\r\n' > "$ENV"; keep
oc "$HL" "$P" "$A1" "$A2"
same "8 CRLF file: LOCAL_WP_PATH kept, two added" \
  "$(L "added    ADA_CLOCK_ADAPTER=$REAL" "kept     LOCAL_WP_PATH" "added    LOCAL_WP=$WPC" "setup: $REAL")"
t "8 the old lines are untouched" prefix "$ENV" "$ORIG"
t "8 reads the kept LOCAL_WP_PATH" is LOCAL_WP_PATH /srv/crlf/app/public

# 10. The setup line is the value in force: a kept local-linux on a faked Mac
newrepo
L 'ADA_CLOCK_ADAPTER="local-linux"' 'LOCAL_WP_PATH=/srv/keep/app/public   # mine' "LOCAL_WP='keep-wp'" > "$ENV"; keep
oc "$HM" "$P" "$A1" "$A2"
same "10 quoted and commented values kept; setup: local-linux as it stands" \
  "$(L "kept     ADA_CLOCK_ADAPTER" "kept     LOCAL_WP_PATH" "kept     LOCAL_WP" "setup: local-linux")"
t "10 local.env byte-identical" cmp -s "$ENV" "$ORIG"

# 11. The last line for a key is the one that counts
newrepo; L 'LOCAL_WP_PATH=/srv/first' 'LOCAL_WP_PATH=' > "$ENV"
oc "$HL" "$P" "$A1" "$A2"
t "11a set then emptied (last line empty): LOCAL_WP_PATH added" outhas "added    LOCAL_WP_PATH=$WPP"
t "11a reads the added LOCAL_WP_PATH" is LOCAL_WP_PATH "$WPP"
newrepo; L 'LOCAL_WP_PATH=' 'LOCAL_WP_PATH=/srv/second' > "$ENV"; keep
oc "$HL" "$P" "$A1" "$A2"
t "11b empty then set (last line set): LOCAL_WP_PATH kept" outhas "kept     LOCAL_WP_PATH"
t "11b reads /srv/second" is LOCAL_WP_PATH /srv/second

# 12. Refusals: exit 2, local.env untouched
refused "12 no arguments"
refused "12 LOCAL_WP missing" "$A1"
refused "12 LOCAL_WP_PATH missing" "$A2"
refused "12 another key (LOCAL_URL)" "$A1" "$A2" "LOCAL_URL=https://other.test"
refused "12 not KEY=value" "$A1" "$A2" "foo"
refused "12 a # in a value (after a space)" "$A1" "LOCAL_WP=wp --path=/srv/x #note"
refused "12 a # in a value (no space)" "LOCAL_WP_PATH=/srv/a#b" "$A2"
refused "12 a newline in a value" "$A1" "LOCAL_WP=$(printf 'wp\n--path=/srv/x')"
refused "12 a <placeholder> value" "LOCAL_WP_PATH=<the site's app/public>" "$A2"
refused "12 an empty LOCAL_WP_PATH (same as missing)" "LOCAL_WP_PATH=" "$A2"
refused "12 an empty LOCAL_WP (same as missing)" "$A1" "LOCAL_WP="
newrepo; oc "$HL" "$P" "$A1"
t "12 refused with no local.env: none created" sh -c "[ $RC = 2 ] && [ ! -e '$ENV' ]"

# 13. Outside a git repo
mkdir -p "$W/plain"
(cd "$W/plain" && HOME=$HL bash "$ONB" clock "$A1" "$A2" >/dev/null 2>&1); rc=$?
t "13 outside a git repo: exit 2" test "$rc" = 2
t "13 outside a git repo: nothing written there" test ! -e "$W/plain/.claude"

# 14. From a subfolder: the repo root's local.env
newrepo; mkdir -p "$R/src/deep"
(cd "$R/src/deep" && HOME=$HL bash "$ONB" clock "$A1" "$A2" >/dev/null 2>&1); rc=$?
t "14 from a subfolder: exit 0" test "$rc" = 0
t "14 from a subfolder: the root's local.env written" is LOCAL_WP_PATH "$WPP"
t "14 from a subfolder: no .claude in the subfolder" test ! -e "$R/src/deep/.claude"

# 15. local.env stays git-ignored after ada-onboard gitignore
newrepo
(cd "$R" && bash "$ONB" gitignore >/dev/null 2>&1)
git -C "$R" add .gitignore && git -C "$R" -c user.name=t -c user.email=t@example.com commit -q -m ignore
oc "$HL" "$P" "$A1" "$A2"
t "15 clock ran" test "$RC" = 0
t "15 local.env is ignored" git -C "$R" check-ignore -q .claude/team/local.env
t "15 git status clean" test -z "$(git -C "$R" status --porcelain)"

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

From the brief (Decisions, Onboarding; DoD 1 and 2) and Bob's approved Builder plan, item 3:

- Setup: `local-linux` when `uname -s` is `Linux` and `$HOME/.config/Local` is a folder; `manual`
  otherwise (no `~/.config/Local`, `uname` not Linux, or `~/.config/Local` not a folder).
- For each of `ADA_CLOCK_ADAPTER`, `LOCAL_WP_PATH`, `LOCAL_WP`, in that order: a value already in
  `local.env` that the `ada-clock` parser reads as non-empty is kept (`kept     KEY`); a missing,
  empty or `<placeholder>` one gets `KEY=value` appended (`added    KEY=value`), and the appended
  line wins. A newline goes first if the file lacks one; the file is created if missing. The old
  bytes are never changed.
- Last line `setup: <manual|local-linux>`, the value in force afterwards.
- Exit 2, `local.env` untouched (and not created), for a missing key or an empty value (`KEY=`,
  either key; Ada's answer, 2026-10-29, BRIEF.md), another key, an argument
  that isn't `KEY=value`, a value with a newline, a `#`, or the `<…>` form; and outside a git repo
  (`SYSTEM.md`, Known gotchas).
- Every line `PASS`, last line `0 failed`, exit 0, under the local bash and bash 3.2. Each break in
  step 4 FAILs where it says. On ff93d6a the case 12 and 13 refusals PASS anyway (an unknown
  subcommand also exits 2, changing nothing); every other case FAILs there.

## Observe at

stdout and `echo $?`. On a FAIL the scratch folder is kept and named; the case's repo is
`repo<n>` in it, its `local.env` under `.claude/team/`.

## Regression

- `local.env` stays git-ignored (case 15); the other subcommands are checked by the other scripts
  in this folder, which should be re-run when `bin/ada-onboard` changes.
- `ada-onboard settings` and `refresh` are not changed by this step (the Builder plan): their
  scripts must still pass unchanged.
