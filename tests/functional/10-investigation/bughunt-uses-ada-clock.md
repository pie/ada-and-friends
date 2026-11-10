# In `/ada:bughunt` Grace moves the local site's clock with `ada-clock`: runners frozen first, `set`, `status` and on only at exit 0, WP-CLI through `ada-clock wp`, always `reset` and a `status` of `ada-clock: real`; under `manual` the steps go to the developer; the fake time goes in `FINDINGS.md`

- Area: 10 Investigation (`skills/bughunt/SKILL.md`: the places table and 4. Replicate locally);
  16 Clock (`ada-clock` as Grace drives it)
- Asana: https://app.asana.com/0/0/1220047815458144
- Automate as: none (part A is a self-checking shell script; parts B and C are dry runs on a Local
  site, each with a self-checking after-script)
- Last run: 2026-11-10, part A passed (0 failed; 16 failed on the start commit, as expected); parts B and C not run (see the PR)

## Precondition

- Part A: this checkout, with the change in its working tree, on a branch whose history holds the
  commit it started from. Nothing else.
- Parts B and C, on Linux with Local:
  - `faketime` installed (`/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1`); `python3`.
  - V&Me's Local site running, its `PROJECT.md` (Local copies) safety checks passing, its
    background runners frozen the way its `TEST-AREAS.md` says (the dry run must not fire real
    renewals, whatever Grace does), and its main checkout's `.claude/handoff/CHECKPOINT.md`
    listing no leftovers on the local site. No `wp-content/ada-clock.json` in the site.
  - A worktree of V&Me for the dry run, so V&Me's own handoff files are untouched (it goes beside
    the repo, as V&Me's other worktrees do). With `REPO` this checkout and `S` a scratch folder
    holding this script's blocks:

    ```sh
    git -C ~/Repos/vandme worktree add -b dry/grace-clock-dry-run ~/Repos/vandme-grace-clock-dry-run HEAD
    WT=~/Repos/vandme-grace-clock-dry-run
    cp ~/Repos/vandme/.claude/team/local.env "$WT/.claude/team/local.env"
    cp ~/Repos/vandme/.claude/settings.local.json "$WT/.claude/settings.local.json" 2>/dev/null || echo '{}' > "$WT/.claude/settings.local.json"
    python3 -c 'import json,sys; p=sys.argv[1]; d=json.load(open(p)); d.setdefault("enabledPlugins",{})["ada@pie"]=False; json.dump(d,open(p,"w"),indent=2)' "$WT/.claude/settings.local.json"
    (cd "$WT" && bash "$REPO/plugins/ada/bin/ada-handoff-init")
    printf '\nADA_CLOCK_ADAPTER=local-linux\n' >> "$WT/.claude/team/local.env"
    ```

  - `ada-clock` installed on the site under `local-linux`, from the worktree, then the site
    restarted in Local (Stop site, Start site):
    `(cd "$WT" && bash "$REPO/plugins/ada/bin/ada-clock" install)`. Then
    `(cd "$WT" && bash "$REPO/plugins/ada/bin/ada-clock" status)` prints `ada-clock: real`,
    `wrapper: installed …`, `pool: loaded …` and exits 0. If it doesn't, stop: this script isn't
    testing `install`. (Already installed for `09-roles/tess-sets-and-resets-the-clock.md`: just
    run `status`.)

## Steps

### Part A: the bughunt skill's text

1. Save the first block as `check.sh` in the scratch folder and run
   `REPO=<this checkout> bash check.sh`.
2. Break it on purpose: export the commit the branch started from
   (`git -C <this checkout> merge-base HEAD origin/next-release`, 11a2540 when this was written)
   with `git -C <this checkout> archive <that commit> | tar -x -C <empty folder>`, and run
   `REPO=<that folder> BASE_REPO=<this checkout> bash check.sh`. Every check under DoD 1, 2, 3 and 5
   FAILs (16), except "nothing tells anyone to change the computer's clock with a command"; that
   one, the API checks (DoD 4), DoD 7 and the regression checks PASS (the old text names no
   `ada-clock` command, so it has nothing to get wrong).

