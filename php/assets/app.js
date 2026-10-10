/* TravelCompany — small, dependency-free scripts for the PHP site. */
(function () {
  "use strict";
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  // Translations for the visitor's language (from the page), with {name} placeholders.
  // Written for older phones too: no ?. or ?? (they stop the whole file running on old browsers).
  const SCRIPT_SRC = document.currentScript ? document.currentScript.src : "";
  let STR = {};
  try { const box = document.querySelector("[data-i18n]"); STR = JSON.parse((box && box.dataset.i18n) || "{}"); } catch (e) { STR = {}; }
  const tr = (s, v = {}) => (STR[s] || s).replace(/\{(\w+)\}/g, (m, k) => (k in v ? v[k] : m));
  const LOCALE = document.documentElement.lang || "en";
  const FINE_POINTER = window.matchMedia("(pointer: fine)").matches;
  const calm = window.matchMedia("(prefers-reduced-motion: reduce)").matches; // device asks for less motion
  // Runs fn after the page has loaded and the browser has a quiet moment, so it never slows the first view.
  const afterLoad = (fn) => {
    const go = () => (window.requestIdleCallback ? window.requestIdleCallback(fn, { timeout: 3000 }) : setTimeout(fn, 1500));
    if (document.readyState === "complete") go();
    else window.addEventListener("load", go, { once: true });
  };

  // ---------- Dates (visitor's timezone) ----------
  function todayIso() {
    const d = new Date();
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  }
  function addDays(iso, n) {
    const [y, m, d] = iso.split("-").map(Number);
    return new Date(Date.UTC(y, m - 1, d + n)).toISOString().slice(0, 10);
  }
  const today = todayIso();

  function initDates(form) {
    const dates = $$("input[data-date]", form);
    // Dates start empty: the visitor picks them (no guessed dates).
    dates.forEach((input) => (input.min = today));
    // Keep "return"/"check-out" after the first date
    dates.filter((i) => i.dataset.after).forEach((later) => {
      const earlier = $(`input[name="${later.dataset.after}"]`, form);
      if (!earlier) return;
      const gap = Number(later.dataset.afterDays || 1);
      const sync = () => {
        const min = earlier.value ? addDays(earlier.value, later.dataset.after === "depart" && form.dataset.searchForm === "flight" ? 0 : 1) : today;
        later.min = min;
        // Once the first date is chosen, suggest the second one (unless it's already valid).
        if (earlier.value && (!later.value || later.value < min)) later.value = addDays(earlier.value, gap);
      };
      earlier.addEventListener("change", sync);
      sync();
    });
  }

  // ---------- Airport autocomplete ----------
  // Every airport with scheduled flights (assets/airports.js), main airports first.
  const fold = (s) => s.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
  // Downloaded after the page has loaded (or when a box is tapped), so pages show sooner.
  let airportList, airportsLoading;
  const airports = () => airportList || (window.AIRPORT_ROWS ? (airportList = window.AIRPORT_ROWS.map(([code, city, country, name, size, words = ""]) => ({ code, city, country, name, size, key: fold(`${city} ${name} ${country} ${words}`) }))) : []);
  const loadAirports = (src) => window.AIRPORT_ROWS ? Promise.resolve() : (airportsLoading = airportsLoading || new Promise((resolve) => {
    const s = document.createElement("script");
    s.src = src;
    s.onload = s.onerror = resolve;
    document.head.appendChild(s);
  }));
  function searchAirports(q) {
    q = fold(q.trim());
    if (!q) return airports().slice(0, 8);
    const score = (a) => {
      if (a.code.toLowerCase() === q) return 0;
      const city = fold(a.city);
      if (city.startsWith(q)) return 1 + a.size + city.length / 100;
      if (a.key.split(/[\s,()/-]+/).some((w) => w.startsWith(q))) return 4 + a.size;
      if (q.length > 2 && a.key.includes(q)) return 7 + a.size;
      return -1;
    };
    const out = [];
    for (const a of airports()) {
      const s = score(a);
      if (s >= 0) out.push([s, a]);
    }
    return out.sort((x, y) => x[0] - y[0]).slice(0, 8).map((x) => x[1]);
  }
  const label = (a) => `${a.city} (${a.code})`;

  function initAirport(box) {
    const input = $("[data-airport-input]", box);
    const code = $("[data-airport-code]", box);
    const list = $("[data-airport-list]", box);
    let results = [];
    let hi = 0;
    let query = "";
    const ready = () => loadAirports(box.dataset.airportSrc).then(() => {
      if (document.activeElement !== input) return;
      results = searchAirports(query);
      render();
    });
    afterLoad(ready);
    const close = () => {
      list.classList.add("hidden");
      input.setAttribute("aria-expanded", "false");
    };
    const render = () => {
      list.innerHTML = "";
      results.forEach((a, i) => {
        const li = document.createElement("li");
        li.setAttribute("role", "option");
        li.className = "flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5" + (i === hi ? " bg-brand-50" : "");
        li.innerHTML =
          '<span><span class="block font-medium text-slate-900"></span><span class="block text-xs text-slate-500"></span></span>' +
          '<span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700"></span>';
        li.children[0].children[0].textContent = `${a.city}, ${a.country}`;
        li.children[0].children[1].textContent = a.name;
        li.children[1].textContent = a.code;
        li.addEventListener("mousedown", (e) => {
          e.preventDefault();
          choose(a);
        });
        list.appendChild(li);
      });
      const opening = list.classList.contains("hidden") && results.length > 0;
      list.classList.toggle("hidden", results.length === 0);
      input.setAttribute("aria-expanded", String(results.length > 0));
      // On a computer, scroll a little if the list opens below the bottom of the screen (phones handle this themselves).
      if (opening && FINE_POINTER && list.getBoundingClientRect().bottom > window.innerHeight) list.scrollIntoView({ block: "nearest", behavior: calm ? "auto" : "smooth" });
    };
    const choose = (a) => {
      code.value = a.code;
      input.value = label(a);
      close();
      input.blur();
      box.dispatchEvent(new CustomEvent("airport-change", { bubbles: true }));
    };
    input.addEventListener("focus", () => {
      input.select();
      query = "";
      results = searchAirports(query);
      hi = 0;
      render();
      ready();
    });
    input.addEventListener("input", () => {
      query = input.value;
      results = searchAirports(query);
      hi = 0;
      render();
    });
    input.addEventListener("keydown", (e) => {
      if (list.classList.contains("hidden")) return;
      if (e.key === "ArrowDown") { e.preventDefault(); hi = Math.min(hi + 1, results.length - 1); render(); }
      else if (e.key === "ArrowUp") { e.preventDefault(); hi = Math.max(hi - 1, 0); render(); }
      else if (e.key === "Enter") { e.preventDefault(); if (results[hi]) choose(results[hi]); }
      else if (e.key === "Escape") close();
    });
    input.addEventListener("blur", () => {
      if (!window.AIRPORT_ROWS) return close(); // list not downloaded yet: keep what's shown
      // Accept a typed code like "LHR", otherwise show the last chosen airport again.
      const typed = airports().find((a) => a.code === input.value.trim().toUpperCase());
      if (typed) code.value = typed.code;
      const current = airports().find((a) => a.code === code.value);
      input.value = current ? label(current) : "";
      close();
    });
  }

  // ---------- Travelers popover ----------
  function initTravelers(box) {
    const toggle = $("[data-travelers-toggle]", box);
    const panel = $("[data-travelers-panel]", box);
    const summary = $("[data-travelers-summary]", box);
    const hidden = (n) => $(`input[name="${n}"]`, box);
    const cabinNames = { economy: tr("Economy"), premium: tr("Premium Economy"), business: tr("Business"), first: tr("First") };
    const update = () => {
      // One lap infant per adult: fewer adults means fewer infants.
      $$("[data-counter][data-max-of]", box).forEach((c) => {
        const input = hidden(c.dataset.counter);
        input.value = String(Math.min(Number(input.value), Number(hidden(c.dataset.maxOf).value)));
      });
      const people = ["adults", "children", "infants"].reduce((n, k) => n + Number((hidden(k) || {}).value || 0), 0);
      const parts = [tr(people === 1 ? "{n} traveler" : "{n} travelers", { n: people })];
      if (hidden("rooms")) parts.push(tr(hidden("rooms").value === "1" ? "{n} room" : "{n} rooms", { n: hidden("rooms").value }));
      if (hidden("cabin")) parts.push(cabinNames[hidden("cabin").value]);
      summary.textContent = parts.join(", ");
      $$("[data-counter]", box).forEach((c) => {
        const v = Number(hidden(c.dataset.counter).value);
        $("[data-count]", c).textContent = v;
        $('[data-step="-1"]', c).disabled = v <= Number(c.dataset.min);
        const max = Math.min(Number(c.dataset.max), c.dataset.maxOf ? Number(hidden(c.dataset.maxOf).value) : Infinity);
        $('[data-step="1"]', c).disabled = v >= max;
      });
      $$("[data-cabin]", box).forEach((b) => {
        const on = hidden("cabin") && b.dataset.cabin === hidden("cabin").value;
        b.classList.toggle("border-brand-500", on);
        b.classList.toggle("bg-brand-50", on);
        b.classList.toggle("font-semibold", on);
        b.classList.toggle("text-brand-700", on);
      });
    };
    $$("[data-counter]", box).forEach((c) => {
      $$("[data-step]", c).forEach((btn) =>
        btn.addEventListener("click", () => {
          const input = hidden(c.dataset.counter);
          const max = Math.min(Number(c.dataset.max), c.dataset.maxOf ? Number(hidden(c.dataset.maxOf).value) : Infinity);
          input.value = String(Math.min(max, Math.max(Number(c.dataset.min), Number(input.value) + Number(btn.dataset.step))));
          update();
        }),
      );
    });
    $$("[data-cabin]", box).forEach((b) => b.addEventListener("click", () => { hidden("cabin").value = b.dataset.cabin; update(); }));
    const setOpen = (open) => { panel.classList.toggle("hidden", !open); toggle.setAttribute("aria-expanded", String(open)); };
    toggle.addEventListener("click", () => setOpen(panel.classList.contains("hidden")));
    $("[data-travelers-done]", box).addEventListener("click", () => setOpen(false));
    document.addEventListener("mousedown", (e) => { if (!box.contains(e.target)) setOpen(false); });
    update();
  }

  // ---------- Search forms ----------
  function initSearchForm(form) {
    initDates(form);
    $$("[data-airport]", form).forEach(initAirport);
    $$("[data-travelers]", form).forEach(initTravelers);
    const error = $("[data-form-error]", form);
    const swap = $("[data-swap]", form);
    if (swap) {
      swap.addEventListener("click", () => {
        const [a, b] = $$("[data-airport]", form);
        const ac = $("[data-airport-code]", a), bc = $("[data-airport-code]", b);
        const ai = $("[data-airport-input]", a), bi = $("[data-airport-input]", b);
        [ac.value, bc.value] = [bc.value, ac.value];
        [ai.value, bi.value] = [bi.value, ai.value];
      });
    }
    const returnBox = $("[data-return-box]", form);
    if (returnBox) {
      const ret = $("input[name=return]", returnBox);
      const sync = () => {
        const oneway = ($("input[data-trip]:checked", form) || {}).value === "oneway";
        ret.disabled = oneway;
        returnBox.classList.toggle("opacity-50", oneway);
      };
      $$("input[data-trip]", form).forEach((r) => r.addEventListener("change", sync));
      sync();
    }
    form.addEventListener("submit", (e) => {
      const codes = $$("[data-airport-code]", form).map((i) => i.value);
      if (!codes.length) return; // e.g. the packages destination list
      let msg = "";
      if (form.dataset.searchForm === "flight") {
        if (!codes[0] || !codes[1]) msg = tr("Please choose where you're flying from and to.");
        else if (codes[0] === codes[1]) msg = tr("Origin and destination must be different.");
      } else if (!codes[codes.length - 1]) {
        msg = tr("Please choose a destination.");
      } else if (codes.length === 2 && codes[0] === codes[1]) {
        msg = tr("Leaving from and going to must be different.");
      }
      if (msg) {
        e.preventDefault();
        error.textContent = msg;
        error.classList.remove("hidden");
      }
    });
  }

  // ---------- Results: filter & sort (data attributes on [data-item]) ----------
  function initResults(scope) {
    const list = $("[data-list]", scope);
    const items = $$("[data-item]", list);
    const count = $("[data-visible-count]", scope);
    const empty = $("[data-empty]", scope);
    const original = items.slice();
    let sortKey = ($("[data-sort-by][aria-pressed=true]", scope) || { dataset: {} }).dataset.sortBy || "";

    function apply() {
      const groups = {};
      $$("[data-filter]:checked", scope).forEach((c) => (groups[c.dataset.filter] = groups[c.dataset.filter] || []).push(c.value));
      const maxes = $$("[data-filter-max]", scope);
      const mins = $$("[data-filter-min]:checked", scope);
      const has = $$("[data-filter-has]:checked", scope);
      const flags = $$("[data-filter-flag]:checked", scope);
      const chip = $("[data-filter-chip]:checked", scope);
      let visible = 0;
      items.forEach((it) => {
        let ok = Object.entries(groups).every(([k, vals]) => vals.includes(it.dataset[k]));
        ok = ok && (maxes.every((r) => Number(it.dataset[r.dataset.filterMax]) <= Number(r.value)));
        ok = ok && (mins.every((r) => Number(r.value) === 0 || Number(it.dataset[r.dataset.filterMin]) >= Number(r.value)));
        ok = ok && (has.every((c) => (it.dataset[c.dataset.filterHas] || "").split(" ").includes(c.value)));
        ok = ok && (flags.every((c) => it.dataset[c.dataset.filterFlag] === "1"));
        ok = ok && (!chip || chip.value === "All" || it.dataset[chip.dataset.filterChip] === chip.value);
        it.classList.toggle("hidden", !ok);
        if (ok) visible++;
      });
      if (count) count.textContent = visible;
      if (empty) empty.classList.toggle("hidden", visible > 0);
      // sort ("-key" = descending, "" = original order)
      const desc = sortKey.startsWith("-");
      const key = sortKey.replace(/^-/, "");
      const sorted = key ? original.slice().sort((a, b) => (Number(a.dataset[key]) - Number(b.dataset[key])) * (desc ? -1 : 1)) : original;
      sorted.forEach((it) => list.appendChild(it));
      $$("[data-range-label]", scope).forEach((l) => {
        const r = $(`[data-filter-max="${l.dataset.rangeLabel}"]`, scope);
        if (r) l.textContent = (scope.dataset.curSymbol || "$") + Math.round(Number(r.value) * Number(scope.dataset.curRate || 1)).toLocaleString("en-US");
      });
    }
    $$("input", scope).forEach((i) => i.addEventListener("input", apply));
    $$("input", scope).forEach((i) => i.addEventListener("change", apply));
    $$("[data-sort-by]", scope).forEach((b) =>
      b.addEventListener("click", () => {
        sortKey = b.dataset.sortBy;
        $$("[data-sort-by]", scope).forEach((x) => x.setAttribute("aria-pressed", String(x === b)));
        apply();
      }),
    );
    const select = $("[data-sort-select]", scope);
    if (select) select.addEventListener("change", () => { sortKey = select.value; apply(); });
    const reset = $("[data-reset]", scope);
    if (reset) reset.addEventListener("click", () => {
      $$("[data-filter],[data-filter-has],[data-filter-flag]", scope).forEach((c) => (c.checked = false));
      $$("[data-filter-max]", scope).forEach((r) => (r.value = r.max));
      $$('[data-filter-min][value="0"]', scope).forEach((r) => (r.checked = true));
      apply();
    });
    apply();
  }

  // ---------- Misc ----------
  function initTabs(root) {
    const tabs = $$("[data-tab]", root);
    tabs.forEach((t) =>
      t.addEventListener("click", () => {
        tabs.forEach((x) => x.setAttribute("aria-selected", String(x === t)));
        $$("[data-panel]", root).forEach((p) => p.classList.toggle("hidden", p.dataset.panel !== t.dataset.tab));
      }),
    );
  }

  function initMenus() {
    $$("[data-menu]").forEach((menu) => {
      const btn = $("[data-menu-toggle]", menu);
      const panel = $("[data-menu-panel]", menu);
      const set = (open) => { panel.classList.toggle("hidden", !open); btn.setAttribute("aria-expanded", String(open)); };
      btn.addEventListener("click", () => set(panel.classList.contains("hidden")));
      document.addEventListener("mousedown", (e) => { if (!menu.contains(e.target)) set(false); });
      document.addEventListener("keydown", (e) => { if (e.key === "Escape") set(false); });
    });
    const mt = $("[data-mobile-toggle]");
    const mm = $("[data-mobile-menu]");
    if (mt && mm) mt.addEventListener("click", () => {
      const open = mm.classList.toggle("hidden") === false;
      mt.setAttribute("aria-expanded", String(open));
    });
  }

  function initForms() {
    $$("[data-toggle-password]").forEach((b) =>
      b.addEventListener("click", () => {
        const input = b.parentElement.querySelector("input");
        const show = input.type === "password";
        input.type = show ? "text" : "password";
        b.textContent = show ? tr("Hide") : tr("Show");
        b.setAttribute("aria-label", show ? tr("Hide password") : tr("Show password"));
      }),
    );
    $$("[data-password-rules]").forEach((input) => {
      let box = input.parentElement;
      while (box && !box.querySelector("[data-rules]")) box = box.parentElement;
      const rules = box && $("[data-rules]", box);
      if (!rules) return;
      const tests = { len: (p) => p.length >= 8, letter: (p) => /[a-zA-Z]/.test(p), number: (p) => /[0-9]/.test(p) };
      const run = () => $$("[data-rule]", rules).forEach((li) => li.toggleAttribute("data-ok", tests[li.dataset.rule](input.value)));
      input.addEventListener("input", run);
      run();
    });
    $$("form[data-confirm]").forEach((f) => f.addEventListener("submit", (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
    $$("form[data-pending-form]").forEach((f) =>
      f.addEventListener("submit", () => {
        const btn = $("button[type=submit][data-pending]", f);
        if (btn) setTimeout(() => { btn.disabled = true; btn.textContent = btn.dataset.pending; }, 0);
      }),
    );
    const focusEl = $("[data-scroll-into-view]");
    if (focusEl) focusEl.scrollIntoView({ block: "center" });
    $$("time[data-local]").forEach((t) => {
      const d = new Date(t.getAttribute("datetime"));
      if (!isNaN(d)) t.textContent = t.hasAttribute("data-date-only")
        ? d.toLocaleDateString(LOCALE, { dateStyle: "medium" })
        : d.toLocaleString(LOCALE, { dateStyle: "medium", timeStyle: "short" });
    });
  }

  // ---------- Motion: reveal on scroll, header shadow, search wait screen ----------

  // [data-reveal] fades up when scrolled into view; [data-reveal-stagger] does it for each child, one after another.
  // With "reduce motion" on, it's a plain fade without the movement.
  function initReveal() {
    if (!("IntersectionObserver" in window)) return;
    const items = [...$$("[data-reveal]"), ...$$("[data-reveal-stagger]").flatMap((g) => [...g.children])];
    if (!items.length) return;
    const done = (el) => el.classList.add("revealed");
    // Already on screen: show straight away, no flicker.
    items.forEach((el) => { if (el.getBoundingClientRect().top < window.innerHeight) { el.classList.add("is-in"); done(el); } });
    document.documentElement.classList.add("reveal-on");
    if (calm) document.documentElement.classList.add("reveal-calm");
    const pending = new Set(items.filter((el) => !el.classList.contains("is-in")));
    const reveal = (el, delay) => {
      pending.delete(el);
      io.unobserve(el);
      el.style.setProperty("--reveal-delay", delay + "ms");
      el.classList.add("is-in");
      setTimeout(() => done(el), 1400);
    };
    const io = new IntersectionObserver((entries) => {
      let n = 0;
      entries.forEach((en) => { if (en.isIntersecting && pending.has(en.target)) reveal(en.target, Math.min(n++, 6) * 90); });
      // Jumped past something (a link to #faq, a fast swipe): show it too, so nothing stays hidden above.
      pending.forEach((el) => { if (el.getBoundingClientRect().bottom < 0) reveal(el, 0); });
    }, { rootMargin: "0px 0px -6% 0px" });
    pending.forEach((el) => io.observe(el));
  }

  // Home page: destination photos change every few seconds; the caption links to that destination's fares.
  // (Changes gently even for "reduce motion": a slow fade, without the zoom.)
  function initHero() {
    const hero = $("[data-hero]");
    if (!hero) return;
    const slides = $$(".hero-slide", hero);
    const dots = $$(".hero-dot", hero);
    const cap = $("[data-hero-caption]", hero);
    if (slides.length < 2) return;
    let cur = 0, timer = 0;
    const ready = (pic) => { const img = $("img", pic); return img.complete && img.naturalWidth > 0; };
    const show = (i) => {
      if (i === cur || !ready(slides[i])) return;
      const old = slides[cur];
      old.classList.replace("on", "leaving"); // stays under the new photo while it fades in
      setTimeout(() => old.classList.remove("leaving"), 1800);
      slides[i].classList.remove("leaving");
      slides[i].classList.add("on");
      dots.forEach((d, n) => d.toggleAttribute("aria-current", n === i));
      cap.href = slides[i].dataset.href;
      $("[data-hero-place]", cap).textContent = slides[i].dataset.place;
      cur = i;
    };
    const plane = $(".hero-flight", hero);
    if (calm && plane && plane.pauseAnimations) { plane.pauseAnimations(); plane.setCurrentTime(12.5); } // parked on its route
    // Only the next photo is downloaded (one step ahead), so phones don't fetch all of them at once.
    const fetchSlide = (pic) => new Promise((resolve) => {
      if (ready(pic)) return resolve();
      const img = $("img", pic);
      img.addEventListener("load", resolve, { once: true });
      img.addEventListener("error", resolve, { once: true });
      $$("[data-srcset]", pic).forEach((el) => {
        el.srcset = el.dataset.srcset;
        el.removeAttribute("data-srcset");
        if (el.dataset.src) { el.src = el.dataset.src; el.removeAttribute("data-src"); }
      });
    });
    const go = (i) => fetchSlide(slides[i]).then(() => {
      show(i);
      fetchSlide(slides[(i + 1) % slides.length]);
    });
    const start = () => { clearInterval(timer); timer = setInterval(() => { if (!document.hidden) go((cur + 1) % slides.length); }, calm ? 9000 : 6500); };
    dots.forEach((d, n) => d.addEventListener("click", () => { go(n); start(); }));
    afterLoad(() => fetchSlide(slides[1]));
    start();
  }

  function initHeader() {
    const h = $("body > header");
    if (!h) return;
    const set = () => h.toggleAttribute("data-scrolled", window.scrollY > 8);
    set();
    window.addEventListener("scroll", set, { passive: true });
  }

  // Flight and hotel searches can take a few seconds: show a "finding prices" screen meanwhile.
  function initSearchWait() {
    const wait = $("[data-search-wait]");
    if (!wait) return;
    $$("form[data-search-form=flight], form[data-search-form=hotel]").forEach((f) =>
      f.addEventListener("submit", (e) => {
        if (e.defaultPrevented) return;
        wait.hidden = false;
        requestAnimationFrame(() => wait.classList.add("on"));
      }),
    );
    // Back button: the page comes back from the browser's cache with the screen still showing.
    window.addEventListener("pageshow", () => { wait.classList.remove("on"); wait.hidden = true; });
  }

  // A thin gold bar at the top while the next page loads, so a tap always shows that something is happening.
  function initProgress() {
    const bar = document.createElement("div");
    bar.className = "nav-progress";
    bar.setAttribute("aria-hidden", "true");
    document.body.appendChild(bar);
    let stop = 0;
    const start = () => {
      bar.classList.remove("on");
      void bar.offsetWidth; // restart the animation
      bar.classList.add("on");
      clearTimeout(stop);
      stop = setTimeout(() => bar.classList.remove("on"), 15000);
    };
    document.addEventListener("click", (e) => {
      const a = e.target.closest ? e.target.closest("a[href]") : null;
      if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      if ((a.target && a.target !== "_self") || a.hasAttribute("download")) return;
      const to = new URL(a.href, location.href);
      if (to.origin !== location.origin) return; // other websites, email and phone links
      if (to.pathname === location.pathname && to.search === location.search) return; // same page (e.g. #faq)
      start();
    });
    document.addEventListener("submit", (e) => {
      const f = e.target;
      if (e.defaultPrevented || (f.target && f.target !== "_self") || f.matches("[data-search-form=flight], [data-search-form=hotel]")) return; // searches have their own screen
      start();
    });
    window.addEventListener("pageshow", () => bar.classList.remove("on"));
  }

  // Photos fade in once they have arrived instead of appearing line by line.
  function initImageFade() {
    $$("img[loading=lazy], img[data-fade]").forEach((img) => {
      if (img.complete && img.naturalWidth > 0) return; // already there
      img.classList.add("img-wait");
      const show = () => img.classList.add("img-in");
      img.addEventListener("load", show);
      img.addEventListener("error", show, { once: true });
    });
  }

  // Admin → Diagnostics: whether the scripts run in this browser and whether it asks for less motion.
  function initBrowserCheck() {
    const box = $("[data-browser-check]");
    if (!box) return;
    const row = (key, ok, text) => {
      const r = $(`[data-check=${key}]`, box);
      $("[data-check-icon]", r).textContent = ok ? "✓" : "!";
      $("[data-check-icon]", r).className = "grid h-6 w-6 place-items-center rounded-full text-xs font-bold text-white " + (ok ? "bg-emerald-500" : "bg-amber-500");
      $("[data-check-text]", r).textContent = text;
    };
    const current = SCRIPT_SRC.indexOf(box.dataset.expect) !== -1;
    row("scripts", current, current ? "Running, latest version" : "Running, but an OLDER copy of the website's scripts. Flush the cache in hPanel → Performance → CDN, then press Ctrl + Shift + R.");
    row("motion", !calm, calm
      ? "Reduced: this device asks websites to keep motion to a minimum, so the flying plane and zoom effects stay still here (photos and sections still fade in). To see everything: Windows: Settings → Accessibility → Visual effects → Animation effects ON. iPhone: Settings → Accessibility → Motion → Reduce Motion OFF. Android: Settings → Accessibility → Remove animations OFF."
      : "On: all animations play on this device.");
  }

  // Trip page: the Travel Care Add/Added button switches the totals and summary line straight away.
  // Trip page: the Travel Care button and the tip choice update the summary lines and the total as they change.
  function initCheckoutTotals() {
    const care = $("#care-toggle"), tips = $$("input[name=tip]"), other = $("#tip-other");
    if (!care && !tips.length) return;
    const money = (n) => "$" + n.toLocaleString("en-US");
    const tipAmount = () => {
      const picked = tips.filter((r) => r.checked)[0];
      if (!picked) return 0;
      const n = parseInt(picked.value === "other" ? (other && other.value) : picked.value, 10);
      return n > 0 && n <= (parseInt(other && other.getAttribute("max"), 10) || 500) ? n : 0;
    };
    const sync = () => {
      const withCare = !!(care && care.checked), tip = tipAmount();
      let total = null;
      $$("[data-care-on]").forEach((el) => { el.hidden = !withCare; });
      $$("[data-care-off]").forEach((el) => { el.hidden = withCare; });
      $$("[data-tip-line]").forEach((el) => { el.hidden = !tip; });
      $$("[data-tip-amount]").forEach((el) => { el.textContent = money(tip); });
      $$("[data-total]").forEach((el) => {
        total = (parseInt(el.getAttribute("data-base"), 10) || 0) + (withCare ? parseInt(el.getAttribute("data-care"), 10) || 0 : 0) + tip;
        el.textContent = money(total);
      });
      $$("[data-words]").forEach((el) => { el.hidden = parseInt(el.getAttribute("data-words"), 10) !== total; });
    };
    if (care) care.addEventListener("change", sync);
    tips.forEach((r) => r.addEventListener("change", sync));
    if (other) {
      const pickOther = () => { const r = $("#tip-other-choice"); if (r && !r.checked) r.checked = true; sync(); };
      other.addEventListener("focus", pickOther);
      other.addEventListener("input", pickOther);
    }
    sync();
  }

  // "Check your email" screen: moves on by itself once the link is opened (in another tab or on a phone).
  function initVerifyWait() {
    const box = $("[data-verify-wait]");
    if (!box || !window.fetch) return;
    const started = Date.now();
    let busy = false;
    const check = () => {
      if (busy || document.hidden || Date.now() - started > 30 * 60 * 1000) return;
      busy = true;
      fetch(box.dataset.poll, { credentials: "same-origin", headers: { Accept: "application/json" } })
        .then((r) => (r.ok ? r.json() : {}))
        .then((j) => { if (j && j.verified) location.href = box.dataset.next; })
        .catch(() => {})
        .then(() => { busy = false; });
    };
    setInterval(check, 5000);
    document.addEventListener("visibilitychange", check);
  }

  // Booking page: the trip summary's "Checked bag" line follows the Add checked bag buttons.
  document.addEventListener("DOMContentLoaded", () => {
    $$("form[data-search-form]").forEach(initSearchForm);
    // Airport/date fields outside search forms (e.g. package options on the booking page)
    $$("form:not([data-search-form])").forEach((f) => {
      if ($("[data-airport]", f) || $("input[data-date]", f)) {
        $$("[data-airport]", f).forEach(initAirport);
        initDates(f);
      }
    });
    $$("[data-results]").forEach(initResults);
    $$("[data-tabs]").forEach(initTabs);
    // Each part runs on its own, so a problem in one never stops the others.
    [initImageFade, initProgress, initMenus, initForms, initSearchWait, initHeader, initHero, initReveal, initBrowserCheck, initVerifyWait, initCheckoutTotals].forEach((fn) => {
      try { fn(); } catch (e) { if (window.console) console.error(e); }
    });
  });
})();
