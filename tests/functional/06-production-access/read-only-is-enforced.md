# `prod-wp` enforces read-only: inspectors that call writes are refused, the rest run behind a guard that blocks writes, mail and HTTP, and `PROD_FORBIDDEN` covers posts queries that don't name a type

- Area: 06 Production access (`prod-wp`); 12 Platforms (bash 3.2)
- Asana: https://app.asana.com/1/100438094841948/project/1204538801343792/task/1220130062605486
- Automate as: none (the Steps block is a self-checking shell script; the repo has no test harness)
- Last run: 2026-11-05, 33 PASS, 0 FAIL under bash 5.2 and bash 3.2 (see the PR)

## Precondition

- This checkout (`REPO`). The script makes its own scratch repo (a path with spaces), with a
  `prod.env` and inspectors in `tools/bughunt/`, and stand-ins for `ssh` and `wp`. The stand-in
  `wp eval-file -` runs what `prod-wp` sends in real PHP, the way WP-CLI does
  (`eval( '?>' . <stdin> )` with `$args` set), against a stand-in `$wpdb` that logs the queries
  that reach it. It never reaches a real server.
- `git`, `mktemp`, `php` (the stand-in `wp`). Without `php` the guard checks print `SKIP`.
- Run the checkout's script by path, never a `prod-wp` on the PATH (that is the installed plugin).

## Steps

1. Save the block below as `check.sh` in a scratch folder (not in this repo).
2. Run it under a current bash: `REPO=<this checkout> bash check.sh`, and under bash 3.2:
   `docker run --rm -v <this checkout>:/repo:ro -v <scratch folder>:/c bash:3.2 bash -c 'apk add -q git php >/dev/null; REPO=/repo bash /c/check.sh'`.
3. Break it on purpose, once: copy `plugins/ada` to a scratch folder, delete the `add_filter(
   'query', ...)` call from the guard in the copy's `bin/prod-wp`, and run
   `REPO=<this checkout> PLUGIN=<the copy> bash check.sh`. It must print `FAIL` lines.

