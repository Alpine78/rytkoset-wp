/**
 * Tapahtumapalautteen merkkilaskuri ja kaksoislähetyksen estosuoja.
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

	function initSubmissionGuard( form ) {
		form.addEventListener( 'submit', function ( event ) {
			if ( 'true' === form.dataset.feedbackSubmitting ) {
				event.preventDefault();
				return;
			}

			form.dataset.feedbackSubmitting = 'true';
			form.setAttribute( 'aria-busy', 'true' );

			var button = form.querySelector( 'button[type="submit"]' );
			var status = form.querySelector( '[data-feedback-submit-status]' );

			if ( button ) {
				var label = button.querySelector( '[data-feedback-submit-label]' );
				var submittingLabel = button.getAttribute( 'data-submitting-label' );
				var submittingStatus = button.getAttribute( 'data-submitting-status' );

				button.disabled = true;

				if ( label && submittingLabel ) {
					label.textContent = submittingLabel;
				}
			}

			if ( status && submittingStatus ) {
				status.textContent = submittingStatus;
			}
		} );
	}

	function init() {
		var textareas = document.querySelectorAll( '.event-feedback-form textarea[maxlength]' );
		var forms = document.querySelectorAll( '.event-feedback-form' );

		Array.prototype.forEach.call( textareas, initCounter );
		Array.prototype.forEach.call( forms, initSubmissionGuard );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
