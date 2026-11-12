# `ada-brief areas` and `who <area>...` read the areas table in `PROJECT.md`

Unit B of the plan "Brief approval by area": a project lists its areas of concern, each with its
approvers, in a table in `PROJECT.md`'s Brief approval section. `ada-brief areas` prints the areas
in effect; `ada-brief who <area>...` says whether this developer's brief touching those areas
needs approval. `who` with no arguments is unchanged. Nothing calls the new paths yet.

- Area: 01 Brief approval (`ada-brief who`, `areas`); 03 Plans (`ada-plan status`, a reader of
  `who`); 02 Onboarding (`ada-onboard check`, a reader of `who`); 12 Platforms (bash 3.2,
  Python 3.8, CRLF, a path with a space)
- Asana: https://app.asana.com/1/100438094841948/project/1206474484537567/task/1220198285873178
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-11, 0 failed (638 PASS) on the host (bash 5.3, Python 3.14); Docker, REAL_GH and the breaks not run; see the PR

## Precondition

- This checkout (`REPO`) with the change in its working tree, and the base commit `febfd7b`
  (Unit A's merge into `add/brief-approval-by-area`, the script before Unit B; `BASE=<ref>` for
  another) in its git. In a container a worktree's git is not readable: export the base on the
  host and pass `BASE_PLUGIN` (Steps, 3).
- `git`, `python3` (3.8+), `mktemp`, `awk`, `cmp`, `tar`, `cksum`. No network by default.
- `gh`: the script puts a stand-in first on the PATH. It answers `api user` with `GH_USER`
  (default `tester`), `auth status` with success and `pr list` with `[]` (no PRs), fails anything
  else, and logs every call. The Definition of Done's `who` items name a repo where `gh` is
  logged in as the tester (`<me>`): run once with `REAL_GH=1` and the stand-in sends `api user` to
  the real `gh` instead (read-only; nothing else reaches GitHub). `<me>` is then that login. The
  other logins in the tables (`neil`, `client`, `client2`, `other`, `t`, `a`, `b`, `x`, `y`) must
  not be `<me>`; the script refuses to run if one is.
- `<me>` in the expected output is the login as `who` prints it: the script takes it from the
  base script's `who` in a project with `Approvers: @<me>` (`approver <me>`), so a change in case
  between `gh` and `PROJECT.md` can't make a false failure.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`. A few seconds. Then once with `REAL_GH=1` (gh logged
   in as the tester).
3. Under bash 3.2 and Python 3.8 (Docker). Export the base first, since a container can't read a
   worktree's git (run on the host; `S` is the scratch folder holding `check.sh`):

   ```sh
   git -C <this checkout> archive -o "$S/base.tar" febfd7b plugins/ada
   mkdir -p "$S/base" && tar -x -C "$S/base" -f "$S/base.tar"
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
     sh -c 'apk add -q --no-cache git python3 >/dev/null && REPO=/repo BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
     sh -c 'apt-get -qq update >/dev/null && apt-get -qq install -y git >/dev/null && REPO=/repo BASE_PLUGIN=/s/base/plugins/ada bash /s/check.sh'
   ```

   The `info:` lines at the top say `bash 3.2` and `Python 3.8`; the PASS/FAIL lines are the same
   as under the host's bash. (The `python:3.8-slim` image runs the script under its own bash 5;
   the Python is what that run adds.)
4. Break it on purpose, twice, each run with `PLUGIN=<a plugin folder>`:
   - `PLUGIN` set to the base's plugin (the export in step 3, `$S/base/plugins/ada`): it prints
     `info: the script under test is identical to the base`, no `SETUP FAIL`, and about 225 `FAIL`
     lines (225 of 638 on bash 5.3 with the cases for Amendments 3, 4 and 6 and the comment cases; 143 of 440 before
     them, the same under Docker bash 3.2): at least the usage-header lines, every `areas` line
     that expects exit 0, the bad-table "not the usage message" lines (the second-table and
     no-separator ones too), the section 4b comment lines, and the `who <area>` lines expecting
     `approver`, `needs` or exit 2. Every
     section 7 and 8 line prints `PASS`.
   - A fresh copy of `plugins/ada` in a scratch folder with the change built; in the copy's
     `bin/ada-brief`, change the text `(catch-all)` that `areas` prints to `(catchall)`: section
     2's lines with a catch-all print `FAIL`; the bad-table lines (section 4) still `PASS`.

