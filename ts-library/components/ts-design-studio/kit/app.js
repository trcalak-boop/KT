/* Web ze stavebnice Studia: přepínač režimu a menu na telefonu. Bez cookies, bez úložiště, bez síťových požadavků. */
(function () {
  /* nadpis se nesmí rozdělit uprostřed slova: když se nejdelší slovo nevejde, písmo se zmenší (bez JavaScriptu se slovo zalomí) */
  function fit() {
    document.querySelectorAll('h1,h2,h3,.disp').forEach(function (el) {
      el.style.fontSize = '';
      var fs = parseFloat(getComputedStyle(el).fontSize), n = 0;
      while (el.scrollWidth > el.clientWidth + 1 && fs > 16 && n++ < 40) { fs *= .95; el.style.fontSize = fs.toFixed(1) + 'px'; }
    });
  }
  document.documentElement.classList.add('js'); fit();
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
  var tm; addEventListener('resize', function () { clearTimeout(tm); tm = setTimeout(fit, 120); });
  var root = document.documentElement, t = document.querySelector('.hd-theme'), m = document.querySelector('.hd-menu'), n = document.getElementById('hd-nav');
  if (t) t.addEventListener('click', function () {
    var cur = root.getAttribute('data-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    root.setAttribute('data-theme', cur === 'dark' ? 'light' : 'dark');
  });
  if (m && n) {
    var set = function (o) { m.setAttribute('aria-expanded', String(o)); n.classList.toggle('open', o); };
    m.addEventListener('click', function () { set(m.getAttribute('aria-expanded') !== 'true'); });
    n.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
  }
})();
