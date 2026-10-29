# In run mode Tess sets the local site's clock with `ada-clock`, checks it with `status` before each date check, resets it at cleanup and records the fake time; under `manual` the steps go to the developer, and she doesn't go on unless `status` exits 0

- Area: 09 Roles (`agents/tess.md`, Run mode and Running local checks yourself); 16 Clock
  (`ada-clock` as Tess drives it)
- Asana: https://app.asana.com/1/100438094841948/project/100438094841951/task/1219984586830313
- Automate as: none (part A is a self-checking shell script; parts B and C are dry runs on a Local
  site, each with a self-checking after-script)
- Last run: 29 Oct 2026, part A 0 failed (12 PASS); part B headless as expected (after.sh 0 failed); part C headless as expected after one extra developer answer (after.sh 0 failed, on her report text) (see the PR)

## Precondition

- Part A: this checkout, with the change in its working tree. Nothing else.
- Parts B and C, on Linux with Local:
  - `faketime` installed (`/usr/lib/x86_64-linux-gnu/faketime/libfaketime.so.1`); `python3`.
  - V&Me's Local site running, its `PROJECT.md` (Local copies) safety checks passing, its
    background runners frozen the way its `TEST-AREAS.md` says, and its main checkout's
    `.claude/handoff/CHECKPOINT.md` listing no leftovers on the local site. No
    `wp-content/ada-clock.json` in the site.
  - A worktree of V&Me for the dry run, so V&Me's own handoff files are untouched (V&Me doesn't
    ignore `.claude/worktrees/`, so it goes beside the repo, as its other worktrees do). With
    `REPO` this checkout and `S` a scratch folder holding this script's blocks:

    ```sh
    git -C ~/Repos/vandme worktree add -b dry/tess-clock-dry-run ~/Repos/vandme-tess-clock-dry-run HEAD
    WT=~/Repos/vandme-tess-clock-dry-run
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
    testing `install`.
  - The four handoff files below written into `$WT/.claude/handoff/`, replacing the fresh ones.

## Steps

### Part A: Tess's role file

1. Save the first block as `check.sh` in the scratch folder and run `REPO=<this checkout> bash check.sh`.
2. Break it on purpose: run it with `REPO=` an export of ff93d6a
   (`git -C <this checkout> archive ff93d6a | tar -x -C <empty folder>`) and `BASE_REPO=<this
   checkout>`. The `ada-clock` checks FAIL; the regression checks PASS.

```bash
#!/bin/bash
# Tess's run-mode clock rules, by the text of agents/tess.md. PASS/FAIL; exit 1 if any FAIL.
# Env: REPO (the checkout under test), BASE_REPO (where to export BASE from; default REPO),
# BASE (default ff93d6a).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
BASE_REPO=${BASE_REPO:-$REPO}; BASE=${BASE:-ff93d6a}
T=$REPO/plugins/ada/agents/tess.md
[ -f "$T" ] || { echo "SETUP FAIL: no $T"; exit 2; }
W=$(mktemp -d "${TMPDIR:-/tmp}/tess-clock.XXXXXX") || exit 2
git -C "$BASE_REPO" show "$BASE:plugins/ada/agents/tess.md" > "$W/old.md" || { echo "SETUP FAIL: no $BASE"; exit 2; }
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
# has [-c] <regex>...: some paragraph (blank-line separated) of tess.md matches every regex;
# any case, or exact case with -c
has() { o=-Ei; [ "$1" = -c ] && { o=-E; shift; }
  out=$(awk -v RS= '{gsub(/\n/," "); print}' "$T"); for re in "$@"; do out=$(printf '%s\n' "$out" | grep $o -- "$re"); done; [ -n "$out" ]; }
never() { awk '/^## Never/{p=1;next} p&&/^## /{exit} p&&NF' "$1"; }

