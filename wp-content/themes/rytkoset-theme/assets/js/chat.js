/**
 * AI-tukichatin widget (#413).
 *
 * Kelluva painike + paneeli, joka juttelee REST-reitin `rytkoset/v1/chat` kautta.
 * Keskusteluhistoria pidetään VAIN muistissa (ei localStorage/sessionStorage/keksejä).
 * Mallin vastaus renderöidään turvallisesti DOMiin ilman innerHTML:ää: teksti
 * textContent/createTextNode-solmuina, ja vain oman sivuston paljaat
 * https-osoitteet muutetaan linkeiksi (createElement('a') + validoitu href).
 */
(function () {
  'use strict';

  var config = window.rytkosetChatConfig;
  if (!config || !config.restUrl) {
    return;
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-rytkoset-chat]');
    if (!root) {
      return;
    }

    var toggle = root.querySelector('[data-rytkoset-chat-toggle]');
    var panel = root.querySelector('[data-rytkoset-chat-panel]');
    var closeBtn = root.querySelector('[data-rytkoset-chat-close]');
    var form = root.querySelector('[data-rytkoset-chat-form]');
    var input = root.querySelector('[data-rytkoset-chat-input]');
    var log = root.querySelector('[data-rytkoset-chat-log]');
    var sendBtn = root.querySelector('[data-rytkoset-chat-send]');

    if (!toggle || !panel || !form || !input || !log) {
      return;
    }

    // Keskusteluhistoria vain muistissa. Ei web storagea, ei keksejä.
    var history = [];
    var isSending = false;
    var typingEl = null;

    // JS on käytettävissä: paljasta widget (ilman JS:ää se pysyy piilossa).
    root.hidden = false;

    function isOpen() {
      return !panel.hidden;
    }

    // Mobiilissa paneeli täyttää lähes koko ruudun (ks. chat.css @640px).
    var mobileMql = window.matchMedia('(max-width: 640px)');

    /**
     * Pinnaa auki olevan mobiilipaneelin tarkasti näkyvään viewporttiin
     * `window.visualViewport`-rajapinnalla. iOS Safarissa kiinnitetyn
     * elementin `right`/`inset` lasketaan layout-viewportista, joka voi
     * poiketa näkyvästä viewportista sekä virtuaalinäppäimistön ollessa auki
     * että sivun mahdollisen vaakavierityksen takia — ilman tätä paneeli voi
     * jäädä osittain ruudun ulkopuolelle tai näppäimistön peittoon.
     */
    function pinPanelToViewport() {
      if (!window.visualViewport || !mobileMql.matches || !isOpen()) {
        return;
      }

      var vv = window.visualViewport;
      var rootFontSize = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
      var margin = rootFontSize * 0.5; // Vastaa chat.cssin 0.5rem-inset-arvoa.

      root.style.left = vv.offsetLeft + margin + 'px';
      root.style.top = vv.offsetTop + margin + 'px';
      root.style.right = 'auto';
      root.style.bottom = 'auto';
      root.style.width = Math.max(0, vv.width - margin * 2) + 'px';
      root.style.height = Math.max(0, vv.height - margin * 2) + 'px';
    }

    /**
     * Poistaa pinPanelToViewport()-funktion asettamat inline-tyylit, jotta
     * chat.cssin omat säännöt (kelluva painike / työpöytäpaneeli) ohjaavat
     * sijaintia jälleen normaalisti.
     */
    function unpinPanel() {
      root.style.left = '';
      root.style.top = '';
      root.style.right = '';
      root.style.bottom = '';
      root.style.width = '';
      root.style.height = '';
    }

    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', pinPanelToViewport);
      window.visualViewport.addEventListener('scroll', pinPanelToViewport);
    }

    // Työpöytä/mobiili-rajan ylitys (esim. laitteen kääntö): pinnaus tai sen purku uudelleen.
    if (typeof mobileMql.addEventListener === 'function') {
      mobileMql.addEventListener('change', function () {
        if (isOpen()) {
          mobileMql.matches ? pinPanelToViewport() : unpinPanel();
        }
      });
    }

    function openPanel() {
      panel.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      root.classList.add('is-open');
      pinPanelToViewport();
      input.focus();
    }

    function closePanel() {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      root.classList.remove('is-open');
      unpinPanel();
      toggle.focus();
    }

    function togglePanel() {
      if (isOpen()) {
        closePanel();
      } else {
        openPanel();
      }
    }

    /**
     * Lisää viestikupla lokiin turvallisesti (textContent). Palauttaa elementin.
     */
    function scrollMessageIntoView(el, mode) {
      window.requestAnimationFrame(function () {
        if (mode === 'start') {
          log.scrollTop = Math.max(0, el.offsetTop - log.offsetTop);
          return;
        }

        log.scrollTop = log.scrollHeight;
      });
    }

    /**
     * Sallitaanko osoitteen isäntä linkiksi: vain nykyinen sivusto ja
     * rytkoset.net-alidomainit. Muut osoitteet jäävät pelkäksi tekstiksi.
     */
    function isAllowedLinkHost(hostname) {
      return (
        hostname === window.location.hostname ||
        hostname === 'rytkoset.net' ||
        hostname.slice(-'.rytkoset.net'.length) === '.rytkoset.net'
      );
    }

    /**
     * Renderöi viestin tekstin turvallisesti: tekstisolmuja ja oman sivuston
     * paljaista https-osoitteista rakennettuja <a>-elementtejä. Ei innerHTML:ää,
     * joten mallin vastaus ei voi tuoda merkkausta tai skriptejä DOMiin.
     */
    function renderMessageContent(el, text) {
      var urlPattern = /https?:\/\/[^\s<>"']+/g;
      var lastIndex = 0;
      var match;

      while ((match = urlPattern.exec(text)) !== null) {
        // Lauseen loppuvälimerkit eivät kuulu osoitteeseen.
        var candidate = match[0].replace(/[.,;:!?)]+$/, '');
        var url = null;

        try {
          url = new URL(candidate);
        } catch (e) {
          url = null;
        }

        if (!url || (url.protocol !== 'https:' && url.protocol !== 'http:') || !isAllowedLinkHost(url.hostname)) {
          continue;
        }

        el.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));

        var link = document.createElement('a');
        link.href = url.href;
        link.textContent = candidate;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        el.appendChild(link);

        lastIndex = match.index + candidate.length;
      }

      el.appendChild(document.createTextNode(text.slice(lastIndex)));
    }

    function appendMessage(role, text, scrollMode) {
      var el = document.createElement('div');
      el.className = 'rytkoset-chat__msg rytkoset-chat__msg--' + role;
      if (role === 'assistant') {
        renderMessageContent(el, text);
      } else {
        el.textContent = text;
      }
      log.appendChild(el);
      scrollMessageIntoView(el, scrollMode || 'end');
      return el;
    }

    function showTyping() {
      if (typingEl) {
        return;
      }
      typingEl = document.createElement('div');
      typingEl.className = 'rytkoset-chat__msg rytkoset-chat__msg--typing';
      typingEl.textContent = config.typingText || '…';
      log.appendChild(typingEl);
      log.scrollTop = log.scrollHeight;
    }

    function hideTyping() {
      if (typingEl && typingEl.parentNode) {
        typingEl.parentNode.removeChild(typingEl);
      }
      typingEl = null;
    }

    function setSending(state) {
      isSending = state;
      input.disabled = state;
      if (sendBtn) {
        sendBtn.disabled = state;
      }
    }

    function autoGrow() {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 140) + 'px';
    }

    /**
     * Lähettää keskusteluhistorian REST-reitille ja renderöi vastauksen.
     */
    function sendMessage(text) {
      history.push({ role: 'user', content: text });
      appendMessage('user', text);
      setSending(true);
      showTyping();

      var headers = { 'Content-Type': 'application/json' };
      if (config.nonce) {
        headers['X-WP-Nonce'] = config.nonce;
      }

      fetch(config.restUrl, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify({ messages: history })
      })
        .then(function (response) {
          return response
            .json()
            .catch(function () {
              return {};
            })
            .then(function (data) {
              return { ok: response.ok, data: data };
            });
        })
        .then(function (result) {
          hideTyping();
          if (result.ok && result.data && result.data.reply) {
            history.push({ role: 'assistant', content: result.data.reply });
            appendMessage('assistant', result.data.reply, 'start');
          } else {
            var message = (result.data && result.data.message) || config.errorText;
            appendMessage('error', message, 'start');
          }
        })
        .catch(function () {
          hideTyping();
          appendMessage('error', config.errorText, 'start');
        })
        .then(function () {
          setSending(false);
          input.style.height = 'auto';
          input.focus();
        });
    }

    // --- Tapahtumankäsittelijät ---------------------------------------------

    toggle.addEventListener('click', togglePanel);

    if (closeBtn) {
      closeBtn.addEventListener('click', closePanel);
    }

    // Esc sulkee paneelin.
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && isOpen()) {
        closePanel();
      }
    });

    input.addEventListener('input', autoGrow);

    // Enter lähettää, Shift+Enter tekee rivinvaihdon.
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
      }
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (isSending) {
        return;
      }
      var text = input.value.trim();
      if (!text) {
        return;
      }
      input.value = '';
      sendMessage(text);
    });
  });
})();