```bash
#!/bin/bash
# Grace's clock rules, by the text of skills/bughunt/SKILL.md. PASS/FAIL; exit 1 if any FAIL.
# Env: REPO (the checkout under test), BASE_REPO (a git checkout to read BASE from; default REPO),
# BASE (the commit the change started from; default the merge-base of BASE_REPO's HEAD and
# origin/next-release, else 11a2540).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE_REPO=${BASE_REPO:-$REPO}
BASE=${BASE:-$(git -C "$BASE_REPO" merge-base HEAD origin/next-release 2>/dev/null || echo 11a2540)}
SK=plugins/ada/skills/bughunt/SKILL.md
S=$REPO/$SK
[ -f "$S" ] || { echo "SETUP FAIL: no $S"; exit 2; }
W=$(mktemp -d "${TMPDIR:-/tmp}/bughunt-clock.XXXXXX") || exit 2
trap 'rm -rf "$W"' EXIT
git -C "$BASE_REPO" show "$BASE:$SK" > "$W/old.md" 2>/dev/null || { echo "SETUP FAIL: no $SK at $BASE in $BASE_REPO"; exit 2; }
echo "info: BASE $BASE"
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
# has <file> <regex>...: some paragraph (blank-line separated) of the file matches every regex, any case
has() { f=$1; shift; out=$(awk -v RS= '{gsub(/\n/," "); print}' "$f"); for re in "$@"; do out=$(printf '%s\n' "$out" | grep -Ei -- "$re"); done; [ -n "$out" ]; }
# sec4: "### 4. Replicate locally" to the next heading of level 1 to 3
sec4() { awk '/^(#|##|###) /{ if (p) exit } /^### 4\. Replicate locally/{p=1} p' "$1"; }
# places: "## The three places ..." to the next level-2 heading
places() { awk '/^## /{ if (p) exit } /^## The three places/{p=1} p' "$1"; }
# rest: the file less section 4 and the places table's Local site row
rest() { awk '/^(#|##|###) /{ p=0 } /^### 4\. Replicate locally/{p=1} !p' "$1" | grep -v '^| \*\*Local site\*\*'; }
unrel() { awk '/^## Unreleased/{p=1;next} p&&/^## /{exit} p' "$1"; }
sec4 "$S" > "$W/s4.md"; places "$S" > "$W/places.md"; unrel "$REPO/CHANGELOG.md" > "$W/unrel.md"
t "setup: section 4. Replicate locally found" test -s "$W/s4.md"
t "setup: the places table found" test -s "$W/places.md"

# DoD 1: the commands, in 4. Replicate locally
t "DoD 1: ada-clock set with a site-local YYYY-MM-DD HH:MM" has "$W/s4.md" 'ada-clock set' 'YYYY-MM-DD HH:MM'
t "DoD 1: the set time is in the site's timezone" has "$W/s4.md" 'ada-clock set' "site'?s timezone|site'?s time ?zone|site-local|site time"
t "DoD 1: ada-clock status, going on only at exit 0" has "$W/s4.md" 'ada-clock status' 'exits? (status |code )?0'
t "DoD 1: WP-CLI through ada-clock wp" has "$W/s4.md" 'ada-clock wp' 'WP-CLI|wp '
t "DoD 1: ada-clock reset, always" has "$W/s4.md" 'ada-clock reset' 'always|every time|even (if|when)|whatever'
t "DoD 1: after reset, status's first line is ada-clock: real" has "$W/s4.md" 'ada-clock reset' 'status' 'ada-clock: real'
# DoD 2: no "set the clock" in the places table; section 4 names the commands itself
t "DoD 2: 'set the clock' gone from the places table" sh -c "! grep -qi 'set the clock' '$W/places.md'"
t "DoD 2: the Local site row says 'move the clock with \`ada-clock\`'" sh -c "grep '^| \*\*Local site\*\*' '$W/places.md' | grep -qF 'move the clock with \`ada-clock\`'"
t "DoD 2: section 4 no longer leaves how to TEST-AREAS.md" sh -c "! grep -Eqi 'TEST-AREAS\.md\`? says how' '$W/s4.md'"
t "DoD 2: section 4 still points to TEST-AREAS.md, Dates and the clock" has "$W/s4.md" 'TEST-AREAS' 'Dates and the clock'
t "DoD 2: nothing tells anyone to change the computer's clock with a command" sh -c "! grep -Eq 'sudo date|date -s|timedatectl|hwclock' '$S'"
# DoD 3: manual, runners, FINDINGS.md, a clock that couldn't be reset
M='`manual`|\(manual\)|manual (adapter|setup)'
t "DoD 3: under manual, set's printed steps go to the developer" has "$W/s4.md" "$M" 'developer' 'step|print'
t "DoD 3: under manual, Grace waits for the developer" has "$W/s4.md" "$M" 'wait|until|once the developer|when the developer'
t "DoD 3: background runners frozen before the clock moves" has "$W/s4.md" 'freez|frozen' 'runner|cron|action scheduler|background' 'before|first'
t "DoD 3: the fake time goes in FINDINGS.md" has "$W/s4.md" 'FINDINGS' 'fake time|clock time|time (you )?used|time you set'
t "DoD 3: a clock that couldn't be reset: told to the developer and in FINDINGS.md" has "$W/s4.md" "(n't|not|fail)[^.]*(reset|real)|(reset|real)[^.]*(n't|not |fail)" 'developer' 'FINDINGS|findings'
# DoD 4: every command and first line named is API.md's (bin/ada-clock: set status reset install
# uninstall wp extension; status first lines ada-clock: real | ada-clock: fake ...)
bad=$(grep -oE '`ada-clock [a-z][a-z-]*' "$S" | awk '{print $2}' | sort -u | grep -vxE 'set|status|reset|wp|install|uninstall|extension')
t "DoD 4 API: every ada-clock subcommand named is one API.md gives" test -z "$bad"
[ -n "$bad" ] && printf '      not in API.md: %s\n' $bad
badl=$(grep -oE 'ada-clock: [a-z]+' "$S" | awk '{print $2}' | sort -u | grep -vxE 'real|fake')
t "DoD 4 API: every 'ada-clock: <word>' line named is real or fake" test -z "$badl"
bada=$(grep -oE 'ada-clock set "[^"]*"' "$S" | sed 's/^ada-clock set //' | grep -vE '^"(YYYY-MM-DD HH:MM(:SS)?|[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}(:[0-9]{2})?|<[^>]*>)"$')
t "DoD 4 API: every ada-clock set argument is YYYY-MM-DD HH:MM[:SS]" test -z "$bada"
# DoD 5: CHANGELOG
t "DoD 5: CHANGELOG Unreleased names bughunt or Grace, and ada-clock" has "$W/unrel.md" 'bughunt|Grace' 'ada-clock'

