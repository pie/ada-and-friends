# `ada-plan` stops when `PROJECT.md` has no `**Branch from:**` line, instead of guessing `production`

Step 1 of 10 Nov 2026, "Stop instead of guessing `production` when PROJECT.md has no Branch from":
a missing `**Branch from:**` line is an error, not a default. `ada-plan branches`, `status`,
`check` and `briefs` stop with a message naming `PROJECT.md`, the line and the Code and branches
section, and never fetch `production`; only `init` runs as before (Amendment, 10 Nov, agreed by
Neil: `check` and `briefs` print or write the value). With no `.claude/team/PROJECT.md` at all,
`check`, `briefs`, `branches` and `status`, which read it, stop with
`ada-plan: no .claude/team/PROJECT.md; onboard the repo first` (second Amendment, 10 Nov); `init`
reads nothing from it and runs as released. With the line present nothing changes. `ada-brief overlap`'s half is in
`tests/functional/01-brief-approval/overlap.md`, section 7.

- Area: 03 Plans (`ada-plan`); 12 Platforms (bash 3.2, Python 3.8, a path with a space)
- Asana: https://app.asana.com/0/1206474484537567/1219575720822902
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 10 Nov 2026, 0 failed (87 PASS) under bash 5, Docker bash 3.2 and Docker Python 3.8; the released-plugin break prints 31 FAIL (see the PR)

## Precondition

- This checkout (`REPO`), with `origin/next-release` fetched: the script compares the changed
  `ada-plan` with the released one there.
- `git`, `tar`, `cmp`, Python 3.8+. The script makes its own scratch repos, each a clone called
  `scratch repo` (a path with a space) of a bare `origin`, and never touches this checkout.
- Two stand-ins go on the PATH after the plugin's `bin/`: a `gh` (as in
  `plan-lives-in-main-checkout.md`: every `list` answers `[]`, `auth` and `label create` succeed,
  `pr view` fails, anything else fails and is logged) and a `git` that logs every call to
  `git.log` in the scratch folder and then runs the real git. The `git.log` is how the script
  sees that nothing fetched `production`.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, then under bash 3.2 and Python 3.8 as below.
3. Break it on purpose, once: run it with `PLUGIN` set to the **released** plugin
   (`git -C <this checkout> archive origin/next-release plugins/ada | tar -x -m -C <scratch>`, then
   `PLUGIN=<scratch>/plugins/ada`). It must print `FAIL` for the checks in sections 1 to 3, for
   `check` and `briefs` in section 4 (4b) and for section 8 (the released script guesses
   `production`; in section 1 it fails with "couldn't fetch production", in section 2 it creates
   the branches from `origin/production`, in section 4b `check` and `briefs` exit 0, in section 8
   it prints no `no .claude/team/PROJECT.md` message). Section 4a (`init`) and sections 5 to 7
   must still `PASS`.

