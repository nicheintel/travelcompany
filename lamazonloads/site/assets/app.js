// LamazonLoads — small enhancements and animations (the site works without JavaScript too).
(function () {
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.getElementById('site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Header gets a shadow and a smaller logo after scrolling.
  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 10); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // Run fn when el scrolls into view (once).
  function whenVisible(el, fn, margin) {
    if (!('IntersectionObserver' in window)) { fn(el); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { io.unobserve(en.target); fn(en.target); } });
    }, { rootMargin: margin || '0px 0px -40px 0px' });
    io.observe(el);
  }

  // Fade sections in as they scroll into view, cards in a row one after another.
  document.querySelectorAll('.reveal').forEach(function (el) {
    var sibs = el.parentElement ? Array.prototype.filter.call(el.parentElement.children, function (c) { return c.classList.contains('reveal'); }) : [];
    var i = sibs.indexOf(el);
    if (i > 0) el.style.transitionDelay = (i % 4) * 110 + 'ms';
    whenVisible(el, function (t) { t.classList.add('in'); });
  });

  // "How it works": start the truck when the steps come into view.
  document.querySelectorAll('.route-wrap').forEach(function (el) {
    whenVisible(el, function (t) {
      t.classList.add('go');
      setTimeout(function () { t.classList.add('parked'); }, 3300);
    }, '0px 0px -120px 0px');
  });

  // Live dispatch desk: tick the steps off one by one, then "Load booked", and repeat.
  var list = document.querySelector('[data-dispatch]');
  var live = document.querySelector('[data-live]');
  var bar = document.querySelector('.dc-bar span');
  if (list && live) {
    var steps = list.querySelectorAll('li');
    var idle = live.textContent;
    var n = -1;
    var timer = null;
    var tick = function () {
      n++;
      steps.forEach(function (li, i) {
        li.classList.toggle('is-done', i < n);
        li.classList.toggle('is-active', i === n);
      });
      if (bar) bar.style.width = Math.min(n, steps.length) / steps.length * 100 + '%';
      live.classList.remove('booked');
      if (n < steps.length) {
        live.textContent = steps[n].getAttribute('data-status');
        live.classList.add('working');
        timer = setTimeout(tick, 1700);
      } else if (n === steps.length) {
        live.textContent = 'Load booked ✓';
        live.classList.remove('working');
        void live.offsetWidth;
        live.classList.add('booked');
        timer = setTimeout(tick, 2600);
      } else {
        n = -1;
        steps.forEach(function (li) { li.classList.remove('is-done', 'is-active'); });
        if (bar) bar.style.width = '0';
        live.textContent = idle;
        timer = setTimeout(tick, 900);
      }
    };
    // Only animate while the card is on screen.
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting && !timer) timer = setTimeout(tick, 1400);
          if (!en.isIntersecting && timer) { clearTimeout(timer); timer = null; }
        });
      }).observe(list);
    } else {
      timer = setTimeout(tick, 1400);
    }
  }

  // Hearts on job boxes: save / unsave without reloading the page.
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form.hasAttribute || !form.hasAttribute('data-save') || !window.fetch) return;
    ev.preventDefault();
    var btn = form.querySelector('.jc-save');
    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().then(function (d) { return { status: r.status, d: d }; }); })
      .then(function (res) {
        if (res.status === 401 && res.d.login) { window.location.href = res.d.login; return; }
        var on = !!res.d.saved;
        document.querySelectorAll('form[data-save] input[name=job][value="' + form.querySelector('input[name=job]').value + '"]').forEach(function (i) {
          var b = i.form.querySelector('.jc-save');
          b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false');
          b.setAttribute('aria-label', on ? 'Saved. Remove from saved jobs' : 'Save this job'); b.title = on ? 'Saved' : 'Save job';
        });
        btn.classList.remove('pop'); void btn.offsetWidth; if (on) btn.classList.add('pop');
      })
      .catch(function () { form.submit(); });
  });

  // Buttons that show/hide a section (e.g. "Change email" on the confirm page).
  document.querySelectorAll('[data-toggle]').forEach(function (btn) {
    var target = document.getElementById(btn.getAttribute('data-toggle'));
    if (!target) return;
    btn.addEventListener('click', function () {
      target.hidden = !target.hidden;
      btn.setAttribute('aria-expanded', target.hidden ? 'false' : 'true');
      if (!target.hidden) { var f = target.querySelector('input:not([type=hidden])'); if (f) f.focus(); }
    });
  });

  // "Meet the fleet": the current vehicle drives off, the chosen one drives in. Plays by itself until someone picks one.
  document.querySelectorAll('[data-fleet]').forEach(function (fleet) {
    var cars = fleet.querySelectorAll('[data-fleet-car]');
    var tabs = fleet.querySelectorAll('[data-fleet-tab]');
    var infos = fleet.querySelectorAll('[data-fleet-info]');
    var cur = 0, auto = null, visible = false;
    var show = function (i) {
      if (i === cur) return;
      var out = cars[cur], inn = cars[i];
      out.classList.remove('is-on', 'moving'); out.classList.add('leave');
      setTimeout(function () { out.classList.remove('leave'); }, 750);
      inn.classList.add('is-on', 'moving');
      setTimeout(function () { inn.classList.remove('moving'); }, 1700);
      tabs.forEach(function (t, k) { t.classList.toggle('on', k === i); t.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
      infos.forEach(function (box, k) { box.hidden = k !== i; });
      cur = i;
    };
    var stop = function () { if (auto) { clearInterval(auto); auto = null; } };
    var start = function () { if (!auto && visible) auto = setInterval(function () { show((cur + 1) % cars.length); }, 6500); };
    tabs.forEach(function (t, k) { t.addEventListener('click', function () { stop(); fleet.setAttribute('data-picked', ''); show(k); }); });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) {
        visible = en[0].isIntersecting;
        if (visible && !fleet.hasAttribute('data-picked')) start(); else stop();
      }, { threshold: .35 }).observe(fleet);
    }
  });

  // Application form: show the "Other" boxes and the Walmart cities only when needed.
  // SUV / other vehicles only: we only have Walmart routes for them, so tick it for the driver.
  document.querySelectorAll('[data-apply]').forEach(function (f) {
    var show = function (name, on) { f.querySelectorAll('[data-show-if="' + name + '"]').forEach(function (el) { el.hidden = !on; }); };
    var wasOnlySuv = null;
    var update = function () {
      var veh = Array.prototype.filter.call(f.querySelectorAll('[data-vehicle]'), function (i) { return i.checked; }).map(function (i) { return i.value; });
      show('vehicle-other', veh.indexOf('other') !== -1);
      var own = f.querySelector('[data-ownership]:checked');
      show('ownership-other', !!own && own.value === 'other');
      var onlySuv = veh.length > 0 && veh.every(function (v) { return v === 'suv' || v === 'other'; });
      var note = f.querySelector('[data-suv-note]');
      if (note) note.hidden = !onlySuv;
      var wm = f.querySelector('[data-walmart]');
      if (wm && onlySuv && wasOnlySuv === false && !wm.checked) wm.checked = true; // only when the driver changes vehicles
      wasOnlySuv = onlySuv;
      show('walmart', !!wm && wm.checked);
    };
    f.addEventListener('change', update);
    update();
  });

  // Pop-up windows (the job application form): smooth zoom in, blurred page behind,
  // close with the X, the Esc key or a click outside. Without JavaScript the #apply link still opens it.
  document.querySelectorAll('[data-modal]').forEach(function (m) {
    var lastFocus = null;
    var focusables = function () { return Array.prototype.filter.call(m.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea'), function (el) { return el.offsetParent !== null; }); };
    var open = function () {
      lastFocus = document.activeElement;
      m.classList.remove('is-closing'); m.classList.add('is-open'); m.removeAttribute('aria-hidden');
      document.documentElement.classList.add('modal-lock');
      setTimeout(function () {
        var err = m.querySelector('.errors');
        var first = m.querySelector('.modal-body input:not([type=hidden]):not([type=checkbox]):not([type=radio]), .modal-body .btn');
        if (err) err.scrollIntoView({ block: 'nearest' });
        if (first && !err) first.focus({ preventScroll: true });
      }, 80);
    };
    var close = function () {
      if (!m.classList.contains('is-open')) return;
      m.classList.add('is-closing');
      setTimeout(function () {
        m.classList.remove('is-open', 'is-closing'); m.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('modal-lock');
        if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
      }, 220);
      var search = location.search.replace(/([?&])apply=1(&|$)/, function (all, a, b) { return b ? a : ''; });
      if (location.hash === '#' + m.id || search !== location.search) history.replaceState(null, '', location.pathname + search);
    };
    document.querySelectorAll('[data-modal-open="' + m.id + '"]').forEach(function (b) {
      b.addEventListener('click', function (e) { e.preventDefault(); open(); });
    });
    m.querySelectorAll('[data-modal-close]').forEach(function (b) {
      b.addEventListener('click', function (e) { e.preventDefault(); close(); });
    });
    document.addEventListener('keydown', function (e) {
      if (!m.classList.contains('is-open')) return;
      if (e.key === 'Escape') { close(); return; }
      if (e.key === 'Tab') { // keep the keyboard inside the pop-up
        var f = focusables(); if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
        else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      }
    });
    if (m.classList.contains('is-open') || location.hash === '#' + m.id) open();
  });

  // "Back to all jobs": if the visitor came from the Careers page, go back in history so the list
  // returns exactly where they left it (with the page cross-fade); otherwise open Careers.
  document.querySelectorAll('[data-back]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var target = a.getAttribute('data-back');
      var ref = document.referrer;
      if (ref && ref.indexOf(location.origin) === 0 && ref.split('?')[0].split('#')[0].slice(-target.length) === target && history.length > 1) {
        e.preventDefault();
        history.back();
      }
    });
  });

  // Ask before destructive actions.
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });
})();