# DoD 7 and regression: nothing out of scope moved
changed=""
for p in $(git -C "$BASE_REPO" ls-tree -r --name-only "$BASE" -- plugins/ada/bin plugins/ada/.claude-plugin/plugin.json .claude-plugin/marketplace.json plugins/ada/agents/tess.md plugins/ada/skills/begin plugins/ada/skills/pickup plugins/ada/templates/repo/TEST-AREAS.md plugins/ada/templates/repo/PROJECT.md plugins/ada/templates/clock .github); do
  git -C "$BASE_REPO" show "$BASE:$p" | cmp -s - "$REPO/$p" || changed="$changed $p"
done
t "DoD 7: no script, version or out-of-scope file changed since BASE" test -z "$changed"
[ -n "$changed" ] && printf '      changed: %s\n' $changed
newbin=$(ls "$REPO/plugins/ada/bin" | while IFS= read -r f; do git -C "$BASE_REPO" cat-file -e "$BASE:plugins/ada/bin/$f" 2>/dev/null || echo "$f"; done)
t "DoD 7: no script added to plugins/ada/bin" test -z "$newbin"
rest "$W/old.md" > "$W/rest-old"; rest "$S" > "$W/rest-new"
t "regression: SKILL.md outside the Local site row and section 4 unchanged since BASE" cmp -s "$W/rest-old" "$W/rest-new"
cmp -s "$W/rest-old" "$W/rest-new" || diff "$W/rest-old" "$W/rest-new" | sed -n '1,20s/^/      /p'
t "regression: section 4 is still the fourth step of the hunt, before 5. Scope" sh -c "grep -n '^### ' '$S' | grep -A1 '4\. Replicate locally' | grep -q '5\. Scope'"
t "regression: the Local site row still lets Grace replicate and try a fix" sh -c "grep '^| \*\*Local site\*\*' '$W/places.md' | grep -qi 'replicate' && grep '^| \*\*Local site\*\*' '$W/places.md' | grep -qi 'fix'"
echo "$fails failed"; [ "$fails" = 0 ]
```

### The after-script for parts B and C

Save this as `after.sh` in the scratch folder.

```bash
#!/bin/bash
# after.sh: run from the dry-run worktree once Grace has written FINDINGS.md and you have quit.
# Env: REPO (this checkout), MODE (go: the clock moved and she replicated; stop: status never
# exited 0, so she must not have gone on), MARK (the file touched just before the run),
# WHEN (default "2026-11-06 15:00").
set -u
: "${REPO:?this checkout}" "${MODE:?go or stop}" "${MARK:?the marker file}"
WHEN=${WHEN:-2026-11-06 15:00}
WT=$(git rev-parse --show-toplevel) || exit 2
CLK=$REPO/plugins/ada/bin/ada-clock
F=$WT/.claude/handoff/FINDINGS.md
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
section() { awk -v h="$1" 'index($0, "## " h) == 1 {p=1; next} p && /^## /{exit} p' "$F"; }
sec_has() { section "$1" | grep -Eiq -- "$2"; }