5. `status` and `submit` with a bad table, and a table inside a comment (Amendments 2, 5 and 6),
   on a scratch GitHub repo you own (never a client's), with `gh` logged in. `B` is this
   checkout's `plugins/ada/bin/ada-brief`.
   1. In the scratch repo's main checkout, `PROJECT.md` with `- **Approvers:** none`,
      `- **Trusted:** none` and no table; commit and push. On a new branch `add/area-status`,
      a filled-in `.claude/handoff/BRIEF.md` (as `brief()` in
      `tests/functional/01-brief-approval-areas/submit-and-status-same-as-base.md` writes it, with
      `Branch: add/area-status`, `PR into: main`), then
      `bash "$B" submit 1219000000000099 main`: it prints `opened   <url>`.
   2. `bash "$B" status; echo "exit $?"`: first line `not-needed`, exit 0.
   3. Add to the Brief approval section, after the two lines, a table with no catch-all row
      (don't commit it):

      ```
      | Area | Slug | Approved by |
      |---|---|---|
      | Content | content | @client |
      ```

      `bash "$B" status; echo "exit $?"`: nothing on stdout, one `ada-brief: ` line on stderr
      about the table (not the usage, not an unknown area), `exit 2`.
   4. `submit` with the same bad table in place (Amendment 5; the brief file is unchanged, so
      there is nothing new to commit):
      `bash "$B" submit 1219000000000099 main; echo "exit $?"`: `exit 2`, one `ada-brief: ` line
      on stderr about the table (not the usage, not an unknown area), and no `opened` line on
      stdout. The PR is still open and its body unchanged (`gh pr view --json state,body`).
   5. Wrap the bad table in a comment (Amendment 6): put `<!--` on the line above its header and
      `-->` on the line below its last row. `bash "$B" status`: first line `not-needed` again,
      exit 0, nothing about a table on stderr. `bash "$B" submit 1219000000000099 main`: exit 0,
      no table message.
   6. Take the comment away and add the row `| Rest (catch-all) | rest | Approvers |` to the
      table; `bash "$B" status`: first line `not-needed`, exit 0. `git checkout -- .claude/team/PROJECT.md`,
      close the PR and delete the branch.

```bash
#!/bin/bash
# ada-brief areas and who <area>...: the areas table in PROJECT.md's Brief approval section.
# PASS/FAIL per check; exit 1 if any FAIL, 2 on SETUP FAIL.
# Env: REPO (this checkout; default the repo of the current folder), PLUGIN (the plugin under
#      test; default $REPO/plugins/ada), BASE (the script before Unit B; default febfd7b, Unit A's
#      merge), BASE_PLUGIN (an exported base plugins/ada, for a container), REAL_GH=1 (send
#      `gh api user` to the real gh), GH_USER (the stand-in's login; default tester), KEEP=1
#      (keep the scratch folder).
# bash 3.2 compatible: no mapfile, no [[ =~ ]], no ${x,,}, no associative arrays.
set -u
unset ADA_BRIEF_REQUIRE
BASE=${BASE:-febfd7b}
for tool in git python3 mktemp awk cmp tar cksum; do
  command -v $tool >/dev/null 2>&1 || { echo "SETUP FAIL: $tool not found"; exit 2; }
done
W=$(mktemp -d)
if [ "${KEEP:-}" = 1 ]; then echo "info: scratch folder $W"; else trap 'rm -rf "$W"' EXIT; fi
BASH_BIN=$(command -v bash)
echo "info: $("$BASH_BIN" --version | head -1)"
echo "info: $(python3 --version 2>&1)"

REPO=${REPO:-$(git rev-parse --show-toplevel 2>/dev/null)}
NP=${PLUGIN:-$REPO/plugins/ada}
if [ -n "${BASE_PLUGIN:-}" ]; then
  BP=$BASE_PLUGIN
else
  mkdir -p "$W/base"
  git -C "$REPO" archive "$BASE" plugins/ada | tar -x -C "$W/base" || { echo "SETUP FAIL: no $BASE in $REPO"; exit 2; }
  BP=$W/base/plugins/ada
fi
[ -f "$BP/bin/ada-brief" ] || { echo "SETUP FAIL: no base script at $BP/bin/ada-brief"; exit 2; }
[ -f "$NP/bin/ada-brief" ] || { echo "SETUP FAIL: no script under test at $NP/bin/ada-brief"; exit 2; }
cmp -s "$BP/bin/ada-brief" "$NP/bin/ada-brief" && echo "info: the script under test is identical to the base (sections 7 and 8 prove nothing)"

fails=0; passes=0
ok() { if [ "$1" = 0 ]; then echo "PASS $2"; passes=$((passes+1)); else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()  { local name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
lower() { printf '%s' "$1" | tr '[:upper:]' '[:lower:]'; }
upper() { printf '%s' "$1" | tr '[:lower:]' '[:upper:]'; }

# The gh: a stand-in that logs every call. With REAL_GH=1, `api user` goes to the real gh.
REAL_GH_BIN=''
if [ "${REAL_GH:-}" = 1 ]; then
  REAL_GH_BIN=$(command -v gh) || { echo "SETUP FAIL: REAL_GH=1 but no gh on the PATH"; exit 2; }
  GH_USER=$("$REAL_GH_BIN" api user --jq .login) || { echo "SETUP FAIL: gh api user failed (gh auth login)"; exit 2; }
  echo "info: gh api user goes to the real gh, logged in as $GH_USER"
else
  GH_USER=${GH_USER:-tester}
fi
ME=$(lower "$GH_USER")
for other in neil client client2 other t a b x y; do
  [ "$ME" = "$other" ] && { echo "SETUP FAIL: the tester's login is $ME, which the tables use for someone else"; exit 2; }
done
GH_LOG=$W/gh.log; : > "$GH_LOG"
export GH_LOG GH_USER REAL_GH_BIN
mkdir -p "$W/gh"
cat > "$W/gh/gh" <<'EOF'
#!/bin/sh
printf 'gh %s\n' "$*" >> "$GH_LOG"
case "$1 ${2:-}" in
  'api user') [ -n "$REAL_GH_BIN" ] && exec "$REAL_GH_BIN" "$@"; printf '%s\n' "$GH_USER"; exit 0;;
  'auth status') exit 0;;
  'pr list') echo '[]'; exit 0;;
esac
echo "stand-in gh: not handled: $*" >&2
exit 1
EOF
chmod +x "$W/gh/gh"

# The scratch repo: a path with a space, one commit. PROJECT.md is written per case, not committed.
R="$W/scratch repo"
G() { git -c user.name=t -c user.email=t@example.invalid "$@"; }
git init -q "$R" && mkdir -p "$R/.claude/team" && echo scratch > "$R/README.md" \
  && G -C "$R" add -A && G -C "$R" commit -q -m init || { echo "SETUP FAIL: scratch repo"; exit 2; }
PJ="$R/.claude/team/PROJECT.md"

# proj <approvers> <trusted> [<rows>]: PROJECT.md with a Brief approval section holding the two
# lines and, if <rows> (table rows, one a line) is given, the areas table after them. Another
# section with its own table follows. proj NOSECTION [<rows>]: no Brief approval section; the
# rows, if given, go in a table with the same header under "## Areas" instead. If RAW is set (see
# rawproj), proj writes its text, as it is, in place of the header, separator and rows.
RAW=''
HDR='| Area | Slug | Approved by |'
SEP='|---|---|---|'
proj() {
  {
    printf '# Scratch\n\n## Code and branches\n\n'
    printf '%s\n\n' '- **Branch from:** `main`. **PR into:** `next-release`. **Test on:** a scratch repo.'
    if [ "$1" = NOSECTION ]; then
      if [ -n "${2:-}" ]; then printf '## Areas\n\n%s\n%s\n%s\n\n' "$HDR" "$SEP" "$2"; fi
    else
      printf '## Brief approval\n\nWhose briefs need approving.\n\n'
      printf '%s\n%s\n' "- **Approvers:** $1" "- **Trusted:** $2"
      if [ -n "$RAW" ]; then printf '\n%s\n' "$RAW"
      elif [ -n "${3:-}" ]; then printf '\n%s\n%s\n%s\n' "$HDR" "$SEP" "$3"; fi
      printf '\nApprovers never need approval.\n\n'
    fi
    printf '## Environments\n\n| Branch | Environment | Host / install |\n|---|---|---|\n'
    printf '%s\n' '| `main` | Live | example |'
  } > "$PJ"
}
# rawproj <text>: PROJECT.md with Approvers: @neil, Trusted: none and <text> after the two lines.
rawproj() { RAW=$1; proj "@neil" none; RAW=''; }
lines() { printf '%s\n' "$@"; }
crlf() { awk '{ printf "%s\r\n", $0 }' "$1" > "$1.crlf" && mv "$1.crlf" "$1"; }
sums() { (cd "$R" && find . -path ./.git -prune -o -type f -print | LC_ALL=C sort | while IFS= read -r f; do cksum "$f"; done); }

# run <plugin> <args...>: that plugin's ada-brief in the scratch repo, the stand-in gh first on
# the PATH; REQ=1 sets ADA_BRIEF_REQUIRE=1. stdout $W/o, stderr $W/e, exit code $W/c. Counts a
# run that changed a file in the scratch repo, and an `areas` run that called gh.
REQ=''
changed=0; areas_gh=0; areas_runs=0
run() { local p=$1; shift
  sums > "$W/before"; : > "$GH_LOG"
  ( cd "$R" || exit 99
    PATH="$W/gh:$PATH"; export PATH
    if [ -n "$REQ" ]; then ADA_BRIEF_REQUIRE=1; export ADA_BRIEF_REQUIRE; fi
    exec "$BASH_BIN" "$p/bin/ada-brief" "$@" ) > "$W/o" 2> "$W/e"
  echo $? > "$W/c"
  sums > "$W/after"; cmp -s "$W/before" "$W/after" || { changed=$((changed+1)); echo "info: ada-brief $* changed a file in the scratch repo"; }
  if [ "${1:-}" = areas ]; then
    areas_runs=$((areas_runs+1))
    [ -s "$GH_LOG" ] && { areas_gh=$((areas_gh+1)); echo "info: ada-brief areas called gh:"; sed 's/^/info:   /' "$GH_LOG"; }
  fi
  return 0
}
code() { cat "$W/c"; }
first() { head -1 "$W/o"; }
show() { echo "      exit $(code); stdout:"; sed 's/^/        | /' "$W/o"; echo "      stderr:"; sed 's/^/        | /' "$W/e"; }
save() { cp "$W/o" "$W/$1.o"; cp "$W/e" "$W/$1.e"; cp "$W/c" "$W/$1.c"; }
same() { cmp -s "$W/$1.o" "$W/$2.o" && cmp -s "$W/$1.e" "$W/$2.e" && cmp -s "$W/$1.c" "$W/$2.c"; }
restore() { cp "$W/$1.o" "$W/o"; cp "$W/$1.e" "$W/e"; cp "$W/$1.c" "$W/c"; }

# check <name> <exit> <stdout> <args...>: this checkout's script on the current PROJECT.md, then
# on the same file with CRLF line ends. The LF run: exactly that stdout (lines joined by newlines;
# '' for none) and exit code; <stdout> ANY skips the stdout comparison, for the caller to check.
# The CRLF run: the same stdout, stderr and exit code as the LF run. Leaves the LF run's output.
check() { local name=$1 want_c=$2 want_o=$3; shift 3
  run "$NP" "$@"; save lf
  if [ "$want_o" = ANY ]; then
    if [ "$(code)" = "$want_c" ]; then ok 0 "$name: exit $want_c"; else ok 1 "$name: exit $want_c"; show; fi
  else
    if [ -z "$want_o" ]; then : > "$W/want"; else printf '%s\n' "$want_o" > "$W/want"; fi
    if [ "$(code)" = "$want_c" ] && cmp -s "$W/want" "$W/o"; then ok 0 "$name"; else ok 1 "$name"; echo "      expected exit $want_c; stdout:"; sed 's/^/        | /' "$W/want"; show; fi
  fi
  cp "$PJ" "$W/lf.md"; crlf "$PJ"
  run "$NP" "$@"; save cr
  if same lf cr; then ok 0 "CRLF: $name"; else ok 1 "CRLF: $name"; show; fi
  cp "$W/lf.md" "$PJ"; restore lf
}

# vs_base <name> <args...>: the base script and this checkout's on the current PROJECT.md, LF and
# CRLF: the same stdout, stderr and exit code. Leaves this checkout's LF run's output.
vs_base() { local name=$1; shift
  run "$BP" "$@"; save b; run "$NP" "$@"; save n
  if same b n; then ok 0 "$name: same as base"; else ok 1 "$name: same as base"; echo "      base:"; restore b; show; echo "      this checkout:"; restore n; show; fi
  cp "$PJ" "$W/lf.md"; crlf "$PJ"
  run "$BP" "$@"; save bc; run "$NP" "$@"; save nc
  if same bc nc; then ok 0 "CRLF: $name: same as base"; else ok 1 "CRLF: $name: same as base"; echo "      base:"; restore bc; show; echo "      this checkout:"; restore nc; show; fi
  cp "$W/lf.md" "$PJ"; restore n
}

# <me> as who prints it: the base script's word after "approver" for Approvers: @<me>.
proj "@$ME" none; run "$BP" who
case "$(first)" in
  "approver "*) MEO=$(first); MEO=${MEO#approver } ;;
  *) echo "SETUP FAIL: the base who with Approvers: @$ME printed:"; show; exit 2 ;;
esac
echo "info: <me> is $MEO"

T3=$(printf '| Content | content | @client |\n| Design | design | @client |\n| System behaviour (catch-all) | system | Approvers |')

# 1. Static: bash -n, the usage header, the die 'usage: ...' line, an unknown subcommand.
t "bash -n ada-brief" "$BASH_BIN" -n "$NP/bin/ada-brief"
awk 'NR == 1 { next } !/^#/ { exit } { print }' "$NP/bin/ada-brief" > "$W/header"
t "usage header: ada-brief areas" grep -qF 'ada-brief areas' "$W/header"
t "usage header: ada-brief who [<area>...]" grep -qF 'ada-brief who [<area>...]' "$W/header"
t "usage header: areas' output, the catch-all's (catch-all)" grep -qF '(catch-all)' "$W/header"
t "usage header: areas' output, the trusted: line" grep -qF 'trusted:' "$W/header"
t "usage header: who <area>'s output, the needs lines" grep -qF 'needs ' "$W/header"
t "usage header: who <area>'s unknown area" grep -qF 'unknown area' "$W/header"
grep -F "die 'usage: ada-brief" "$NP/bin/ada-brief" | grep -F ' | ' > "$W/dieline"
t "die 'usage: ...' line: one line lists the subcommands" test "$(wc -l < "$W/dieline" | tr -d ' ')" = 1
t "die 'usage: ...' line: names who [<area>...]" grep -qF 'who [<area>...]' "$W/dieline"
t "die 'usage: ...' line: names areas" grep -qw 'areas' "$W/dieline"
proj "@neil" none; run "$NP" bogus
t "ada-brief bogus: exit 2" test "$(code)" = 2
t "ada-brief bogus: the usage names who [<area>...]" grep -qF 'who [<area>...]' "$W/e"
t "ada-brief bogus: the usage names areas" grep -qw 'areas' "$W/e"

# 2. areas: the areas in effect (Definition of Done 2 and 3).
proj "@a" none
check "areas, no table, Approvers: @a" 0 "$(printf 'everything: @a (catch-all)\ntrusted: none')" areas
t "areas, no table: nothing on stderr" test ! -s "$W/e"
proj "@neil" "@t" "$(printf '| Content | content | @client |\n| Design | design | @client |\n| System behaviour (catch-all) | system | Approvers |')"
check "areas, the brief's table" 0 "$(printf 'content: @client\ndesign: @client\nsystem: @neil (catch-all)\ntrusted: @t')" areas
t "areas, the brief's table: nothing on stderr" test ! -s "$W/e"
proj "@neil" "@x, @y" "$(printf '| Content | content | @client |\n| System behaviour (catch-all) | system | Approvers |\n| Design | design | @client |')"
check "areas: table order, the catch-all in the middle; two Trusted" 0 "$(printf 'content: @client\nsystem: @neil (catch-all)\ndesign: @client\ntrusted: @x, @y')" areas
proj "@Neil, @B" "@T" "$(printf '| Content | content | @Client |\n| Rest (catch-all) | rest | Approvers |')"
check "areas: logins lowercased, in the order given" 0 "$(printf 'content: @client\nrest: @neil, @b (catch-all)\ntrusted: @t')" areas
proj "@neil" none "$(printf '| Content | content | none |\n| Rest (catch-all) | rest | Approvers |')"
check "areas: Approved by none" 0 "$(printf 'content: none\nrest: @neil (catch-all)\ntrusted: none')" areas
proj none none "$(printf '| Content | content | @client |\n| Rest (catch-all) | rest | Approvers |')"
check "areas: Approvers with Approvers: none is none" 0 "$(printf 'content: @client\nrest: none (catch-all)\ntrusted: none')" areas
proj "@neil" none "$(printf '| Content | content | @client, Approvers |\n| Rest (catch-all) | rest | @other |')"
check "areas: @login and Approvers in one cell" 0 ANY areas
grep '^content: ' "$W/o" > "$W/line"
t "areas: @login and Approvers: the content line has @client" grep -qF '@client' "$W/line"
t "areas: @login and Approvers: the content line has @neil" grep -qF '@neil' "$W/line"
t "areas: @login and Approvers: the content line has nothing else" test -s "$W/line" -a "$(sed -e 's/^content: //' -e 's/@client//' -e 's/@neil//' -e 's/[ ,]//g' "$W/line")" = ''
t "areas: @login and Approvers: rest: @other (catch-all)" grep -qx 'rest: @other (catch-all)' "$W/o"
S40=abcdefghij-abcdefghij-abcdefghij-abcdefg
proj "@neil" none "$(printf '| Long | %s | @client |\n| Digits | 2fa | @client |\n| Rest (catch-all) | a-1 | Approvers |' "$S40")"
check "areas: a 40-character slug, a slug starting with a digit, one with a hyphen" 0 "$(printf '%s: @client\n2fa: @client\na-1: @neil (catch-all)\ntrusted: none' "$S40")" areas
cat > "$PJ" <<EOF
# Scratch

## Brief approval

$HDR
$SEP
| Content | content | @client |
| System behaviour (catch-all) | system | Approvers |

- **Approvers:** @neil
- **Trusted:** none
EOF
check "areas: the table before the two lines, the section last in the file" 0 "$(printf 'content: @client\nsystem: @neil (catch-all)\ntrusted: none')" areas
cat > "$PJ" <<EOF
# Scratch

## Areas

$HDR
$SEP
| Content | content | @client |
| Rest (catch-all) | rest | Approvers |

## Brief approval

- **Approvers:** @neil
- **Trusted:** none
EOF
check "areas: a table with the same header in another section is not read" 0 "$(printf 'everything: @neil (catch-all)\ntrusted: none')" areas

# 3. areas with no Brief approval section: everything: none (catch-all), and who's warning.
proj NOSECTION
run "$BP" who; save bw
check "areas, no Brief approval section" 0 "$(printf 'everything: none (catch-all)\ntrusted: none')" areas
t "areas, no section: the same stderr warning as the base who" cmp -s "$W/bw.e" "$W/e"
t "areas, no section: the warning is not empty" test -s "$W/e"

# 4. Bad tables: exit 2, an ada-brief: message on stderr, nothing on stdout (Definition of Done 4),
#    for areas, who <area> and who with no arguments (Amendment 2), a table with the wrong
#    header (Amendment 1), more than one table (Amendment 3) and no separator line (Amendment 4).
# bad_now <name> <area> [<text>]: on the PROJECT.md as it is, areas, who <area> and who each
# exit 2 with nothing on stdout and an ada-brief: message on stderr (which has <text>, if given).
# <area> is a slug the table has, so who <area> can't fail as an unknown area.
bad_now() { local name=$1 area=$2 text=${3:-} args
  for args in areas "who $area" who; do
    check "bad table, $name: $args exits 2, nothing on stdout" 2 '' $args
    t "bad table, $name: $args: stderr starts ada-brief:" grep -q '^ada-brief: ' "$W/e"
    t "bad table, $name: $args: stderr is not the usage message" eval '! grep -q "usage:" "$W/e"'
    t "bad table, $name: $args: stderr is not the unknown-area message" eval '! grep -q "unknown area" "$W/e"'
    if [ -n "$text" ]; then t "bad table, $name: $args: stderr says '$text'" grep -qi "$text" "$W/e"; fi
  done
}
# bad <name> <rows> [<area>]: a table with the usual header and separator, then <rows>.
bad() { proj "@neil" none "$2"; bad_now "$1" "${3:-rest}"; }
REST='| Rest (catch-all) | rest | Approvers |'
bad "no catch-all" "$(printf '| Content | content | @client |\n| Design | design | @client |')" content
bad "two catch-alls" "$(printf '| Content (catch-all) | content | @client |\n%s' "$REST")"
bad "slug Design" "$(printf '| Design | Design | @client |\n%s' "$REST")"
bad "slug catch-all" "$(printf '| Design | catch-all | @client |\n%s' "$REST")"
bad "slug everything" "$(printf '| Design | everything | @client |\n%s' "$REST")"
bad "a duplicate slug" "$(printf '| Design | design | @client |\n| Visual design | design | @client |\n%s' "$REST")"
bad "a duplicate of the catch-all's slug" "$(printf '| Design | rest | @client |\n%s' "$REST")"
bad "an empty Approved by" "$(printf '| Design | design | |\n%s' "$REST")"
bad "Approved by client" "$(printf '| Design | design | client |\n%s' "$REST")"
bad "a 41-character slug" "$(printf '| Long | %sh | @client |\n%s' "$S40" "$REST")"
bad "slug starting with a hyphen" "$(printf '| Design | -design | @client |\n%s' "$REST")"
bad "slug with an underscore" "$(printf '| Design | de_sign | @client |\n%s' "$REST")"
bad "an empty slug" "$(printf '| Design | | @client |\n%s' "$REST")"
GOOD=$(printf '| Content | content | @client |\n%s' "$REST")
HDR='| Area | Slug | Approvers |';  bad "header | Area | Slug | Approvers |" "$GOOD"
HDR='| Name | Slug | Approved by |'; bad "header | Name | Slug | Approved by |" "$GOOD"
HDR='| Area | Approved by |';       bad "header | Area | Approved by |" "$(printf '| Content | @client |\n| Rest (catch-all) | Approvers |')"
HDR='| Area | Slug | Approved by |'
# More than one table in the section (Amendment 3), the second one valid on its own, so only the
# count can be what's wrong; and a table with no separator line (Amendment 4), where dropping
# the first row would leave a valid table.
LC='| Content | content | @client |'; LD='| Design | design | @client |'; LS='| System behaviour (catch-all) | system | Approvers |'
rawproj "$(lines "$HDR" "$SEP" "$LC" "$LS" '' 'And further down, another table:' '' "$HDR" "$SEP" "$LD")"
bad_now "a second table with the same header" system 'more than one table'
rawproj "$(lines "$HDR" "$SEP" "$LC" "$LS" '' 'And a table about something else:' '' '| Note | Value |' '|---|---|' '| a | b |')"
bad_now "a second table with another header" system 'more than one table'
rawproj "$(lines "$HDR" "$LC" "$LS")"
bad_now "no separator line" system
rawproj "$(lines "$HDR" "$LC" "$LD" "$LS")"
bad_now "no separator line, three rows" system
# status and submit with a bad table (Amendments 2 and 5) need a submitted PR: Steps 5, on a scratch GitHub repo.
NOCATCH=$(printf '| Content | content | @client |\n| Design | design | @client |')
# The two lines stay required, with or without a table.
for line in Approvers Trusted; do
  for tab in '' "$REST"; do
    proj "@neil" none "$tab"; grep -v "^- \*\*$line:\*\*" "$PJ" > "$W/p" && cp "$W/p" "$PJ"
    label="no $line line, $( [ -n "$tab" ] && echo 'a table' || echo 'no table')"
    check "$label: areas exits 2, nothing on stdout" 2 '' areas
    t "$label: areas: stderr starts ada-brief:" grep -q '^ada-brief: ' "$W/e"
    t "$label: areas: stderr is not the usage message" eval '! grep -q "usage:" "$W/e"'
    vs_base "$label: who" who
  done
done

# 4b. HTML comments in the Brief approval section are stripped before the table is read
#     (Amendment 6): a table inside a comment is ignored, whatever it holds.
CMT_GOOD=$(lines '<!-- An example, not live:' "$HDR" "$SEP" '| Ghost | ghost | @ghost |' '| Rest (catch-all) | ghostrest | Approvers |' '-->')
CMT_BAD=$(lines '<!-- Another example, with no catch-all and a bad slug:' "$HDR" "$SEP" '| Ghost | ghost | @ghost |' '| Bad | Bad_Slug | client |' '-->')
CMT_OPEN=$(lines 'Text, then a comment <!-- opening on this line:' "$HDR" "$SEP" '| Ghost | ghost | @ghost |' 'and closing here --> and more text.')
LIVE=$(lines "$HDR" "$SEP" "$LC" "$LD" "$LS")
LIVE_AREAS=$(printf 'content: @client\ndesign: @client\nsystem: @neil (catch-all)\ntrusted: none')
# nolive <name>: the table is only in a comment, so only everything is in effect.
nolive() { local name=$1 who_first
  check "comment, $name: areas is the implicit area" 0 "$(printf 'everything: @neil (catch-all)\ntrusted: none')" areas
  t "comment, $name: areas: nothing on stderr" test ! -s "$W/e"
  check "comment, $name: who everything" 0 ANY who everything
  t "comment, $name: who everything: the first line is who's (required $MEO)" test "$(first)" = "required $MEO"
  check "comment, $name: who ghost is an unknown area" 2 '' who ghost
  t "comment, $name: who ghost: the message" test "$(cat "$W/e")" = 'ada-brief: unknown area ghost; ada-brief areas lists them'
  vs_base "comment, $name: who" who
}
# live <name>: a live table beside the comment: the live table is the one read.
live() { local name=$1
  check "comment, $name: areas is the live table's" 0 "$LIVE_AREAS" areas
  t "comment, $name: areas: nothing on stderr" test ! -s "$W/e"
  check "comment, $name: who design" 0 "$(printf 'required %s\nneeds design: @client' "$MEO")" who design
  check "comment, $name: who ghost is an unknown area" 2 '' who ghost
  vs_base "comment, $name: who" who
}
rawproj "$CMT_GOOD";                          nolive "a good table alone"
rawproj "$CMT_BAD";                           nolive "a bad table alone"
rawproj "$CMT_OPEN";                          nolive "a comment opening mid-line"
rawproj "$(lines "$CMT_BAD" '' "$LIVE")";     live "a bad table in a comment, then the live table"
rawproj "$(lines "$LIVE" '' "$CMT_BAD")";     live "the live table, then a bad table in a comment"
rawproj "$(lines "$LIVE" "$CMT_BAD")";        live "the live table, a comment right after its last row"
rawproj "$(lines "$CMT_GOOD" '' "$LIVE")";    live "a good second table in a comment, then the live table"
rawproj "$(lines "$LIVE" '' "$CMT_OPEN")";    live "the live table, then a comment opening mid-line"
# A comment that ends on the line directly above the live table, no blank line between: the
# table is still the live one, read as for the plain table (areas, who design, exit 0).
rawproj "$(lines 'Note: <!-- x -->' "$LIVE")";                              live "a line ending in a one-line comment, directly above the table"
rawproj "$(lines 'Note: <!-- x -->' '' "$LIVE")";                           live "a line ending in a one-line comment, a blank line, the table"
rawproj "$(lines '<!-- a comment' 'over two lines -->' "$LIVE")";           live "a two-line comment ending directly above the table"
rawproj "$(lines '<!-- a comment' 'over' 'three lines' '-->' "$LIVE")";     live "a three-line comment, its --> alone, directly above the table"
rawproj "$(lines 'Note: <!-- opens here' 'and closes here -->' "$LIVE")";   live "text, then a comment opening mid-line and ending directly above the table"
rawproj "$(lines "$CMT_BAD" "$LIVE")";                                      live "a bad table in a comment ending directly above the live table"

# 5. who <area>... (Definition of Done 6 and 7). Exit 0 unless an area is unknown.
SYS=$(printf '| Content | content | @%s |\n| Design | design | @%s |\n| System behaviour (catch-all) | system | @other |' "$ME" "$ME")
proj "@neil" none "$SYS"
check "who design content, <me> approves both" 0 "approver $MEO" who design content
check "who content, <me> approves it" 0 "approver $MEO" who content
check "who design system, system is @other" 0 "$(printf 'required %s\nneeds system: @other' "$MEO")" who design system
check "who system" 0 "$(printf 'required %s\nneeds system: @other' "$MEO")" who system
check "who system system: a repeated area listed once" 0 "$(printf 'required %s\nneeds system: @other' "$MEO")" who system system
check "who design design content, <me> approves both" 0 "approver $MEO" who design design content
check "who nope: exit 2, nothing on stdout" 2 '' who nope
t "who nope: the message" test "$(cat "$W/e")" = 'ada-brief: unknown area nope; ada-brief areas lists them'
check "who design nope: exit 2, nothing on stdout" 2 '' who design nope
t "who design nope: the message names nope" test "$(cat "$W/e")" = 'ada-brief: unknown area nope; ada-brief areas lists them'
check "who everything, with a table: exit 2" 2 '' who everything
t "who everything, with a table: unknown area everything" test "$(cat "$W/e")" = 'ada-brief: unknown area everything; ada-brief areas lists them'
check "who catch-all: exit 2" 2 '' who catch-all
check "who Design (not the slug): exit 2" 2 '' who Design
REQ=1
check "ADA_BRIEF_REQUIRE=1 who design content, <me> approves both" 0 "required $MEO" who design content
check "ADA_BRIEF_REQUIRE=1 who design system" 0 "$(printf 'required %s\nneeds system: @other' "$MEO")" who design system
REQ=''
proj "@neil" "@$ME" "$T3"
check "who design, <me> in Trusted only" 0 "trusted $MEO" who design
check "who design system, <me> in Trusted only" 0 "trusted $MEO" who design system
proj "@neil" "@$ME" "$(printf '| Content | content | @client |\n| Design | design | @%s |\n| System behaviour (catch-all) | system | Approvers |' "$ME")"
check "who design, <me> approves it and is Trusted: approver comes first" 0 "approver $MEO" who design
NONE=$(printf '| Content | content | @%s |\n| Design | design | none |\n| System behaviour (catch-all) | system | Approvers |' "$ME")
proj "@neil" none "$NONE"
check "who design, design approved by none" 0 "not-needed" who design
REQ=1
check "ADA_BRIEF_REQUIRE=1 who design, design approved by none: not-needed comes first" 0 "not-needed" who design
REQ=''
check "who design system: design needs nothing, so only system is listed" 0 "$(printf 'required %s\nneeds system: @neil' "$MEO")" who design system
check "who design content: content approved by <me>, design by none" 0 "approver $MEO" who design content
proj "@neil" none "$(printf '| Content | content | @client |\n| Design | design | @client2, @b |\n| System behaviour (catch-all) | system | Approvers |')"
check "who design content, <me> on no list: needs lines in argument order" 0 "$(printf 'required %s\nneeds design: @client2, @b\nneeds content: @client' "$MEO")" who design content
check "who content design, <me> on no list: needs lines in argument order" 0 "$(printf 'required %s\nneeds content: @client\nneeds design: @client2, @b' "$MEO")" who content design
check "who design content design: a repeated area listed once" 0 "$(printf 'required %s\nneeds design: @client2, @b\nneeds content: @client' "$MEO")" who design content design
proj "@$ME" none "$(printf '| Content | content | @client, Approvers |\n| System behaviour (catch-all) | system | Approvers |')"
check "who system, approved by Approvers, <me> an approver" 0 "approver $MEO" who system
check "who content, approved by @client and Approvers, <me> an approver" 0 "approver $MEO" who content
proj none none "$(printf '| Content | content | @client |\n| System behaviour (catch-all) | system | Approvers |')"
check "who system, approved by Approvers with Approvers: none" 0 "not-needed" who system
UPME=$(upper "$ME")
proj "@neil" none "$(printf '| Design | design | @%s |\n| System behaviour (catch-all) | system | Approvers |' "$UPME")"
check "who design, @<ME> in upper case in the table" 0 "approver $MEO" who design
check "areas, @<ME> in upper case in the table: printed lower case" 0 "$(printf 'design: @%s\nsystem: @neil (catch-all)\ntrusted: none' "$ME")" areas

# 6. No table: who everything gives who's first line (Definition of Done 8).
notable() { local name=$1 who_first
  run "$NP" who; who_first=$(first)
  run "$NP" who everything
  t "no table, $name: who everything's first line is who's ($who_first)" eval 'test -n "$who_first" && test "$(first)" = "$who_first"'
  check "no table, $name: who content: exit 2" 2 '' who content
}
proj "@$ME" none;    notable "<me> an approver"
proj "@neil" "@$ME"; notable "<me> trusted"
proj "@neil" none;   notable "<me> on neither list"
proj none none;      notable "Approvers: none"
proj NOSECTION;      notable "no section"

# 7. who with no arguments: the same as the base, with and without a table (Definition of Done 5).
G7=$(printf '| Content | content | @%s |\n| Design | design | @client |\n| System behaviour (catch-all) | system | Approvers |' "$ME")
grid() { tab=$1; label=$2
  proj "@$ME" none "$tab";    vs_base "who, $label, <me> an approver" who;     t "who, $label, <me> an approver: approver" test "$(first)" = "approver $MEO"
  proj "@neil" "@$ME" "$tab"; vs_base "who, $label, <me> trusted" who;          t "who, $label, <me> trusted: trusted" test "$(first)" = "trusted $MEO"
  proj "@neil" none "$tab";   vs_base "who, $label, <me> on neither list" who;  t "who, $label, <me> on neither list: required" test "$(first)" = "required $MEO"
  proj none none "$tab";      vs_base "who, $label, Approvers: none" who;       t "who, $label, Approvers: none: not-needed" test "$(first)" = "not-needed"
  REQ=1
  proj "@$ME" none "$tab";    vs_base "ADA_BRIEF_REQUIRE=1 who, $label, <me> an approver" who; t "ADA_BRIEF_REQUIRE=1 who, $label: required" test "$(first)" = "required $MEO"
  REQ=''
  proj NOSECTION "$tab";      vs_base "who, $label, no section" who;            t "who, $label, no section: not-needed" test "$(first)" = "not-needed"
}
grid '' "no table"
grid "$G7" "a table"

# 8. The readers of who's first line, in a repo with a table: ada-onboard check and ada-plan
#    status, the base plugin's and this checkout's (each calls its own ada-brief).
M="$W/plan repo"; O="$W/origin.git"
git init -q --bare "$O"
git init -q "$M" && git -C "$M" symbolic-ref HEAD refs/heads/main && mkdir -p "$M/.claude/team"
printf '.claude/handoff/\n.claude/worktrees/\n' > "$M/.gitignore"
cp "$PJ" "$M/.claude/team/PROJECT.md"
G -C "$M" add -A && G -C "$M" commit -q -m init && G -C "$M" branch next-release \
  && G -C "$M" remote add origin "$O" && G -C "$M" push -q origin main next-release && G -C "$M" fetch -q origin \
  || { echo "SETUP FAIL: plan repo"; exit 2; }
(cd "$M" && "$BASH_BIN" "$NP/bin/ada-handoff-init") > /dev/null 2>&1
(cd "$M" && PATH="$W/gh:$PATH" "$BASH_BIN" "$NP/bin/ada-plan" init) > /dev/null 2>&1 || { echo "SETUP FAIL: ada-plan init"; exit 2; }
cat > "$M/.claude/handoff/plan/PLAN.md" <<'EOF'
# Plan: Widgets

Epic: https://app.asana.com/0/1/1219000000000009
Status: agreed

## Outcome

Widgets go out.

## Units

| ID | Unit | Task | Branch | Parent | Ships |
|---|---|---|---|---|---|
| A | Widget export | 1219000000000001 | add/widget-export | | independent |
| B | Gadget import | 1219000000000002 | add/gadget-import | | after Unit A |

## Amendments

| Date | What changed | Why | Agreed by |
|---|---|---|---|
| | | | |
EOF
# reader <plugin> <script> <args...>: in the plan repo; output to $W/o, exit code to $W/c.
reader() { local p=$1 script=$2; shift 2
  (cd "$M" && PATH="$W/gh:$PATH" exec "$BASH_BIN" "$p/bin/$script" "$@") > "$W/o" 2> "$W/e"; echo $? > "$W/c"; }
readers() { local label=$1 s
  cp "$PJ" "$M/.claude/team/PROJECT.md"
  for s in "ada-onboard check" "ada-plan status"; do
    set -- $s
    reader "$BP" "$@"; save rb; reader "$NP" "$@"; save rn
    if same rb rn; then ok 0 "$s, $label: same as base"; else ok 1 "$s, $label: same as base"; echo "      base:"; restore rb; show; echo "      this checkout:"; restore rn; show; fi
    restore rn
    case "$s" in
      "ada-onboard check") t "$s, $label: no brief approval error" eval '! grep -q "^error *brief approval" "$W/o"' ;;
      "ada-plan status") t "$s, $label: exit 0" test "$(code)" = 0
                         if [ "$label" = "<me> on neither list" ]; then t "$s, $label: not-submitted shown" grep -q 'not-submitted' "$W/o"
                         else t "$s, $label: not-needed shown, no not-submitted" eval 'grep -q not-needed "$W/o" && ! grep -q not-submitted "$W/o"'; fi ;;
    esac
  done
}
proj "@$ME" none "$G7";  readers "<me> an approver"
proj "@neil" none "$G7"; readers "<me> on neither list"
proj none none "$G7";    readers "Approvers: none"
# A bad table (Amendment 2): who exits 2, so ada-onboard check reports it and ada-plan status
# falls back to required, even for an approver. This checkout's plugin only (the base ignores tables).
proj "@$ME" none "$NOCATCH"; cp "$PJ" "$M/.claude/team/PROJECT.md"
reader "$NP" ada-onboard check
t "ada-onboard check, bad table: an error    brief approval: ada-brief: line" grep -q '^error    brief approval: ada-brief: ' "$W/o"
reader "$NP" ada-plan status
t "ada-plan status, bad table, <me> an approver: exit 0" test "$(code)" = 0
t "ada-plan status, bad table, <me> an approver: falls back to required (not-submitted)" grep -q 'not-submitted' "$W/o"

# 9. Read-only: no run changed a file in the scratch repo; areas never called gh.
t "no ada-brief run changed a file in the scratch repo" test "$changed" = 0
t "areas never called gh ($areas_runs runs)" test "$areas_gh" = 0

# 10. CHANGELOG: the Brief approval by area. bullet under Unreleased.
CL=${CHANGELOG:-$REPO/CHANGELOG.md}
if [ -f "$CL" ]; then
  awk '/^## Unreleased/ { u = 1; next } u && /^## / { exit } u' "$CL" > "$W/unrel"
  awk '/\*\*Brief approval by area\.\*\*/ { b = 1 } b && /^$/ { exit } b && NR > 1 && /^- / && !/Brief approval by area/ { exit } b' "$W/unrel" > "$W/bullet"
  t "CHANGELOG: **Brief approval by area.** under Unreleased" test -s "$W/bullet"
  t "CHANGELOG: the bullet says what ada-brief areas does" grep -q 'areas' "$W/bullet"
  t "CHANGELOG: the bullet says what who <area> does" grep -qF 'who <area' "$W/bullet"
else
  ok 1 "CHANGELOG: no file at $CL"
fi

echo "$passes passed, $fails failed"
[ "$fails" = 0 ] || exit 1
exit 0
```

## Expected

Every line `PASS`; the last line `<n> passed, 0 failed`; exit 0. The same under bash 3.2 and
Python 3.8 (step 3) and with `REAL_GH=1`. Put the `info:` lines, the totals and both breaks'
results in the Run record.

Sources, all the brief (Unit B, task 1220198285873178) unless named:

- Section 1: Definition of Done 11 and 12 (the usage header documents `areas` and
  `who [<area>...]` with their outputs; the `die 'usage: …'` line names both; `bash -n`).
- Section 2: Definition of Done 2 and 3 exactly; the rest from Decisions: one line per area in
  table order, `<slug>: @a, @b` or `<slug>: none`, the catch-all's ending ` (catch-all)`, then
  `trusted: @x, @y` or `trusted: none`; logins lowercased; `Approvers` is the Approvers line's
  logins; slugs `[a-z0-9][a-z0-9-]*`, at most 40 characters; the table is in the Brief
  approval section (so the same header elsewhere is not read). Logins are expected in the order
  written, and a cell holding both an `@login` and `Approvers` is checked as a set (the brief
  doesn't fix its order).
- Section 3: Decisions, `ada-brief areas` with no Brief approval section.
- Section 4: Definition of Done 4, the seven tables it names, and the other rules in Decisions
  (`everything` reserved, 41 characters, the character set, a duplicate of the catch-all's slug).
  A bad table stops `areas`, `who <area>` and `who` with no arguments alike (Amendment 2, agreed
  by Neil 2026-11-10), as does a table in the section whose header isn't `| Area | Slug |
  Approved by |` (Amendment 1). `who <area>` names an area the table has, and its message must
  not be the unknown-area one, so it can't pass as an unknown area. A bad table's message is not
  the usage message (which the base prints for `areas`, a subcommand it doesn't know). The
  `- **Approvers:**` and `- **Trusted:**` lines stay required (Decisions): with either missing,
  `areas` exits 2 and `who` does what the base does.
- Section 5: Definition of Done 6 and 7 exactly; the rest from Decisions' order of first lines
  and the `needs` lines, and Ada's answers of 2026-11-10: `needs` lines in argument order, a
  repeated area once; an area approved by `none` is skipped when deciding whether `<me>`
  approves every named area; exit 0 for every outcome but an unknown area or a bad table.
- Section 6: Definition of Done 8; with no table only `everything` is in effect.
- Section 7: Definition of Done 5, against the base script (`febfd7b`), LF and CRLF, with the
  expected first words checked too so two identical wrong outputs can't pass.
- Section 8: Definition of Done 10 and the brief's Surfaces (`ada-plan status` and `ada-onboard
  check` in a project with a table). `ada-plan status` reads only `who`'s first word
  (`API.md`). With a bad table (Amendment 2, Ada's answer 1) `who` exits 2, so `ada-onboard
  check` prints `error    brief approval: ada-brief: …` and `ada-plan status` falls back to
  `required` (`not-submitted`) even for an approver.
- Section 4, the second-table and no-separator cases: Amendments 3 and 4 (exit 2, `more than
  one table` in the message for the first; the second table is valid on its own and the no-separator
  table would be valid with its first row dropped, so only the rule can be what fails them).
- Section 4b: Amendment 6. A table inside an HTML comment is ignored, whatever it holds: a
  commented-out table alone leaves the implicit catch-all area (`everything`) and no table
  (Definition of Done 8 for `who everything`'s first line; `who` is as the base's); a commented-out
  bad table beside a good one leaves the good one's areas; a commented-out second table is not
  "more than one table".
  A comment (or a line ending in one) directly above the live table, with no blank line, and a
  multi-line comment ending on the line directly above it, leave the live table read as for the
  plain table: its areas, `who design`, exit 0.
- Steps 5: Amendments 2, 5 and 6, `status` and `submit` with a bad table, and with the table in
  a comment (these need a submitted PR, so a scratch GitHub repo).
- Every `check` line has a `CRLF:` twin: Definition of Done 9.
- Section 9: Decisions (`areas` is read-only and needs no `gh`).
- Section 10: Definition of Done 13 and Constraints (S105).

## Observe at

The script's output and exit code. A failing check prints the run's exit code, stdout and stderr
under it. `KEEP=1` keeps the scratch folder (named on the first line): `scratch repo/.claude/team/PROJECT.md`
holds the last case's file, and `gh.log` the last run's `gh` calls.

## Regression

Section 7 (`who` with no arguments, every policy, with and without a table, LF and CRLF) and
section 8 (its two readers). `policy()` also serves `submit` and `status`: run
`tests/functional/01-brief-approval-areas/submit-and-status-offline-stub-gh.md` with
`BASE=febfd7b` too. (Unit A's `refactor-static-and-usage.md` checks that the usage header did not
change; Unit B changes it on purpose, so that script's usage checks no longer apply.)
