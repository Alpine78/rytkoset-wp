/**
 * AI-tukichatin widget (#413).
 *
 * Kelluva painike + paneeli, joka juttelee REST-reitin `rytkoset/v1/chat` kautta.
 * Keskusteluhistoria pidetään VAIN muistissa (ei localStorage/sessionStorage/keksejä).
 * Mallin vastaus renderöidään turvallisesti DOMiin (textContent, ei innerHTML).
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

    function openPanel() {
      panel.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      root.classList.add('is-open');
      input.focus();
    }

    function closePanel() {
      panel.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      root.classList.remove('is-open');
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
    function appendMessage(role, text) {
      var el = document.createElement('div');
      el.className = 'rytkoset-chat__msg rytkoset-chat__msg--' + role;
      el.textContent = text;
      log.appendChild(el);
      log.scrollTop = log.scrollHeight;
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
            appendMessage('assistant', result.data.reply);
          } else {
            var message = (result.data && result.data.message) || config.errorText;
            appendMessage('error', message);
          }
        })
        .catch(function () {
          hideTyping();
          appendMessage('error', config.errorText);
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