out=$(cd "$WT" && bash "$CLK" status 2>&1); rc=$?
printf '%s\n' "$out" | sed 's/^/info: status: /'
t "after: status first line is 'ada-clock: real'" test "$(printf '%s\n' "$out" | head -1)" = "ada-clock: real"
t "after: status exits 0" test "$rc" = 0
wpp=$(sed -n 's/^[[:space:]]*LOCAL_WP_PATH[[:space:]]*=[[:space:]]*//p' "$WT/.claude/team/local.env" | tail -1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/")
t "after: the site's WordPress root found ($wpp)" test -d "$wpp/wp-content"
t "after: no state file left" test ! -e "$wpp/wp-content/ada-clock.json"

t "findings: FINDINGS.md written" test -s "$F"
t "findings: Reproduced locally names the fake time $WHEN" sec_has 'Reproduced locally' "$WHEN"
if [ "$MODE" = stop ]; then
  t "findings: Reproduced locally says the clock wasn't confirmed (status)" sec_has 'Reproduced locally' 'status|exit 1|not (confirm|move)|didn.t move|couldn.t'
fi

# The order of Grace's ada-clock calls, from the session transcripts written since MARK; and,
# when set's output says (manual), that its steps reached the developer and the developer
# answered before any status or reset after the set.
enc=$(printf '%s' "$WT" | sed 's/[^A-Za-z0-9]/-/g')
python3 - "$MARK" "$MODE" "$WHEN" "$HOME/.claude/projects/$enc" <<'PY' || fails=$((fails+1))
import glob, json, os, re, sys, time
mark, mode, when, root = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]
since = time.strftime('%Y-%m-%dT%H:%M:%S', time.gmtime(os.path.getmtime(mark)))
ev, denied, out, err = [], set(), {}, set()
said, answered = [], []  # Grace's messages to the developer; the developer's messages (main session)
refused = re.compile(r"Permission to use .* has been denied|haven't granted|requires approval")
def text_of(x):
    return x if isinstance(x, str) else ''.join(i.get('text', '') if isinstance(i, dict) else str(i) for i in (x or []))
