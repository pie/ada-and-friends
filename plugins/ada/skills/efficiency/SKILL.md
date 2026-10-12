---
name: efficiency
description: How the ada team keeps its context and token use down - grep before read, no re-reads, conclusions not working, and Ada handing big reads to a subagent. Preloaded into Bob, Claudia and Tess; Ada loads it at session start.
user-invocable: false
---

# Working efficiently

Everything you read stays in the conversation and is paid for again on every later turn. On the
first V&Me dry run (12 Oct 2026) most of the cost was the main session's long context being
re-read, not any one subagent. These rules are about that.

Adapted from Three Man Team's token-optimisation rules (MIT, Russell Aaron).

## Everyone

- **Grep before you read.** Find the line, then read with an offset and limit around it. Read a
  whole file only when you need the whole file.
- **Read a file once per task.** Keep what you need from the first read; don't read it again to
  check something you already saw.
- **No speculative calls.** Each tool call answers a question you actually have.
- **Independent calls go together**, in one turn.
- **Simple commands.** One command per Bash call, with absolute paths, not `cd x && ...`. They
  are easier to read back, and the project's permission rules can match them.
- **Docs first, code to confirm.** `SYSTEM.md` and `API.md` tell you where to look and why. Check
  the code before you change or test anything they describe, because docs drift. When they're
  wrong, say so (Claudia flags it; Ada gets it fixed in the same PR).
- **Conclusions, not working.** A handoff file or a reply holds what you found and the evidence
  for it (file and line, command and result), not the search that found it.
- **Don't restate** what the developer, the brief or another role just said. Refer to it.
- **Don't redo a check another role has recorded** as done (Claudia's "Cleared", Bob's phpcs
  counts) unless your role is to check it independently. Claudia checks independently; Tess,
  running the plan, goes straight to behaviour.

## Ada

You are the long-lived session, so your context is the expensive one.

- **Anything that means reading code, logs or output over about 20 lines goes to a subagent**
  (Bob, Tess, or an Explore agent for a question), and you keep its conclusion. Read the handoff
  files, the brief's sections of `SYSTEM.md`, and short results yourself; not source files.
- Keep your messages to the developer short: what happened, what you need from them.
- When a step closes, write the checkpoint and stop. A fresh session with `/ada:pickup` is cheaper
  than carrying a long one into the next step.
