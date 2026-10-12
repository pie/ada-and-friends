<?php
/**
 * Bootstrap for the unit tests: no WordPress, no database. WordPress and WooCommerce functions
 * that the code under test calls are stubbed per test with Brain Monkey (TestCase sets the
 * common ones). Only files whose top level defines functions, classes and constants can be
 * loaded here; a file that calls WordPress while loading needs a stub first.
 *
 * @package {{PACKAGE}}
 */

require_once dirname( __DIR__, 2 ) . '/tools/vendor/autoload.php';

define( 'ABSPATH', '/tmp/wordpress/' );
define( 'TESTS_CODE_DIR', dirname( __DIR__, 2 ) . '/{{CODE_DIR}}' );

// Constants the code under test expects, which are normally defined by a file that hooks into
// WordPress as it loads, go here, e.g. define( 'Project\DATE_FORMAT', 'Y-m-d' );

require_once __DIR__ . '/TestCase.php';

// The files under test, e.g. require_once TESTS_CODE_DIR . '/inc/business-rules.php';