```bash
#!/usr/bin/env bash
# prod-wp in a scratch repo, with stand-ins for ssh and WP-CLI: the inspector scan, the guard
# (run in real PHP against a stand-in $wpdb), the READ ONLY session for sql, and the posts rule
# under PROD_FORBIDDEN. Never reaches a server.
# Prints PASS/FAIL/SKIP per check; exits 1 if any FAIL.
# Env: REPO (this checkout), PLUGIN (plugin under test, default $REPO/plugins/ada).
set -u
REPO=${REPO:-$(git rev-parse --show-toplevel)}
PLUGIN=${PLUGIN:-$REPO/plugins/ada}
PW=$PLUGIN/bin/prod-wp
unset PROD_SSH PROD_PREFIX PROD_LOGS PROD_FORBIDDEN
W=$(mktemp -d)
fails=0
ok()   { if [ "$1" = 0 ]; then echo "PASS $2"; else echo "FAIL $2"; fails=$((fails+1)); fi; }
t()    { name=$1; shift; if "$@" >/dev/null 2>&1; then ok 0 "$name"; else ok 1 "$name"; fi; }
not()  { ! "$@"; }
# run <args...>: the checkout's prod-wp from the scratch repo, the stand-ins first on the PATH.
run()  { rm -f "$W/log/"*; (cd "$P" && PATH="$W/bin:$PATH" bash "$PW" "$@") > "$W/out" 2> "$W/err"; echo $? > "$W/rc"; }
rc()   { cat "$W/rc"; }
says() { cat "$W/out" "$W/err" | grep "$@" >/dev/null; }
sent() { [ -f "$W/log/ssh" ]; }
ran()  { grep "$@" "$W/log/queries" >/dev/null 2>&1; }

mkdir -p "$W/bin" "$W/log"
# ssh: runs the remote script locally, so the stand-in wp below answers.
cat > "$W/bin/ssh" <<'SH'
#!/usr/bin/env bash
touch "$LOG/ssh"
bash -s
SH
# wp: db query logs its SQL; eval-file - runs stdin as WP-CLI does, with a stand-in WordPress.
cat > "$W/bin/wp" <<'SH'
#!/usr/bin/env bash
case "$1 $2" in
'db query') printf '%s\n' "$3" >> "$LOG/queries" ;;
'eval-file -')
	shift 2
	cat > "$LOG/stdin.php"
	php "$STUB" "$LOG/stdin.php" "$@" ;;
*) echo "stand-in wp: $*" >> "$LOG/other" ;;
esac
SH
cat > "$W/stub.php" <<'PHP'
<?php
$GLOBALS['hooks'] = array();
function add_filter( $tag, $fn, $prio = 10, $n = 1 ) { $GLOBALS['hooks'][ $tag ][] = array( $fn, $n ); }
function apply_filters( $tag, $value, ...$more ) {
	foreach ( isset( $GLOBALS['hooks'][ $tag ] ) ? $GLOBALS['hooks'][ $tag ] : array() as $h ) {
		$value = call_user_func_array( $h[0], array_slice( array_merge( array( $value ), $more ), 0, $h[1] ) );
	}
	return $value;
}
function add_query_arg( $k, $v, $url ) { return $url . '?' . $k . '=' . $v; }
class WP_Error { public function __construct( $c, $m ) { $this->m = $m; } }
class Stub_DB {
	public function suppress_errors( $s ) { return false; }
	public function query( $q ) {
		$q = apply_filters( 'query', $q );
		if ( ! $q ) { return false; }
		file_put_contents( getenv( 'LOG' ) . '/queries', $q . "\n", FILE_APPEND );
		return 1;
	}
}
function wp_mail( $to, $subject ) {
	$pre = apply_filters( 'pre_wp_mail', null, array( 'to' => $to, 'subject' => $subject ) );
	if ( null !== $pre ) { return $pre; }
	file_put_contents( getenv( 'LOG' ) . '/mail', $subject . "\n", FILE_APPEND );
	return true;
}
function wp_remote_get( $url ) {
	$pre = apply_filters( 'pre_http_request', false, array(), $url );
	if ( false !== $pre ) { return $pre; }
	file_put_contents( getenv( 'LOG' ) . '/http', $url . "\n", FILE_APPEND );
	return array();
}
$GLOBALS['wpdb'] = new Stub_DB();
// WP-CLI's eval-file -: the arguments in $args, then the file, evaluated as WP-CLI does.
( function ( $file, $args ) { eval( '?>' . file_get_contents( $file ) ); } )( $argv[1], array_slice( $argv, 2 ) );
PHP
chmod +x "$W/bin/ssh" "$W/bin/wp"
export LOG="$W/log" STUB="$W/stub.php"

P="$W/scratch repo"
mkdir -p "$P/.claude/team" "$P/tools/bughunt"
git -C "$P" init -q
printf 'PROD_SSH=install@example.test\nPROD_PREFIX=wp_\n' > "$P/.claude/team/prod.env"
# read-it: marked, reads, prints its argument, and its comments name writes (allowed).
cat > "$P/tools/bughunt/read-it.php" <<'PHP'
<?php
/**
 * READ-ONLY. Prints an order's row. Never update_option() here.
 */
global $wpdb;
// Not wp_insert_post() either.
$wpdb->query( "SELECT * FROM `wp_posts` WHERE ID = " . (int) $args[0] );
echo 'arg=' . $args[0] . "\n";
PHP
# writes: marked, but calls update_option in code.
cat > "$P/tools/bughunt/writes.php" <<'PHP'
<?php
// READ-ONLY (it says so, but isn't)
update_option( 'x', 1 );
PHP
# sneaky: marked, hides its writes from the scan; the guard must stop them.
cat > "$P/tools/bughunt/sneaky.php" <<'PHP'
<?php
// READ-ONLY
global $wpdb;
$wpdb->query( "SELECT 1" );
$wpdb->query( "UPDATE wp_options SET option_value = 1 WHERE option_name = 'x'" );
$wpdb->query( "/* hi */ (DELETE FROM wp_posts)" );
$wpdb->query( "SET SESSION TRANSACTION READ WRITE" );
$m = 'wp_' . 'mail'; $m( 'a@example.test', 'hello' );
$h = 'wp_remote_' . 'get'; $h( 'https://example.test/' );
echo "done\n";
PHP
printf '<?php\n// nothing marked\n' > "$P/tools/bughunt/unmarked.php"
printf '<?php\n// READ-ONLY\necho "ADA_BUGHUNT_PHP";\n' > "$P/tools/bughunt/marker.php"
printf '<?php\n// READ-ONLY\n$x = add_query_arg( "a", 1, "/" );\nset_time_limit( 5 );\n' > "$P/tools/bughunt/allowed.php"

# Inspector scan: refused before anything is sent.
run inspect writes
t 'a script calling update_option is refused'        test "$(rc)" = 2
t '... with the line it calls it on'                  says -F '3:update_option'
t '... before anything is sent'                       not sent
run inspect unmarked
t 'an unmarked script is still refused'               test "$(rc)" = 2
run inspect marker
t "a script holding prod-wp's own marker is refused"  test "$(rc)" = 2
t '... before anything is sent'                       not sent

if command -v php >/dev/null 2>&1; then
	run inspect read-it 123
	t 'a read-only script runs'                           test "$(rc)" = 0
	t '... with its argument'                             says -x 'arg=123'
	t '... its SELECT reaches the database'               ran -F 'SELECT * FROM `wp_posts` WHERE ID = 123'
	t '... after the session is made READ ONLY'           test "$(head -1 "$W/log/queries")" = 'SET SESSION TRANSACTION READ ONLY'
	t '... and comments naming writes are fine'           not says 'calls something'
	run inspect allowed
	t 'add_query_arg and set_time_limit are allowed'      test "$(rc)" = 0
	run inspect sneaky
	t 'a script hiding its writes from the scan runs'     test "$(rc)" = 0
	t '... to the end'                                    says -x 'done'
	t '... its SELECT reaches the database'               ran -x 'SELECT 1'
	t '... its UPDATE does not'                           not ran 'UPDATE'
	t '... nor a DELETE behind a comment and a bracket'   not ran 'DELETE'
	t '... nor a SET that would undo READ ONLY'           not ran 'READ WRITE'
	t '... and each is reported'                          test "$(grep -c '^prod-wp: blocked a write' "$W/err")" = 3
	t '... no email is sent'                              test ! -f "$W/log/mail"
	t '... and that is reported'                          says 'prod-wp: blocked an email: hello'
	t '... no HTTP request is made'                       test ! -f "$W/log/http"
	t '... and that is reported'                          says -F 'prod-wp: blocked an HTTP request: https://example.test/'
else
	echo 'SKIP the guard checks (no php)'
fi

# sql: a READ ONLY session, and the posts rule only under PROD_FORBIDDEN.
run sql "SELECT ID FROM {prefix}posts WHERE post_content LIKE '%x%'"
t 'sql without PROD_FORBIDDEN runs a posts query that names no type' test "$(rc)" = 0
t '... in a READ ONLY session'                        ran -x "SET SESSION TRANSACTION READ ONLY; SELECT ID FROM wp_posts WHERE post_content LIKE '%x%'"
printf 'PROD_FORBIDDEN=feedback\n' >> "$P/.claude/team/prod.env"
run sql "SELECT ID FROM {prefix}posts WHERE post_content LIKE '%x%'"
t 'with PROD_FORBIDDEN, a posts query naming no type is refused' test "$(rc)" = 2
t '... before anything is sent'                       not sent
run sql "SELECT ID FROM {prefix}posts WHERE post_type = 'feedback'"
t '... a forbidden name is still refused'             test "$(rc)" = 2
run sql "SELECT ID FROM wp_posts WHERE post_type IN ('page', 'post') AND post_content LIKE '%x%'"
t '... one naming its post types runs'                test "$(rc)" = 0
run sql "SELECT post_title FROM {prefix}posts WHERE ID = 12"
t '... one naming an ID runs'                         test "$(rc)" = 0
run sql "SELECT meta_value FROM {prefix}postmeta WHERE meta_value LIKE '%@%'"
t '... a postmeta query naming no post is refused'    test "$(rc)" = 2
run sql "SELECT meta_value FROM {prefix}postmeta WHERE post_id = 12"
t '... one naming its post_id runs'                   test "$(rc)" = 0
run sql "SELECT option_value FROM {prefix}options WHERE option_name = 'x'"
t '... a query on another table is unaffected'        test "$(rc)" = 0

rm -rf "$W"
echo "$fails failed"
[ "$fails" = 0 ]
```

## Expected

Every line `PASS`, `0 failed`, exit 0, under both bashes. The source of each expectation is the
Asana task (an inspector's writes are stopped, not just discouraged; a forbidden post type can't
come back from a query that doesn't name it), the header of `bin/prod-wp`, and `API.md` (every
script exits 2 with `<script>: <message>` on stderr). `SKIP` only where `php` is missing. With
the broken copy, the `UPDATE`, `DELETE` and `READ WRITE` checks print `FAIL`.

## Observe at

The script's output and exit code. On a practice install (never a client's production), also
`prod-wp inspect` with a script that hides an `update_option()` the same way: the option is
unchanged afterwards and `prod-wp: blocked a write` is printed.

## Regression

The other `prod-wp` commands (`config`, `notes`, `option`, `actions`, `logs`) send what they did
before; an existing project's inspectors that only read still run (`tools/bughunt/README.md` has
the names that are now refused).
