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
      if (item.closest('.mobile-menu__account')) {
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
        <span aria-hidden="true" class="mobile-submenu-toggle__icon">&#9662;</span>
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

  const shareBlocks = document.querySelectorAll('[data-share]');

  shareBlocks.forEach((share) => {
    const status = share.querySelector('[data-share-status]');
    const copyButton = share.querySelector('[data-share-copy]');
    const trigger = share.querySelector('[data-share-trigger]');
    const menu = share.querySelector('[data-share-menu]');
    const nativeButton = share.querySelector('[data-share-native]');
    const supportsNativeShare = Boolean(navigator && navigator.share);

    let isMenuOpen = false;
    let outsideListener = null;

    const getShareData = () => {
      const url = trigger?.getAttribute('data-share-url') || share.dataset.shareUrl || window.location.href;
      const title = trigger?.getAttribute('data-share-title') || share.dataset.shareTitle || document.title;
      const text = trigger?.getAttribute('data-share-text') || share.dataset.shareText || title;

      return { title, text, url };
    };

    const showStatus = (message) => {
      if (!status || !message) return;
      status.textContent = message;
      status.hidden = false;
      window.setTimeout(() => {
        status.hidden = true;
      }, 2500);
    };

    const closeMenu = () => {
      if (menu) {
        menu.hidden = true;
        share.classList.remove('share--open');
      }
      isMenuOpen = false;
      if (outsideListener) {
        document.removeEventListener('click', outsideListener, true);
        outsideListener = null;
      }
    };

    const openMenu = () => {
      if (!menu) return;
      menu.hidden = false;
      share.classList.add('share--open');
      isMenuOpen = true;

      outsideListener = (event) => {
        if (!share.contains(event.target)) {
          closeMenu();
        }
      };

      document.addEventListener('click', outsideListener, true);
    };

    const tryNativeShare = async () => {
      if (!navigator?.share) {
        return false;
      }

      const shareData = getShareData();

      if (navigator.canShare && !navigator.canShare(shareData)) {
        return false;
      }

      try {
        await navigator.share(shareData);
        return true;
      } catch (error) {
        if (error && error.name === 'AbortError') {
          return true;
        }
        return false;
      }
    };

    if (trigger) {
      trigger.addEventListener('click', async () => {
        if (isMenuOpen) {
          closeMenu();
        } else {
          openMenu();
        }
      });

      trigger.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeMenu();
        }
      });
    }

    if (menu) {
      menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeMenu();
          trigger?.focus();
        }
      });
    }

    if (nativeButton) {
      if (!supportsNativeShare) {
        nativeButton.hidden = true;
        nativeButton.setAttribute('aria-hidden', 'true');
        nativeButton.setAttribute('tabindex', '-1');
      } else {
        nativeButton.addEventListener('click', async () => {
          const shared = await tryNativeShare();
          if (shared) {
            showStatus(nativeButton.dataset.shareSuccess);
            closeMenu();
          } else {
            showStatus(nativeButton.dataset.shareError || 'Jakaminen ei onnistunut');
          }
        });
      }
    }

    if (copyButton) {
      copyButton.addEventListener('click', async () => {
        const url = copyButton.getAttribute('data-share-copy');
        if (!url) return;

        try {
          await navigator.clipboard.writeText(url);
          showStatus(copyButton.dataset.shareSuccess || 'Linkki kopioitu leikepöydälle');
        } catch (error) {
          showStatus(copyButton.dataset.shareError || 'Linkin kopiointi ei onnistunut');
        }
      });
    }
  });
});

(function () {
  const accountItems = document.querySelectorAll('.account-menu__user');
  if (!accountItems.length) return;

  const closeItem = (item) => {
    const trigger = item.querySelector('.account-menu__user-trigger');
    const submenu = item.querySelector(':scope > .sub-menu');
    if (!trigger || !submenu) return;
    item.classList.remove('submenu-open');
    trigger.setAttribute('aria-expanded', 'false');
  };

  accountItems.forEach((item) => {
    const trigger = item.querySelector('.account-menu__user-trigger');
    const submenu = item.querySelector(':scope > .sub-menu');
    if (!trigger || !submenu) return;

    closeItem(item);

    trigger.addEventListener('click', (event) => {
      event.preventDefault();
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

  const createThemeToggle = () => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'theme-toggle';
    btn.setAttribute('aria-pressed', 'false');
    btn.innerHTML = `
      <span class="theme-toggle__icon" aria-hidden="true">🌙</span>
      <span class="theme-toggle__label">Teema</span>
    `;
    return btn;
  };

  const ensureMenuToggles = () => {
    const submenus = document.querySelectorAll('.account-nav .sub-menu');
    submenus.forEach((submenu) => {
      if (submenu.querySelector('.theme-toggle')) return;
      const li = document.createElement('li');
      li.className = 'menu-item theme-toggle-item';
      li.appendChild(createThemeToggle());
      submenu.appendChild(li);
    });
  };

  ensureMenuToggles();

  const toggles = Array.from(document.querySelectorAll('.theme-toggle'));
  if (!toggles.length) return;

  const applyTheme = (theme) => {
    root.setAttribute('data-theme', theme);
    toggles.forEach((btn) => {
      btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
      const icon = btn.querySelector('.theme-toggle__icon');
      const label = btn.querySelector('.theme-toggle__label');
      if (icon) {
        icon.textContent = theme === 'dark' ? '☀️' : '🌙';
      }
      if (label) {
        label.textContent = theme === 'dark' ? 'Teema: tumma' : 'Teema: vaalea';
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