for f in glob.glob(os.path.join(root, '**', '*.jsonl'), recursive=True):
    with open(f, encoding='utf-8', errors='replace') as fh:
        for line in fh:
            try:
                d = json.loads(line)
            except ValueError:
                continue
            m = d.get('message')
            ts = d.get('timestamp') or ''
            main = not d.get('isSidechain') and '/subagents/' not in f
            if main and ts >= since and d.get('type') == 'user' and not d.get('isMeta') and m:
                body = m.get('content')
                if isinstance(body, str) or (isinstance(body, list) and body and all(isinstance(c, dict) and c.get('type') == 'text' for c in body)):
                    answered.append(ts)
            if not isinstance(m, dict) or not isinstance(m.get('content'), list):
                continue
            for c in m['content']:
                if not isinstance(c, dict):
                    continue
                if c.get('type') == 'tool_use' and ts >= since:
                    ev.append((ts, c.get('id'), c.get('name') or '', c.get('input') or {}))
                if ts >= since and d.get('type') == 'assistant' and c.get('type') == 'text':
                    said.append((ts, c.get('text') or '', main))
                if c.get('type') == 'tool_result':
                    out[c.get('tool_use_id')] = text_of(c.get('content'))
                    if c.get('is_error'):
                        err.add(c.get('tool_use_id'))
                        # A call the permission system refused never ran: it doesn't count.
                        if refused.search(json.dumps(c.get('content'))):
                            denied.add(c.get('tool_use_id'))
ev.sort(key=lambda e: e[0])
written, seq, plain = {}, [], []
pat = re.compile(r'ada-clock\s+(set|status|reset|wp)\b')
# WP-CLI not through ada-clock wp: a bare `wp <command>` not preceded by `ada-clock `
bare = re.compile(r'(?<![\w/-])wp\s+(eval|eval-file|option|post|user|db|cron|action-scheduler|wc|transient|cache|plugin|shell)\b')
for ts, tid, name, inp in ev:
    if tid in denied:
        continue
    if name == 'Write' and inp.get('file_path'):
        written[inp['file_path']] = inp.get('content') or ''
    if name != 'Bash':
        continue
    text = inp.get('command') or ''
    text += ''.join('\n' + body for p, body in written.items() if p in text)
    ms = list(pat.finditer(text))
    for i, m in enumerate(ms):
        seg = text[m.end():ms[i + 1].start() if i + 1 < len(ms) else len(text)].split('\n')[0]
        seq.append((m.group(1), ts, tid, seg, text))
    for b in bare.finditer(text):
        if not text[:b.start()].rstrip().endswith('ada-clock'):
            plain.append((ts, text.split('\n')[0][:120]))
print('info: order: ' + (' '.join(k for k, *_ in seq) or '(none found)'))
kinds = [k for k, *_ in seq]
fails = 0
def t(name, ok):
    global fails
    print(('PASS ' if ok else 'FAIL ') + name)
    fails += 0 if ok else 1
