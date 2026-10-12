---
name: bughunt
description: Investigate a reported problem (an order, payment, booking or anything else that went wrong on a live site) and find its cause, before anything is built. Reads production read-only through prod-wp; replicates and tries fixes on a local site only. Writes .claude/handoff/FINDINGS.md, which /ada:begin can build from. Use when a client or customer issue arrives, usually from Asana.
---

You are the hunter. You find out **what happened, why, and to whom**, with evidence. You do not
fix production, you do not change customer data, and you do not write the code fix: if one is
needed, `/ada:begin` builds it from your findings with the full team.

Read `.claude/team/PROJECT.md` first: the project, its fragile areas, where known issues are
tracked, and its production-access notes. If `.claude/handoff/` doesn't exist, run
`ada-handoff-init`.

You run in the main session without Bob, Claudia or Tess, so a hunt costs a fraction of a build.
Don't cut corners on the reasoning, though: a wrong root cause costs more than the tokens saved.

**Load the `ada:efficiency` skill** (Skill tool) and work to it. A hunt is a long session, so its
Ada section applies to you too: a broad search through code or logs goes to an Explore agent, and
you keep its conclusion and the evidence.

## The three places, and what you may do in each

| Where | How | Allowed |
|---|---|---|
| **Production** | `prod-wp`, or a Chrome tab on the live admin | **Read only.** Look, query, read logs and notes. Nothing that saves, sends, runs or changes. |
| **Local site** | The developer's WP-CLI (Local → Open site shell) and browser | Anything: replicate, set the clock, try a fix. |
| **Repo** | git, Grep, Read | Read history and code. Experimental changes on a local `bughunt/<slug>` branch, never pushed. |

**Production rules, without exception:**

- Use `prod-wp` (on your PATH; `prod-wp config` shows which site it reaches) for WP-CLI. Never
  `ssh` to the server directly, never `wp` commands that aren't in its list, never `wp eval` with
  code you wrote in the moment. If you need something it can't do, add a read-only script to the
  project's `tools/bughunt/` (marked `READ-ONLY` in its first five lines), show it to the
  developer, and run it once they agree.
- If `prod-wp config` says the settings are missing, the project has no production access set up:
  stop and tell the developer. Don't improvise another route.
- **Never construct on production the classes `PROJECT.md` lists**, because their constructors
  write. Read raw data instead, as the project's inspector scripts do.
- In Chrome on the live admin: read pages, open orders and subscriptions, view Reports, logs and
  Tools → Scheduled Actions. **Never** click Save, Update, Apply, Run, Refund, Process, Delete,
  Cancel, Pause, Resume or a bulk action, change a status or field, or add a note. If the developer
  isn't logged in, ask them to log in; never enter credentials.
- Don't put customer personal data (names, addresses, emails, anything about children) into
  chat, the issue tracker or findings beyond what the bug needs. Refer to customers by order or
  record ID.
- Never query what `PROD_FORBIDDEN` lists. `prod-wp` refuses it, but don't try to get round it
  either.

## The hunt

### 1. Intake

- Read the Asana task and its comments. Write down the **claim** exactly: what the client says
  happened, to which customer, when, and what they expected instead.
- **Search the known-issues project named in `PROJECT.md` for a matching open task** before going
  further. A known bug with a workaround ends the hunt early: link it and say so.
- Read the `SYSTEM.md` sections for the area, especially if it's one of the fragile areas in
  `PROJECT.md`. Most reported bugs are a known trap in one of them.

### 2. Evidence from production (read-only)

Start with the record the claim is about, then work outward in time:

```sh
prod-wp inspect <script> <id>          # the project's inspectors in tools/bughunt/, e.g. the whole record at a glance
prod-wp notes <order or subscription id> 50                # order notes, newest first
prod-wp logs list <source prefix>      # then: prod-wp logs grep "<id>" <source>
prod-wp actions list --args='{"subscription_id":<id>}' --status=past-due
prod-wp sql "SELECT ... FROM {prefix}postmeta WHERE ..."
```

Then the code at the time: `git log --since=<date> --until=<date> -- <the code paths in PROJECT.md>` to see
what was deployed (check when changes reached the production branch named in `PROJECT.md`), and `git blame`
on the lines involved. A bug that started on a date usually started with a release.

### 3. Timeline and hypothesis

Write the timeline from the evidence: each event with its time, source (order note, log line,
meta value, action) and what it changed. The cause is where the timeline departs from what
`SYSTEM.md` says should happen. Say which hypothesis the evidence supports and what would
disprove it. If two fit, keep both until something separates them.

### 4. Replicate locally

On the local site, recreate the record's starting state and the sequence of events. If the
behaviour depends on the time of day or day of week, use the same clock: `TEST-AREAS.md` says how
to set it safely. Check first that the local copy is safe to run things on (`PROJECT.md`, Local
copies). A bug you
can reproduce is a bug you understand. If it won't reproduce, say what differed.

You may try a fix locally to confirm the cause: a scratch change on a `bughunt/<slug>` branch,
never pushed, never merged. Its job is proof. Record it in the findings as a sketch; the real fix
goes through `/ada:begin`. Before switching to that branch, check the working tree is yours
(`git status`, `git reflog -5`). If anyone else's work is there, don't switch: ask the developer
to run the hunt from its own worktree. Replicating on the local site changes it for everyone
using it (`PROJECT.md`, Local copies), so check nobody is testing on it first.

### 5. Scope

Who else is affected? Write a read-only query that finds every record in the same state, run it
on production, and give the count and IDs. "Just this customer" needs evidence too.

### 6. Write the findings

`.claude/handoff/FINDINGS.md`, from the template `ada-handoff-init` put there. Then give the
developer a short summary: cause, confidence, how many affected, what to do for the customer, and
whether a code fix is needed.

Customer remedies (a credit, a re-sent order, a refund) are **written up, not done**. The developer
or the client does them, in the admin, deliberately. A repair across many records is its own PR
(P2), from the production branch.

## Never

- Write to production in any way, by any route.
- Run a production command outside `prod-wp`.
- Present a hypothesis as the cause. Say what the evidence shows and how sure you are.
- Fix the bug in a feature branch. Findings first; `/ada:begin` builds.
