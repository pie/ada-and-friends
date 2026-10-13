#!/usr/bin/env python3
"""Sets a release's version, for the Release workflow (.github/workflows/release.yml).

    release-bump.py <version> <notes-file>

- plugins/ada/.claude-plugin/plugin.json gets "version": "<version>".
- CHANGELOG.md: what is under "## Unreleased" moves under "## <version>, <date>", and a fresh
  Unreleased ("Nothing yet.") goes above it.
- <notes-file> gets that section's text, for the GitHub release.

Fails, changing nothing, if there is nothing under Unreleased: a release with no entries is
more likely a mislabelled PR than a release.
"""
import datetime
import json
import re
import sys

PLUGIN = 'plugins/ada/.claude-plugin/plugin.json'
CHANGELOG = 'CHANGELOG.md'
EMPTY = 'Nothing yet.'


def main(version, notes_file):
    if not re.fullmatch(r'\d+\.\d+\.\d+', version):
        sys.exit(f'release-bump: not a version: {version}')

    text = open(CHANGELOG, encoding='utf-8').read()
    m = re.search(r'^## Unreleased[ \t]*\n(.*?)(?=^## |\Z)', text, re.M | re.S)
    if not m:
        sys.exit('release-bump: CHANGELOG.md has no "## Unreleased" section')
    notes = m.group(1).strip()
    if not notes or notes == EMPTY:
        sys.exit('release-bump: nothing under Unreleased in CHANGELOG.md; was the PR labelled by mistake?')

    today = datetime.date.today()
    heading = f'## {version}, {today.day} {today:%b %Y}'
    text = text[:m.start()] + f'## Unreleased\n\n{EMPTY}\n\n{heading}\n\n{notes}\n\n' + text[m.end():]

    # Only the version's value changes, so the file keeps its layout.
    plugin = open(PLUGIN, encoding='utf-8').read()
    plugin, n = re.subn(r'("version"\s*:\s*")[^"]*(")', r'\g<1>' + version + r'\g<2>', plugin)
    if n != 1:
        sys.exit(f'release-bump: expected one "version" in {PLUGIN}, found {n}')
    json.loads(plugin)

    open(CHANGELOG, 'w', encoding='utf-8', newline='\n').write(text)
    open(PLUGIN, 'w', encoding='utf-8', newline='\n').write(plugin)
    open(notes_file, 'w', encoding='utf-8', newline='\n').write(notes + '\n')
    print(f'release-bump: {version}, {today.day} {today:%b %Y}')


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit('usage: release-bump.py <version> <notes-file>')
    main(sys.argv[1], sys.argv[2])
