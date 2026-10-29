/*
 * ada-clock: shifts the page's Date to the local site's fake time.
 *
 * Runs in the page's own world at document_start, before the page's scripts, on the local hosts
 * in manifest.json only. It reads the ada_clock cookie, which ada-clock's mu-plugin sets to PHP's
 * time() while a fake time is set and expires otherwise. With no cookie it changes nothing.
 * With the cookie, new Date(), Date() and Date.now() run at the cookie's time plus the time
 * since this script ran; a Date built from explicit values is unchanged. performance.now(),
 * timers, Intl, workers and about:blank or srcdoc frames are not shifted.
 */
( function () {
	'use strict';

	// The cookie the mu-plugin sets (templates/clock/ada-clock.php, ADA_CLOCK_COOKIE).
	const COOKIE_NAME = 'ada_clock';
	const prefix = COOKIE_NAME + '=';
	let cookies;
	try {
		cookies = document.cookie;
	} catch ( error ) {
		// A frame sandboxed without allow-same-origin throws on any cookie read. That counts as
		// no cookie, so nothing reaches the site's console.
		return;
	}
	const entry = cookies.split( '; ' ).find( ( part ) => part.startsWith( prefix ) );
	if ( entry === undefined ) {
		return;
	}
	const value = entry.slice( prefix.length );
	// The mu-plugin writes time() as digits only. Any other value, empty included, leaves Date
	// native and logs nothing, the same as a cookie read that throws.
	if ( ! /^[0-9]+$/.test( value ) ) {
		return;
	}

	const NativeDate = Date;
	const offsetMs = Number( value ) * 1000 - NativeDate.now();

	function fakeNow() {
		return NativeDate.now() + offsetMs;
	}

	// A function, not a class: Date() called without new must return a string, and a class
	// constructor throws when called that way. new.target is passed on so that a page's own
	// subclass of Date still gets its own prototype.
	function AdaClockDate( ...args ) {
		if ( new.target === undefined ) {
			return new NativeDate( fakeNow() ).toString();
		}
		if ( args.length === 0 ) {
			return Reflect.construct( NativeDate, [ fakeNow() ], new.target );
		}
		return Reflect.construct( NativeDate, args, new.target );
	}

	// The native prototype is shared, not subclassed, so a Date the browser makes itself
	// (input.valueAsDate) is still instanceof Date, and d.constructor === Date holds.
	AdaClockDate.prototype = NativeDate.prototype;
	Object.defineProperty( NativeDate.prototype, 'constructor', {
		value: AdaClockDate,
		writable: true,
		enumerable: false,
		configurable: true,
	} );
	// Date.UTC and Date.parse are inherited from the native Date.
	Object.setPrototypeOf( AdaClockDate, NativeDate );
	Object.defineProperty( AdaClockDate, 'now', {
		value: function now() {
			return fakeNow();
		},
		writable: true,
		enumerable: false,
		configurable: true,
	} );
	Object.defineProperty( AdaClockDate, 'name', { value: 'Date' } );
	Object.defineProperty( AdaClockDate, 'length', { value: 7 } );

	window.Date = AdaClockDate;
	window.__adaClock = { offsetMs };
}() );
