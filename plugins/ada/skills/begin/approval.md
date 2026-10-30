# Ada: brief approval and amending an approved brief

Read from `/ada:begin` once a brief is agreed, before the build starts, and whenever an approved
brief is amended.

## Getting the brief approved

**Then get it approved, if this project needs that.** Run `ada-brief who`:

- `not-needed`, `approver <login>` or `trusted <login>`: no approval is needed. Write which, and
  why, on the brief's `Approval:` line (e.g. "not needed: @login is trusted") and carry on.
- `required <login>`: run `ada-brief submit <Asana task id> <the PR base from PROJECT.md>`. It
  commits the brief to `.claude/briefs/`, pushes the branch and opens a draft PR with the brief as
  its description. Set the brief's status to `awaiting approval`, give the developer the PR link
  and ask them to link it to the Asana task (Apps, GitHub, "Add GitHub pull request"), write
  `CHECKPOINT.md`, and **stop**. Nothing is built, and Tess and Bob are not spawned, until
  `ada-brief status` says `approved`. The developer carries on with `/ada:pickup` once the
  approver has looked. In a plan, submit every brief that can be submitted (top-level briefs, and
  the parts of an approved feature) before you stop, with the branch as the third argument;
  `ada-brief status <branch>` checks each one, and `ada-plan status` checks them all.
- If `ada-brief` says `gh` is missing or not logged in, the developer has to fix that first; the
  brief can't be approved without it.

`ada-brief status` prints the state on its first line:

- `approved @login <time>`: record "approved by @login on <date>" on the `Approval:` line and
  carry on. A second line, `amended since approval; no re-approval needed`, means the brief has
  changed since the label and every change was recorded as `not needed`: carry on.
- `changes-requested`, followed by what the approvers said: go through each point with the
  developer, change the brief, and `ada-brief submit` again. That resets the approval.
- `waiting`: say so, with any note it prints (an earlier version was approved, and why the
  changes since need approving again; a label added by someone who isn't an approver doesn't
  count), and stop.

## Amending an approved brief

If the brief was approved by an approver, an amendment needs
approving again only when it hits a re-approval trigger. Decisions, and Constraints less its
`- Out of scope:` bullet, are what the approver approved; the rest of the brief is the
developer's. For each agreed change:

1. Check it against the triggers: the defaults below, and any the project adds on the
   `- **Re-approval triggers:**` line of `PROJECT.md`'s Brief approval section.
2. Record the row with the Re-approval column filled: `not needed`, or `needed: <trigger>`
   naming the trigger it hits. Tell the developer which, and why.
3. Run `ada-brief submit` with the same arguments as before. It compares the brief with the
   approved version and prints the result:
   - `updated <url> (approval kept)`: carry on. The approver gets a PR comment listing the new
     rows.
   - `updated <url> (approval reset)`: the label is gone. Set the brief's status to
     `awaiting approval`, write `CHECKPOINT.md` and stop until `ada-brief status` says
     `approved`.

The default triggers. An amendment needs approving again when:

- it reaches a fragile area the brief didn't name;
- something the customer sees or pays changes;
- it adds a new dependency, external service, data repair or migration;
- it touches a security, auth or payment path;
- the Expected review level goes up;
- it reverses a Decision.

When unsure, record `needed:`. Give every change to Decisions or Constraints its own row.
`ada-brief` resets the approval when they changed and no row was added, but once one row has
been added it can't tell which change a row is for.
