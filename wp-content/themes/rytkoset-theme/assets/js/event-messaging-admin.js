/**
 * Tapahtumat > Viestintä (#696).
 *
 * Changing the event or status filter reloads the page with the new recipient
 * count. The page tells the user this in advance (WCAG 3.2.2), keeps a typed
 * subject and message across the reload in sessionStorage and returns focus to
 * the filter that changed. Without JavaScript the "Päivitä vastaanottajat"
 * button submits the filter instead.
 *
 * If a typed message cannot be saved, the page is not reloaded: the message
 * would be lost. The refresh button and a warning are shown instead, and the
 * send button is disabled because the send form still carries the old filter.
 */
( function () {
	'use strict';

	var DRAFT_KEY = 'rytkosetEventMessagingDraft';
	var FOCUS_KEY = 'rytkosetEventMessagingFocus';

	// No write probe here: a full storage must still let the saved draft be read.
	function storage() {
		try {
			return window.sessionStorage;
		} catch ( error ) {
			return null;
		}
	}

	// Reads and writes fail independently (quota, privacy mode); every access is guarded.
	function read( store, key ) {
		try {
			return store ? store.getItem( key ) : null;
		} catch ( error ) {
			return null;
		}
	}

	function write( store, key, value ) {
		try {
			if ( ! store ) {
				return false;
			}

			store.setItem( key, value );
			return true;
		} catch ( error ) {
			return false;
		}
	}

	function remove( store, key ) {
		try {
			if ( store ) {
				store.removeItem( key );
			}
		} catch ( error ) {
			// Nothing to clean up.
		}
	}

	function init() {
		var store = storage();
		var filter = document.getElementById( 'rytkoset-event-messaging-filter' );
		var form = document.getElementById( 'rytkoset-event-messaging-form' );
		var subject = document.getElementById( 'rytkoset-event-messaging-subject' );
		var body = document.getElementById( 'rytkoset-event-messaging-body' );
		var help = document.getElementById( 'rytkoset-event-messaging-filter-help' );
		var error = document.getElementById( 'rytkoset-event-messaging-filter-error' );
		var send = document.getElementById( 'rytkoset-event-messaging-submit' );

		if ( ! filter ) {
			return;
		}

		if ( subject && body ) {
			var saved = null;

			try {
				saved = JSON.parse( read( store, DRAFT_KEY ) || 'null' );
			} catch ( parseError ) {
				saved = null;
			}

			if ( saved ) {
				if ( '' === subject.value && saved.subject ) {
					subject.value = saved.subject;
				}

				if ( '' === body.value && saved.body ) {
					body.value = saved.body;
				}
			}

			remove( store, DRAFT_KEY );

			// A submitted message must not come back on the next visit.
			if ( form ) {
				form.addEventListener( 'submit', function () {
					remove( store, DRAFT_KEY );
				} );
			}
		}

		var focusId = read( store, FOCUS_KEY );
		remove( store, FOCUS_KEY );

		if ( focusId ) {
			var target = document.getElementById( focusId );

			if ( target ) {
				target.focus();
			}
		}

		function blockReload() {
			filter.querySelectorAll( '.hide-if-js' ).forEach( function ( node ) {
				node.classList.remove( 'hide-if-js' );
			} );

			if ( send ) {
				send.disabled = true;
			}

			if ( error ) {
				error.hidden = false;
			}
		}

		filter.querySelectorAll( 'select' ).forEach( function ( select ) {
			if ( help ) {
				select.setAttribute( 'aria-describedby', help.id );
			}

			select.addEventListener( 'change', function () {
				var hasDraft = subject && body && ( subject.value || body.value );

				if ( hasDraft && ! write( store, DRAFT_KEY, JSON.stringify( { subject: subject.value, body: body.value } ) ) ) {
					blockReload();
					return;
				}

				write( store, FOCUS_KEY, select.id );
				filter.submit();
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
