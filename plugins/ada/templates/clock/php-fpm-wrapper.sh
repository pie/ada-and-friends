#!/bin/sh
# ada-clock: php-fpm wrapper, written by ada-clock install, which replaces it.
# Local starts php-fpm with paths under ~/.config/Local/run/<site ID>/. When that site has a clock
# file in @CLOCK_DIR@ and libfaketime is present, php-fpm.real runs under libfaketime at the
# offset in that file; otherwise it runs unchanged. ada-clock uninstall puts php-fpm.real back
# as php-fpm when the last site using this PHP is uninstalled.
real="$(dirname "$0")/php-fpm.real"
lib='@LIBFAKETIME@'
clocks='@CLOCK_DIR@'
id=''
for arg in "$@"; do
	case "$arg" in
		*/Local/run/*/*)
			id="${arg#*/Local/run/}"
			id="${id%%/*}"
			;;
	esac
done
# A site ID is letters, digits, - and _ only, so it can't name a path outside the clock folder.
case "$id" in
	'' | *[!A-Za-z0-9_-]*) id='' ;;
esac
if [ -n "$id" ] && [ -f "$clocks/$id" ] && [ -f "$lib" ]; then
	LD_PRELOAD="$lib${LD_PRELOAD:+ $LD_PRELOAD}"
	FAKETIME_TIMESTAMP_FILE="$clocks/$id"
	FAKETIME_NO_CACHE=1
	FAKETIME_DONT_FAKE_MONOTONIC=1
	export LD_PRELOAD FAKETIME_TIMESTAMP_FILE FAKETIME_NO_CACHE FAKETIME_DONT_FAKE_MONOTONIC
fi
exec "$real" "$@"
