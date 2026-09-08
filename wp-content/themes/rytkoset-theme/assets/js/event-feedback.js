/**
 * Tapahtumapalautteen merkkilaskuri.
 *
 * Progressiivinen parannus: `maxlength` hoitaa varsinaisen rajoituksen jo
 * ilman skriptiä, ja pituusraja kerrotaan ruudunlukijalle staattisessa
 * `aria-describedby`-tekstissä. Tämä skripti päivittää vain visuaalisen
 * laskurin, joka on merkitty `aria-hidden`-attribuutilla, jottei jokainen
 * näppäinpainallus keskeytä ruudunlukijaa.
 */
(function () {
	'use strict';

	function initCounter( textarea ) {
		var group = textarea.closest( '.event-feedback-q' );

		if ( ! group ) {
			return;
		}

		var output = group.querySelector( '[data-feedback-count-current]' );

		if ( ! output ) {
			return;
		}

		var update = function () {
			output.textContent = String( textarea.value.length );
		};

		textarea.addEventListener( 'input', update );
		update();
	}

	function init() {
		var textareas = document.querySelectorAll( '.event-feedback-form textarea[maxlength]' );

		Array.prototype.forEach.call( textareas, initCounter );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
