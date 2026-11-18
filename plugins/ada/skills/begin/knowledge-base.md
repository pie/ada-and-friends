# Ada: a product's knowledge-base entry

Read from `/ada:begin` (Direct Bob, Claudia and Tess, item 3) to write a product's knowledge-base
entry, once Bob's `REVIEW-REQUEST.md` says `Ready for Review: YES`, before you spawn Claudia.

**A product's knowledge-base entry.** Only when `PROJECT.md` says `Kind:` is `product` and
`Knowledge base:` gives a folder; with no `Kind:` line, `site`, or `none`, skip this. Before
you spawn Claudia, write or update the entry for a brief whose `Asana:` task is a card from the
product's support project, fix or not. To check, look the task up in Asana: it is a support
card when its projects (`memberships`) include the gid in `asana.projectGid` of the folder's
`config.json`. If you can't check (no Asana tools, an error, no `config.json`), ask the
developer; never write "not a support card" without having checked. Follow the folder's
`README.md`: "When to write an entry", "To write one" steps 1 to 4, with `sources`
`asana:<task id>`, and "No customer data", because the entry is pushed with the PR and some
product repos are public. A fix on the development branch is not released yet:
`fixed-in-release` with `fixed_in` only once that version is in the product's changelog; until
then `open-bug`, with the fix described in Solution. The `INDEX.md` line's status icon matches
the entry's status. Write the entry, and `INDEX.md` for a new one, in this step's worktree, not
the main checkout: it is committed in the PR (`pr-gate.md`, step 5), so Claudia reviews it
with the build. You commit it, not the developer, because it was written in the step's
worktree; the README's step 5 ("Leave the entry and `INDEX.md` uncommitted; the developer
commits them") is for an entry written outside a PR, such as Grace's in `/ada:bughunt`. Add a
`Knowledge base:` line to `BUILD-LOG.md`: the entry's file and `written` or `updated`, or
`none` and why (the README exception from "What doesn't get an entry", or "not a support card"
and how you checked: the task's projects, or the developer's answer). Claudia's finding on the
entry is yours to fix, not Bob's.