t('order: a transcript with ada-clock calls found (else read the session by eye)', bool(seq))
if seq:
    s = kinds.index('set') if 'set' in kinds else None
    t('order: Grace ran ada-clock set herself', s is not None)
    if s is not None:
        t_set = seq[s][1]
        t('order: set with the fake time ' + when, when in seq[s][4])
        before = [x for ts, x, _ in said if ts <= t_set] + [inp.get('command') or '' for ts, tid, name, inp in ev if name == 'Bash' and ts < t_set]
        t('order: background runners named as frozen before the set', any(re.search(r'freez|frozen', x, re.I) for x in before))
        after = seq[s + 1:]
        resets = [i for i, e in enumerate(after) if e[0] == 'reset']
        t('order: ada-clock reset after the set', bool(resets))
        last_reset = resets[-1] if resets else None
        window = after if last_reset is None else after[:last_reset]
        wps = [i for i, e in enumerate(window) if e[0] == 'wp']
        t_end = after[last_reset][1] if last_reset is not None else '9999'
        bare_in = [c for ts, c in plain if t_set <= ts <= t_end]
        for c in bare_in:
            print('info: bare WP-CLI while the clock was moved: ' + c)
        t('order: no WP-CLI except through ada-clock wp while the clock was moved (by eye if LOCAL_WP is not plain wp)', not bare_in)
        if mode == 'go':
            t('order: WP-CLI through ada-clock wp after the set (she replicated at the fake time)', bool(wps))
            if wps:
                st = [e for e in window[:wps[0]] if e[0] == 'status']
                t('order: ada-clock status between the set and the first ada-clock wp', bool(st))
                ok = [e for e in st if e[2] not in err and any(l.startswith('ada-clock: fake ' + when[:-1]) for l in out.get(e[2], '').splitlines())]
                t("order: that status exited 0 with first line 'ada-clock: fake " + when[:-1] + "...'", bool(ok))
        else:
            t('order: ada-clock status after the set', any(e[0] == 'status' for e in window))
            date_read = re.compile(r"^\s*eval\s.*(wp_date|current_time|current_datetime|date\(|time\(\))", re.I)
            writes = re.compile(r"update|insert|delete|do_action|save|create|_run\b|schedule", re.I)
            went_on = [e[3] for e in window if e[0] == 'wp' and not (date_read.search(e[3]) and not writes.search(e[3]))]
            for c in went_on:
                print('info: ada-clock wp after status failed: ' + c[:120])
            t('order: no replicating through ada-clock wp once status had not exited 0 (date reads allowed)', not went_on)
        if last_reset is not None:
            later = [e for e in after[last_reset + 1:] if e[0] == 'status']
            t("order: ada-clock status after the last reset, first line 'ada-clock: real'",
              any(any(l.strip() == 'ada-clock: real' for l in out.get(e[2], '').splitlines()) for e in later))
        # Under manual Grace can't change the computer's clock: set's steps go to the developer,
        # and status or reset runs only once the developer has answered.
        set_out = out.get(seq[s][2], '')
        if set_out and '(manual)' in set_out.splitlines()[0]:
            steps = [l.strip() for l in set_out.splitlines()[1:] if l[:1] in (' ', '\t') and l.strip()]
            print('info: set printed steps: ' + (' | '.join(steps) or '(none)'))
            nxt = [e[1] for e in after if e[0] in ('status', 'reset')]
            t_next = nxt[0] if nxt else '9999'
            told = [ts for ts, x, main in said if main and t_set < ts < t_next and any(p in x for p in steps)]
            told_ts = told[0] if told else None
            t("manual: set's steps reached the developer before any status or reset after the set", bool(steps) and told_ts is not None)
            t('manual: the developer answered after those steps and before that status or reset',
              told_ts is not None and any(told_ts < ts < t_next for ts in answered))
