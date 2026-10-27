---
version: alpha
name: "<project or brand name>"
description: "<one sentence: what this design system is for>"
colors:
  primary: "<#RRGGBB>"
  on-primary: "<#RRGGBB>"
  surface: "<#RRGGBB>"
  on-surface: "<#RRGGBB>"
  error: "<#RRGGBB>"
typography:
  headline-lg:
    fontFamily: "<font family>"
    fontSize: "<rem>"
    fontWeight: "<number>"
    lineHeight: "<number>"
  body-md:
    fontFamily: "<font family>"
    fontSize: "<rem>"
    fontWeight: "<number>"
    lineHeight: "<number>"
  label-md:
    fontFamily: "<font family>"
    fontSize: "<rem>"
    fontWeight: "<number>"
    lineHeight: "<number>"
rounded:
  sm: "<px>"
  md: "<px>"
  full: 9999px
spacing:
  xs: "<px>"
  sm: "<px>"
  md: "<px>"
  lg: "<px>"
  xl: "<px>"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.label-md}"
    rounded: "{rounded.sm}"
    padding: "<px>"
  button-primary-hover:
    backgroundColor: "<#RRGGBB>"
---

# <project or brand name>

## Overview

<The look and feel: who it is for, its personality, and how it should feel to use.>

## Colors

<Each palette and its role, by token name. Text colours meet 4.5:1 on their background.>

## Typography

<Each text style and where it is used. Font sizes are in rem. Body text is at least 1rem, with a
line height of at least 1.5.>

## Layout

<The grid or layout model, the maximum content width, and how the spacing scale is used.>

## Elevation & Depth

<How hierarchy is shown: shadows, tonal layers or borders.>

## Shapes

<The corner radius of controls, cards and inputs, by token name.>

## Components

<Each component and its states (default, hover, focus, error), by token name.>

## Do's and Don'ts

- Do keep text contrast at 4.5:1 or more, or 3:1 for large text.
- Do keep a visible focus indicator at 3:1 or more on every control.
- <Don't …>
