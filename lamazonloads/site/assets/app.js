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

  // Ask before destructive actions.
  document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) ev.preventDefault();
  });
})();
