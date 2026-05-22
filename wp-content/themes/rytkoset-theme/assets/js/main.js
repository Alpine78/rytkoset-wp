(function () {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.main-navigation');

  if (!toggle || !nav) return;

  toggle.addEventListener('click', function () {
    nav.classList.toggle('is-open');
    const expanded = nav.classList.contains('is-open');
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  });
})();


document.addEventListener('DOMContentLoaded', () => {
  const toggleButton = document.querySelector('.mobile-menu-toggle');
  const mobileMenu = document.getElementById('mobile-menu');
  const overlay = document.querySelector('.mobile-menu__overlay');
  const closeButton = document.querySelector('.mobile-menu__close');

  const focusableSelectors = [
    'a[href]',
    'button:not([disabled])',
    'textarea:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
  ].join(',');

  if (!toggleButton || !mobileMenu) return;

  let lastFocusedElement = null;

  const trapFocus = (event) => {
    if (!mobileMenu.classList.contains('mobile-menu--open') || event.key !== 'Tab') {
      return;
    }

    const focusableElements = mobileMenu.querySelectorAll(focusableSelectors);
    if (!focusableElements.length) {
      return;
    }

    const first = focusableElements[0];
    const last = focusableElements[focusableElements.length - 1];

    if (event.shiftKey && document.activeElement === first) {
      last.focus();
      event.preventDefault();
    }

    if (!event.shiftKey && document.activeElement === last) {
      first.focus();
      event.preventDefault();
    }
  };

  const resetSubmenus = () => {
    const openItems = mobileMenu.querySelectorAll('.menu-item-has-children.submenu-open');

    openItems.forEach((item) => {
      const toggle = item.querySelector(':scope > .mobile-submenu-toggle');
      const submenu = item.querySelector(':scope > .sub-menu');

      if (!toggle || !submenu) return;

      toggle.setAttribute('aria-expanded', 'false');
      submenu.hidden = true;
      item.classList.remove('submenu-open');
    });
  };

  const openCurrentAncestorSubmenus = () => {
    const ancestorSelectors = [
      '.current-menu-ancestor',
      '.current-menu-parent',
      '.current-menu-item.menu-item-has-children',
    ];
    const ancestors = mobileMenu.querySelectorAll(ancestorSelectors.join(','));

    ancestors.forEach((item) => {
      if (item.closest('.mm-section--account')) return;

      const toggle = item.querySelector(':scope > .mobile-submenu-toggle');
      const submenu = item.querySelector(':scope > .sub-menu');

      if (!toggle || !submenu) return;

      toggle.setAttribute('aria-expanded', 'true');
      submenu.hidden = false;
      item.classList.add('submenu-open');
    });
  };

  const closeMenu = () => {
    toggleButton.setAttribute('aria-expanded', 'false');
    mobileMenu.classList.remove('mobile-menu--open');
    mobileMenu.setAttribute('aria-hidden', 'true');
    mobileMenu.setAttribute('aria-expanded', 'false');
    overlay?.classList.remove('is-active');
    overlay?.setAttribute('hidden', '');

    resetSubmenus();

    document.removeEventListener('keydown', trapFocus);
    document.removeEventListener('keydown', handleEscape);

    if (lastFocusedElement) {
      lastFocusedElement.focus();
    }
  };

  const openMenu = () => {
    lastFocusedElement = document.activeElement;
    toggleButton.setAttribute('aria-expanded', 'true');
    mobileMenu.classList.add('mobile-menu--open');
    mobileMenu.setAttribute('aria-hidden', 'false');
    mobileMenu.setAttribute('aria-expanded', 'true');
    overlay?.classList.add('is-active');
    overlay?.removeAttribute('hidden');

    openCurrentAncestorSubmenus();

    mobileMenu.focus();

    document.addEventListener('keydown', trapFocus);
    document.addEventListener('keydown', handleEscape);
  };

  const toggleMenu = (open) => {
    const isOpen = open !== undefined ? open : !mobileMenu.classList.contains('mobile-menu--open');
    if (isOpen) {
      openMenu();
    } else {
      closeMenu();
    }
  };

  toggleButton.addEventListener('click', () => {
    toggleMenu();
  });

  if (closeButton) {
    closeButton.addEventListener('click', () => toggleMenu(false));
  }

  const handleEscape = (event) => {
    if (event.key !== 'Escape') return;

    const activeSubmenuItem = mobileMenu.querySelector('.menu-item-has-children.submenu-open');
    if (activeSubmenuItem) {
      const toggle = activeSubmenuItem.querySelector(':scope > .mobile-submenu-toggle');
      const submenu = activeSubmenuItem.querySelector(':scope > .sub-menu');

      if (toggle && submenu) {
        toggle.setAttribute('aria-expanded', 'false');
        submenu.hidden = true;
        activeSubmenuItem.classList.remove('submenu-open');
        toggle.focus();
        return;
      }
    }

    toggleMenu(false);
  };

  if (overlay) {
    overlay.addEventListener('click', () => toggleMenu(false));
  }

  const initSubmenuToggles = () => {
    const submenuItems = mobileMenu.querySelectorAll('.menu-item-has-children');

    submenuItems.forEach((item, index) => {
      if (item.closest('.mm-section--account')) {
        return;
      }

      const submenu = item.querySelector(':scope > .sub-menu');
      if (!submenu) return;

      const submenuId = submenu.id || `mobile-submenu-${index}`;
      submenu.id = submenuId;

      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'mobile-submenu-toggle';
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-controls', submenuId);
      toggle.innerHTML = `
        <span class="screen-reader-text">
          ${toggleButton.getAttribute('data-submenu-label') || 'Avaa alavalikko'}
        </span>
        <span aria-hidden="true" class="mobile-submenu-toggle__icon">
          <svg viewBox="0 0 12 12" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2.5 4.5 6 8l3.5-3.5" />
          </svg>
        </span>
      `;

      const link = item.querySelector(':scope > a');
      if (link) {
        link.insertAdjacentElement('afterend', toggle);
      } else {
        item.prepend(toggle);
      }

      submenu.hidden = true;

      toggle.addEventListener('click', () => {
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!isExpanded));
        submenu.hidden = isExpanded;
        item.classList.toggle('submenu-open', !isExpanded);
      });

      toggle.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        toggle.setAttribute('aria-expanded', 'false');
        submenu.hidden = true;
        item.classList.remove('submenu-open');
      });
    });
  };

  initSubmenuToggles();

  // Jakopainikkeiden logiikka on siirretty omaan tiedostoon: assets/js/share.js
});

