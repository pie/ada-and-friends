# `/ada:onboard` asks whether the project is a product or a site; for a product it creates and fills the knowledge-base folder, for a site it does nothing new

Unit B1 of the knowledge-base plan (Asana 1219987986280083). The script side (`ada-onboard kb`,
`check`, the template bullets, the skill's text) is `kb-folder-and-check.md`. This script checks
what only the skill decides: what it asks, whether it runs `ada-onboard kb`, and what it writes.

- Area: 02 Onboarding and refresh (the `onboard` skill); 11 Templates (`PROJECT.md`'s Kind and
  Knowledge base bullets as onboarding fills them)
- Asana: https://app.asana.com/0/0/1220058587596437/f
- Automate as: none (a dry run for a person; the skill's text is checked by
  `kb-folder-and-check.md`, section 8)
- Last run: not yet run

## What this checks

When a developer runs `/ada:onboard`, Ada asks whether the project is a product or a site. Only a
product gets a knowledge base. You play a developer onboarding two made-up projects, one of each,
then check what onboarding wrote.

## Who does what

- Setup and the checks afterwards can be done by Ada, in a session in this checkout. Each has a
  prompt to paste.
- Only the two onboarding conversations (B1 and B2) need you, because you answer Ada's questions
  in a fresh `claude` session in each scratch repo.

## Precondition

- `claude` logged in, and this checkout with the change in its working tree.
- Two scratch repos, made by the Setup block below, outside this checkout. Each turns `ada@pie`
  off in `.claude/settings.local.json` so only the checkout's plugin loads
  (`DEVELOPING.md:15-22`).
- Made-up support details only: no real mailbox, query or Asana project. The values below are
  examples; whatever you type, the same values must reach `config.json`.
- In this script, `<this checkout>` means the full path of the `ada-and-friends` checkout you
  are testing. The path may have spaces, so quote it.

### Setup

Paste this into a shell, or ask Ada to run it:

```sh
for k in product site; do
  R=$HOME/scratch-onboard-$k; rm -rf "$R"; mkdir -p "$R/.claude"; git -C "$R" init -q
  printf '%s\n' '{ "enabledPlugins": { "ada@pie": false } }' > "$R/.claude/settings.local.json"
  printf '%s\n' '<?php' '/* Plugin Name: Scratch Waitlist' ' * Text Domain: scratch-waitlist */' > "$R/scratch.php"
  git -C "$R" add -A; git -C "$R" -c user.name=t -c user.email=t@example.com commit -q -m init
done
```

Or paste this to Ada, in a session in this checkout:

```text
Set up the two scratch repos for the onboard dry run in tests/functional/02-onboarding/onboard-asks-kind-and-creates-knowledge-base.md (its Setup block), then wait while I run the onboarding.
```

## Steps

### B1. A product (you)

1. Open a new terminal. Don't use the one Ada is running in.
2. `cd ~/scratch-onboard-product`
3. `claude --plugin-dir "<this checkout>/plugins/ada"`
4. Type `/ada:onboard`.
5. When it asks the kind of project, answer `product`.
6. Only after that should it ask for the Gmail mailbox, the search query and the Asana support
   project. Answer with the made-up values:
   - mailbox: `support@example.com`
   - search query: `label:scratch-support`
   - Asana support project: `Scratch support`, GID `1200000000000001`
7. For any other question, give a sensible answer, or `none`.
8. Say **no** to anything that pushes, opens a PR or installs anything.
9. Near the end it should run `ada-onboard kb` and print five `created` lines. If it says
   something like unknown command `kb`, the installed Ada ran instead of this checkout's. Stop
   and note it.
10. Type `/exit`.
11. Note down the questions it asked, in order.

### B2. A site (you)

1. In a new terminal, `cd ~/scratch-onboard-site`
2. `claude --plugin-dir "<this checkout>/plugins/ada"`
3. Type `/ada:onboard`.
4. When it asks the kind of project, answer `site`.
5. It should not ask about Gmail or Asana support, and should not run `ada-onboard kb`.
6. If it asks whether the project is clock-sensitive, say no. Answer any other question as in B1
   (sensible answer or `none`), and say no to anything that pushes, opens a PR or installs.
7. Type `/exit`.
8. Note down the questions it asked, in order.

### Checks and clean-up (Ada, or you by hand)

Paste this to Ada, in a session in this checkout, with the questions filled in:

```text
The onboard dry run is done. Product run asked: <questions>. Site run asked: <questions>. Run the snapshot in both scratch repos, compare with Expected, record the run in TEST-REPORT.md and this script's Last run, then delete both scratch repos.
```

By hand, run the snapshot in each scratch repo's root (`cd ~/scratch-onboard-product`, then
`cd ~/scratch-onboard-site`). Replace `<this checkout>` with the checkout's path, keeping the
quotes. It uses this checkout's `ada-onboard`.

```sh
echo "== Kind / Knowledge base lines"; grep -E '^- \*\*(Kind|Knowledge base):\*\*' .claude/team/PROJECT.md
echo "== folders"; ls -A knowledge-base 2>&1; ls -A knowledge-base/issues 2>&1
echo "== config.json"; cat knowledge-base/config.json 2>&1
echo "== placeholders left"; grep -c '{{' knowledge-base/config.json 2>&1
echo "== sync-state.json"; cat knowledge-base/sync-state.json 2>&1
echo "== check, knowledge-base lines"; bash "<this checkout>/plugins/ada/bin/ada-onboard" check | grep -Ei 'knowledge|config\.json' || echo "(none)"
```

Compare each output with Expected below. Then clean up:

```sh
rm -rf ~/scratch-onboard-product ~/scratch-onboard-site
```

## Expected

From the brief (Outcome; Decisions: the Kind row, the products-only step after step 4; a site
behaves exactly as today):

**B1, product**

- Step 2 of the skill asks the kind of project (product or site) and, because the answer is
  product, asks for the Gmail mailbox, the search query and the Asana project for support
  tickets. It doesn't ask them before the kind is known to be product.
- After step 4, it runs `ada-onboard kb` (the transcript shows the `created` lines for
  `README.md`, `INDEX.md`, `config.json`, `sync-state.json`, `issues/.gitkeep`).
- Snapshot: `Kind:` is `product` (in a code span); `Knowledge base:` names the folder it made,
  e.g. `` `knowledge-base/` ``; `knowledge-base/` holds the four files and `issues/` holds
  `.gitkeep`; `config.json` has `gmail.mailbox` `support@example.com`, `gmail.query`
  `label:scratch-support`, `asana.projectName` `Scratch support`, `asana.projectGid`
  `1200000000000001`, and `0` placeholders left; `sync-state.json` is untouched
  (`lastSync` 0 and null, `items` empty); `check` prints no knowledge-base or `config.json` line.

**B2, site**

- It asks the kind; on **site** it asks nothing about Gmail, a search query or an Asana support
  project, and never runs `ada-onboard kb`.
- Snapshot: `Kind:` is `site`; `Knowledge base:` is `` `none` ``; `ls knowledge-base` says no
  such file or directory; `check` prints `(none)`.

Write in the Run record which questions it asked, in order, and paste each snapshot.

## Observe at

The session transcript (questions and the commands it ran), then the snapshot's output.
`git status` in each scratch repo shows what onboarding wrote.

## Regression

- B2 is the regression: a site's onboarding writes exactly what it did before, plus the two
  bullets filled with `site` and `none`.
- The clock step still behaves as in `onboard-asks-before-clock-install.md` (answer the
  Clock-sensitive question **no** here, so it asks nothing about the clock).
