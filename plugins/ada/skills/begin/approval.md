# Ada: brief approval and amending an approved brief

Read from `/ada:begin` once a brief is agreed, before the build starts, and whenever an approved
brief is amended.

## Getting the brief approved

**Then get it approved, if this project needs that.** An author needs no approval for the areas
they approve, and Trusted approves every area. Everyone else needs each area's approvers.

1. **The areas.** Run `ada-brief areas`. In a project without an areas table its only area
   line is `everything: …` (then the `trusted:` line): leave the brief's `Areas:` line as the template's prompt, and the
   brief is approved as a whole with the `brief-approved` label, as before. Otherwise judge
   which of the listed areas the brief touches, from what it changes (the catch-all covers what
   no other area does), and write their slugs on its `Areas:` line, e.g. `Areas: content,
   design`.
2. **The author.** Always write `Author: @<login>`, with the login from `gh api user -q .login`:
   the developer the step is for, who runs `ada-brief submit` and so opens the draft PR.
   `ada-brief status` exempts the author from the areas they approve only when `Author:` names
   the PR's opener.
3. **Who.** Run `ada-brief who <the slugs on Areas:>`, or `ada-brief who` with no table:
   - `not-needed`, `approver <login>` or `trusted <login>`: no approval is needed. Write which,
     and why, on the brief's `Approval:` line (e.g. "not needed: @login approves content", "not
     needed: @login is trusted") and carry on.
   - `required <login>`: with areas, a `needs <slug>: @a, @b` line follows for each area that
     waits on someone else. Tell the developer which areas need approval and name their
     approvers, and only them: an area the developer approves isn't listed. Then run
     `ada-brief submit <Asana task id> <the PR base from PROJECT.md>`. It commits the brief to
     `.claude/briefs/`, pushes the branch and opens a draft PR with the brief as its
     description, and the labels the approvers add: `approved:<slug>` for each area, or
     `brief-approved` with no table. Set the brief's status to `awaiting approval`, give the
     developer the PR link and ask them to link it to the Asana task (Apps, GitHub, "Add GitHub
     pull request"), write `CHECKPOINT.md`, and **stop**. Nothing is built, and Tess and Bob are
     not spawned, until `ada-brief status` says `approved`. The developer carries on with
     `/ada:pickup` once the approvers have looked. In a plan, submit every brief that can be
     submitted (top-level briefs, and the parts of an approved feature) before you stop, with
     the branch as the third argument; `ada-brief status <branch>` checks each one, and
     `ada-plan status` checks them all.
   - If `ada-brief` says `gh` is missing or not logged in, the developer has to fix that first;
     the brief can't be approved without it. If it stops on an area it doesn't list, correct
     `Areas:` from `ada-brief areas`.

`ada-brief status` prints the state on its first line:

- `approved @login <time>`: record it on the `Approval:` line and carry on. A second line,
  `amended since approval; no re-approval needed`, means the brief has changed since the label
  and every change was recorded as `not needed`: carry on.
- `changes-requested`, followed by what the approvers said: go through each point with the
  developer, change the brief, and `ada-brief submit` again. That resets the approval.
- `waiting`: say so, with any note it prints (an earlier version was approved, and why the
  changes since need approving again; a label added by someone who doesn't approve that area
  doesn't count), and stop.

For a brief with `Areas:`, one `area <slug>: …` line per area follows, in `Areas:` order:

- `approved @x <time>`, `not needed (@y approves it)` (the author approves it) or
  `not needed (no approvers)`: that area is done.
- `waiting (@a, @b)`: it waits on those logins; name them to the developer. A line ending
  `; no Author: line` or `; Author: @x is not the PR's opener @y, so it isn't exempt` means the
  author approves the area but `Author:` doesn't name the PR's opener: correct `Author:` and
  `ada-brief submit` again.
- `changes-requested`: as above, for that area.

The `Approval:` line records each area, e.g. `approved: design by @x on <date>; content not
needed (@y approves it)`; with no table, "approved by @login on <date>".

## Amending an approved brief

If the brief was approved by an approver, an amendment needs approving again only when it hits
a re-approval trigger, and only by the areas it touches. Decisions, and Constraints less its
`- Out of scope:` bullet, are what the approvers approved; the rest of the brief is the
developer's. For each agreed change:

1. Check it against the triggers: the defaults below, and any the project adds on the
   `- **Re-approval triggers:**` line of `PROJECT.md`'s Brief approval section. A trigger the
   project adds goes to the area it names, otherwise to the catch-all.
2. Record the row with the Re-approval column filled: `not needed`, or `needed: <area>[,
   <area>]` naming the slugs of the areas it goes back to. Send each trigger to the area that
   covers it, judged from the names in `PROJECT.md`'s areas table as well as the slugs: the
   starter slug below is the default, but a project that renamed `content` to `copy` sends
   wording to `copy`. Record the catch-all's slug only when no area fits. An area not on the
   brief's `Areas:` line is added to it
   in the same amendment; otherwise `ada-brief` sends the brief back to every area. With no
   areas table, record `needed: <trigger>` naming the trigger, as before. Tell the developer
   which, and why.
3. Run `ada-brief submit` with the same arguments as before. It compares the brief with the
   approved version and prints the result:
   - `updated <url> (approval kept)`: carry on. The approvers get a PR comment listing the new
     rows.
   - `updated <url> (approval reset: <slug>, ...)`: those areas' labels are gone, or never
     given; the other areas keep theirs. Name the areas and their approvers (`ada-brief
     status`'s `area` lines), then as below.
   - `updated <url> (approval reset)`: the label is gone. Set the brief's status to
     `awaiting approval`, write `CHECKPOINT.md` and stop until `ada-brief status` says
     `approved`.

The default triggers, each with the starter slug of the area it goes back to. An amendment needs
approving again when:

- it reaches a fragile area the brief didn't name: `fragile`;
- something the customer sees changes: `content` for its wording, `design` for how it looks; or
  something the customer pays: `price`;
- it adds a new dependency, external service, data repair or migration: `dependencies`;
- it touches a security, auth or payment path: `security`;
- the Expected review level goes up: the catch-all;
- it reverses a Decision: the catch-all.

When unsure, record `needed:`. Give every change to Decisions or Constraints its own row.
`ada-brief` resets the approval when they changed and no row was added (with areas, sending the
brief back to the catch-all), but once one row has been added it can't tell which change a row
is for.