(function () {
  const nav = document.querySelector('.site-nav');
  if (!nav) return;

  const submenuItems = Array.from(nav.querySelectorAll('.site-nav__list > .menu-item-has-children'));
  if (!submenuItems.length) return;

  let activeItem = null;
  let suppressFocusOpen = false;
  let closeTimer = null;

  const clearCloseTimer = () => {
    if (!closeTimer) return;
    window.clearTimeout(closeTimer);
    closeTimer = null;
  };

  const closeItem = (item) => {
    const link = item.querySelector(':scope > a');
    if (!link) return;

    item.classList.remove('is-open');
    link.setAttribute('aria-expanded', 'false');

    if (activeItem === item) {
      activeItem = null;
    }
  };

  const closeAll = (except = null) => {
    clearCloseTimer();
    submenuItems.forEach((item) => {
      if (item !== except) {
        closeItem(item);
      }
    });
  };

  const openItem = (item) => {
    const link = item.querySelector(':scope > a');
    if (!link) return;

    clearCloseTimer();
    closeAll(item);
    item.classList.add('is-open');
    link.setAttribute('aria-expanded', 'true');
    activeItem = item;
  };

  const scheduleClose = (item) => {
    clearCloseTimer();
    closeTimer = window.setTimeout(() => {
      closeItem(item);
      closeTimer = null;
    }, 180);
  };

  submenuItems.forEach((item) => {
    const link = item.querySelector(':scope > a');
    const submenu = item.querySelector(':scope > .sub-menu');

    if (!link || !submenu) return;

    link.setAttribute('aria-haspopup', 'true');
    link.setAttribute('aria-expanded', 'false');

    item.addEventListener('mouseenter', () => openItem(item));
    item.addEventListener('mouseleave', () => scheduleClose(item));

    link.addEventListener('focus', () => {
      if (suppressFocusOpen) return;
      openItem(item);
    });

    item.addEventListener('focusout', (event) => {
      if (!item.contains(event.relatedTarget)) {
        closeItem(item);
      }
    });

    link.addEventListener('click', () => {
      closeAll();
    });
  });

  document.addEventListener('pointerdown', (event) => {
    if (!nav.contains(event.target)) {
      closeAll();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !activeItem) return;

    const link = activeItem.querySelector(':scope > a');
    suppressFocusOpen = true;
    closeItem(activeItem);
    link?.focus();
    window.setTimeout(() => {
      suppressFocusOpen = false;
    }, 0);
  });
})();