```bash
#!/bin/bash
# ada-plan with and without **Branch from:** in PROJECT.md. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO (this checkout), BASE (default origin/next-release), PLUGIN (plugin under test,
# default $REPO/plugins/ada), OLD_PLUGIN (released plugin; default: exported from BASE),
# SH (how to run the script under test; default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE=${BASE:-origin/next-release}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
SH=${SH:-bash}
REALGIT=$(command -v git) || { echo "SETUP FAIL: git not found"; exit 2; }
command -v python3 >/dev/null 2>&1 || { echo "SETUP FAIL: python3 not found"; exit 2; }
W=$(mktemp -d)
if [ -z "${OLD_PLUGIN:-}" ]; then
  mkdir -p "$W/old"
  "$REALGIT" -C "$REPO" archive "$BASE" plugins/ada | tar -x -m -C "$W/old" || { echo "SETUP FAIL: cannot export $BASE"; exit 2; }
  OLD_PLUGIN=$W/old/plugins/ada
fi
H=.claude/handoff
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
G()   { "$REALGIT" -c user.name=t -c user.email=t@example.invalid -c init.defaultBranch=init "$@"; }

# The stand-ins: gh, and a git that logs each call.
mkdir -p "$W/bin"
cat > "$W/bin/gh" <<EOF
#!/bin/sh
echo "gh \$*" >> "$W/gh.log"
case " \$* " in
  *" list "*) echo '[]'; exit 0;;
  *" auth "*) exit 0;;
  *" label create "*) exit 0;;
  *" pr view "*) exit 1;;
esac
echo "stand-in gh: not handled: \$*" >&2; exit 1
EOF
cat > "$W/bin/git" <<EOF
#!/bin/sh
echo "git \$*" >> "$W/git.log"
exec "$REALGIT" "\$@"
EOF
chmod +x "$W/bin/gh" "$W/bin/git"

# run <repo> <plugin> <script> args...: output in $W/out, $W/err, both in $W/all, exit in $W/rc,
# this run's git calls in $W/git.log.
run() { local d=$1 pl=$2 sc=$3; shift 3; : > "$W/git.log"
        (cd "$d" && PATH="$pl/bin:$W/bin:$PATH" $SH "$pl/bin/$sc" "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"
        cat "$W/out" "$W/err" > "$W/all"; }
rc()  { cat "$W/rc"; }
info() { echo "info: $1: exit $(rc)"; sed 's/^/info:   /' "$W/all"; }
# The brief's message: one line, '<prefix>: ...', naming PROJECT.md, **Branch from:** and Code and branches.
msg_ok() { grep -E "^$1: " "$W/all" | grep -F 'PROJECT.md' | grep -F '**Branch from:**' | grep -qF 'Code and branches'; }
no_msg() { ! grep -qF '**Branch from:**' "$W/all"; }
fetched_prod() { grep -E '(^| )fetch( |$)' "$W/git.log" | grep -q production; }
fetched() { grep -E '(^| )fetch( |$)' "$W/git.log" | grep -q "$1"; }
cant_fetch_prod() { grep -qi "couldn't fetch production" "$W/all"; }
refs() { "$REALGIT" -C "$1" for-each-ref --format='%(refname) %(objectname)' refs/heads; }
tip()  { "$REALGIT" -C "$1" rev-parse "$2"; }

# mkrepo <name> <branch lines, or -> [<extra origin branch>]: a bare origin with main and
# next-release (and the extra branch), its clone "$W/<name>/scratch repo" (in $M), handoff files and
# a plan of two top-level units whose branches exist nowhere yet. Sets D, O, M.
mkrepo() {
  D=$W/$1; O=$D/origin.git; M="$D/scratch repo"
  mkdir -p "$D"; G init -q --bare "$O"
  G init -q "$M"; G -C "$M" symbolic-ref HEAD refs/heads/main
  mkdir -p "$M/.claude/team"
  {
    printf '# Scratch\n\n## Code and branches\n\n'
    if [ "$2" != - ]; then printf '%s\n' "$2"; fi
    printf -- '- **Test on:** a scratch repo.\n\n## Brief approval\n\n- **Approvers:** none\n- **Trusted:** none\n'
  } > "$M/.claude/team/PROJECT.md"
  printf '.claude/handoff/\n.claude/worktrees/\n' > "$M/.gitignore"
  G -C "$M" add -A && G -C "$M" commit -q -m init
  "$REALGIT" -C "$M" config user.name t; "$REALGIT" -C "$M" config user.email t@example.invalid
  G -C "$M" branch next-release
  G -C "$M" remote add origin "$O" && G -C "$M" push -q origin main next-release
  if [ -n "${3:-}" ]; then G -C "$M" branch "$3" && G -C "$M" push -q origin "$3"; fi
  G -C "$M" fetch -q origin
  (cd "$M" && PATH="$PLUGIN/bin:$W/bin:$PATH" bash "$PLUGIN/bin/ada-handoff-init") > /dev/null 2>&1
}
mkplan() {
  mkdir -p "$M/$H/plan"
  cat > "$M/$H/plan/PLAN.md" <<'EOF'
# Plan: Widgets and gadgets

Epic: https://app.asana.com/0/1/1219000000000009
Status: agreed

## Outcome

Widgets go out and gadgets come in.

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
}
# later <branch>: push a new commit to origin's <branch> from another clone, so the scratch repo's
# origin/<branch> is stale until something fetches it. Prints the new tip.
later() { G clone -q "$O" "$D/other" 2>/dev/null; G -C "$D/other" checkout -q "$1"
          echo later >> "$D/other/later.txt"; G -C "$D/other" add -A; G -C "$D/other" commit -q -m "later on $1"
          G -C "$D/other" push -q origin "$1"; tip "$D/other" HEAD; rm -rf "$D/other"; }
WR=$(cd "$W" && pwd -P)
norm() { sed -e "s|$W/[^/ ]*/|<D>/|g" -e "s|$WR/[^/ ]*/|<D>/|g" "$1"; }

WITH='- **Branch from:** `main`. **PR into:** `next-release`.'
WITHOUT='- **PR into:** `next-release`.'

# 0. Static.
t "bash -n ada-plan" bash -n "$PLUGIN/bin/ada-plan"
t "bash -n ada-brief" bash -n "$PLUGIN/bin/ada-brief"

# 1. DoD 1: no **Branch from:** line, and no production branch on origin (like this repo).
mkrepo nobf "$WITHOUT"; mkplan; refs "$M" > "$W/refs.before"
for sub in branches status; do
  run "$M" "$PLUGIN" ada-plan $sub; info "no Branch from, $sub"
  t "no Branch from, $sub: exits non-zero" test "$(rc)" != 0
  t "no Branch from, $sub: one line 'ada-plan: ...' naming PROJECT.md, **Branch from:** and Code and branches" msg_ok ada-plan
  t "no Branch from, $sub: no \"couldn't fetch production\"" not cant_fetch_prod
  t "no Branch from, $sub: no git fetch of production" not fetched_prod
  refs "$M" > "$W/refs.after"
  t "no Branch from, $sub: no branch created or moved" cmp -s "$W/refs.before" "$W/refs.after"
done

# 2. DoD 1, a guess that would have worked: no line, but origin has production. Still an error.
mkrepo prod "$WITHOUT" production; mkplan; refs "$M" > "$W/refs.before"
for sub in branches status; do
  run "$M" "$PLUGIN" ada-plan $sub; info "no Branch from, origin has production, $sub"
  t "no Branch from, origin has production, $sub: exits non-zero" test "$(rc)" != 0
  t "no Branch from, origin has production, $sub: the 'ada-plan: ...' message" msg_ok ada-plan
  t "no Branch from, origin has production, $sub: no git fetch of production" not fetched_prod
  refs "$M" > "$W/refs.after"
  t "no Branch from, origin has production, $sub: no branch created" cmp -s "$W/refs.before" "$W/refs.after"
done

# 3. DoD 1: no branch lines at all (PR into missing too): the Branch from error, not a PR into one.
mkrepo none -; mkplan; refs "$M" > "$W/refs.before"
for sub in branches status; do
  run "$M" "$PLUGIN" ada-plan $sub
  t "no branch lines at all, $sub: exits non-zero with the 'ada-plan: ...' Branch from message" eval 'test "$(rc)" != 0 && msg_ok ada-plan'
  t "no branch lines at all, $sub: no git fetch of production" not fetched_prod
done
refs "$M" > "$W/refs.after"
t "no branch lines at all: no branch created" cmp -s "$W/refs.before" "$W/refs.after"

# 4. DoD 4 and the first Amendment (10 Nov): with the line missing, init is the only subcommand
#    that still runs (as the released one does); check and briefs stop like branches and status.
#    4a: init, twin repos, one per plugin.
for p in new old; do
  if [ $p = new ]; then P=$PLUGIN; else P=$OLD_PLUGIN; fi
  mkrepo "dod4-$p" "$WITHOUT"
  run "$M" "$P" ada-plan init; norm "$W/all" > "$W/init.$p"; echo "$(rc)" > "$W/init.rc.$p"
  cp "$M/$H/plan/PLAN.md" "$W/init.plan.$p" 2>/dev/null
done
echo "info: no Branch from, init: exit $(cat "$W/init.rc.new")"; sed 's/^/info:   /' "$W/init.new"
t "no Branch from, init: exits 0" test "$(cat "$W/init.rc.new")" = 0
t "no Branch from, init: same exit as released" test "$(cat "$W/init.rc.new")" = "$(cat "$W/init.rc.old")"
t "no Branch from, init: same output as released (paths aside)" cmp -s "$W/init.old" "$W/init.new"
t "no Branch from, init: no Branch from message" eval 'not grep -qF "**Branch from:**" "$W/init.new"'
t "no Branch from, init: PLAN.md is the template" cmp -s "$PLUGIN/templates/plan/PLAN.md" "$W/init.plan.new"
#    4b: check and briefs, with a plan in place, stop and do nothing.
mkrepo nobf-cb "$WITHOUT"; mkplan; refs "$M" > "$W/refs.before"
for sub in check briefs; do
  run "$M" "$PLUGIN" ada-plan $sub; info "no Branch from, $sub"
  t "no Branch from, $sub: exits non-zero" test "$(rc)" != 0
  t "no Branch from, $sub: one line 'ada-plan: ...' naming PROJECT.md, **Branch from:** and Code and branches" msg_ok ada-plan
  t "no Branch from, $sub: no \"couldn't fetch production\"" not cant_fetch_prod
  t "no Branch from, $sub: no git fetch of production" not fetched_prod
  refs "$M" > "$W/refs.after"
  t "no Branch from, $sub: no branch created or moved" cmp -s "$W/refs.before" "$W/refs.after"
done
t "no Branch from, briefs: no brief drafted" eval 'test ! -e "$M/$H/plan/1219000000000001.md" && test ! -e "$M/$H/plan/1219000000000002.md"'

# 5. DoD 3: with **Branch from:** `main`, status and branches fetch main and use it, as released.
#    origin/main moves on after the clone's last fetch, so using it means fetching it first.
for p in new old; do
  if [ $p = new ]; then P=$PLUGIN; else P=$OLD_PLUGIN; fi
  mkrepo "with-$p" "$WITH"; mkplan; eval "M_$p=\"\$M\""
  new_tip=$(later main); eval "T_$p=\$new_tip"
  run "$M" "$P" ada-plan status; norm "$W/all" > "$W/status.$p"; echo "$(rc)" > "$W/status.rc.$p"
  cp "$W/git.log" "$W/status.git.$p"
  run "$M" "$P" ada-plan branches; norm "$W/all" > "$W/branches.$p"; echo "$(rc)" > "$W/branches.rc.$p"
  cp "$W/git.log" "$W/branches.git.$p"
done
for sub in status branches; do
  echo "info: with Branch from main, $sub: exit $(cat "$W/$sub.rc.new")"; sed 's/^/info:   /' "$W/$sub.new"
  t "with Branch from main, $sub: exits 0" test "$(cat "$W/$sub.rc.new")" = 0
  t "with Branch from main, $sub: same output as released (paths aside)" cmp -s "$W/$sub.old" "$W/$sub.new"
  t "with Branch from main, $sub: fetches main" eval 'grep -E "(^| )fetch( |\$)" "$W/$sub.git.new" | grep -q main'
  t "with Branch from main, $sub: no git fetch of production" eval '! grep -E "(^| )fetch( |\$)" "$W/$sub.git.new" | grep -q production'
  t "with Branch from main, $sub: no Branch from message" eval 'not grep -qF "**Branch from:**" "$W/$sub.new"'
done
t "with Branch from main, branches: add/widget-export created at origin/main's new tip" test "$(tip "$M_new" add/widget-export 2>/dev/null)" = "$T_new"
t "with Branch from main, branches: add/gadget-import created at origin/main's new tip" test "$(tip "$M_new" add/gadget-import 2>/dev/null)" = "$T_new"

# 6. Regression: an explicit **Branch from:** `production` (an onboarded WordPress project) still works.
mkrepo explicit-prod '- **Branch from:** `production`. **PR into:** `next-release`.' production; mkplan
pt=$(later production)
run "$M" "$PLUGIN" ada-plan branches; info "Branch from production named, branches"
t "Branch from production named, branches: exits 0" test "$(rc)" = 0
t "Branch from production named, branches: fetches production" fetched production
t "Branch from production named, branches: add/widget-export created at origin/production's new tip" test "$(tip "$M" add/widget-export 2>/dev/null)" = "$pt"
t "Branch from production named, branches: no Branch from message" no_msg

# 7. Regression: the PR into default (next-release) is unchanged: Branch from only, as released.
for p in new old; do
  if [ $p = new ]; then P=$PLUGIN; else P=$OLD_PLUGIN; fi
  mkrepo "bfonly-$p" '- **Branch from:** `main`.'; mkplan
  run "$M" "$P" ada-plan status; norm "$W/all" > "$W/bfo-status.$p"; echo "$(rc)" > "$W/bfo-status.rc.$p"
  run "$M" "$P" ada-plan branches; norm "$W/all" > "$W/bfo-branches.$p"; echo "$(rc)" > "$W/bfo-branches.rc.$p"
done
for sub in status branches; do
  t "Branch from only (PR into defaults), $sub: exits 0" test "$(cat "$W/bfo-$sub.rc.new")" = 0
  t "Branch from only (PR into defaults), $sub: same output as released (paths aside)" cmp -s "$W/bfo-$sub.old" "$W/bfo-$sub.new"
done

# 8. Second Amendment (10 Nov): no .claude/team/PROJECT.md at all. The subcommands that read it
#    (check, briefs, branches, status) stop with the brief's exact line: no traceback, no default,
#    no fetch of production, nothing created. init reads nothing from it and runs as released.
mkrepo nopm "$WITHOUT"; mkplan; rm -f "$M/.claude/team/PROJECT.md"; refs "$M" > "$W/refs.before"
NOPM='ada-plan: no .claude/team/PROJECT.md; onboard the repo first'
has_nopm() { grep -qxF "$NOPM" "$W/all"; }
no_traceback() { ! grep -qi 'traceback' "$W/all"; }
for sub in check briefs branches status; do
  run "$M" "$PLUGIN" ada-plan $sub; info "no PROJECT.md, $sub"
  t "no PROJECT.md, $sub: exits non-zero" test "$(rc)" != 0
  t "no PROJECT.md, $sub: prints '$NOPM' on a line of its own" has_nopm
  t "no PROJECT.md, $sub: no Python traceback" no_traceback
  t "no PROJECT.md, $sub: no git fetch of production" not fetched_prod
  refs "$M" > "$W/refs.after"
  t "no PROJECT.md, $sub: no branch created or moved" cmp -s "$W/refs.before" "$W/refs.after"
done
t "no PROJECT.md, stopped commands: nothing wrote a PROJECT.md" test ! -e "$M/.claude/team/PROJECT.md"
#    init runs as released. It needs a repo with no PLAN.md yet (it refuses to overwrite one).
mkrepo nopm-init "$WITHOUT"; rm -f "$M/.claude/team/PROJECT.md"
run "$M" "$PLUGIN" ada-plan init; info "no PROJECT.md, init"
t "no PROJECT.md, init: exits 0" test "$(rc)" = 0
t "no PROJECT.md, init: no Python traceback" no_traceback
t "no PROJECT.md, init: not stopped with the 'no PROJECT.md' line" not has_nopm
t "no PROJECT.md, init: PLAN.md is the template" cmp -s "$PLUGIN/templates/plan/PLAN.md" "$M/$H/plan/PLAN.md"
t "no PROJECT.md, init: nothing wrote a PROJECT.md" test ! -e "$M/.claude/team/PROJECT.md"

echo "info: gh calls:"; sed 's/^/info:   /' "$W/gh.log" 2>/dev/null
echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

### bash 3.2 and Python 3.8

With Docker and the `bash:3.2` and `python:3.8-slim` images (neither has git; the containers add
it, which needs the network). In a worktree checkout `/repo/.git` points outside the mount, so
export the released plugin first. With `S` the scratch folder holding `check.sh`:

```sh
mkdir -p "$S/old"
git -C <this checkout> archive origin/next-release plugins/ada | tar -x -m -C "$S/old"
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s bash:3.2 \
  sh -c 'apk add -q --no-cache git python3 >/dev/null && git config --global --add safe.directory "*" && bash --version | head -1 && REPO=/repo OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
