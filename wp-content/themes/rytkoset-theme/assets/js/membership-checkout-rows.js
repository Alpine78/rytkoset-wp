/**
 * Dynamic member rows for the family membership checkout (#520).
 *
 * The member fields are server-registered WooCommerce Block Checkout
 * additional fields whose visibility follows the `member_row_count` value in
 * the `rytkoset_membership` Store API cart extension. This script renders the
 * "+ Lisää jäsen" button and per-row remove buttons, and posts the desired
 * row count to the server with `extensionCartUpdate`; the server clamps the
 * count and returns it in the cart response, which makes the checkout block
 * mount/unmount the rows reactively.
 *
 * The checkout block re-renders its field list whenever the row count or
 * other checkout state changes, which unmounts injected controls. All UI is
 * therefore (re-)ensured from an idempotent render pass driven by a store
 * subscription and a DOM observer instead of being injected once.
 */
(function () {
	const config = window.rytkosetMembershipRows;

	if ( ! config || ! window.wp || ! window.wp.data ) {
		return;
	}

	const namespace = config.namespace;
	const i18n = config.i18n || {};
	const sprintf = ( template, ...args ) => {
		let index = 0;
		return String( template ).replace( /%(\d+\$)?d/g, ( match, position ) => {
			const argIndex = position ? parseInt( position, 10 ) - 1 : index++;
			return String( args[ argIndex ] );
		} );
	};
	const setText = ( element, value ) => {
		const text = String( value || '' );

		if ( element.textContent !== text ) {
			element.textContent = text;
		}
	};

	const fieldId = ( row, part ) => 'rytkoset/member_' + row + '_' + part;
	const inputId = ( row, part ) => 'order-rytkoset-member_' + row + '_' + part;

	const getContainer = () => document.querySelector( '#order.wc-block-components-address-form' );

	const getExtensionData = () => {
		const cartStore = window.wp.data.select( 'wc/store/cart' );

		if ( ! cartStore || typeof cartStore.getCartData !== 'function' ) {
			return null;
		}

		const cart = cartStore.getCartData();
		const extension = cart && cart.extensions && cart.extensions[ namespace ];

		if ( ! extension ) {
			return null;
		}

		return {
			count: parseInt( extension.member_row_count, 10 ) || 0,
			max: parseInt( extension.member_row_max, 10 ) || 0,
		};
	};

	let pendingUpdate = false;
	let updateError = '';

	const postRowCount = ( rows ) => {
		const blocksCheckout = window.wc && window.wc.blocksCheckout;

		if ( ! blocksCheckout || typeof blocksCheckout.extensionCartUpdate !== 'function' ) {
			updateError = i18n.error;
			ensureUi();
			return Promise.resolve( false );
		}

		pendingUpdate = true;
		updateError = '';
		ensureUi();

		return blocksCheckout
			.extensionCartUpdate( { namespace, data: { member_rows: rows } } )
			.then( () => true, () => false )
			.then( ( succeeded ) => {
				pendingUpdate = false;
				updateError = succeeded ? '' : i18n.error;
				ensureUi();
				return succeeded;
			} );
	};

	// --- Injected UI -------------------------------------------------------

	let controls = null;
	let addButton = null;
	let countLabel = null;
	let maxNote = null;
	let errorNote = null;
	let liveRegion = null;

	const announce = ( text ) => {
		if ( ! liveRegion ) {
			return;
		}

		liveRegion.textContent = '';
		window.setTimeout( () => {
			liveRegion.textContent = text;
		}, 60 );
	};

	const focusWhenPresent = ( elementId, fallback ) => {
		let tries = 0;
		const attempt = () => {
			const el = document.getElementById( elementId );

			if ( el ) {
				el.focus();
				return;
			}

			tries += 1;

			if ( tries < 40 ) {
				window.setTimeout( attempt, 50 );
			} else if ( fallback ) {
				fallback.focus();
			}
		};

		attempt();
	};

	const addRow = () => {
		const data = getExtensionData();

		if ( ! data || pendingUpdate || data.count >= data.max ) {
			return;
		}

		const next = data.count + 1;

		postRowCount( next ).then( ( succeeded ) => {
			if ( ! succeeded ) {
				announce( i18n.error );
				return;
			}

			announce( sprintf( i18n.added, next ) );
			focusWhenPresent( inputId( next, 'name' ), addButton );
		} );
	};

	const removeRow = ( row ) => {
		const data = getExtensionData();

		if ( ! data || pendingUpdate || row < 2 || row > data.count ) {
			return;
		}

		// Compact rows row+1..count one step up and clear the last row, so the
		// removed row's data can never end up on the order.
		const checkoutStore = window.wp.data.select( 'wc/store/checkout' );
		const fields = ( checkoutStore && checkoutStore.getAdditionalFields() ) || {};
		const updates = {};

		for ( let i = row; i < data.count; i++ ) {
			updates[ fieldId( i, 'name' ) ] = fields[ fieldId( i + 1, 'name' ) ] || '';
			updates[ fieldId( i, 'email' ) ] = fields[ fieldId( i + 1, 'email' ) ] || '';
		}

		updates[ fieldId( data.count, 'name' ) ] = '';
		updates[ fieldId( data.count, 'email' ) ] = '';

		window.wp.data.dispatch( 'wc/store/checkout' ).setAdditionalFields( updates );

		const newCount = data.count - 1;

		postRowCount( newCount ).then( ( succeeded ) => {
			if ( ! succeeded ) {
				window.wp.data.dispatch( 'wc/store/checkout' ).setAdditionalFields( fields );
				announce( i18n.error );
				return;
			}

			announce( sprintf( i18n.removed, row ) );

			// Focus the remove button that now sits where the removed row was,
			// or the previous row's remove button, or the add button.
			const focusRow = Math.min( row, newCount );
			const target = focusRow >= 2
				? document.querySelector( '.rytkoset-member-remove[data-member-row="' + focusRow + '"]' )
				: null;

			( target || addButton || document.body ).focus();
		} );
	};

	const trashIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3"/></svg>';
	const plusIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';

	const buildRemoveButton = ( row ) => {
		const button = document.createElement( 'button' );

		button.type = 'button';
		button.className = 'rytkoset-member-remove';
		button.dataset.memberRow = String( row );
		button.innerHTML = trashIcon;
		button.setAttribute( 'aria-label', sprintf( i18n.remove, row ) );
		button.title = sprintf( i18n.remove, row );
		button.addEventListener( 'click', () => removeRow( row ) );

		return button;
	};

	const buildControls = () => {
		controls = document.createElement( 'div' );
		controls.className = 'rytkoset-member-add';

		addButton = document.createElement( 'button' );
		addButton.type = 'button';
		addButton.className = 'rytkoset-member-add__btn';
		addButton.innerHTML = plusIcon;
		addButton.appendChild( document.createTextNode( ' ' + i18n.add ) );
		addButton.addEventListener( 'click', addRow );

		countLabel = document.createElement( 'span' );
		countLabel.className = 'rytkoset-member-count';

		maxNote = document.createElement( 'span' );
		maxNote.className = 'rytkoset-member-max-note';

		errorNote = document.createElement( 'span' );
		errorNote.className = 'rytkoset-member-error';
		errorNote.setAttribute( 'role', 'alert' );

		liveRegion = document.createElement( 'div' );
		liveRegion.className = 'screen-reader-text';
		liveRegion.setAttribute( 'aria-live', 'polite' );

		controls.appendChild( addButton );
		controls.appendChild( countLabel );
		controls.appendChild( maxNote );
		controls.appendChild( errorNote );
		controls.appendChild( liveRegion );
	};

	let heading = null;

	const buildHeading = ( isFamily ) => {
		const wrapper = document.createElement( 'div' );
		wrapper.className = 'rytkoset-checkout-fields-heading rytkoset-member-heading';

		const title = document.createElement( 'h3' );
		title.className = 'rytkoset-checkout-fields-heading__title';
		title.textContent = i18n.heading;

		const intro = document.createElement( 'p' );
		intro.className = 'rytkoset-checkout-fields-heading__intro';
		intro.textContent = isFamily ? i18n.introFamily : i18n.introSingle;

		wrapper.appendChild( title );
		wrapper.appendChild( intro );

		return wrapper;
	};

	const ensureHeading = ( container, data ) => {
		const firstInput = document.getElementById( inputId( 1, 'name' ) );
		const firstWrapper = firstInput ? firstInput.closest( '.wc-block-components-text-input' ) : null;

		if ( ! firstWrapper || firstWrapper.parentNode !== container ) {
			if ( heading && heading.parentNode ) {
				heading.parentNode.removeChild( heading );
			}
			return;
		}

		const isFamily = data.max > 1;

		if ( ! heading || heading.dataset.family !== String( isFamily ) ) {
			const fresh = buildHeading( isFamily );
			fresh.dataset.family = String( isFamily );

			if ( heading && heading.parentNode ) {
				heading.parentNode.replaceChild( fresh, heading );
			}

			heading = fresh;
		}

		if ( heading.nextElementSibling !== firstWrapper ) {
			firstWrapper.insertAdjacentElement( 'beforebegin', heading );
		}
	};

	const ensureUi = () => {
		const container = getContainer();
		const data = getExtensionData();

		if ( ! container || ! data || data.max < 1 ) {
			if ( controls && controls.parentNode ) {
				controls.parentNode.removeChild( controls );
			}
			if ( heading && heading.parentNode ) {
				heading.parentNode.removeChild( heading );
			}
			return;
		}

		ensureHeading( container, data );

		if ( data.max < 2 ) {
			if ( controls && controls.parentNode ) {
				controls.parentNode.removeChild( controls );
			}
			return;
		}

		if ( ! controls ) {
			buildControls();
		}

		// The controls sit inside the field container right after the last
		// visible member row, so the DOM tab order stays member fields → add
		// button → later fields (e.g. Tampere participants). React inserts its
		// own nodes relative to nodes it owns, so a foreign sibling is safe;
		// this pass re-anchors the controls whenever the row count changes.
		const lastEmailInput = document.getElementById( inputId( data.count, 'email' ) );
		const lastWrapper = lastEmailInput ? lastEmailInput.closest( '.wc-block-components-text-input' ) : null;

		if ( lastWrapper && lastWrapper.parentNode === container ) {
			if ( controls.previousElementSibling !== lastWrapper ) {
				lastWrapper.insertAdjacentElement( 'afterend', controls );
			}
		} else if ( controls.previousElementSibling !== container ) {
			container.insertAdjacentElement( 'afterend', controls );
		}

		setText( countLabel, sprintf( i18n.count, data.count, data.max ) );

		const atMax = data.count >= data.max;
		addButton.disabled = atMax || pendingUpdate;
		setText( maxNote, atMax ? i18n.maxNote : '' );
		setText( errorNote, updateError );

		const busy = pendingUpdate ? 'true' : 'false';
		if ( controls.getAttribute( 'aria-busy' ) !== busy ) {
			controls.setAttribute( 'aria-busy', busy );
		}

		// Remove buttons for rows 2..count, injected inside the email field
		// wrapper so a row unmount always takes its button along.
		for ( let row = 2; row <= data.max; row++ ) {
			const input = document.getElementById( inputId( row, 'email' ) );
			const wrapper = input ? input.closest( '.wc-block-components-text-input' ) : null;

			if ( ! wrapper || row > data.count ) {
				continue;
			}

			let removeButton = wrapper.querySelector( '.rytkoset-member-remove' );

			if ( ! removeButton ) {
				removeButton = buildRemoveButton( row );
				wrapper.appendChild( removeButton );
			}

			removeButton.disabled = pendingUpdate;
		}
	};

	// --- Render loop -------------------------------------------------------

	let scheduled = false;
	const scheduleEnsure = () => {
		if ( scheduled ) {
			return;
		}

		scheduled = true;
		window.requestAnimationFrame( () => {
			scheduled = false;
			ensureUi();
		} );
	};

	window.wp.data.subscribe( scheduleEnsure );

	const observer = new MutationObserver( scheduleEnsure );
	observer.observe( document.body, { childList: true, subtree: true } );

	scheduleEnsure();
})();
