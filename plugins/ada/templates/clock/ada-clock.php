<?php
/**
 * Plugin Name: Ada clock
 * Description: Lets a local site run at a fake time set with ada-clock. Does nothing unless the site's home URL is local.
 * Author: PIE
 *
 * Ada clock: copied by ada-clock install, which replaces it.
 *
 * On a local site only (home_url()'s host is localhost, 127.0.0.1, or ends in .local or .test):
 * - while wp-content/ada-clock.json exists, sets the database connection's timestamp to PHP's
 *   time() once on load, so NOW() and CURDATE() follow PHP's clock for this request;
 * - keeps logins valid for ten years (a login with Remember Me ticked also gets a browser cookie
 *   that lasts as long), so a clock moved forward doesn't log anyone out;
 * - sets the ada_clock cookie to PHP's time() while the state file exists, and expires it while
 *   it doesn't, on every front-end and admin response;
 * - answers GET /wp-json/ada-clock/v1/time with the site's time, whether it is fake, and its
 *   timezone.
 *
 * @package Ada_Clock
 */

defined( 'ABSPATH' ) || exit;

/**
 * The name of the cookie that carries PHP's time() to the browser while a fake time is set.
 */
const ADA_CLOCK_COOKIE = 'ada_clock';

/**
 * Whether this site's home URL is a local one.
 *
 * The same rule as is_local_url() in the plugin's bin/ada-clock; change both together.
 *
 * @return bool True for localhost, 127.0.0.1, or a host ending in .local or .test.
 */
function ada_clock_is_local_site() {
	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

	return 'localhost' === $host
		|| '127.0.0.1' === $host
		|| 1 === preg_match( '/^([a-z0-9-]+\.)+(local|test)$/', $host );
}

/**
 * Whether a fake time is set: ada-clock set writes the state file, and reset deletes it.
 *
 * @return bool True while wp-content/ada-clock.json exists.
 */
function ada_clock_is_fake() {
	return file_exists( WP_CONTENT_DIR . '/ada-clock.json' );
}

/**
 * Logins stay valid for ten years, so a login survives the clock jumping forward.
 *
 * WordPress uses this lifetime for the login's expiry on the server, and for the browser cookie's
 * expiry only when Remember Me is ticked; otherwise the browser cookie is a session cookie.
 *
 * @return int The auth cookie's lifetime in seconds.
 */
function ada_clock_auth_cookie_expiration() {
	return 10 * YEAR_IN_SECONDS;
}

/**
 * Sets the ada_clock cookie to PHP's time() while a fake time is set, and expires it otherwise.
 *
 * A session cookie on path /, readable by scripts, so the browser extension can read it before
 * the page's own scripts run.
 *
 * @return void
 */
function ada_clock_send_cookie() {
	if ( ada_clock_is_fake() ) {
		setcookie( ADA_CLOCK_COOKIE, (string) time(), 0, '/' );
	} else {
		setcookie( ADA_CLOCK_COOKIE, '', time() - YEAR_IN_SECONDS, '/' );
	}
}

/**
 * Registers GET ada-clock/v1/time.
 *
 * The permission callback is the local-site test: the route exists only on a local site, and
 * needs no login there.
 *
 * @return void
 */
function ada_clock_register_route() {
	register_rest_route(
		'ada-clock/v1',
		'/time',
		array(
			'methods'             => 'GET',
			'callback'            => 'ada_clock_time',
			'permission_callback' => 'ada_clock_is_local_site',
		)
	);
}

/**
 * The site's time, whether it is fake, and the site's timezone.
 *
 * @return WP_REST_Response The answer.
 */
function ada_clock_time() {
	return rest_ensure_response(
		array(
			'now'      => time(),
			'fake'     => ada_clock_is_fake(),
			'timezone' => wp_timezone_string(),
		)
	);
}

// On any other host the file stops here: no SET, no filter, no cookie, no route.
if ( ! ada_clock_is_local_site() ) {
	return;
}

// $wpdb connects before mu-plugins load, so this runs before any of the request's own queries.
if ( ada_clock_is_fake() ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- sets the connection's clock; no WordPress API does, and there is no data to cache.
	$wpdb->query( $wpdb->prepare( 'SET timestamp = %d', time() ) );
}

add_filter( 'auth_cookie_expiration', 'ada_clock_auth_cookie_expiration' );
add_action( 'send_headers', 'ada_clock_send_cookie' );
add_action( 'admin_init', 'ada_clock_send_cookie' );
add_action( 'rest_api_init', 'ada_clock_register_route' );
