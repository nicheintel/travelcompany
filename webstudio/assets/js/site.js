/* Website interactions. Each part runs on its own, so one problem can't stop the others.
   Written in older JavaScript on purpose, so it also works on old phones. */
(function () {
  var doc = document.documentElement;
  doc.className += ' js';
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
  function part(fn) { try { fn(); } catch (err) { if (window.console) console.error(err); } }

  // ---- Header: solid background after scrolling, mobile menu ----
  part(function () {
    var header = document.querySelector('[data-header]');
    if (!header) return;
    var toggle = header.querySelector('[data-nav-toggle]');
    function onScroll() {
      if (window.pageYOffset > 12) header.classList.add('is-scrolled');
      else header.classList.remove('is-scrolled');
    }
    function setOpen(open) {
      header.classList.toggle('nav-open', open);
      if (toggle) {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
      }
    }
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    if (toggle) toggle.addEventListener('click', function () { setOpen(!header.classList.contains('nav-open')); });
    all('.nav a', header).forEach(function (a) { a.addEventListener('click', function () { setOpen(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    document.addEventListener('click', function (e) { if (!header.contains(e.target)) setOpen(false); });
  });

  // ---- Fade sections in as they scroll into view ----
  // Anything above the bottom of the screen is shown, so jumping down the page never leaves gaps.
  part(function () {
    var items = all('.reveal, [data-steps]');
    if (!items.length) return;
    var ticking = false;
    function check() {
      ticking = false;
      var limit = window.innerHeight * 0.92;
      items = items.filter(function (el) {
        if (el.getBoundingClientRect().top > limit) return true;
        el.classList.add('in');
        if (el.classList.contains('reveal')) {
          // When the fade is done, hand control back to the element's own hover effects.
          var delay = parseInt(el.style.getPropertyValue('--d'), 10) || 0;
          setTimeout(function () { el.classList.remove('reveal', 'in'); }, delay + 900);
        }
        return false;
      });
      if (!items.length) {
        window.removeEventListener('scroll', schedule);
        window.removeEventListener('resize', schedule);
      }
    }
    function schedule() {
      if (!ticking) { ticking = true; (window.requestAnimationFrame || setTimeout)(check); }
    }
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    window.addEventListener('load', schedule);
    check();
  });

  // ---- Hero: the demo website changes business every few seconds ----
  part(function () {
    var box = document.querySelector('.hero-visual[data-demo]');
    if (!box) return;
    var demos = [
      { theme: '', url: 'yourbakery.com', kicker: 'Fresh every morning', title: 'Baked with love, served daily.', cta: 'Order now', emoji: '🥐' },
      { theme: 'salon', url: 'yoursalon.com', kicker: 'Book in 30 seconds', title: 'Look good. Feel even better.', cta: 'Book a visit', emoji: '💇‍♀️' },
      { theme: 'clinic', url: 'yourclinic.com', kicker: 'Caring for families', title: 'Healthy smiles start here.', cta: 'Book a check-up', emoji: '🦷' },
      { theme: 'shop', url: 'yourshop.com', kicker: 'New collection', title: 'Style that ships to your door.', cta: 'Shop now', emoji: '🛍️' },
      { theme: 'gym', url: 'yourgym.com', kicker: 'First class free', title: 'Stronger every single day.', cta: 'Join today', emoji: '💪' }
    ];
    var i = 0;
    var swapping = all('[data-demo-swap]', box);
    function show(d) {
      swapping.forEach(function (el) { el.classList.add('is-swapping'); });
      setTimeout(function () {
        if (d.theme) box.setAttribute('data-theme', d.theme); else box.removeAttribute('data-theme');
        all('[data-demo]', box).forEach(function (el) {
          var key = el.getAttribute('data-demo');
          if (d[key] !== undefined) el.textContent = d[key];
        });
        swapping.forEach(function (el) { el.classList.remove('is-swapping'); });
      }, 380);
    }
    setInterval(function () {
      if (document.hidden) return;
      var r = box.getBoundingClientRect();
      if (r.bottom < 0 || r.top > window.innerHeight) return; // off screen: wait
      i = (i + 1) % demos.length;
      show(demos[i]);
    }, 3800);
  });

  // ---- Portfolio filters ----
  part(function () {
    var bar = document.querySelector('[data-filters]');
    var grid = document.querySelector('[data-work-grid]');
    if (!bar || !grid) return;
    bar.addEventListener('click', function (e) {
      var btn = e.target.closest ? e.target.closest('[data-filter]') : null;
      if (!btn) return;
      var f = btn.getAttribute('data-filter');
      all('[data-filter]', bar).forEach(function (b) {
        var on = b === btn;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      all('.work-card', grid).forEach(function (card) {
        var match = f === '*' || card.getAttribute('data-category') === f;
        card.hidden = !match;
        card.classList.remove('reveal', 'in', 'is-filtering');
        if (match && !reduceMotion) { void card.offsetWidth; card.classList.add('is-filtering'); }
      });
    });
  });

  // ---- "Choose package" buttons fill in the form below ----
  part(function () {
    var select = document.querySelector('[data-package-select]');
    var quote = document.getElementById('quote');
    if (!select || !quote) return;
    all('[data-pick-package]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        select.value = btn.getAttribute('data-pick-package');
        quote.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
        select.classList.add('just-picked');
        setTimeout(function () {
          var name = document.getElementById('q-name');
          if (name && !name.value) name.focus({ preventScroll: true });
        }, reduceMotion ? 0 : 700);
      });
    });
  });

  // ---- Request form: show a spinner while sending; focus the first problem ----
  part(function () {
    var form = document.querySelector('[data-quote-form]');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      var missing = all('[required]', form).filter(function (el) { return !el.value.trim(); });
      if (missing.length) {
        e.preventDefault();
        missing.forEach(function (el) { el.setAttribute('aria-invalid', 'true'); });
        missing[0].focus();
        return;
      }
      var btn = form.querySelector('[data-submit]');
      if (btn) btn.classList.add('is-loading');
    });
    all('input, textarea, select', form).forEach(function (el) {
      el.addEventListener('input', function () { if (el.value.trim()) el.removeAttribute('aria-invalid'); });
    });
    var bad = form.querySelector('[aria-invalid="true"]');
    if (bad) setTimeout(function () { bad.focus({ preventScroll: true }); }, 300);
  });

  // ---- Copy buttons (tracking link) ----
  part(function () {
    all('[data-copy]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-copy'));
        if (!input) return;
        var label = btn.querySelector('span');
        function done() { if (label) { var old = label.textContent; label.textContent = 'Copied!'; setTimeout(function () { label.textContent = old; }, 1800); } }
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(input.value).then(done, function () { input.select(); });
        } else {
          input.select();
          try { document.execCommand('copy'); done(); } catch (err) { /* select is enough */ }
        }
      });
    });
  });

  // ---- A little confetti when a request was just sent ----
  part(function () {
    var box = document.querySelector('[data-confetti]');
    if (!box || reduceMotion) return;
    var colors = ['#f9812a', '#ffbb85', '#3b6af0', '#6b8ff7', '#ffffff', '#22c55e'];
    for (var n = 0; n < 70; n++) {
      var p = document.createElement('i');
      p.style.left = Math.random() * 100 + '%';
      p.style.background = colors[n % colors.length];
      p.style.animationDelay = (Math.random() * 1.4) + 's';
      p.style.animationDuration = (2.4 + Math.random() * 1.8) + 's';
      p.style.width = (6 + Math.random() * 6) + 'px';
      box.appendChild(p);
    }
  });
})();
