/**
 * Digital magazine editor (#698): shows only the product fields that the
 * selected access mode uses. Hidden fields keep their values and are still
 * submitted, so switching the mode back restores the earlier choice. Without
 * this script every field stays visible.
 */
( function () {
	'use strict';

	function sync() {
		var select = document.getElementById( 'rytkoset_magazine_access_mode' );
		var nodes = document.querySelectorAll( '[data-access-modes]' );

		if ( ! select || ! nodes.length ) {
			return;
		}

		nodes.forEach( function ( node ) {
			var modes = ( node.getAttribute( 'data-access-modes' ) || '' ).split( /\s+/ );
			node.hidden = modes.indexOf( select.value ) === -1;
		} );
	}

	function init() {
		var select = document.getElementById( 'rytkoset_magazine_access_mode' );

		if ( ! select || select.dataset.rytkosetBound ) {
			return;
		}

		select.dataset.rytkosetBound = '1';
		select.addEventListener( 'change', sync );
		sync();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Block editor meta boxes can mount after DOMContentLoaded.
	window.addEventListener( 'load', init );
}() );
