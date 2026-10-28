# `ada-worktree remove` archives a finished step's handoff files, removes the worktree, keeps the branch, and refuses anything unsafe

Step 1 of 25 Oct 2026, "One worktree per step". `remove` deletes a directory, so this script
spends most of its checks on what it must refuse, and on proving a refusal changes nothing.

- Area: 15 Worktrees (`ada-worktree`); 05 Handoff files (the archive); 12 Platforms (bash 3.2, a
  path with a space)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219769422658693
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 28 Oct 2026 (after the rebase onto PR #39), 0 failed (78 PASS) under bash 5, bash --posix and Docker bash 3.2; both step 3 breaks FAIL; see the PR

## Precondition

- This checkout (`REPO`). The script makes its own scratch repo, `scratch repo` (a path with a
  space), and never touches this checkout. Its worktrees are made with plain `git worktree add`
  and `ada-handoff-init`, so a fault in `ada-worktree new` doesn't hide or fake one here.
- `git` 2.17 or later, `cmp`, `cksum`, `date`, Python 3.8+ (for `ada-onboard`).
- If the clock passes midnight during the run, the archive may carry either date; the script
  accepts both.

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run `REPO=<this checkout> bash check.sh`, then `SH="bash --posix"` as well, then under bash
   3.2 with the Docker command in `tests/functional/15-worktrees/new-check-list.md` (this
   `check.sh` in place of that one).
3. Break it on purpose, once: in a scratch copy of `plugins/ada`, edit the copy's
   `bin/ada-worktree remove` two ways at once: remove its uncommitted-changes test (make it always
   pass) AND add `--force` to its `git worktree remove` call. Both are needed: `remove` copies the
   handoff files and then runs `git worktree remove`, which refuses a dirty worktree itself, and
   `remove` puts the copies back, so with only the status test removed the user-visible result is
   unchanged and the script correctly prints `0 failed` (Tess ran that, 28 Oct 2026). Run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` for sections 3
   to 5 (the refusals, "nothing moved, deleted or archived", and "still a worktree with its
   handoff files"). Delete the scratch folder it names afterwards. Break it a second time: in another copy,
   make `remove` move the handoff files before it runs `git worktree remove`, and not put them
   back if that fails. It must print `FAIL` for section 9.

```bash
#!/bin/bash
# ada-worktree remove. PASS/FAIL per check; exit 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada),
# SH (how to run the script under test; default bash).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
SH=${SH:-bash}
W=$(mktemp -d)
WT=$PLUGIN/bin/ada-worktree
H=.claude/handoff
fails=0
ok()  { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()   { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not() { ! "$@"; }
G()   { git -c user.name=t -c user.email=t@example.invalid "$@"; }
run() { d=$1; shift; (cd "$d" && $SH "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()  { cat "$W/rc"; }
listed() { git -C "$M" worktree list --porcelain | grep -qE "^worktree .*/\.claude/worktrees/$1\$"; }   # by slug
snap() {    # worktrees, refs, and every file under the main checkout but .git, with checksums
  { git -C "$M" worktree list --porcelain; git -C "$M" for-each-ref --format='%(refname) %(objectname)'
    (cd "$M" && find . -path ./.git -prune -o -print | LC_ALL=C sort)
    (cd "$M" && find . -path ./.git -prune -o -type f -print | LC_ALL=C sort | while IFS= read -r f; do cksum "$f"; done)
  } > "$1" 2>&1; }
mkwt() {    # branch slug: a step's worktree as ada-worktree new would leave it, without using it
  P="$M/.claude/worktrees/$2"
  G -C "$M" worktree add -q -b "$1" "$P" main || { echo "SETUP FAIL: worktree $1"; exit 2; }
  (cd "$P" && bash "$PLUGIN/bin/ada-handoff-init") > /dev/null 2>&1 || { echo "SETUP FAIL: handoff in $2"; exit 2; }
  mkdir -p "$P/.claude/team" && cp "$M/.claude/team/local.env" "$P/.claude/team/local.env"
  printf 'BRIEF-MARKER %s\n' "$1" >> "$P/$H/BRIEF.md"
  printf -- '- Branch: %s\n' "$1" >> "$P/$H/CHECKPOINT.md"; }
refused() { # label path expected-exit (2, or "nz" for any non-zero): refuses, and nothing moves
  snap "$W/b"; run "$M" "$WT" remove "$2"; code=$(rc)
  echo "info: $1: exit $code: $(head -n 1 "$W/err")"
  if [ "$3" = nz ]; then t "$1: refuses (non-zero exit)" test "$code" != 0
  else t "$1: refuses with exit $3" test "$code" = "$3"; fi
  snap "$W/a"; t "$1: nothing moved, deleted or archived" cmp -s "$W/b" "$W/a"; }

# Setup: an onboarded scratch repo on main, its own handoff files and local.env in place.
M="$W/scratch repo"
git init -q "$M" && git -C "$M" symbolic-ref HEAD refs/heads/main
printf 'scratch\n' > "$M/README.md"
(cd "$M" && bash "$PLUGIN/bin/ada-onboard" gitignore) > /dev/null 2>&1 || { echo "SETUP FAIL: ada-onboard gitignore"; exit 2; }
grep -qxF .claude/worktrees/ "$M/.gitignore" || printf '.claude/worktrees/\n' >> "$M/.gitignore"
G -C "$M" add -A && G -C "$M" commit -q -m init
(cd "$M" && bash "$PLUGIN/bin/ada-handoff-init") > /dev/null 2>&1 || { echo "SETUP FAIL: ada-handoff-init"; exit 2; }
printf 'MAIN-BRIEF-MARKER\n' >> "$M/$H/BRIEF.md"
mkdir -p "$M/.claude/team" && printf 'LOCAL_URL=https://scratch.local\n' > "$M/.claude/team/local.env"
main_handoff() { (cd "$M/$H" && for f in *.md; do cksum "$f"; done); }
main_handoff > "$W/main.before"

# 1. A clean worktree, by the absolute path (Definition of Done 5).
mkwt change/r r; R=$P
printf 'r\n' > "$R/r.txt"; G -C "$R" add r.txt && G -C "$R" commit -q -m "r work"
sha_r=$(git -C "$M" rev-parse change/r)
t "setup r: clean apart from ignored files (handoff, local.env)" test -z "$(git -C "$R" status --porcelain)"
t "setup r: it does have ignored files" eval 'git -C "$R" status --porcelain --ignored | grep -q "^!! "'
mkdir -p "$W/saved-r" && cp "$R/$H/"*.md "$W/saved-r/"
d1=$(date +%Y-%m-%d); run "$M" "$WT" remove "$R"; d2=$(date +%Y-%m-%d)
echo "info: remove r: exit $(rc); archives now: $(ls "$M/$H/archive" 2>/dev/null | tr '\n' ' ')"
t "r: exits 0" test "$(rc)" = 0
t "r: the worktree directory is gone" test ! -e "$R"
t "r: git no longer lists it" not listed r
t "r: branch change/r still exists, at the same commit" test "$(git -C "$M" rev-parse -q --verify refs/heads/change/r)" = "$sha_r"
A="$M/$H/archive/$d1-r"; [ -d "$A" ] || A="$M/$H/archive/$d2-r"
t "r: archived in the main checkout's .claude/handoff/archive/<YYYY-MM-DD>-r/" test -d "$A"
t "r: the archive holds the worktree's handoff files, no more and no fewer" \
  test "$(cd "$A" 2>/dev/null && ls *.md | LC_ALL=C sort)" = "$(cd "$W/saved-r" && ls *.md | LC_ALL=C sort)"
for f in "$W/saved-r/"*.md; do t "r: archived ${f##*/} is the worktree's, byte for byte" cmp -s "$f" "$A/${f##*/}"; done
main_handoff > "$W/main.after"
t "r: the main checkout's own handoff files are untouched" cmp -s "$W/main.before" "$W/main.after"
t "r: the main checkout's local.env is untouched" grep -qxF 'LOCAL_URL=https://scratch.local' "$M/.claude/team/local.env"
t "r: git status in the main checkout shows nothing" test -z "$(git -C "$M" status --porcelain)"

# 2. A clean worktree by the relative path, run from the main checkout.
mkwt change/s s; S=$P
run "$M" "$WT" remove .claude/worktrees/s
t "s (relative path): exits 0" test "$(rc)" = 0
t "s: the worktree directory is gone" test ! -e "$S"
t "s: git no longer lists it" not listed s
t "s: branch change/s still exists" git -C "$M" rev-parse -q --verify refs/heads/change/s
t "s: archived as archive/<date>-s" eval 'ls -d "$M/$H/archive/"*-s >/dev/null 2>&1 && grep -q "BRIEF-MARKER change/s" "$M/$H/archive/"*-s/BRIEF.md'

# 3 to 5. Uncommitted changes: refused, and nothing moves.
mkwt change/t t; T=$P; printf 'edit\n' >> "$T/README.md"
refused "a tracked file changed, not staged" "$T" 2
t "tracked change: the edit is still there" grep -q edit "$T/README.md"
mkwt change/u u; U=$P; printf 'new\n' > "$U/new.txt"
refused "an untracked file (not ignored)" "$U" 2
mkwt change/v v; V=$P; printf 'staged\n' > "$V/staged.txt"; G -C "$V" add staged.txt
refused "a staged new file" "$V" 2
for d in "$T" "$U" "$V"; do t "refused ${d##*/}: still a worktree with its handoff files" sh -c 'test -f "$1/.claude/handoff/BRIEF.md"' _ "$d"; done

# 6. Not a step's worktree: refused, and nothing moves (the brief's "it deletes a directory").
refused "the main checkout itself" "$M" nz
t "main checkout: still a repo with its handoff" sh -c 'test -d "$1/.git" && grep -q MAIN-BRIEF-MARKER "$1/.claude/handoff/BRIEF.md"' _ "$M"
mkdir -p "$M/.claude/worktrees/notwt/$H"; printf 'NOT-A-WORKTREE\n' > "$M/.claude/worktrees/notwt/$H/BRIEF.md"
refused "a folder that is not a worktree" "$M/.claude/worktrees/notwt" nz
refused "a path that doesn't exist" "$M/.claude/worktrees/nothere" nz
snap "$W/b"; run "$M" "$WT" remove; snap "$W/a"
t "remove with no path: exit 2" test "$(rc)" = 2
t "remove with no path: nothing moves" cmp -s "$W/b" "$W/a"

# 7. Run from inside another worktree: the archive still goes to the main checkout.
mkwt change/w w; Wd=$P; mkwt change/z z; Z=$P
run "$Wd" "$WT" remove "$Z"
t "z removed from inside w: exits 0" test "$(rc)" = 0
t "z: the worktree directory is gone" test ! -e "$Z"
t "z: archived in the main checkout's archive" eval 'grep -q "BRIEF-MARKER change/z" "$M/$H/archive/"*-z/BRIEF.md'
t "z: nothing archived in w" test ! -e "$Wd/$H/archive"

# 8. The same slug twice in a day (fix/r after change/r): the first archive is never overwritten,
#    and no handoff file of the second is lost.
if [ -e "$M/.claude/worktrees/r" ] || [ ! -d "$A" ]; then
  ok 1 "same slug twice: not run, section 1 left no archive or didn't remove r"
else
  (cd "$A" && for f in *.md; do cksum "$f"; done) > "$W/arch.before"
  mkwt fix/r r; R2=$P
  run "$M" "$WT" remove "$R2"; echo "info: second r: exit $(rc): $(head -n 1 "$W/err")"
  (cd "$A" && for f in *.md; do cksum "$f"; done) > "$W/arch.after"
  t "same slug twice: the earlier archive is unchanged" cmp -s "$W/arch.before" "$W/arch.after"
  t "same slug twice: fix/r's brief is either still in its worktree or in an archive" \
    eval 'grep -q "BRIEF-MARKER fix/r" "$R2/$H/BRIEF.md" 2>/dev/null || grep -rqs "BRIEF-MARKER fix/r" "$M/$H/archive"'
  t "same slug twice: branch fix/r still exists" git -C "$M" rev-parse -q --verify refs/heads/fix/r
fi

# 9. `git worktree remove` refuses after every check of remove's own has passed (Claudia's Should
#    Fix, made Must Fix by Ada, 2026-10-28): nothing may be left half done. A stand-in git, first on
#    the PATH, refuses `git worktree remove` and runs every other command as the real git. Afterwards
#    the worktree and its handoff files are intact, no archive is left, and a retry succeeds.
REALGIT=$(command -v git)
mkdir -p "$W/shim"
cat > "$W/shim/git" <<EOF
#!/bin/sh
prev=
for a in "\$@"; do
  if [ "\$prev" = worktree ] && [ "\$a" = remove ]; then echo "fatal: stand-in git: worktree remove refused" >&2; exit 1; fi
  prev=\$a
done
exec "$REALGIT" "\$@"
EOF
chmod +x "$W/shim/git"
mkwt change/f f; F=$P
mkdir -p "$W/saved-f" && cp "$F/$H/"*.md "$W/saved-f/"
snap "$W/b"
(cd "$M" && PATH="$W/shim:$PATH" $SH "$WT" remove "$F") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"
echo "info: remove with git worktree remove refused: exit $(rc): $(head -n 2 "$W/err" | tr '\n' ' ')"
t "git worktree remove refused: remove exits non-zero" test "$(rc)" != 0
t "git worktree remove refused: the worktree is still there and listed" sh -c 'test -d "$1"' _ "$F"
listed f && ok 0 "git worktree remove refused: git still lists it" || ok 1 "git worktree remove refused: git still lists it"
for f in "$W/saved-f/"*.md; do t "git worktree remove refused: ${f##*/} is still in the worktree, unchanged" cmp -s "$f" "$F/$H/${f##*/}"; done
t "git worktree remove refused: no archive folder left for f" test -z "$(ls -d "$M/$H/archive/"*-f 2>/dev/null)"
snap "$W/a"; t "git worktree remove refused: nothing under the main checkout changed" cmp -s "$W/b" "$W/a"
t "git worktree remove refused: branch change/f still exists" git -C "$M" rev-parse -q --verify refs/heads/change/f
run "$M" "$WT" remove "$F"
echo "info: retry: exit $(rc): $(tr '\n' ' ' < "$W/out")"
t "retry without the stand-in: exits 0" test "$(rc)" = 0
t "retry: the worktree is gone" test ! -e "$F"
AF=$(ls -d "$M/$H/archive/"*-f 2>/dev/null | head -n 1)
t "retry: archived in the main checkout's archive/<date>-f/" test -n "$AF" -a -d "$AF"
for f in "$W/saved-f/"*.md; do t "retry: archived ${f##*/} is the worktree's, byte for byte" cmp -s "$f" "$AF/${f##*/}"; done

echo "$fails failed"
if [ "$fails" = 0 ]; then rm -rf "$W"; exit 0; else echo "scratch kept at $W"; exit 1; fi
```

## Expected

Every line `PASS`, last line `0 failed`, exit 0, in every run. The first break in step 3 (status test
removed and `--force` added) prints `FAIL` for sections 3 to 5; the second, for section 9. The `info:` lines record each refusal's exit code and message; put them in
the Run record.

Sources: the brief's Decisions (`ada-worktree remove <path>` moves that worktree's
`.claude/handoff/*.md` to the main checkout's `.claude/handoff/archive/<date>-<slug>/`, then runs
`git worktree remove`; refuses if the worktree has uncommitted tracked or untracked, not ignored,
changes; never deletes the branch) and Definition of Done 5 and 11. Exit 2 for its own refusals:
`SYSTEM.md`, Known gotchas. Readings the brief doesn't spell out, raised with Ada in
`TEST-PLAN.md`: `<date>` is `YYYY-MM-DD`; the main checkout, a folder that isn't a worktree, or a
missing path are refused with nothing moved (section 6); a second archive of the same slug on
the same day never overwrites the first (section 8). Section 9 is `REVIEW-FEEDBACK.md`'s Should
Fix, promoted to Must Fix by Ada on 2026-10-28: if `git worktree remove` refuses after the
pre-checks pass, the worktree, its handoff files and the main checkout are as they were and a
retry works (the brief's "never deletes", P20).

## Observe at

The script's output and exit code. On a failure the scratch folder is kept and named: look at
`git -C "<scratch>/scratch repo" worktree list`, `git branch`, and
`ls -R "<scratch>/scratch repo/.claude/handoff/archive"`.

## Regression

The main checkout's own handoff files, `local.env` and `git status` are untouched by every
removal (section 1). Branches are never deleted (sections 1, 2, 8).
