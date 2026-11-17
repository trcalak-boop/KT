/* Šablona „restaurace“ – menu na mobilu a přepínač světlého/tmavého režimu (bez ukládání, bez síťových požadavků). */
(function () {
  var root = document.documentElement, btn = document.getElementById('burger'), nav = document.getElementById('nav');
  if (btn && nav) {
    btn.addEventListener('click', function () { var o = btn.getAttribute('aria-expanded') !== 'true'; btn.setAttribute('aria-expanded', String(o)); nav.classList.toggle('open', o); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) { nav.classList.remove('open'); btn.setAttribute('aria-expanded', 'false'); } });
  }
  function label() { var l = root.getAttribute('data-theme') === 'light' ? 'Přepnout na tmavý režim' : 'Přepnout na světlý režim'; document.querySelectorAll('[data-mode-btn]').forEach(function (b) { b.setAttribute('aria-label', l); b.title = l; }); }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-mode-btn]')) return;
    if (root.getAttribute('data-theme') === 'light') root.removeAttribute('data-theme'); else root.setAttribute('data-theme', 'light');
    label();
  });
  label();
})();
