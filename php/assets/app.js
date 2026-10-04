/* TravelCompany — small, dependency-free scripts for the PHP site. */
(function () {
  "use strict";
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

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
    dates.forEach((input) => {
      input.min = today;
      if (!input.value) input.value = addDays(today, Number(input.dataset.offset || 14));
    });
    // Keep "return"/"check-out" after the first date
    dates.filter((i) => i.dataset.after).forEach((later) => {
      const earlier = $(`input[name="${later.dataset.after}"]`, form);
      if (!earlier) return;
      const gap = Number(later.dataset.afterDays || 1);
      const sync = () => {
        const min = earlier.value ? addDays(earlier.value, later.dataset.after === "depart" && form.dataset.searchForm === "flight" ? 0 : 1) : today;
        later.min = min;
        if (!later.value || later.value < min) later.value = addDays(earlier.value || today, gap);
      };
      earlier.addEventListener("change", sync);
      sync();
    });
  }

  // ---------- Airport autocomplete ----------
  const AIRPORTS = window.AIRPORTS || [];
  function searchAirports(q) {
    q = q.trim().toLowerCase();
    if (!q) return AIRPORTS.slice(0, 8);
    return AIRPORTS.filter(
      (a) =>
        a.code.toLowerCase().startsWith(q) ||
        a.city.toLowerCase().includes(q) ||
        a.country.toLowerCase().includes(q) ||
        a.name.toLowerCase().includes(q),
    ).slice(0, 8);
  }
  const label = (a) => `${a.city} (${a.code})`;

  function initAirport(box) {
    const input = $("[data-airport-input]", box);
    const code = $("[data-airport-code]", box);
    const list = $("[data-airport-list]", box);
    let results = [];
    let hi = 0;
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
      list.classList.toggle("hidden", results.length === 0);
      input.setAttribute("aria-expanded", String(results.length > 0));
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
      results = searchAirports("");
      hi = 0;
      render();
    });
    input.addEventListener("input", () => {
      results = searchAirports(input.value);
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
      // Accept a typed code like "LHR", otherwise show the last chosen airport again.
      const typed = AIRPORTS.find((a) => a.code === input.value.trim().toUpperCase());
      if (typed) code.value = typed.code;
      const current = AIRPORTS.find((a) => a.code === code.value);
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
    const cabinNames = { economy: "Economy", premium: "Premium Economy", business: "Business", first: "First" };
    const update = () => {
      const people = Number(hidden("adults").value) + Number(hidden("children").value);
      const parts = [`${people} traveler${people === 1 ? "" : "s"}`];
      if (hidden("rooms")) parts.push(`${hidden("rooms").value} room${hidden("rooms").value === "1" ? "" : "s"}`);
      if (hidden("cabin")) parts.push(cabinNames[hidden("cabin").value]);
      summary.textContent = parts.join(", ");
      $$("[data-counter]", box).forEach((c) => {
        const v = Number(hidden(c.dataset.counter).value);
        $("[data-count]", c).textContent = v;
        $('[data-step="-1"]', c).disabled = v <= Number(c.dataset.min);
        $('[data-step="1"]', c).disabled = v >= Number(c.dataset.max);
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
          input.value = String(Math.min(Number(c.dataset.max), Math.max(Number(c.dataset.min), Number(input.value) + Number(btn.dataset.step))));
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
        const oneway = $("input[data-trip]:checked", form)?.value === "oneway";
        ret.disabled = oneway;
        returnBox.classList.toggle("opacity-50", oneway);
      };
      $$("input[data-trip]", form).forEach((r) => r.addEventListener("change", sync));
      sync();
    }
    form.addEventListener("submit", (e) => {
      const codes = $$("[data-airport-code]", form).map((i) => i.value);
      let msg = "";
      if (form.dataset.searchForm === "flight") {
        if (!codes[0] || !codes[1]) msg = "Please choose where you're flying from and to.";
        else if (codes[0] === codes[1]) msg = "Origin and destination must be different.";
      } else if (!codes[codes.length - 1]) {
        msg = "Please choose a destination.";
      } else if (codes.length === 2 && codes[0] === codes[1]) {
        msg = "Leaving from and going to must be different.";
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
    let sortKey = $("[data-sort-by][aria-pressed=true]", scope)?.dataset.sortBy || "";

    function apply() {
      const groups = {};
      $$("[data-filter]:checked", scope).forEach((c) => (groups[c.dataset.filter] ||= []).push(c.value));
      const maxes = $$("[data-filter-max]", scope);
      const mins = $$("[data-filter-min]:checked", scope);
      const has = $$("[data-filter-has]:checked", scope);
      const flags = $$("[data-filter-flag]:checked", scope);
      const chip = $("[data-filter-chip]:checked", scope);
      let visible = 0;
      items.forEach((it) => {
        let ok = Object.entries(groups).every(([k, vals]) => vals.includes(it.dataset[k]));
        ok &&= maxes.every((r) => Number(it.dataset[r.dataset.filterMax]) <= Number(r.value));
        ok &&= mins.every((r) => Number(r.value) === 0 || Number(it.dataset[r.dataset.filterMin]) >= Number(r.value));
        ok &&= has.every((c) => (it.dataset[c.dataset.filterHas] || "").split(" ").includes(c.value));
        ok &&= flags.every((c) => it.dataset[c.dataset.filterFlag] === "1");
        ok &&= !chip || chip.value === "All" || it.dataset[chip.dataset.filterChip] === chip.value;
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
        if (r) l.textContent = "$" + Number(r.value).toLocaleString("en-US");
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
        b.textContent = show ? "Hide" : "Show";
        b.setAttribute("aria-label", show ? "Hide password" : "Show password");
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
        ? d.toLocaleDateString("en-US", { dateStyle: "medium" })
        : d.toLocaleString("en-US", { dateStyle: "medium", timeStyle: "short" });
    });
  }

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
    initMenus();
    initForms();
  });
})();
