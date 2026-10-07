/* Admin dashboard interactions. Each part runs on its own, so one problem can't stop the others. */
(function () {
  function all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function part(fn) { try { fn(); } catch (err) { if (window.console) console.error(err); } }

  // ---- Sidebar on small screens ----
  part(function () {
    var body = document.body;
    all('[data-sidebar-open]').forEach(function (b) {
      b.addEventListener('click', function () { body.classList.add('sidebar-open'); });
    });
    all('[data-sidebar-close]').forEach(function (b) {
      b.addEventListener('click', function () { body.classList.remove('sidebar-open'); });
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') body.classList.remove('sidebar-open'); });
  });

  // ---- "Are you sure?" before deleting ----
  part(function () {
    all('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
      });
    });
  });

  // ---- Whole table rows open the request ----
  part(function () {
    all('tr[data-href]').forEach(function (row) {
      row.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('a, button, input')) return;
        window.location.href = row.getAttribute('data-href');
      });
    });
  });

  // ---- Chart tooltips (hover and keyboard focus) ----
  part(function () {
    all('[data-chart]').forEach(function (chart) {
      var tip = chart.querySelector('[data-chart-tip]');
      var svg = chart.querySelector('svg');
      if (!tip || !svg) return;
      function show(rect) {
        var box = chart.getBoundingClientRect();
        var r = rect.getBoundingClientRect();
        tip.textContent = rect.getAttribute('data-tip');
        tip.hidden = false;
        var x = r.left - box.left + r.width / 2;
        var half = tip.offsetWidth / 2;
        x = Math.max(half, Math.min(box.width - half, x));
        tip.style.left = x + 'px';
        tip.style.top = (r.top - box.top + 14) + 'px';
      }
      function hide() { tip.hidden = true; }
      all('.hit', svg).forEach(function (rect) {
        rect.addEventListener('mouseenter', function () { show(rect); });
        rect.addEventListener('focus', function () { show(rect); });
        rect.addEventListener('mouseleave', hide);
        rect.addEventListener('blur', hide);
        // Native tooltip off while ours shows.
        var t = rect.querySelector('title');
        if (t) t.parentNode.removeChild(t);
      });
    });
  });

  // ---- Copy buttons ----
  part(function () {
    all('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-copy'));
        if (!input) return;
        function done() {
          btn.classList.add('is-copied');
          var old = btn.getAttribute('title');
          btn.setAttribute('title', 'Copied!');
          setTimeout(function () { btn.classList.remove('is-copied'); btn.setAttribute('title', old || 'Copy'); }, 1600);
        }
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(input.value).then(done, function () { input.select(); });
        } else {
          input.select();
          try { document.execCommand('copy'); done(); } catch (err) { /* selected is enough */ }
        }
      });
    });
  });

  // ---- Show / hide password ----
  part(function () {
    all('[data-pw-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = btn.parentNode.querySelector('input');
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
    });
  });

  // ---- Small menus: only one open at a time, close when clicking elsewhere ----
  part(function () {
    var menus = all('details.tm-menu');
    menus.forEach(function (d) {
      d.addEventListener('toggle', function () {
        if (d.open) menus.forEach(function (o) { if (o !== d) o.open = false; });
      });
    });
    document.addEventListener('click', function (e) {
      menus.forEach(function (d) { if (d.open && !d.contains(e.target)) d.open = false; });
    });
  });
})();
