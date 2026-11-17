/* Šablona „ordinace“ – režim podle zařízení s přepínačem, větší písmo (A+) a menu na mobilu. Bez ukládání a bez síťových požadavků. */
(function () {
  var root = document.documentElement, btn = document.getElementById('burger'), nav = document.getElementById('menu'), aplus = document.getElementById('aplus');
  if (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) root.setAttribute('data-theme', 'dark');
  if (btn && nav) {
    btn.addEventListener('click', function () { var o = btn.getAttribute('aria-expanded') !== 'true'; btn.setAttribute('aria-expanded', String(o)); nav.classList.toggle('open', o); root.classList.toggle('menu-open', o); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) { nav.classList.remove('open'); root.classList.remove('menu-open'); btn.setAttribute('aria-expanded', 'false'); } });
  }
  if (aplus) aplus.addEventListener('click', function () { var on = !root.classList.contains('large'); root.classList.toggle('large', on); aplus.setAttribute('aria-pressed', String(on)); });
  function label() { var l = root.getAttribute('data-theme') === 'dark' ? 'Přepnout na světlý režim' : 'Přepnout na tmavý režim'; document.querySelectorAll('[data-mode-btn]').forEach(function (b) { b.setAttribute('aria-label', l); b.title = l; }); }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-mode-btn]')) return;
    root.setAttribute('data-theme', root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    label();
  });
  label();
})();
