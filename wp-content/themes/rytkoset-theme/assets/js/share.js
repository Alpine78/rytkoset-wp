/**
 * Rytköset — jakopainikkeiden ("Jaa tapahtuma") toiminnallisuus.
 *
 * - Web Share API (navigator.share) kun saatavilla
 * - Fallback: kopioi linkki leikepöydälle + statusviesti
 * - Erillinen "Kopioi linkki" -nappi (data-share-copy)
 * - Jos selain ei tue natiivijakoa, .share saa .no-webshare-luokan,
 *   joka piilottaa "Jaa laitteella" -kutsut CSS:llä
 */
(function () {
  'use strict';

  var hasNativeShare =
    typeof navigator !== 'undefined' && typeof navigator.share === 'function';

  function flash(root, msg, ms) {
    var out = root.querySelector('.share__status');
    if (!out) return;
    out.hidden = false;
    out.textContent = msg;
    clearTimeout(out._t);
    out._t = setTimeout(function () {
      out.hidden = true;
      out.textContent = '';
    }, ms || 2400);
  }

  function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    // Fallback < iOS 13.4 / vanha Edge
    return new Promise(function (resolve, reject) {
      try {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        var ok = document.execCommand('copy');
        document.body.removeChild(ta);
        ok ? resolve() : reject(new Error('execCommand returned false'));
      } catch (e) {
        reject(e);
      }
    });
  }

  function copyUrl(root) {
    var url = root.getAttribute('data-share-url') || location.href;
    copyToClipboard(url).then(
      function () {
        flash(root, 'Linkki kopioitu leikepöydälle.');
      },
      function () {
        flash(root, 'Kopioiminen ei onnistunut. Kopioi käsin: ' + url, 6000);
      }
    );
  }

  function nativeShare(root) {
    var data = {
      title: root.getAttribute('data-share-title') || document.title,
      url: root.getAttribute('data-share-url') || location.href,
    };
    if (!hasNativeShare) {
      copyUrl(root);
      return;
    }
    navigator.share(data).catch(function (err) {
      // AbortError = käyttäjä sulki valikon. Älä tee mitään.
      if (err && err.name !== 'AbortError') {
        copyUrl(root);
      }
    });
  }

  function initRoot(root) {
    // Idempotenssi: älä sido kuuntelijoita kahdesti.
    if (root.dataset.shareBound === '1') return;
    root.dataset.shareBound = '1';

    // CSS piilottaa "Jaa laitteella" -kutsut .no-webshare-luokalla,
    // jos selain ei tue natiivijakoa.
    if (!hasNativeShare) {
      root.classList.add('no-webshare');
    }

    var nativeBtns = root.querySelectorAll('[data-share-native]');
    Array.prototype.forEach.call(nativeBtns, function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        nativeShare(root);
      });
    });

    var copyBtns = root.querySelectorAll('[data-share-copy]');
    Array.prototype.forEach.call(copyBtns, function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        copyUrl(root);
      });
    });
  }

  function init() {
    var roots = document.querySelectorAll('.share');
    Array.prototype.forEach.call(roots, initRoot);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
