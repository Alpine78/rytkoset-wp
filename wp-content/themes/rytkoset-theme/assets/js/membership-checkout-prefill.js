/**
 * Member row 1 account-field synchronization (#521, #661).
 *
 * Keeps the logged-in buyer's own name and email as the authoritative values
 * for member row 1 in the WooCommerce Block Checkout. The rendered inputs are
 * visible but disabled; the values remain in the checkout data store so the
 * Store API still receives required member fields.
 *
 * The checkout block can asynchronously refresh its store after mounting
 * (e.g. right after a cart change), which resets additional fields and would
 * wipe a single one-shot update. Synchronization therefore keeps re-applying
 * the account values during a short startup window.
 */
(function () {
  const prefill = window.rytkosetMembershipPrefill;

  if (!prefill || typeof prefill !== 'object') return;
  if (!window.wp || !window.wp.data) return;

  const fieldIds = Object.keys(prefill).filter((fieldId) => prefill[fieldId]);
  if (!fieldIds.length) return;

  const inputId = (fieldId) => 'order-' + fieldId.replace(/\//g, '-');
  const deadline = Date.now() + 15000;
  let timer = 0;

  const stop = () => window.clearInterval(timer);

  const synchronize = () => {
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

      if (fields[fieldId] !== prefill[fieldId]) {
        updates[fieldId] = prefill[fieldId];
      }

      if (input) {
        input.readOnly = true;
        input.disabled = true;
        input.setAttribute('aria-disabled', 'true');
        input.setAttribute('data-rytkoset-account-field', 'true');
      }
    });

    if (Object.keys(updates).length) {
      window.wp.data.dispatch('wc/store/checkout').setAdditionalFields(updates);
    }
  };

  synchronize();
  timer = window.setInterval(synchronize, 300);
})();
