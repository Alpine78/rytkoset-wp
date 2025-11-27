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

  if (!toggleButton || !mobileMenu) return;

  const toggleMenu = (open) => {
    const isOpen = open !== undefined ? open : !mobileMenu.classList.contains('mobile-menu--open');
    toggleButton.setAttribute('aria-expanded', String(isOpen));
    mobileMenu.classList.toggle('mobile-menu--open', isOpen);
  };

  toggleButton.addEventListener('click', () => {
    toggleMenu();
  });

  // Esc-näppäin sulkee mobiilivalikon
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      toggleMenu(false);
    }
  });
});