t "she may run ada-clock set" has 'ada-clock set'
t "ada-clock status before a date check, going on only on exit 0" has 'ada-clock status' 'exit 0' 'date check|each check|before'
t "ada-clock reset is on the cleanup list, always" has 'ada-clock reset' 'clean'
t "the fake time goes in the Run record" has 'Run record' 'fake|ada-clock set'
t "under manual, set's steps go to the developer" has '`manual`|\(manual\)' 'developer' 'ada-clock'
t "rule kept: freeze the runners first" has 'ada-clock|date check' 'freez|frozen'
t "rule kept: actions one at a time by ID" has -c '[Oo]ne at a time' '[ (`]IDs?[ ,.;:)`]'
t "rule kept: never wp action-scheduler run" has "(never|not|n't)[^.]*wp action-scheduler run"
t "rule kept: payment tests on real time" has 'payment[^.]*real (time|clock)|real (time|clock)[^.]*payment'
t "no sudo date" sh -c "! grep -q 'sudo date' '$T'"
# Regression: nothing taken out of the Never list or the tools line
missing=$(never "$W/old.md" | while IFS= read -r l; do grep -Fxq -- "$l" "$T" || echo "$l"; done)
t "regression: every Never line of $BASE still there" test -z "$missing"
[ -n "$missing" ] && printf '      missing: %s\n' "$missing"
t "regression: the tools: line unchanged" test "$(grep -m1 '^tools:' "$T")" = "$(grep -m1 '^tools:' "$W/old.md")"
rm -rf "$W"
echo "$fails failed"; [ "$fails" = 0 ]
```

### The handoff files for parts B and C

`$WT/.claude/handoff/BRIEF.md`:

```markdown
# Brief — Dry run: Tess sets the clock

Status: agreed
Approval: not needed

## Outcome

A dry run of the ada plugin's Unit D. Nothing is built: the site's own date, read through
WP-CLI, shows the fake time Tess sets.

## Definition of done

- [ ] With the clock at Friday 2026-11-06 12:30 site time, `ada-clock wp eval 'echo wp_date("D Y-m-d H:i");'` prints `Fri 2026-11-06 12:3` and one more digit.

## Amendments

| Date | What changed (old → new) | Why (evidence) | Agreed by |
|---|---|---|---|
```

`$WT/.claude/handoff/TEST-PLAN.md`:

```markdown
# Test plan — Dry run: Tess sets the clock

- Definition of Done items covered: 1
- Clock positions used: Friday 2026-11-06 12:30 site time: `ada-clock set "2026-11-06 12:30"`

## Checks

| # | Kind | Precondition | Action | Expected | Observe at |
|---|---|---|---|---|---|
| 1 | local | Runners frozen; the clock at Friday 2026-11-06 12:30 site time (`ada-clock set "2026-11-06 12:30"`) | `ada-clock wp eval 'echo wp_date("D Y-m-d H:i");'` | `Fri 2026-11-06 12:3` and one more digit (the fake clock runs on) | terminal |

## Tests supplied

| DoD item | Type | File | If none, why |
|---|---|---|---|
| 1 | none | | A dry run of the plugin; nothing to supply |
```

`$WT/.claude/handoff/REVIEW-REQUEST.md`:

```markdown
Ready for Review: YES

# Review request — Dry run: Tess sets the clock

## What was built

Nothing: a dry run of Tess's run mode.

## Files changed

None.
```

`$WT/.claude/handoff/CHECKPOINT.md`:

```markdown
# Checkpoint

- Written: (today)
- Step: Dry run: Tess sets the clock
- Branch: dry/tess-clock-dry-run

## Where we stopped

Built and reviewed (nothing changed). Next: Tess in run mode.

## Leftovers on the local site

