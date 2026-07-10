/**
 * Tampere 2026 participant card headers on the checkout (#520).
 *
 * The participant fields are server-registered Block Checkout additional
 * fields (name, diet, buffet per participant). This script injects a header
 * with the participant number, participant type (adult/child variation) and
 * unit price above each participant's field group, using the participant
 * lines published in the `rytkoset_tampere_2026` Store API cart extension.
 * The card framing itself is CSS (shop.css).
 *
 * The checkout block re-renders its field list on state changes, which
 * unmounts injected nodes, so the header is (re-)ensured from an idempotent
 * render pass like the membership row controls.
 */
(function () {
	const config = window.rytkosetTampereParticipants;

	if ( ! config || ! window.wp || ! window.wp.data ) {
		return;
	}

	const namespace = config.namespace;
	const i18n = config.i18n || {};

	const getParticipants = () => {
		const cartStore = window.wp.data.select( 'wc/store/cart' );

		if ( ! cartStore || typeof cartStore.getCartData !== 'function' ) {
			return [];
		}

		const cart = cartStore.getCartData();
		const extension = cart && cart.extensions && cart.extensions[ namespace ];

		return extension && Array.isArray( extension.participants ) ? extension.participants : [];
	};

	const buildHead = ( index, line ) => {
		const head = document.createElement( 'div' );
		head.className = 'rytkoset-participant-head';
		head.setAttribute( 'aria-hidden', 'true' );

		const num = document.createElement( 'span' );
		num.className = 'rytkoset-participant-head__num';
		num.textContent = String( index );

		const title = document.createElement( 'span' );
		title.className = 'rytkoset-participant-head__title';
		title.textContent = String( i18n.title ).replace( '%d', String( index ) );

		head.appendChild( num );
		head.appendChild( title );

		if ( line && line.type ) {
			const type = document.createElement( 'span' );
			type.className = 'rytkoset-participant-head__type';
			type.textContent = line.type;
			head.appendChild( type );
		}

		if ( line && line.price ) {
			const price = document.createElement( 'span' );
			price.className = 'rytkoset-participant-head__price';
			price.textContent = line.price;
			head.appendChild( price );
		}

		return head;
	};

	let heading = null;

	const ensureHeading = () => {
		const firstInput = document.getElementById( 'order-rytkoset-participant_1_name' );
		const firstWrapper = firstInput ? firstInput.closest( '.wc-block-components-text-input' ) : null;

		if ( ! firstWrapper ) {
			if ( heading && heading.parentNode ) {
				heading.parentNode.removeChild( heading );
			}
			return;
		}

		if ( ! heading ) {
			heading = document.createElement( 'div' );
			heading.className = 'rytkoset-checkout-fields-heading rytkoset-participant-heading';

			const title = document.createElement( 'h3' );
			title.className = 'rytkoset-checkout-fields-heading__title';
			title.textContent = i18n.heading;

			const intro = document.createElement( 'p' );
			intro.className = 'rytkoset-checkout-fields-heading__intro';
			intro.textContent = i18n.intro;

			heading.appendChild( title );
			heading.appendChild( intro );
		}

		if ( heading.nextElementSibling !== firstWrapper ) {
			firstWrapper.insertAdjacentElement( 'beforebegin', heading );
		}
	};

	const ensureUi = () => {
		const participants = getParticipants();

		ensureHeading();

		for ( let index = 1; index <= Math.max( participants.length, 10 ); index++ ) {
			const input = document.getElementById( 'order-rytkoset-participant_' + index + '_name' );
			const wrapper = input ? input.closest( '.wc-block-components-text-input' ) : null;

			if ( ! wrapper ) {
				continue;
			}

			const line = participants[ index - 1 ] || null;
			const existing = wrapper.querySelector( '.rytkoset-participant-head' );
			const head = buildHead( index, line );

			if ( existing ) {
				if ( existing.innerHTML !== head.innerHTML ) {
					existing.replaceWith( head );
				}
			} else {
				wrapper.appendChild( head );
			}
		}
	};

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