docker run --rm -v <this checkout>:/repo:ro -v "$S":/s python:3.8-slim \
  sh -c 'apt-get -qq update >/dev/null && apt-get -qq install -y git >/dev/null && git config --global --add safe.directory "*" && python3 --version && REPO=/repo OLD_PLUGIN=/s/old/plugins/ada bash /s/check.sh'
```

The bash 3.2 run's first line names 3.2; the Python run's names 3.8. The Python run is what
proves the heredocs `ada-plan` runs on these paths work on Python 3.8 (Definition of Done 6).

## Expected

Every line `PASS`, last line `0 failed`, exit 0, in all three runs. Step 3's break prints `FAIL`
for sections 1 to 3, 4b and 8. Put the `info:` lines (each command's output) in the Run record.

Sources: the brief's Outcome and Decisions (a missing `**Branch from:**` line is an error, not a
default; the message names `PROJECT.md`, the missing line and the Code and branches section, e.g.
`ada-plan: .claude/team/PROJECT.md has no **Branch from:** line; add one under Code and branches`;
only commands that use the value stop; with the line present behaviour is unchanged; the
`**PR into:**` fallback is unchanged), Definition of Done 1, 3, 4 and 6, and the two Amendments of
10 Nov (agreed by Neil: `check` and `briefs` stop too, only `init` runs without the line; a missing
`PROJECT.md` gives `ada-plan: no .claude/team/PROJECT.md; onboard the repo first`, the exact line
asserted in section 8 for `check`, `briefs`, `branches` and `status`, the commands that read it;
`init` is exempt and runs as released, as the coordinator clarified on 10 Nov). Not from the brief, so not asserted: the exact exit code (any non-zero),
whether the message is on stdout or stderr, its exact wording beyond the three things it names
(the PROJECT.md message is given in full, so it is asserted in full).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: `out`,
`err`, `rc` and `git.log` hold the last run; `<name>/scratch repo` each scratch repo
(`git branch -v`, `git log --oneline origin/main`); `gh.log` the stand-in's calls.

## Regression

Section 4a keeps `init` running as released with the line missing;
section 5 compares `status` and `branches` with the released script when the line is present;
section 6 keeps an explicit `**Branch from:** \`production\`` working; section 7 keeps the
`next-release` default for `**PR into:**`. `ada-plan` from a unit's worktree, with the line
present, is `plan-lives-in-main-checkout.md`.
