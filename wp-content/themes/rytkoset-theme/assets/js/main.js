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
