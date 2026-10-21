---
name: standards-accessibility
description: PIE's visual accessibility standards (P32 to P39), to WCAG 2.2 level AA - text and non-text contrast, colour, text size and spacing, reflow, focus, target size, motion and images of text. Preloaded into Bob and Claudia; load it before designing or reviewing a design. The other P rules are in ada:standards; a project's own rules (S numbers) are in its .claude/team/STANDARDS.md.
user-invocable: false
---

# PIE standards: accessibility

What a page must do so that people with low vision, colour blindness, tremor or motion
sensitivity can use it. The bar is **WCAG 2.2 level AA**, because that's what UK accessibility
law and public-sector clients expect, and what an audit will test against. The numbering,
citing and severity are the same as in `ada:standards`.

- **Severity:** a WCAG AA failure is "Must fix". The PIE extras, marked **PIE**, go further than
  WCAG and are "Should fix".
- **A design applies these too.** Colours, sizes and states are chosen in the design, not added
  later in the code. A design that fails a rule is sent back before anything is built.
- **Every state is checked:** default, hover, focus, active, visited, error, selected, and on
  each background the component is used on. Disabled controls and logos are exempt from
  contrast.
- **How to measure:** work out the contrast ratio from the actual colours (the CSS, `theme.json`
  or the design's tokens), not by eye. In a browser, use the DevTools contrast checker or a
  script that reads the computed colours. When the project's palette has been checked, list the
  pairs that pass in `.claude/team/STANDARDS.md`, so later reviews don't have to measure again.

## Contrast

**P32. Text contrast is at least 4.5:1, or 3:1 for large text** (WCAG 1.4.3). Large text means
at least 24px, or at least 18.66px when bold. This covers body text, links, buttons, labels,
placeholder text that carries meaning, error messages, and text inside images or video.

- Text over a photo or gradient is measured against its **lightest (or darkest) part**, not the
  average. To pass, add an overlay or a solid panel behind the text, or move the text off the
  image.
- Brand colours that fail as text can still be used for borders, backgrounds and decoration.
  Pick a darker shade for text and record it as a token.

**P33. Controls, icons and focus rings have at least 3:1 contrast** (WCAG 1.4.11, 2.4.7, 2.4.11).
Whatever someone needs to see to use the control must reach 3:1 against what's next to it:
an input's border, a checkbox, a toggle's state, a meaningful icon, a chart's lines and
segments.

- **Focus is always visible.** Never set `outline: none` or `outline: 0` without putting an
  indicator of at least 3:1 in its place. Use `:focus-visible`.
- **Focus is never hidden.** A sticky header, cookie banner or chat widget must not cover the
  focused element. Use `scroll-padding-top` to keep the header clear.

## Colour

**P34. Colour is never the only signal** (WCAG 1.4.1). Whatever a colour tells you has to be
shown some other way as well.

- **Links in body text are underlined.** Links in navigation, buttons and cards don't need an
  underline, because where they sit already shows they're links.
- **Errors and required fields** say so in words or with an icon, not just a red border.
- **Charts and status badges** use labels, patterns or shapes as well as colour.
- **The selected or current item** (a tab, page or filter) is marked by more than its colour:
  weight, an underline, a border, or `aria-current`.

## Text and layout

**P35. Text resizes and respaces without breaking** (WCAG 1.4.4, 1.4.12).

- Font sizes are set in `rem` (or `clamp()` with a `rem` in it), so a reader's browser setting
  and zoom take effect. Never `px` alone for type.
- At 200% zoom, nothing is cut off or overlaps. Containers that hold text have no fixed height;
  use `min-height`.
- Text still fits when a reader overrides the spacing to: line height 1.5, paragraph spacing 2×
  the font size, letter spacing 0.12em, word spacing 0.16em.
- The viewport meta tag never has `maximum-scale=1` or `user-scalable=no`.
- **PIE:** body text is at least 16px (`1rem`), and no text is smaller than 14px (`0.875rem`),
  legal small print included. Body text has a line height of at least 1.5, and lines are at
  most about 80 characters long. WCAG sets no minimum font size; this is PIE's floor.

**P36. Pages reflow to 320px wide** (WCAG 1.4.10, 1.3.4). At 320 CSS pixels (a 1280px screen at
400% zoom) the content fits in one column with no horizontal scroll. Data tables, maps and
diagrams are exempt, but a table scrolls inside its own container, not the page. Don't lock the
page to portrait or landscape.

**P37. Targets are at least 24×24px** (WCAG 2.5.8). Any button, link or control that isn't a
link inside a sentence is at least 24×24 CSS pixels, or has enough space around it that a
24px circle centred on it doesn't touch another target.

- **PIE:** the main touch controls (menu toggle, add to basket, form submit, quantity steppers,
  carousel arrows) are at least 44×44px on mobile.

## Movement and things that appear

**P38. Movement can be stopped, and pop-ups behave** (WCAG 2.2.2, 2.3.1, 1.4.13).

- Anything that moves, scrolls or updates by itself for more than 5 seconds (carousels, tickers,
  background video) has a visible pause control. Nothing flashes more than three times a
  second.
- Content that appears on hover or focus (tooltips, mega-menus) stays up while the pointer moves
  onto it, stays until the reader moves away, and closes with Esc.
- **PIE:** under `prefers-reduced-motion: reduce`, parallax, auto-playing carousels and large
  transitions are turned off, and animations shrink to a fade.

## Images

**P39. Text is real text, not part of an image** (WCAG 1.4.5, 1.1.1). Headings, offers, prices
and buttons are HTML text styled with CSS, so they resize, reflow and can be translated. Logos
are the only exception. Every meaningful image has alt text that says what it shows or does.
Decorative images have empty alt text (`alt=""`) or are set as CSS backgrounds.
