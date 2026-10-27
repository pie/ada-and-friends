# Design — <short title>

Asana: <task URL>
Designed: <date>, by <developer> with Iris
Status: draft | agreed | superseded
Artifact: <the Design artifact link | text only>
Project DESIGN.md: created | updated (<tokens>) | unchanged
Lint: 0 errors | not linted (no npx)

<!-- Written by Iris (`/ada:design`). This is the step's design. The project's design system is
the other DESIGN.md, at the repo root: this file names its tokens (`{colors.primary}`) and
doesn't repeat their values. Bob, Claudia and Tess work to this file, with the token values from
the project's DESIGN.md, only when Status says `agreed`. They never open the canvas: what they
need is written here. -->

## The problem

What was wrong, and for whom. Who it's for, and what it must do: from the brief's Outcome and
Definition of Done, or gathered by Iris in a standalone run.

## What was found

Sources used, and the ones missing:

- Project DESIGN.md: <read first | none>
- Design guide: <the path, URL or link | none>, <from `PROJECT.md` | from the developer, as
  `PROJECT.md` has no `Design guide:` line>
- Designs the developer supplied: <what they were | none>
- The existing local site: <the pages and widths looked at | no site yet>
- Otherwise: <the spec and the client's brand assets | accessible defaults, as told to the
  developer>

The P32 to P39 failures found, with values (the colours and the ratio, the size in px, the width
where it breaks), in the current page and in any supplied design. "New page, nothing to measure"
if so.

## Options considered

One line each, and why the others weren't chosen.

## The agreed design

### Layout per breakpoint

For a non-web output (a PDF), replace these with its page size and orientation, in pt or mm.

- 320px:
- 768px:
- 1280px:

### Type scale

Each text style by its token in the project's `DESIGN.md` (`{typography.body-md}`), and where it
is used. Its size (in `rem`) and line height are in that file. A token this design adds is
marked new.

### Colour tokens

Each colour by its token in the project's `DESIGN.md` (`{colors.primary}`). Its value is in that
file.

| Token | New | Used on (background token) | Contrast ratio |
|---|---|---|---|
| | | | |

### Spacing

Each spacing token used (`{spacing.md}`), and where. A token this design adds is marked new.

### States

Every state each control and section has: default, hover, focus, active, visited, error,
selected, empty, loading, confirmation. A state left out here is not designed. Name the
component tokens (`{components.button-primary-hover}`) where the project's `DESIGN.md` has them.

### Copy

The final wording, as it appears.

### Images and alt text

### Token changes

Each existing token in the project's `DESIGN.md` that this design changes or removes, with the
developer's explicit yes. "None" if none.

| Token | Old → new, or removed | Agreed by (developer, date) |
|---|---|---|
| | | |

## Constraints

The theme, blocks or templates it must fit, and the plugins that render the markup.

## Out of scope
