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
