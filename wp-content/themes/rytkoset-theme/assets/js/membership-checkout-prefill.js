/**
 * Member row 1 checkout prefill (#521).
 *
 * Suggests the logged-in buyer's own name and email for member row 1 in the
 * WooCommerce Block Checkout. Only fields that are empty are ever filled, so
 * values the buyer has edited (or that were already saved on the checkout
 * draft order) are never overwritten.
 *
 * The checkout block can asynchronously refresh its store after mounting
 * (e.g. right after a cart change), which resets additional fields and would
 * wipe a single one-shot fill. The fill therefore keeps re-applying to fields
 * that are still empty during a short startup window, and stops permanently
 * as soon as the buyer interacts with a member 1 field.
 */
(function () {
  const prefill = window.rytkosetMembershipPrefill;

  if (!prefill || typeof prefill !== 'object') return;
  if (!window.wp || !window.wp.data) return;

  const fieldIds = Object.keys(prefill).filter((fieldId) => prefill[fieldId]);
  if (!fieldIds.length) return;

  const inputId = (fieldId) => 'order-' + fieldId.replace(/\//g, '-');
  const inputIds = fieldIds.map(inputId);
  const deadline = Date.now() + 15000;
  let timer = 0;

  const stop = () => window.clearInterval(timer);

  // The buyer touched a member 1 field: it is theirs now, never fill again.
  document.addEventListener(
    'input',
    (event) => {
      if (event.isTrusted && event.target && inputIds.indexOf(event.target.id) !== -1) {
        stop();
      }
    },
    true
  );

  timer = window.setInterval(() => {
    if (Date.now() > deadline) {
      stop();
      return;
    }

    const checkout = window.wp.data.select('wc/store/checkout');
    if (!checkout || typeof checkout.getAdditionalFields !== 'function') return;

    // A freshly created draft order can hydrate an additionalFields object
    // without the member keys at all, so their absence must not block the
    // fill; the rendered input is the signal that the field is in use.
    const fields = checkout.getAdditionalFields() || {};

    const updates = {};
    fieldIds.forEach((fieldId) => {
      const input = document.getElementById(inputId(fieldId));
      if (!fields[fieldId] && input && !input.value) {
        updates[fieldId] = prefill[fieldId];
      }
    });

    if (Object.keys(updates).length) {
      window.wp.data.dispatch('wc/store/checkout').setAdditionalFields(updates);
    }
  }, 300);
})();
