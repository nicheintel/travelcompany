// LamazonLoads — small enhancements and animations (the site works without JavaScript too).
(function () {
  document.documentElement.classList.add('js'); // pop-ups are now opened and closed by script only (see .modal in style.css)
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
    }, { rootMargin: margin || '0px 0px 8% 0px' }); // start just before it scrolls into view
    io.observe(el);
  }

  // Fade sections in as they scroll into view, cards in a row one after another.
  document.querySelectorAll('.reveal').forEach(function (el) {
    var sibs = el.parentElement ? Array.prototype.filter.call(el.parentElement.children, function (c) { return c.classList.contains('reveal'); }) : [];
    var i = sibs.indexOf(el);
    if (i > 0) el.style.transitionDelay = (i % 4) * 60 + 'ms';
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
        var first = m.querySelector('[data-autofocus]') || m.querySelector('.modal-body input:not([type=hidden]):not([type=checkbox]):not([type=radio]), .modal-body .btn');
        if (err) err.scrollIntoView({ block: 'nearest' });
        if (first && !err) first.focus({ preventScroll: true });
      }, 80);
    };
    var close = function () {
      if (!m.classList.contains('is-open')) return;
      m.classList.add('is-closing');
      setTimeout(function () {
        m.classList.remove('is-open', 'is-closing'); m.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.modal.is-open')) document.documentElement.classList.remove('modal-lock');
        if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
        m.dispatchEvent(new Event('modal:closed'));
      }, 220);
      var param = m.getAttribute('data-modal-param') || 'apply';
      var search = location.search.replace(new RegExp('([?&])' + param + '=[^&]*(&|$)'), function (all, a, b) { return b ? a : ''; });
      if (location.hash === '#' + m.id || search !== location.search) history.replaceState(null, '', location.pathname + search);
    };
    document.querySelectorAll('[data-modal-open="' + m.id + '"]').forEach(function (b) {
      b.addEventListener('click', function (e) { e.preventDefault(); open(); });
    });
    m.addEventListener('click', function (e) { // also works for content loaded later
      if (e.target.closest('[data-modal-close]')) { e.preventDefault(); close(); }
    });
    m.addEventListener('modal:open', open);
    document.addEventListener('keydown', function (e) {
      if (!m.classList.contains('is-open')) return;
      var opened = document.querySelectorAll('.modal.is-open');
      if (opened[opened.length - 1] !== m) return; // a pop-up opened on top of this one handles the keys
      if (document.querySelector('dialog[open]')) return; // so does an "Are you sure?" box
      if (e.key === 'Escape') { close(); return; }
      if (e.key === 'Tab') { // keep the keyboard inside the pop-up
        var f = focusables(); if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
        else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      }
    });
    if (m.classList.contains('is-open') || location.hash === '#' + m.id) open();
  });

  // Admin → Applications: tap a row to see the full application in the pop-up (no page reload).
  var appModal = document.getElementById('app');
  document.querySelectorAll('[data-app-open]').forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (!appModal || e.metaKey || e.ctrlKey || e.shiftKey) return;
      e.preventDefault();
      var href = row.getAttribute('href');
      fetch(href + (href.indexOf('?') === -1 ? '?' : '&') + 'partial=1', { credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
        .then(function (html) {
          appModal.querySelector('[data-modal-content]').innerHTML = html;
          history.replaceState(null, '', href);
          appModal.dispatchEvent(new Event('modal:open'));
        })
        .catch(function () { window.location.href = href; });
    });
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

  // Phone boxes: format US numbers while typing, e.g. 5551234567 -> (555) 123-4567.
  // Numbers starting with + (other countries) are left as typed.
  var fmtPhone = function (d) {
    if (d.length > 10 && d.charAt(0) === '1') d = d.slice(1);
    d = d.slice(0, 10);
    if (!d) return '';
    if (d.length <= 3) return '(' + d;
    if (d.length <= 6) return '(' + d.slice(0, 3) + ') ' + d.slice(3);
    return '(' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6);
  };
  document.querySelectorAll('input[type=tel]').forEach(function (input) {
    if (!input.placeholder) input.placeholder = '(555) 123-4567';
    var last = input.value;
    var apply = function (e) {
      var v = input.value;
      if (/^\s*\+/.test(v) && !/^\s*\+\s*1/.test(v)) { last = v; return; }
      var caret = input.selectionStart === null ? v.length : input.selectionStart;
      var before = v.slice(0, caret).replace(/\D/g, '').length; // digits before the cursor
      var all = v.replace(/\D/g, '');
      var deleting = e && e.inputType && e.inputType.indexOf('delete') === 0;
      // Backspace on "(", ")", " " or "-" removes the digit before it instead of doing nothing
      if (deleting && e.inputType === 'deleteContentBackward' && all === last.replace(/\D/g, '') && before > 0) {
        all = all.slice(0, before - 1) + all.slice(before);
        before--;
      }
      if (all.length > 10 && all.charAt(0) === '1' && v.replace(/\s/g, '').charAt(0) !== '(') before = Math.max(0, before - 1);
      var out = fmtPhone(all);
      last = out;
      if (out === v) return;
      input.value = out;
      var pos = 0, seen = 0;
      while (pos < out.length && seen < before) { if (/\d/.test(out.charAt(pos))) seen++; pos++; }
      if (seen > 0) while (pos < out.length && /\D/.test(out.charAt(pos))) pos++; // hop over ") " and "-"
      if (document.activeElement === input) input.setSelectionRange(pos, pos);
    };
    input.addEventListener('input', apply);
    if (input.value) apply();
  });

  // Pause looping animations (map, road stripes, cursor…) while they are off screen, so scrolling stays smooth.
  if ('IntersectionObserver' in window) {
    var animIo = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        en.target.classList.toggle('anim-paused', !en.isIntersecting);
        en.target.querySelectorAll('svg').forEach(function (svg) {
          if (svg.pauseAnimations) { if (en.isIntersecting) svg.unpauseAnimations(); else svg.pauseAnimations(); }
        });
      });
    }, { rootMargin: '100px 0px' });
    document.querySelectorAll('.hero, .sol-grid, .code-card, .page-hero, .job-band, .site-footer, .auth-side').forEach(function (el) { animIo.observe(el); });
  }

  // FAQ page: search as you type, highlight the section you're reading, open a question from a link (#fee)
  var faqSearch = document.querySelector('[data-faq-search]');
  if (faqSearch) {
    var items = document.querySelectorAll('[data-faq-item]');
    var groups = document.querySelectorAll('[data-faq-group]');
    var empty = document.querySelector('[data-faq-empty]');
    faqSearch.addEventListener('input', function () {
      var words = faqSearch.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
      var shown = 0;
      items.forEach(function (d) {
        var text = d.textContent.toLowerCase();
        var hit = words.every(function (w) { return text.indexOf(w) !== -1; });
        d.hidden = !hit; if (hit) shown++;
        if (words.length && hit && words.length) d.open = shown <= 3; // open the best few matches
        if (!words.length) d.open = false;
      });
      groups.forEach(function (g) { g.hidden = !g.querySelector('[data-faq-item]:not([hidden])'); });
      empty.hidden = shown > 0;
      empty.querySelector('[data-faq-term]').textContent = faqSearch.value;
    });
    var links = document.querySelectorAll('[data-faq-link]');
    if ('IntersectionObserver' in window) {
      var gio = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) links.forEach(function (a) { a.classList.toggle('on', a.getAttribute('data-faq-link') === en.target.id); });
        });
      }, { rootMargin: '-30% 0px -60% 0px' });
      groups.forEach(function (g) { gio.observe(g); });
    }
    var openHash = function () {
      var el = location.hash && document.getElementById(location.hash.slice(1));
      if (el && el.tagName === 'DETAILS') { el.open = true; el.scrollIntoView({ block: 'center' }); }
    };
    window.addEventListener('hashchange', openHash); openHash();
  }

  // Privacy policy: highlight the section you're reading in the side menu
  var legalLinks = document.querySelectorAll('[data-legal-link]');
  if (legalLinks.length && 'IntersectionObserver' in window) {
    var lio = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) legalLinks.forEach(function (a) { a.classList.toggle('on', a.getAttribute('data-legal-link') === en.target.id); });
      });
    }, { rootMargin: '-25% 0px -65% 0px' });
    document.querySelectorAll('.legal-body > section').forEach(function (sec) { lio.observe(sec); });
  }

  // "Copy" buttons (FAQ quick driver response)
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = document.getElementById(btn.getAttribute('data-copy')).textContent;
      var label = btn.querySelector('[data-copy-label]');
      var done = function () { var old = label.textContent; label.textContent = 'Copied!'; btn.classList.add('copied'); setTimeout(function () { label.textContent = old; btn.classList.remove('copied'); }, 1800); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(done, function () { fallback(); });
      else fallback();
      function fallback() { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (e) {} t.remove(); }
    });
  });

  // Keep form tokens fresh: when someone comes back to an old tab, uses Back, or submits a form that has been
  // open a long time, quietly fetch the current token first so they never see "please try again".
  var csrfUrl = (document.querySelector('a.brand') || {}).getAttribute ? document.querySelector('a.brand').getAttribute('href').replace(/\/?$/, '/') + 'csrf.php' : 'csrf.php';
  var loadedAt = Date.now(), hiddenAt = 0;
  var refreshCsrf = function () {
    return fetch(csrfUrl, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.csrf) return;
        document.querySelectorAll('input[name=csrf]').forEach(function (i) { i.value = d.csrf; });
        document.querySelectorAll('[data-csrf]').forEach(function (el) { el.setAttribute('data-csrf', d.csrf); });
        loadedAt = Date.now();
      })
      .catch(function () {});
  };
  window.addEventListener('pageshow', function (e) { if (e.persisted) refreshCsrf(); });
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) hiddenAt = Date.now();
    else if (hiddenAt && Date.now() - hiddenAt > 5 * 60 * 1000) refreshCsrf();
  });
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (e.defaultPrevented || !form.querySelector('input[name=csrf]') || Date.now() - loadedAt < 10 * 60 * 1000 || form.getAttribute('data-fresh')) return;
    e.preventDefault();
    var submitter = e.submitter;
    refreshCsrf().then(function () {
      form.setAttribute('data-fresh', '1');
      if (form.requestSubmit) form.requestSubmit(submitter && submitter.form === form ? submitter : undefined); else form.submit();
      form.removeAttribute('data-fresh');
    });
  }, true);

  // Messages ("Saved", "Application sent", errors) slide in and tidy themselves away. The thin bar shows the time
  // left; it pauses while the pointer is on the message or the tab is in the background. Swipe sideways to dismiss.
  document.querySelectorAll('[data-toast]').forEach(function (t, i) {
    var keep = t.hasAttribute('data-toast-keep'), bar = t.querySelector('.toast-bar'), gone = false, timer = 0, startedAt = 0;
    var left = (t.classList.contains('toast-error') ? 10000 : 6000) + i * 700;
    if (bar) bar.style.animationDuration = left + 'ms';
    var close = function () {
      if (gone) return; gone = true; clearTimeout(timer);
      t.classList.add('is-leaving'); setTimeout(function () { t.remove(); }, 340);
    };
    var run = function () { if (gone || keep) return; t.classList.remove('is-paused'); startedAt = Date.now(); clearTimeout(timer); timer = setTimeout(close, left); };
    var pause = function () { if (gone || keep || t.classList.contains('is-paused')) return; clearTimeout(timer); left = Math.max(0, left - (Date.now() - startedAt)); t.classList.add('is-paused'); };
    t.querySelector('[data-toast-close]').addEventListener('click', close);
    t.addEventListener('mouseenter', pause);
    t.addEventListener('mouseleave', run);
    document.addEventListener('visibilitychange', function () { if (document.hidden) pause(); else run(); });
    var x0 = null;
    t.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse' || e.target.closest('button, a')) return;
      x0 = e.clientX; t.style.transition = 'none'; pause();
      if (t.setPointerCapture) t.setPointerCapture(e.pointerId);
    });
    t.addEventListener('pointermove', function (e) {
      if (x0 === null) return;
      var dx = e.clientX - x0; t.style.transform = 'translateX(' + dx + 'px)'; t.style.opacity = String(1 - Math.min(Math.abs(dx) / 260, .7));
    });
    var release = function (e) {
      if (x0 === null) return;
      var dx = e.clientX - x0; x0 = null; t.style.transition = '';
      if (Math.abs(dx) > 70) {
        gone = true; clearTimeout(timer);
        t.style.transform = 'translateX(' + (dx > 0 ? 110 : -110) + '%)'; t.classList.add('is-swiped');
        setTimeout(function () { t.remove(); }, 260);
      } else { t.style.transform = ''; t.style.opacity = ''; run(); }
    };
    t.addEventListener('pointerup', release);
    t.addEventListener('pointercancel', release);
    if (!document.hidden) run(); else if (!keep) t.classList.add('is-paused');
  });

  // Applications list: tick rows, then "Delete selected".
  var bulk = document.querySelector('[data-bulk]');
  if (bulk) {
    var boxes = document.querySelectorAll('[data-bulk-box]'), all = document.querySelector('[data-bulk-all]');
    var bar = document.querySelector('[data-bulk-bar]'), count = document.querySelector('[data-bulk-count]');
    var sync = function () {
      var n = 0;
      boxes.forEach(function (b) { b.closest('.app-item').classList.toggle('is-picked', b.checked); if (b.checked) n++; });
      count.textContent = n; bar.hidden = n === 0;
      if (all) { all.checked = n > 0 && n === boxes.length; all.indeterminate = n > 0 && n < boxes.length; }
      bulk.setAttribute('data-confirm', 'Delete ' + (n === 1 ? 'this application' : 'these ' + n + ' applications') + '? This can’t be undone. Their accounts and documents stay.');
    };
    boxes.forEach(function (b) { b.addEventListener('change', sync); });
    if (all) all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); sync(); });
    document.querySelector('[data-bulk-clear]').addEventListener('click', function () { boxes.forEach(function (b) { b.checked = false; }); sync(); });
    sync();
  }

  // Document viewer: PDFs and photos open in a smooth pop-up instead of a new tab. Phones without a built-in
  // PDF viewer (most Android phones) get the pages drawn by PDF.js, loaded only then. Word files offer a download.
  var dv = document.getElementById('docview');
  if (dv) {
    var dvBody = dv.querySelector('[data-dv-body]'), dvList = [], dvAt = 0, dvRun = 0, dvPdf = null, pdfjs = null;
    var dvq = function (s) { return dv.querySelector(s); };
    var pdfBase = dv.getAttribute('data-pdfjs');
    var nativePdf = navigator.pdfViewerEnabled === true;
    var loadPdfjs = function () {
      pdfjs = pdfjs || import(pdfBase + 'pdf.min.js').then(function (lib) {
        lib.GlobalWorkerOptions.workerSrc = pdfBase + 'pdf.worker.min.js';
        return lib;
      }, function (err) { pdfjs = null; throw err; });
      return pdfjs;
    };
    var dropPdf = function () { if (dvPdf) { dvPdf.destroy(); dvPdf = null; } };
    var loaded = function (run) { if (run === dvRun) dvBody.classList.remove('is-loading'); };
    var card = function (title, text, a) {
      dvBody.className = 'dv-body';
      dvBody.innerHTML = '<div class="dv-card"><div class="verify-ico"></div><h3></h3><p></p><a class="btn btn-primary"></a></div>';
      dvBody.querySelector('.verify-ico').innerHTML = dvq('[data-dv-download]').innerHTML;
      dvBody.querySelector('h3').textContent = title;
      dvBody.querySelector('p').textContent = text;
      var btn = dvBody.querySelector('.btn');
      btn.href = a.getAttribute('data-doc-download'); btn.innerHTML = dvq('[data-dv-download]').innerHTML + ' Download';
    };
    var show = function (i) {
      var a = dvList[i], run = ++dvRun;
      var type = a.getAttribute('data-doc-view'), src = a.getAttribute('href'), name = a.getAttribute('data-doc-name') || 'Document';
      dvAt = i; dropPdf();
      dvq('[data-dv-kind]').textContent = a.getAttribute('data-doc-kind') || 'Document';
      dvq('[data-dv-name]').textContent = name;
      dvq('[data-dv-download]').href = a.getAttribute('data-doc-download');
      dvq('[data-dv-tab]').href = src; dvq('[data-dv-tab]').hidden = type === 'file';
      var many = dvList.length > 1, prev = dvq('[data-dv-prev]'), next = dvq('[data-dv-next]'), count = dvq('[data-dv-count]');
      prev.hidden = next.hidden = count.hidden = !many;
      prev.disabled = i === 0; next.disabled = i === dvList.length - 1;
      count.textContent = (i + 1) + ' of ' + dvList.length;
      dvBody.className = 'dv-body is-loading dv-' + type; dvBody.scrollTop = 0;
      dvBody.innerHTML = '<div class="dv-spin" role="status"><span></span>Opening…</div>';
      var fail = function () { if (run === dvRun) card('We couldn’t show this file here', 'You can still download it and open it on your device.', a); };
      if (type === 'image') {
        var img = new Image();
        img.className = 'dv-img'; img.alt = name;
        img.onload = function () { if (run !== dvRun) return; dvBody.appendChild(img); loaded(run); };
        img.onerror = fail;
        img.addEventListener('click', function () { dvBody.classList.toggle('is-zoom'); });
        img.src = src;
      } else if (type === 'pdf' && nativePdf) {
        var f = document.createElement('iframe');
        f.className = 'dv-frame'; f.title = name;
        f.onload = function () { loaded(run); };
        f.src = src + '#view=FitH';
        dvBody.appendChild(f);
      } else if (type === 'pdf') {
        loadPdfjs().then(function (lib) {
          return lib.getDocument({ url: src, isEvalSupported: false, standardFontDataUrl: pdfBase + 'standard_fonts/' }).promise;
        }).then(function (pdf) {
          if (run !== dvRun) { pdf.destroy(); return; }
          dvPdf = pdf;
          var wrap = document.createElement('div'), pages = Math.min(pdf.numPages, 60), width = Math.max(dvBody.clientWidth - 24, 200);
          var ratio = Math.min(window.devicePixelRatio || 1, 2), chain = Promise.resolve();
          wrap.className = 'dv-pages';
          var draw = function (n) {
            return pdf.getPage(n).then(function (page) {
              if (run !== dvRun) return;
              var scale = width / page.getViewport({ scale: 1 }).width, vp = page.getViewport({ scale: scale * ratio });
              var c = document.createElement('canvas');
              c.className = 'dv-page'; c.width = Math.floor(vp.width); c.height = Math.floor(vp.height); c.style.width = Math.floor(vp.width / ratio) + 'px';
              wrap.appendChild(c);
              if (n === 1) { dvBody.appendChild(wrap); loaded(run); }
              return page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
            });
          };
          for (var n = 1; n <= pages; n++) chain = chain.then(draw.bind(null, n));
          return chain;
        }).catch(fail);
      } else {
        card('Preview not available', 'Word files can’t be shown here. Download it to open it on your device.', a);
      }
    };
    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('[data-doc-view]');
      if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button) return;
      e.preventDefault();
      // The other documents in the same list can be flipped through with the arrows
      var scope = a.closest('table, .modal-body, [data-dv-scope]') || a.parentNode, seen = {};
      dvList = Array.prototype.filter.call(scope.querySelectorAll('[data-doc-view]'), function (x) {
        var h = x.getAttribute('href'); if (seen[h]) return false; seen[h] = true; return true;
      });
      var at = dvList.map(function (x) { return x.getAttribute('href'); }).indexOf(a.getAttribute('href'));
      dv.dispatchEvent(new Event('modal:open'));
      show(at < 0 ? 0 : at);
    });
    dvq('[data-dv-prev]').addEventListener('click', function () { if (dvAt > 0) show(dvAt - 1); });
    dvq('[data-dv-next]').addEventListener('click', function () { if (dvAt < dvList.length - 1) show(dvAt + 1); });
    document.addEventListener('keydown', function (e) {
      if (!dv.classList.contains('is-open') || dvList.length < 2) return;
      if (e.key === 'ArrowLeft' && dvAt > 0) show(dvAt - 1);
      if (e.key === 'ArrowRight' && dvAt < dvList.length - 1) show(dvAt + 1);
    });
    dv.addEventListener('modal:closed', function () { dvRun++; dropPdf(); dvBody.innerHTML = ''; });
  }

  // Inline rename (Admin → Walmart cities): the Save button shows only after the name is changed.
  document.querySelectorAll('[data-inline-edit]').forEach(function (f) {
    var input = f.querySelector('input[type=text]'), btn = f.querySelector('[data-save]'), start = input.value;
    input.addEventListener('input', function () { btn.hidden = input.value.trim() === start; });
    input.addEventListener('keydown', function (e) { if (e.key === 'Escape') { input.value = start; btn.hidden = true; input.blur(); } });
  });

  // Onboarding: payment details form shows the right field for Zelle / Cash App / Apple Pay / direct deposit.
  document.querySelectorAll('[data-payout]').forEach(function (f) {
    var labels = { zelle: 'Zelle email or phone number', cashapp: 'Your $Cashtag', applepay: 'Apple Pay phone number or email' };
    var holders = { zelle: 'you@email.com or (555) 123-4567', cashapp: '$YourName', applepay: '(555) 123-4567' };
    var box = f.querySelector('[data-pay-handle]'), label = f.querySelector('[data-pay-label]'), dd = f.querySelector('[data-pay-dd]'), input = box.querySelector('input');
    var sync = function () {
      var m = (f.querySelector('input[name=payout_method]:checked') || {}).value || 'zelle';
      box.hidden = m === 'direct_deposit'; dd.hidden = m !== 'direct_deposit';
      if (labels[m]) { label.textContent = labels[m]; input.placeholder = holders[m]; }
    };
    f.addEventListener('change', sync); sync();
  });

  // Onboarding: show the chosen file name(s) on the upload button.
  document.querySelectorAll('[data-onb-file]').forEach(function (input) {
    input.addEventListener('change', function () {
      var out = input.parentNode.querySelector('[data-onb-name]');
      var n = input.files ? input.files.length : 0;
      if (out && n) out.textContent = n === 1 ? input.files[0].name : n + ' files selected';
      input.closest('.onb-drop').classList.toggle('has-file', n > 0);
    });
  });

  // Agreement signing: draw a signature with a finger or mouse; it's sent as a PNG with the form.
  document.querySelectorAll('[data-sig-pad]').forEach(function (pad) {
    var canvas = pad.querySelector('canvas');
    if (!canvas || !canvas.getContext) return;
    var ctx = canvas.getContext('2d');
    var form = pad.closest('form'), input = form.querySelector('[data-sig-input]'), hint = pad.querySelector('[data-sig-hint]');
    var drawing = false, inked = false, last = null;
    var size = function () {
      var r = canvas.getBoundingClientRect(), ratio = Math.min(window.devicePixelRatio || 1, 2);
      var keep = inked ? canvas.toDataURL('image/png') : null;
      canvas.width = Math.round(r.width * ratio); canvas.height = Math.round(r.height * ratio);
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
      ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0A2463';
      if (keep) { var img = new Image(); img.onload = function () { ctx.drawImage(img, 0, 0, r.width, r.height); }; img.src = keep; }
    };
    var pos = function (e) { var r = canvas.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; };
    canvas.addEventListener('pointerdown', function (e) { drawing = true; last = pos(e); canvas.setPointerCapture(e.pointerId); e.preventDefault(); });
    canvas.addEventListener('pointermove', function (e) {
      if (!drawing) return;
      var p = pos(e);
      ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
      last = p; inked = true; hint.hidden = true;
    });
    var end = function () { if (drawing) { drawing = false; if (inked) input.value = canvas.toDataURL('image/png'); } };
    canvas.addEventListener('pointerup', end); canvas.addEventListener('pointercancel', end);
    pad.querySelector('[data-sig-clear]').addEventListener('click', function () {
      ctx.clearRect(0, 0, canvas.width, canvas.height); inked = false; input.value = ''; hint.hidden = false;
    });
    window.addEventListener('resize', size); size();
  });

  // "Print or save as PDF" on the signed agreement
  document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  // Admin menu on phones is a row of tabs: start it scrolled to the current page.
  var anActive = document.querySelector('.an-links a.active, .dash-nav > a.active');
  if (anActive && window.matchMedia('(max-width: 860px)').matches) anActive.parentNode.scrollLeft = anActive.offsetLeft - 12;

  // Sign-up: the vehicle choice is shown for drivers and owner-operators only
  document.querySelectorAll('select[data-drives]').forEach(function (sel) {
    var field = sel.form && sel.form.querySelector('[data-vehicle-field]');
    if (!field) return;
    var sync = function () { field.hidden = sel.getAttribute('data-drives').split(',').indexOf(sel.value) === -1; };
    sel.addEventListener('change', sync); sync();
  });

  // Vehicle type cards: ticking "Other" opens the box to type the vehicle
  document.querySelectorAll('[data-veh-pick]').forEach(function (set) {
    var other = set.querySelector('input[value="other"]'), box = set.querySelector('[data-veh-other]');
    if (!other || !box) return;
    other.addEventListener('change', function () {
      box.hidden = !other.checked;
      if (other.checked) box.querySelector('input').focus();
    });
  });

  // Show / hide the password being typed
  document.querySelectorAll('[data-pw-toggle]').forEach(function (b) {
    var input = document.getElementById(b.getAttribute('aria-controls'));
    if (!input) return;
    b.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.setAttribute('aria-pressed', show ? 'true' : 'false');
      b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
    if (input.form) input.form.addEventListener('submit', function () { input.type = 'password'; b.setAttribute('aria-pressed', 'false'); }); // so the browser offers to save it
  });

  // City picker: type a few letters of the city, or a ZIP code, then pick "City, ST" from the list of every US
  // city. Typed text alone isn't accepted, so there are no misspelled cities. The list ("City, ST<TAB>ZIP codes"
  // lines, ~650 KB, about 250 KB compressed on the way) loads on first use. If it can't load, the typed
  // "City, ST" is sent and checked on the server against the same list.
  var STATE_NAMES = { AL: 'Alabama', AK: 'Alaska', AZ: 'Arizona', AR: 'Arkansas', CA: 'California', CO: 'Colorado', CT: 'Connecticut', DE: 'Delaware', DC: 'District of Columbia', FL: 'Florida', GA: 'Georgia', HI: 'Hawaii', ID: 'Idaho', IL: 'Illinois', IN: 'Indiana', IA: 'Iowa', KS: 'Kansas', KY: 'Kentucky', LA: 'Louisiana', ME: 'Maine', MD: 'Maryland', MA: 'Massachusetts', MI: 'Michigan', MN: 'Minnesota', MS: 'Mississippi', MO: 'Missouri', MT: 'Montana', NE: 'Nebraska', NV: 'Nevada', NH: 'New Hampshire', NJ: 'New Jersey', NM: 'New Mexico', NY: 'New York', NC: 'North Carolina', ND: 'North Dakota', OH: 'Ohio', OK: 'Oklahoma', OR: 'Oregon', PA: 'Pennsylvania', RI: 'Rhode Island', SC: 'South Carolina', SD: 'South Dakota', TN: 'Tennessee', TX: 'Texas', UT: 'Utah', VT: 'Vermont', VA: 'Virginia', WA: 'Washington', WV: 'West Virginia', WI: 'Wisconsin', WY: 'Wyoming' };
  var cityData = null, cityLoading = null;
  var cityKey = function (t) { return t.toLowerCase().replace(/[\u2019'.]/g, '').replace(/-/g, ' ').replace(/\s+/g, ' ').trim(); }; // "St. Mary's" = "st marys", "Winston-Salem" = "winston salem"
  var loadCities = function (src) {
    if (cityData) return Promise.resolve(cityData);
    cityLoading = cityLoading || fetch(src, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('City list: HTTP ' + r.status);
      return r.text();
    }).then(function (t) {
      var out = [];
      t.split('\n').forEach(function (line) {
        var tab = line.indexOf('\t'), label = (tab === -1 ? line : line.slice(0, tab)).trim(), i = label.lastIndexOf(', '), st = label.slice(i + 2);
        if (i < 1 || !STATE_NAMES[st]) return;
        out.push({ label: label, city: label.slice(0, i), st: st, key: cityKey(label.slice(0, i)), zips: tab === -1 ? '' : ' ' + line.slice(tab + 1).trim() });
      });
      if (out.length < 1000) throw new Error('City list: unexpected content'); // e.g. an error page instead of the list
      cityData = out;
      return cityData;
    });
    cityLoading.catch(function () { cityLoading = null; }); // try again next time
    return cityLoading;
  };
  var isZip = function (q) { return /^\d{1,5}$/.test(q.trim()); };
  var searchable = function (q) { q = q.trim(); return isZip(q) ? q.length >= 3 : q.length >= 2; };
  document.querySelectorAll('[data-city-pick]').forEach(function (box) {
    var input = box.querySelector('[data-city-input]'), value = box.querySelector('[data-city-value]'), list = box.querySelector('.city-list'), hint = box.querySelector('[data-city-hint]');
    var form = input.form, items = [], active = -1, hintText = hint.textContent, offline = false;
    var setValid = function (ok) { box.classList.toggle('is-valid', ok); };
    setValid(value.value !== '' && value.value === input.value);
    var close = function () { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; };
    var choose = function (c) {
      input.value = c.label; value.value = c.label; setValid(true); close();
      box.classList.remove('has-error'); hint.textContent = hintText;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    };
    var search = function (q) {
      q = cityKey(q);
      if (isZip(q)) { // ZIP code: every city with a ZIP starting with these digits
        if (q.length < 3) return [];
        var hits = [];
        for (var z = 0; z < cityData.length && hits.length < 60; z++) {
          var at = cityData[z].zips.indexOf(' ' + q);
          if (at !== -1) hits.push({ c: cityData[z], zip: cityData[z].zips.substr(at + 1, 5) });
        }
        return hits;
      }
      var st = null, m = q.match(/^(.*?)[ ,]+([a-z]{2})$/); // "atlanta ga" or "atlanta, ga"
      if (m && STATE_NAMES[m[2].toUpperCase()]) { st = m[2].toUpperCase(); q = m[1].replace(/,$/, '').trim(); }
      q = q.replace(/,$/, '');
      if (q.length < 2 && !st) return [];
      var qs = [q]; // "st louis" also finds "Saint Louis" (the USPS spelling), "ft worth" "Fort Worth", and back
      [['st', 'saint'], ['ft', 'fort'], ['mt', 'mount']].forEach(function (p) {
        [[p[0], p[1]], [p[1], p[0]]].forEach(function (ab) {
          var re = new RegExp('(^| )' + ab[0] + ' ');
          if (re.test(q)) qs.push(q.replace(re, '$1' + ab[1] + ' '));
        });
      });
      var starts = [], words = [];
      for (var i = 0; i < cityData.length && starts.length < 60; i++) {
        var c = cityData[i], start = false, word = false;
        if (st && c.st !== st) continue;
        for (var v = 0; v < qs.length; v++) {
          if (!qs[v] || c.key.indexOf(qs[v]) === 0) start = true;
          else if (c.key.indexOf(' ' + qs[v]) !== -1) word = true;
        }
        if (start) starts.push({ c: c });
        else if (word && words.length < 60) words.push({ c: c });
      }
      return starts.concat(words).slice(0, 60);
    };
    var render = function (zip) {
      list.innerHTML = '';
      if (!items.length) {
        var none = document.createElement('li'); none.className = 'city-none';
        none.textContent = zip ? 'No city found for that ZIP code. Check the number, or type your city.' : 'No city found. Check the spelling, or try your ZIP code.';
        list.appendChild(none);
      }
      items.forEach(function (it, i) {
        var c = it.c, li = document.createElement('li'); li.id = input.id + '-opt-' + i; li.setAttribute('role', 'option'); li.className = 'city-opt';
        var b = document.createElement('b'); b.textContent = c.city + ', ' + c.st; li.appendChild(b);
        var s = document.createElement('small'); s.textContent = (STATE_NAMES[c.st] || '') + (it.zip ? ' · ' + it.zip : ''); li.appendChild(s);
        li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(c); }); // before the field loses focus
        list.appendChild(li);
      });
      list.hidden = false; input.setAttribute('aria-expanded', 'true');
    };
    var highlight = function (i) {
      var opts = list.querySelectorAll('.city-opt'); if (!opts.length) return;
      active = (i + opts.length) % opts.length;
      opts.forEach(function (o, k) { o.classList.toggle('on', k === active); o.setAttribute('aria-selected', k === active ? 'true' : 'false'); });
      input.setAttribute('aria-activedescendant', opts[active].id); opts[active].scrollIntoView({ block: 'nearest' });
    };
    var show = function () { items = search(input.value); render(isZip(input.value)); if (items.length) highlight(0); };
    var goOffline = function () { // the list didn't load: let them type "City, ST" (the server checks it)
      offline = true; close();
      hint.textContent = 'Type your city and state, like Dallas, TX.';
      input.placeholder = 'City, ST';
    };
    var load = function () { return loadCities(box.getAttribute('data-src')).then(function (d) { offline = false; return d; }, function (err) { goOffline(); throw err; }); };
    input.addEventListener('focus', function () { load().catch(function () {}); });
    input.addEventListener('input', function () {
      value.value = ''; setValid(false); box.classList.remove('has-error'); if (!offline) hint.textContent = hintText;
      var q = input.value;
      load().then(function () {
        if (input.value !== q) return; // they kept typing
        if (!searchable(q)) { close(); return; }
        show();
      }, function () {});
    });
    input.addEventListener('keydown', function (e) {
      if (list.hidden) { if (e.key === 'ArrowDown' && cityData && searchable(input.value)) { show(); e.preventDefault(); } return; }
      if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
      else if (e.key === 'Enter') { if (items[active]) { e.preventDefault(); choose(items[active].c); } }
      else if (e.key === 'Escape') { close(); }
    });
    input.addEventListener('blur', function () {
      setTimeout(close, 120);
      if (value.value || !input.value.trim() || !cityData) return;
      var typed = input.value.trim().toLowerCase().replace(/\s+/g, ' ');
      var exact = cityData.filter(function (c) { return c.label.toLowerCase() === typed; })[0];
      if (!exact && /^\d{5}$/.test(typed)) { var z = search(typed); if (z.length === 1) exact = z[0].c; }
      if (exact) choose(exact); // typed it exactly, e.g. "Atlanta, GA" or a ZIP code
    });
    if (form) form.addEventListener('submit', function (e) {
      if (value.value) return;
      var typed = input.value.trim().replace(/\s+/g, ' ').replace(/ ?, ?/g, ', ');
      if (!typed && !box.hasAttribute('data-required')) return;
      var m = typed.match(/^(.+?),? ([A-Za-z]{2})$/);
      if (offline && m && STATE_NAMES[m[2].toUpperCase()]) { value.value = m[1] + ', ' + m[2].toUpperCase(); return; }
      e.preventDefault();
      box.classList.add('has-error');
      hint.textContent = offline ? 'Please type your city and state, like Dallas, TX.' : typed ? 'Please choose your city from the list.' : 'Please choose your city and state.';
      input.focus();
    });
  });

  // A link to a section inside a closed panel (e.g. settings.php#delete) opens that panel
  if (location.hash.length > 1) {
    var target = document.getElementById(location.hash.slice(1)), box = target && target.closest('details');
    if (box) box.open = true;
  }

  // "Chat with us" links open the chat window when the page has one
  document.querySelectorAll('[data-open-chat]').forEach(function (a) {
    a.addEventListener('click', function (e) { var fab = document.querySelector('[data-chat-open]'); if (fab) { e.preventDefault(); fab.click(); } });
  });

  // Keep the last two words of a sentence together, so one word never sits alone on the last line
  // (and "cut-off times" can't split at its hyphen). Chrome's text-wrap: pretty skips short text and
  // squeezes two-line text, and Firefox and Safari don't fully support it, so this is done here.
  var kept = [];
  document.querySelectorAll('p, li, td, dd, small, label, figcaption, blockquote, .hint, .ss-hint span, b, strong, span, h1, h2, h3, h4').forEach(function (el) {
    if (el.closest('.contract-body, .chat-msgs, pre, code, textarea, [contenteditable], .btn, button, svg, l-nw')) return;
    if (/^(B|STRONG|SPAN)$/.test(el.tagName) && getComputedStyle(el).display === 'inline') return; // inline bits belong to their sentence
    if (/^H[1-4]$/.test(el.tagName) && el.textContent.trim().split(/\s+/).length < 5) return; // short headings: balance handles them
    var walk = document.createTreeWalker(el, NodeFilter.SHOW_TEXT), n, nodes = [];
    while ((n = walk.nextNode())) if (n.data.trim()) nodes.push(n);
    var last = nodes[nodes.length - 1];
    if (!last || last.parentNode.closest('l-nw')) return;
    var m = last.data.match(/(\S+)[ \u00a0](\S{1,16})(\s*)$/);
    if (m) {
      if ((m[1] + m[2]).length > 28) return;
      if (/flex|grid/.test(getComputedStyle(last.parentNode).display)) { // a wrapper would become its own layout box here
        last.data = last.data.slice(0, m.index) + m[1] + '\u00a0' + m[2] + m[3];
        return;
      }
      var keep = document.createElement('l-nw'); keep.textContent = m[1] + ' ' + m[2];
      var rest = document.createTextNode(m[3]);
      last.data = last.data.slice(0, m.index);
      last.parentNode.insertBefore(rest, last.nextSibling); last.parentNode.insertBefore(keep, rest); kept.push(keep);
    } else if (nodes.length > 1 && /^\s*\S{1,16}\s*$/.test(last.data) && / $/.test(nodes[nodes.length - 2].data)) {
      // the last word is in its own tag ("Anything else? <span>(optional)</span>"): glue it to the word before
      var prev = nodes[nodes.length - 2]; prev.data = prev.data.replace(/ $/, '\u00a0');
    }
  });
  // Two long words in big type can be wider than a phone screen ("Owner-Operator Agreement"): let those wrap
  kept.filter(function (k) {
    var box = k.parentNode; while (box.parentNode && !box.clientWidth) box = box.parentNode; // inline tags have no width of their own
    return k.getBoundingClientRect().width > box.clientWidth;
  }).forEach(function (k) {
    k.parentNode.replaceChild(document.createTextNode(k.textContent), k);
  });

  // "Are you sure?" pop-up. The question becomes the title and the rest the note under it:
  // "Delete this chat? This can't be undone." Buttons: Cancel and "Delete" (red), or data-confirm-ok's label.
  var cfm = document.querySelector('[data-cfm]');
  window.LLConfirm = function (msg, opts) {
    opts = opts || {};
    if (!cfm || !cfm.showModal) return Promise.resolve(window.confirm(msg));
    var parts = msg.match(/[^.?!]+[.?!]+(\s+|$)/g) || [msg], q = -1;
    parts.forEach(function (p, i) { if (q < 0 && /\?\s*$/.test(p)) q = i; });
    if (q < 0) q = 0;
    var title = parts[q].trim(), text = parts.filter(function (p, i) { return i !== q; }).join('').trim();
    var verb = (title.match(/^(Delete|Remove|End)\b/) || [])[1];
    var danger = opts.danger || !!verb;
    var yes = cfm.querySelector('[data-cfm-yes]'), no = cfm.querySelector('[data-cfm-no]');
    cfm.querySelector('[data-cfm-title]').textContent = title;
    cfm.querySelector('[data-cfm-text]').textContent = text;
    yes.textContent = opts.ok || verb || 'Continue';
    yes.className = 'btn ' + (danger ? 'btn-confirm-danger' : 'btn-primary');
    cfm.classList.toggle('is-danger', danger);
    cfm.setAttribute('data-kind', /^(Delete|Remove)$/.test(verb || '') ? 'delete' : (danger ? 'warn' : 'info'));
    cfm.classList.remove('is-closing');
    var back = document.activeElement;
    return new Promise(function (resolve) {
      var finish = function (ok) {
        yes.removeEventListener('click', onYes); no.removeEventListener('click', onNo);
        cfm.removeEventListener('cancel', onCancel); cfm.removeEventListener('click', onBackdrop);
        cfm.classList.add('is-closing');
        setTimeout(function () {
          cfm.classList.remove('is-closing'); cfm.close();
          if (back && back.focus && !ok) back.focus();
          resolve(ok);
        }, 170);
      };
      var onYes = function () { finish(true); }, onNo = function () { finish(false); };
      var onCancel = function (e) { e.preventDefault(); finish(false); };
      var onBackdrop = function (e) { if (e.target === cfm) finish(false); };
      yes.addEventListener('click', onYes); no.addEventListener('click', onNo);
      cfm.addEventListener('cancel', onCancel); cfm.addEventListener('click', onBackdrop);
      cfm.showModal();
      (danger ? no : yes).focus();
    });
  };
  document.addEventListener('submit', function (ev) {
    var form = ev.target, msg = form.getAttribute && form.getAttribute('data-confirm');
    if (!msg || ev.defaultPrevented) return;
    if (form._confirmedAt && Date.now() - form._confirmedAt < 60000) { form._confirmedAt = 0; return; }
    ev.preventDefault();
    var submitter = ev.submitter;
    window.LLConfirm(msg, { ok: form.getAttribute('data-confirm-ok') || '', danger: form.hasAttribute('data-confirm-danger') }).then(function (ok) {
      if (!ok) return;
      form._confirmedAt = Date.now();
      if (form.requestSubmit) form.requestSubmit(submitter && submitter.form === form ? submitter : undefined); else { form._confirmedAt = 0; form.submit(); }
    });
  });

  // Sending a form: the button shows a spinner and "Sending…" and can't be pressed twice while the page loads.
  // Runs last, so forms that are stopped first (the "Are you sure?" box, fresh security token, save-a-job) are skipped.
  var busyWords = { Send: 'Sending…', Save: 'Saving…', Upload: 'Uploading…', Sign: 'Signing…', Create: 'Creating…', Update: 'Updating…', Delete: 'Deleting…', Submit: 'Sending…', Approve: 'Approving…', Remove: 'Removing…', Email: 'Sending…', Resend: 'Sending…', Apply: 'Sending…', Change: 'Saving…', Add: 'Adding…', Reset: 'Saving…' };
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (e.defaultPrevented || form.hasAttribute('data-no-busy') || form.getAttribute('target')) return;
    var btn = (e.submitter && e.submitter.form === form) ? e.submitter : form.querySelector('button[type=submit], button:not([type])');
    if (!btn || btn.classList.contains('is-busy')) return;
    var word = (btn.textContent.trim().match(/^[A-Za-z]+/) || [''])[0];
    var label = btn.getAttribute('data-busy') || busyWords[word] || 'Please wait…';
    btn.setAttribute('data-label', btn.innerHTML);
    btn.style.minWidth = btn.offsetWidth + 'px';
    btn.classList.add('is-busy'); btn.setAttribute('aria-busy', 'true');
    btn.innerHTML = '<span class="btn-spin" aria-hidden="true"></span>' + label;
    document.documentElement.classList.add('is-sending');
    form.querySelectorAll('button[type=submit], button:not([type])').forEach(function (b) { if (b !== btn) b.disabled = true; });
    // keep the button's name/value in the request (a disabled button isn't sent), and undo if the page stays (rare)
    setTimeout(function () { btn.disabled = true; }, 0);
    setTimeout(function () { reset(form); }, 20000);
  });
  var reset = function (scope) {
    document.documentElement.classList.remove('is-sending');
    (scope || document).querySelectorAll('.is-busy').forEach(function (b) {
      b.innerHTML = b.getAttribute('data-label') || b.innerHTML; b.classList.remove('is-busy'); b.removeAttribute('aria-busy'); b.disabled = false; b.style.minWidth = '';
    });
    (scope || document).querySelectorAll('form button[disabled]').forEach(function (b) { if (!b.hasAttribute('data-keep-disabled')) b.disabled = false; });
  };
  window.addEventListener('pageshow', function (e) { if (e.persisted) reset(); }); // back button: buttons work again
})();
