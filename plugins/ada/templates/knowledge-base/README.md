# Support knowledge base

Past support issues for this product: what caused them and how they were resolved. They come from
the support emails and the support tickets in Asana. Use it to answer new tickets faster, to spot
problems that keep coming back, and, when investigating a bug, to check whether it has been seen
before.

This folder is for the team and is never part of a release, like everything else Ada adds to a
repo (`.claude/`, `tests/`). Keeping it out of the release is the job of the project's build.

## What's here

```
README.md          This file
INDEX.md           One line per entry, grouped by area
config.json        The Gmail search and the Asana project the sync reads
sync-state.json    Sync progress: last sync times and which threads and tasks are done (IDs only)
issues/            One Markdown file per issue
```

## Finding an answer

1. Scan [INDEX.md](INDEX.md): one line per entry, grouped by area, with its status.
2. Or search the entries, e.g. `grep -ril "nonce" issues/` from this folder. Error messages are
   quoted exactly, so search for the customer's wording.
3. Each entry gives the symptoms, the cause, the solution, how to confirm it's the same issue, and
   the source tickets.

An entry describes the code on the development branch when it was last checked. Before giving a
customer a snippet or a code explanation, check it against the version they run.

## Entry format

File name: `issues/<short-kebab-case-summary>.md`.

```markdown
---
title: Guests get "security verification was not successful" when joining
category: configuration    # bug | conflict | configuration | how-to | feature | licensing | support-process | development
status: resolved           # resolved | workaround | fixed-in-release | open-bug | unresolved
tags: [nonce, caching, guests]
plugin_versions: [2.6.2]   # versions the issue was reported on
fixed_in:                  # a version in the product's changelog, when status is fixed-in-release
first_seen: 2026-09-13
last_seen: 2026-09-25
sources:
  - gmail:1a09985d426e92a7
  - asana:1218435735798081
---

## Symptoms
What the customer sees, including exact error messages (they are what people search for).

## Cause
Why it happens. Say so if it is suspected rather than confirmed. "n/a: <why>" when there is no fault (a how-to).

## Solution
What fixed it, or the recommended fix. Include code or CSS snippets where relevant.

## How to diagnose
Steps and questions to confirm this is the issue.

## Notes
Optional: related entries, environment details that mattered, reply wording that worked.
```

- **Required:** the front matter fields `title`, `category`, `status`, `tags` and `sources`, with
  `category` and `status` from the lists above, and the four sections Symptoms, Cause, Solution
  and How to diagnose, none of them empty. Notes is optional. `###` sub-headings inside a section
  are fine.
- **Categories:** `bug`, `conflict` (with another plugin or theme), `configuration`, `how-to`,
  `feature`, `licensing`, `support-process` and `development`.
- **Statuses:** `resolved` (the customer confirmed the fix, or it's a how-to that works);
  `workaround` (a way round it, no fix in the product); `fixed-in-release` (fixed in the product,
  with `fixed_in`); `open-bug` (a confirmed bug, not fixed yet); `unresolved` (no confirmed cause
  or fix).
- **Sources** are `gmail:<thread id>` and `asana:<task id>`: IDs only, never links or content
  from the thread.
- **Code references:** a file in this repo is cited in backticks by its full repo path, with the
  function or method named beside it and no line numbers (they move with every release). A link
  to code is relative to the entry. Paths on a customer's site (`wp-content/themes/...`,
  `functions.php`) are written as site paths.

## No customer data

Entries are read by the whole team and by Claude. They hold no customer data: no names, email
addresses (other than `@pie.co.de` or `example.com`), phone numbers, order numbers, site URLs or
licence keys. Describe the setup instead ("site using Super Page Cache", "Woodmart theme").

Customer emails and tickets stay in Gmail and Asana. `/ada:kb-sync`'s subagent reads them there,
through the Gmail and Asana connectors, so Claude sees what they say while it writes the entries.
Nothing from them is saved in this repo except the anonymised entry and the thread and task IDs
in `sync-state.json`. An entry is written from what the ticket showed, in our own words.

## Adding or updating an entry by hand

1. Search `issues/` first. If an entry covers the same problem, update it: add the source, widen
   `plugin_versions` and `last_seen`, refine the cause or solution. Otherwise create
   `issues/<short-kebab-case-summary>.md` in the format above.
2. Check the code and the changelog on the development branch: is the bug still there, or fixed
   (set `status: fixed-in-release` and `fixed_in`)? Name the file and function.
3. Be honest about the status: if the ticket closed without the customer confirming, it's
   `unresolved` and the entry records what was suggested. Don't present a guess as the fix.
4. Add one line for a new entry to [INDEX.md](INDEX.md), under its area, with the status icon.
   Each entry is linked from the index exactly once.
5. Commit the entry and `INDEX.md` together.

What doesn't get an entry: routine work (compatibility updates, review checks, coding-standards
chores), auto-replies and spam. Feature requests go into one `issues/feature-support-faq.md`
rather than an entry each. Old bugs already fixed are covered by the changelog; add an entry only
if the ticket has diagnostic value. A ticket often exists as both an email thread and an Asana
task with the same subject: that's one entry with both sources.

## Syncing from Gmail and Asana

`/ada:kb-sync` keeps this folder up to date through the Gmail and Asana connectors.

- `config.json` holds what it reads: the Gmail mailbox and search query (`gmail.mailbox`,
  `gmail.query`), and the Asana project for support tickets (`asana.projectName`,
  `asana.projectGid`).
- `sync-state.json` holds its progress: the last sync time for each source (`gmail.lastSync`,
  `asana.lastSync`) and the threads and tasks already done (`items`, IDs only). A `lastSync` of
  `0` or `null` means that source has never been synced. Each sync starts from these times, so
  tickets after them aren't in the knowledge base yet.

Leave both files as they are when adding entries by hand.