(function () {
  const search = document.querySelector('.site-header__search');
  if (!search) return;

  const toggle = search.querySelector('.site-search-toggle');
  const form = search.querySelector('.site-header__search-form');
  const input = search.querySelector('.site-header__search-input');

  if (!toggle || !form || !input) return;

  const openLabel = toggle.getAttribute('data-label-open') || 'Avaa haku';
  const closeLabel = toggle.getAttribute('data-label-close') || 'Sulje haku';

  const openSearch = () => {
    search.classList.add('is-open');
    form.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', closeLabel);
    window.requestAnimationFrame(() => input.focus());
  };

  const closeSearch = (returnFocus = false) => {
    search.classList.remove('is-open');
    form.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', openLabel);

    if (returnFocus) {
      toggle.focus();
    }
  };

  toggle.addEventListener('click', () => {
    if (search.classList.contains('is-open')) {
      closeSearch();
    } else {
      openSearch();
    }
  });

  document.addEventListener('pointerdown', (event) => {
    if (!search.contains(event.target)) {
      closeSearch();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && search.classList.contains('is-open')) {
      closeSearch(true);
    }
  });
})();

(function () {
  const accountItems = document.querySelectorAll('.account-menu__user');
  if (!accountItems.length) return;

  let closeTimer = null;

  const clearCloseTimer = () => {
    if (!closeTimer) return;
    window.clearTimeout(closeTimer);
    closeTimer = null;
  };

  const closeItem = (item) => {
    const trigger = item.querySelector('.account-menu__user-trigger');
    const submenu = item.querySelector(':scope > .sub-menu');
    if (!trigger || !submenu) return;
    item.classList.remove('submenu-open');
    trigger.setAttribute('aria-expanded', 'false');
  };

  const openItem = (item) => {
    const trigger = item.querySelector('.account-menu__user-trigger');
    const submenu = item.querySelector(':scope > .sub-menu');
    if (!trigger || !submenu) return;
    clearCloseTimer();
    item.classList.add('submenu-open');
    trigger.setAttribute('aria-expanded', 'true');
  };

  const scheduleClose = (item) => {
    clearCloseTimer();
    closeTimer = window.setTimeout(() => {
      closeItem(item);
      closeTimer = null;
    }, 180);
  };

  accountItems.forEach((item) => {
    const trigger = item.querySelector('.account-menu__user-trigger');
    const submenu = item.querySelector(':scope > .sub-menu');
    if (!trigger || !submenu) return;

    closeItem(item);

    item.addEventListener('mouseenter', () => openItem(item));
    item.addEventListener('mouseleave', () => scheduleClose(item));

    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      clearCloseTimer();
      const isOpen = item.classList.toggle('submenu-open');
      trigger.setAttribute('aria-expanded', String(isOpen));
    });
  });

  document.addEventListener('click', (event) => {
    accountItems.forEach((item) => {
      if (!item.contains(event.target)) {
        closeItem(item);
      }
    });
  });
})();

(function () {
  const root = document.documentElement;
  const storageKey = 'rytkoset-theme';

  const toggles = Array.from(document.querySelectorAll('.theme-toggle'));
  if (!toggles.length) return;

  const applyTheme = (theme) => {
    root.setAttribute('data-theme', theme);
    toggles.forEach((btn) => {
      btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      const icon = btn.querySelector('.theme-toggle__icon');
      const label = btn.querySelector('.theme-toggle__label');
      if (icon) {
        icon.textContent = theme === 'dark' ? '🌙' : '☀️';
      }
      if (label) {
        label.textContent = theme === 'dark' ? 'Tumma' : 'Vaalea';
      }
    });
  };

  const stored = localStorage.getItem(storageKey);
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const initialTheme = stored || (prefersDark ? 'dark' : 'light');
  applyTheme(initialTheme);

  toggles.forEach((btn) => {
    btn.addEventListener('click', () => {
      const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
      const next = current === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      localStorage.setItem(storageKey, next);
    });
  });
})();

(function () {
  const config = window.rytkosetCheckoutConfig;
  const checkoutNotes = Array.isArray(config?.checkoutNotes)
    ? config.checkoutNotes
    : config?.showMembershipNote && config.membershipNoteHtml
      ? [config.membershipNoteHtml]
      : [];

  if (!checkoutNotes.length) {
    return;
  }

  const insertCheckoutNotes = () => {
    const checkoutRoot = document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout');

    if (!checkoutRoot || document.querySelector('.rytkoset-checkout-note')) {
      return false;
    }

    checkoutRoot.insertAdjacentHTML('beforebegin', checkoutNotes.join(''));
    return true;
  };

  if (insertCheckoutNotes()) {
    return;
  }

  const observer = new MutationObserver(() => {
    if (insertCheckoutNotes()) {
      observer.disconnect();
    }
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
  });

  window.setTimeout(() => observer.disconnect(), 10000);
})();