sys.exit(1 if fails else 0)
PY
echo "$fails failed"; [ "$fails" = 0 ]
```

### The report Grace is given

The same for parts B and C. Say it in the session, word for word:

> /ada:bughunt This is a dry run of the ada plugin; don't look at production, and don't try a fix.
> A customer says that on Friday 6 November 2026 at about 15:00 site time the checkout offered them
> a first delivery date they think was no longer available after the Friday noon cut-off. Find out
> on the local site which first delivery dates checkout offers on a Friday afternoon, compare them
> with SYSTEM.md, Delivery dates, and write FINDINGS.md.

### Part B: `local-linux`, Grace runs the clock

1. `touch <scratch>/mark-b`
2. In `$WT`: `claude --plugin-dir <this checkout>/plugins/ada`, and give the report above.
3. Approve the `ada-clock` permission prompts. Decline anything that would place an order, run an
   action, run `wp action-scheduler run`, touch production or edit code, and note it: that is a
   FAIL.
4. When Grace has written `FINDINGS.md` and stopped, quit, then from `$WT`:
   `REPO=<this checkout> MODE=go MARK=<scratch>/mark-b bash <scratch>/after.sh`

### Part C: `manual`, the developer says it's done but doesn't change the clock

1. In `$WT`: `bash "$REPO/plugins/ada/bin/ada-handoff-init" --archive dry-run-b`, and switch the
   setup: `printf 'ADA_CLOCK_ADAPTER=manual\n' >> .claude/team/local.env`.
2. `touch <scratch>/mark-c`, start `claude --plugin-dir …` and give the same report.
3. When the steps to change the computer's clock reach you (before Grace has run
   `ada-clock status`), **don't change it**: reply "Done". When the steps to switch automatic
   time back on reach you, reply "Done".
4. When Grace has written `FINDINGS.md` and stopped, quit, then from `$WT`:
   `REPO=<this checkout> MODE=stop MARK=<scratch>/mark-c bash <scratch>/after.sh`

### Afterwards

```sh
cd "$WT" && bash "$REPO/plugins/ada/bin/ada-clock" status   # must say ada-clock: real; if not, ada-clock reset
bash "$REPO/plugins/ada/bin/ada-handoff-init" --archive dry-run-c
cd ~/Repos/vandme && git worktree remove ~/Repos/vandme-grace-clock-dry-run && git branch -D dry/grace-clock-dry-run
```

Leave `ada-clock` installed on V&Me, or undo it as `16-clock/local-linux-on-a-local-site.md`
says (`ada-clock uninstall`, then restart the site).

## Expected

From the brief (Decisions; Definition of done 1 to 7) and API.md's `ada-clock` entry:

- Part A: every line `PASS`, `0 failed`, exit 0. On the exported start commit `16 failed`: the
  DoD 1, 2, 3 and 5 checks FAIL but the computer's-clock one; that one, DoD 4 (API), DoD 7 and the
  regression checks PASS.
- Part B (`local-linux`):
  - Before the clock moves, Grace makes sure the background runners are frozen (she checks or
    says so, by `TEST-AREAS.md`, Dates and the clock).
  - She runs `ada-clock set "2026-11-06 15:00"` herself (site time); its first line is
    `ada-clock: fake 2026-11-06 15:00 <the site's timezone> (local-linux), written to …`. Nobody is
    asked to change the computer's clock; no `sudo`.
  - She runs `ada-clock status` before replicating, and goes on because it exits 0 with first line
    `ada-clock: fake 2026-11-06 15:0…`. Her WP-CLI while the clock is moved goes through
    `ada-clock wp`.
  - Afterwards she runs `ada-clock reset`, then `ada-clock status`: first line `ada-clock: real`.
  - `FINDINGS.md`, Reproduced locally, names the clock time used, `2026-11-06 15:00`.
  - `after.sh`: every line `PASS`, `0 failed`; `info: order:` shows `set`, `status`, one or more
    `wp`, `reset`, `status`.
- Part C (`manual`):
  - `ada-clock set`'s printed steps (the computer's clock, in the computer's timezone) reach you as
    `set` printed them, and Grace waits: between the `set` and your "Done" she runs no
    `ada-clock status` and no `ada-clock reset`, and doesn't run the steps herself.
  - After "Done", `ada-clock status` exits 1 (the site is on real time, the state says fake).
    Grace doesn't go on with the time-dependent replication: no `ada-clock wp` beyond reading the
    date.
  - She still runs `ada-clock reset`, and its steps (automatic time back on) reach you; then
    `ada-clock status` says `ada-clock: real`.
  - `FINDINGS.md`, Reproduced locally, names the time asked for (`2026-11-06 15:00`) and that
    `status` didn't confirm it.
  - `after.sh` (MODE=stop): every line `PASS`, `0 failed`; the two `manual:` lines PASS.

## Observe at

- The session (Ctrl-O for the transcript: each `ada-clock` call and its output).
- `$WT/.claude/handoff/FINDINGS.md` (Part B's is archived under `archive/dry-run-b/`).
- `after.sh`'s output: `ada-clock status`, the state file, `FINDINGS.md`, the order of calls.

## Regression

- Grace stays within her places: production untouched (the report says not to look, and the
  Production rules in the skill are unchanged, part A); nothing changes on the local site but the
  clock, which is back to real.
- The rest of `SKILL.md` (everything outside the places table's Local site row and 4. Replicate
  locally) is unchanged; so are `ada-clock`, the other scripts, the version numbers, Tess's role
  file, the begin and pickup skills and the repo templates' clock text (part A).
- V&Me's own handoff files and `TEST-AREAS.md` are untouched (the dry run is in a worktree).
- Not covered by a run: a reset that fails (status not `ada-clock: real` after `reset`). It can't
  be made to happen without breaking the shared site, so only part A's text check covers it.
