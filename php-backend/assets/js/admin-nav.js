(function () {
  var links = document.getElementById('adminNavLinks');
  var overlay = document.getElementById('navOverlay');
  var btn = document.getElementById('hamburgerBtn');
  if (!links || !overlay || !btn) return;

  var ICON_MENU = '\u2630';
  var ICON_CLOSE = '\u2715';

  function openMenu() {
    links.classList.add('open');
    overlay.classList.add('show');
    btn.classList.add('is-open');
    btn.textContent = ICON_CLOSE;
    btn.setAttribute('aria-expanded', 'true');
    btn.setAttribute('aria-label', 'Close menu');
  }
  function closeMenu() {
    links.classList.remove('open');
    overlay.classList.remove('show');
    btn.classList.remove('is-open');
    btn.textContent = ICON_MENU;
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('aria-label', 'Open menu');
  }

  btn.addEventListener('click', function () {
    links.classList.contains('open') ? closeMenu() : openMenu();
  });
  overlay.addEventListener('click', closeMenu);
  links.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', closeMenu);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMenu();
  });
})();
