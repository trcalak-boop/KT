/* Šablona „salon“ – obsah na mobilu, přepínač světlého/tmavého režimu a logotyp přes celou šířku (bez ukládání, bez síťových požadavků). */
(function () {
  var root = document.documentElement, btn = document.getElementById('burger'), nav = document.getElementById('menu');
  if (btn && nav) {
    btn.addEventListener('click', function () { var o = btn.getAttribute('aria-expanded') !== 'true'; btn.setAttribute('aria-expanded', String(o)); nav.classList.toggle('open', o); root.classList.toggle('menu-open', o); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) { nav.classList.remove('open'); root.classList.remove('menu-open'); btn.setAttribute('aria-expanded', 'false'); } });
  }
  function label() { var l = root.getAttribute('data-theme') === 'dark' ? 'Přepnout na světlý režim' : 'Přepnout na tmavý režim'; document.querySelectorAll('[data-mode-btn]').forEach(function (b) { b.setAttribute('aria-label', l); b.title = l; }); }
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-mode-btn]')) return;
    if (root.getAttribute('data-theme') === 'dark') root.removeAttribute('data-theme'); else root.setAttribute('data-theme', 'dark');
    label();
  });
  label();
  /* delší název salonu: logotyp se zmenší, aby se vešel na šířku */
  var logo = document.querySelector('.logotype');
  function fit() { if (!logo) return; logo.style.fontSize = ''; var w = logo.parentNode.clientWidth, s = logo.scrollWidth; if (s > w) logo.style.fontSize = Math.floor(parseFloat(getComputedStyle(logo).fontSize) * w / s * 0.98) + 'px'; }
  fit(); window.addEventListener('resize', fit);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
})();