None.
```

### The after-script

Save this as `after.sh` in the scratch folder.

```bash
#!/bin/bash
# after.sh: run from the dry-run worktree once Tess has finished.
# Env: REPO (this checkout), MODE (go: the date check ran; stop: it must not have run),
# MARK (the file touched just before the run), WHEN (default "2026-11-06 12:30").
set -u
: "${REPO:?this checkout}" "${MODE:?go or stop}" "${MARK:?the marker file}"
WHEN=${WHEN:-2026-11-06 12:30}
WT=$(git rev-parse --show-toplevel) || exit 2
CLK=$REPO/plugins/ada/bin/ada-clock
TR=$WT/.claude/handoff/TEST-REPORT.md
fails=0
t() { tn=$1; shift; if "$@" >/dev/null 2>&1; then echo "PASS $tn"; else echo "FAIL $tn"; fails=$((fails+1)); fi; }
section() { awk -v h="$1" 'index($0, "## " h) == 1 {p=1; next} p && /^## /{exit} p' "$TR"; }
sec_has() { section "$1" | grep -Eq -- "$2"; }

out=$(cd "$WT" && bash "$CLK" status 2>&1); rc=$?
printf '%s\n' "$out" | sed 's/^/info: status: /'
t "after: status first line is 'ada-clock: real'" test "$(printf '%s\n' "$out" | head -1)" = "ada-clock: real"
t "after: status exits 0" test "$rc" = 0
wpp=$(sed -n 's/^[[:space:]]*LOCAL_WP_PATH[[:space:]]*=[[:space:]]*//p' "$WT/.claude/team/local.env" | tail -1 | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/")
t "after: the site's WordPress root found ($wpp)" test -d "$wpp/wp-content"
t "after: no state file left" test ! -e "$wpp/wp-content/ada-clock.json"

t "report: TEST-REPORT.md written" test -s "$TR"
t "report: the Run record names the fake time $WHEN" sec_has 'Run record' "$WHEN"
t "report: the Run record names the reset" sec_has 'Run record' 'reset'
t "report: names ada-clock reset (cleanup)" grep -q 'ada-clock reset' "$TR"
t "report: names status's 'ada-clock: real' after the reset" grep -q 'ada-clock: real' "$TR"
if [ "$MODE" = go ]; then
  t "report: check 1 under Verified, with the fake date" sec_has 'Verified' '2026-11-06'
else
  t "report: check 1 not under Verified" sh -c "! awk 'index(\$0,\"## Verified\")==1{p=1;next} p&&/^## /{exit} p' '$TR' | grep -q 'Fri 2026-11-06'"
  t "report: Not verified or Escalate gives status as the reason" sh -c "awk 'index(\$0,\"## Not verified\")==1||index(\$0,\"## Escalate\")==1{p=1;next} /^## /{p=0} p' '$TR' | grep -Eiq 'status|exit 1'"
fi

# The order of Tess's ada-clock calls, from the session transcripts written since MARK; and,
# when set's output says (manual), that its steps reached the developer through Ada and the
# developer answered before any status after the set.
enc=$(printf '%s' "$WT" | sed 's/[^A-Za-z0-9]/-/g')
python3 - "$MARK" "$MODE" "$HOME/.claude/projects/$enc" <<'PY' || fails=$((fails+1))
import glob, json, os, re, sys, time
mark, mode, root = sys.argv[1], sys.argv[2], sys.argv[3]
since = time.strftime('%Y-%m-%dT%H:%M:%S', time.gmtime(os.path.getmtime(mark)))
ev, denied, out = [], set(), {}
said, answered = [], []  # Ada's messages to the developer; the developer's messages (main session)
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
                if main and ts >= since and d.get('type') == 'assistant' and c.get('type') == 'text':
                    said.append((ts, c.get('text') or ''))
                if c.get('type') == 'tool_result':
                    out[c.get('tool_use_id')] = text_of(c.get('content'))
                    # A call the permission system refused never ran: it doesn't count.
                    if c.get('is_error') and refused.search(json.dumps(c.get('content'))):
                        denied.add(c.get('tool_use_id'))
ev.sort(key=lambda e: e[0])
written, seq = {}, []
# set, status, reset; and a date check: `ada-clock wp ...` that reads wp_date (in the command,
# or in a file the session wrote and the command names). wp_date without ada-clock is not one.
pat = re.compile(r'ada-clock\s+(set|status|reset|wp)\b')
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
        if m.group(1) != 'wp':
            seq.append((m.group(1), ts, tid))
        elif 'wp_date' in text[m.end():ms[i + 1].start() if i + 1 < len(ms) else len(text)]:
            seq.append(('check', ts, tid))
print('info: order: ' + (' '.join(k for k, _, _ in seq) or '(none found)'))
kinds = [k for k, _, _ in seq]
fails = 0
def t(name, ok):
    global fails
    print(('PASS ' if ok else 'FAIL ') + name)
    fails += 0 if ok else 1
t('order: a transcript with ada-clock calls found (else read the session by eye)', bool(seq))
if seq:
    s = kinds.index('set') if 'set' in kinds else None
    t('order: Tess ran ada-clock set herself', s is not None)
    if s is not None:
        after = kinds[s + 1:]
        r = after.index('reset') if 'reset' in after else None
        t('order: ada-clock reset after the set', r is not None)
        window = after if r is None else after[:r]
        if mode == 'go':
            c = window.index('check') if 'check' in window else None
            t('order: the date check ran after the set', c is not None)
            t('order: ada-clock status between the set and the date check', c is not None and 'status' in window[:c])
            last = max(i for i, x in enumerate(kinds) if x == 'check') if 'check' in kinds else -1
            t('order: ada-clock reset after the last date check', 'reset' in kinds[last + 1:])
        else:
            t('order: ada-clock status after the set', 'status' in window)
            t('order: no date check between the set and the reset', 'check' not in window)
        # Under manual, Tess can't change the computer's clock: set's steps go to the developer
        # through Ada, and status runs only once the developer has answered.
        set_out = out.get(seq[s][2], '')
        if set_out and '(manual)' in set_out.splitlines()[0]:
            steps = [l.strip() for l in set_out.splitlines()[1:] if l[:1] in (' ', '\t') and l.strip()]
            print('info: set printed steps: ' + (' | '.join(steps) or '(none)'))
            t_set = seq[s][1]
            # The first status or reset after the set must wait for the developer.
            nxt = [ts for k, ts, _ in seq[s + 1:] if k in ('status', 'reset')]
            t_next = nxt[0] if nxt else '9999'
            told = [ts for ts, x in said if t_set < ts < t_next and any(p in x for p in steps)]
            told_ts = told[0] if told else None
            t("manual: set's steps reached the developer (Ada's message) before any status or reset after the set", bool(steps) and told_ts is not None)
            t('manual: the developer answered after those steps and before that status or reset',
              told_ts is not None and any(told_ts < ts < t_next for ts in answered))
sys.exit(1 if fails else 0)
PY
echo "$fails failed"; [ "$fails" = 0 ]
```

### Part B: `local-linux`, Tess runs the clock

1. `touch <scratch>/mark-b`
2. In `$WT`: `claude --plugin-dir <this checkout>/plugins/ada`. Say: "This is a dry run of the ada
   plugin. The step's handoff files are ready and the build and review are done; nothing
   changed. Spawn ada:tess in run mode now. I ask Tess to run the local checks herself. Chrome is
   not connected, and she may not switch to any user."
3. Approve the `ada-clock` permission prompts (`ada-onboard settings` doesn't allow `ada-clock`;
   a Known Gap of this step). Decline anything that would place an order, run an action or run
   `wp action-scheduler run`, and note it: that is a FAIL.
4. When Tess has handed back, quit, then from `$WT`:
   `REPO=<this checkout> MODE=go MARK=<scratch>/mark-b bash <scratch>/after.sh`

### Part C: `manual`, the developer says it's done but doesn't change the clock

1. In `$WT`: `bash "$REPO/plugins/ada/bin/ada-handoff-init" --archive dry-run-b`, write the four
   handoff files again, and switch the setup: `printf 'ADA_CLOCK_ADAPTER=manual\n' >> .claude/team/local.env`.
2. `touch <scratch>/mark-c`, start `claude --plugin-dir …` and say the same as in B.
3. When the steps to change the computer's clock reach you (from Ada, before Tess has run
   `ada-clock status`), **don't change it**: reply "Done". When the steps to switch automatic
   time back on reach you, reply "Done".
4. When Tess has handed back, quit, then from `$WT`:
   `REPO=<this checkout> MODE=stop MARK=<scratch>/mark-c bash <scratch>/after.sh`

### Afterwards

```sh
cd "$WT" && bash "$REPO/plugins/ada/bin/ada-clock" status   # must say ada-clock: real; if not, ada-clock reset
bash "$REPO/plugins/ada/bin/ada-handoff-init" --archive dry-run-c
cd ~/Repos/vandme && git worktree remove ~/Repos/vandme-tess-clock-dry-run && git branch -D dry/tess-clock-dry-run
```

Leave `ada-clock` installed on V&Me, or undo it as `16-clock/local-linux-on-a-local-site.md`
says (`ada-clock uninstall`, then restart the site). Remove the transcripts' worktree folder in
`~/.claude/projects/` if you like.

## Expected

From the brief (Decisions, Tess; DoD 3) and Bob's approved Builder plan, item 1:

- Part A: every line `PASS`, `0 failed`, exit 0. On ff93d6a the `ada-clock` checks FAIL and the
  two regression checks PASS.
- Part B (`local-linux`):
  - Before setting the clock, Tess checks V&Me's preconditions (step 1): the safety checks, the
    runners frozen, nothing left over, and the site's clock as WP-CLI sees it. `ada-clock status`
    is not among them: it first runs in step 5, once before the first date check (exit 0 or 1
    means a clock setup exists), then again before each date check.
  - She runs `ada-clock set "2026-11-06 12:30"` herself; its first line is `ada-clock: fake
    2026-11-06 12:30 <the site's timezone> (local-linux), written to …`. Nobody is asked to change
    the computer's clock, and there is no `sudo`.
  - She runs `ada-clock status` before the date check; it exits 0 with first line `ada-clock: fake
    2026-11-06 12:30 …`. The check runs through `ada-clock wp` and shows `Fri 2026-11-06 12:3x`.
  - At cleanup she runs `ada-clock reset`, then `ada-clock status`: `ada-clock: real`.
  - TEST-REPORT's Run record names the fake time `2026-11-06 12:30` (its timezone) and the
    reset, with `status`'s `ada-clock: real` after it; check 1 is under Verified.
  - `after.sh`: every line `PASS`, `0 failed`; `info: order:` shows `set`, then `status`, then
    `check`, then `reset`.
- Part C (`manual`):
  - `ada-clock set`'s printed steps (the computer's clock, in the computer's timezone) reach you
    through Ada, as `set` printed them, and Tess waits for your answer: between the `set` and
    your "Done", she runs no `ada-clock status` and no `ada-clock reset`.
  - After "Done", `ada-clock status` exits 1 (the site is on real time, the state says fake).
    Tess doesn't run the date check: check 1 is Not verified (or escalated) with `status` as the
    reason, not Verified and not Failed for the site's behaviour.
  - She still runs `ada-clock reset` at cleanup, and its steps (automatic time back on) reach you
    through Ada; the Run record names the fake time asked for and the reset.
  - `after.sh` (MODE=stop): every line `PASS`, `0 failed`; no `check` between `set` and `reset`;
    and, because `set` printed `(manual)`, the two `manual:` lines PASS: one of Ada's messages
    holds a step `set` printed, and your answer came after it, both before the first `status` or
    `reset` after the `set`.

## Observe at

- The session (Ctrl-O for the transcript: each `ada-clock` call and its output).
- `$WT/.claude/handoff/TEST-REPORT.md` (Part B's is archived under `archive/dry-run-b/`).
- `after.sh`'s output: `ada-clock status`, the state file, the report, the order.

## Regression

- Tess's other rules hold: she places no order, runs no action except one at a time by ID, never
  `wp action-scheduler run`, doesn't browse (Chrome not connected), switches to no user.
- The Never list and the `tools:` line of `tess.md` are unchanged (part A).
- V&Me's own handoff files and `TEST-AREAS.md` are untouched (the dry run is in a worktree; the
  brief keeps V&Me's `TEST-AREAS.md` out of scope).
