/* Live chat alerts on every admin page (like Messenger): a pop-up when someone writes, a mini chat window to
   answer without leaving the page, a live "Support chats" badge, an optional sound and desktop notifications.
   Talks to chat.php (a=notify, a=admin, a=reply, a=typing). Messages are shown as text only (never as HTML). */
(function () {
  "use strict";
  var root = document.querySelector("[data-admin-live]");
  if (!root) return;
  var endpoint = root.getAttribute("data-endpoint"), csrf = root.getAttribute("data-csrf"), chatsUrl = root.getAttribute("data-chats");
  var site = root.getAttribute("data-site") || "", quiet = root.hasAttribute("data-quiet");   // quiet: on Support chats itself
  var since = +root.getAttribute("data-since") || 0, unread = 0;
  var baseTitle = document.title, lastActive = Date.now();
  var chat = null;   // the open mini chat window: { t, last, box, list, ta, timer }

  function el(tag, cls, text) { var x = document.createElement(tag); if (cls) x.className = cls; if (text != null) x.textContent = text; return x; }
  function initials(name) { return String(name || "?").trim().split(/\s+/).slice(0, 2).map(function (p) { return p.charAt(0); }).join("").toUpperCase(); }
  function first(name) { return String(name || "").trim().split(/\s+/)[0] || name; }
  function when(at) {
    var ago = Date.now() / 1000 - at, d = new Date(at * 1000);
    if (ago < 60) return "just now";
    if (ago < 3600) return Math.floor(ago / 60) + " min ago";
    return d.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
  }
  function store(key, value) { try { if (value === undefined) return localStorage.getItem(key); localStorage.setItem(key, value); } catch (e) { return null; } return null; }
  function get(params) {
    return fetch(endpoint + "?" + new URLSearchParams(params).toString(), { credentials: "same-origin", headers: { Accept: "application/json" } })
      .then(function (r) { return r.json(); });
  }
  function post(params) {
    var fd = new FormData();
    Object.keys(params).forEach(function (k) { fd.append(k, params[k]); });
    fd.append("csrf", csrf);
    return fetch(endpoint, { method: "POST", body: fd, credentials: "same-origin", headers: { Accept: "application/json" } })
      .then(function (r) { return r.json(); });
  }

  // Is the admin actually at the screen? (customers see "Online now" only then)
  ["pointerdown", "keydown", "mousemove", "scroll", "touchstart"].forEach(function (ev) {
    window.addEventListener(ev, function () { lastActive = Date.now(); }, { passive: true });
  });
  function here() { return !document.hidden && Date.now() - lastActive < 10 * 60 * 1000; }

  /* ---------- sound (a soft two-note "ding", made in the browser: no file to download) ---------- */
  var audio = null;
  function soundOn() { return store("ff-admin-sound") !== "off"; }
  function unlockAudio() {
    var AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;
    if (!audio) audio = new AC();
    if (audio.state === "suspended") audio.resume();
  }
  ["pointerdown", "keydown"].forEach(function (ev) { window.addEventListener(ev, unlockAudio, { once: true }); });
  function ding() {
    if (!soundOn() || !audio || audio.state !== "running") return;   // browsers allow sound only after a click on the page
    [[880, 0], [1320, 0.13]].forEach(function (n) {
      var o = audio.createOscillator(), g = audio.createGain(), t = audio.currentTime + n[1];
      o.type = "sine"; o.frequency.value = n[0];
      g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.18, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.35);
      o.connect(g); g.connect(audio.destination); o.start(t); o.stop(t + 0.4);
    });
  }

  /* ---------- "Alerts" button in the admin bar: sound and desktop notifications ---------- */
  var controls = document.querySelector("[data-alert-controls]");
  var soundBtn, deskBtn, deskNote;
  function paintControls() {
    if (!controls) return;
    soundBtn.textContent = soundOn() ? "Sound: on" : "Sound: off";
    soundBtn.setAttribute("aria-pressed", String(soundOn()));
    if (!("Notification" in window)) { deskBtn.hidden = true; deskNote.textContent = "This browser can't show desktop alerts."; return; }
    var p = Notification.permission;
    deskBtn.textContent = p === "granted" ? "Desktop alerts: on" : p === "denied" ? "Desktop alerts: blocked" : "Turn on desktop alerts";
    deskBtn.disabled = p !== "default";
    deskNote.textContent = p === "granted" ? "You'll get an alert on your computer when someone writes while this tab is in the background."
      : p === "denied" ? "Your browser blocks alerts for this site. Allow them in the browser's site settings (the icon left of the address), then reload."
      : "Shows an alert on your computer, like Facebook, when someone writes while this tab is in the background.";
  }
  if (controls) {
    var wrap = el("div", "al-alerts");
    var toggle = el("button", "al-ctl", "🔔 Alerts"); toggle.type = "button"; toggle.setAttribute("aria-expanded", "false");
    var menu = el("div", "al-menu"); menu.hidden = true;
    soundBtn = el("button", "al-opt"); soundBtn.type = "button";
    deskBtn = el("button", "al-opt"); deskBtn.type = "button";
    deskNote = el("p", "al-note");
    menu.appendChild(el("b", null, "Chat alerts"));
    menu.appendChild(soundBtn); menu.appendChild(deskBtn); menu.appendChild(deskNote);
    wrap.appendChild(toggle); wrap.appendChild(menu); controls.appendChild(wrap);
    var setOpen = function (open) { menu.hidden = !open; toggle.setAttribute("aria-expanded", String(open)); };
    toggle.addEventListener("click", function () { setOpen(menu.hidden); });
    document.addEventListener("pointerdown", function (e) { if (!wrap.contains(e.target)) setOpen(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") setOpen(false); });
    soundBtn.addEventListener("click", function () { store("ff-admin-sound", soundOn() ? "off" : "on"); unlockAudio(); paintControls(); if (soundOn()) setTimeout(ding, 50); });
    deskBtn.addEventListener("click", function () {
      if (!("Notification" in window) || Notification.permission !== "default") return paintControls();
      Notification.requestPermission().then(paintControls);
    });
    paintControls();
  }

  /* ---------- badge and tab title ---------- */
  function paintUnread() {
    document.querySelectorAll("[data-chat-badge]").forEach(function (b) { b.textContent = unread; b.hidden = !unread; });
    document.title = unread && document.hidden ? "(" + unread + ") New message · " + site : baseTitle;
  }
  document.addEventListener("visibilitychange", function () { paintUnread(); if (!document.hidden) check(); });

  /* ---------- pop-ups ---------- */
  var toasts = el("div", "al-toasts"); toasts.setAttribute("aria-live", "polite");
  root.appendChild(toasts);
  function toast(m) {
    var old = toasts.querySelector('[data-t="' + m.t + '"]');
    if (old) old.remove();
    var card = el("div", "al-toast"); card.setAttribute("data-t", m.t); card.setAttribute("role", "status");
    var av = el("span", "al-av", initials(m.name));
    var main = el("div", "al-main");
    var top = el("div", "al-top");
    top.appendChild(el("b", null, m.name));
    top.appendChild(el("small", null, (m.member ? "Customer" : "Visitor") + " · " + when(m.at)));
    main.appendChild(top);
    main.appendChild(el("p", null, m.text));
    var acts = el("div", "al-acts");
    var reply = el("button", "al-reply", "Reply"); reply.type = "button";
    var close = el("button", "al-x", "×"); close.type = "button"; close.setAttribute("aria-label", "Dismiss");
    acts.appendChild(reply); acts.appendChild(close);
    main.appendChild(acts);
    card.appendChild(av); card.appendChild(main);
    reply.addEventListener("click", function () { card.remove(); openChat(m.t); });
    close.addEventListener("click", function () { card.remove(); });
    toasts.appendChild(card);
    while (toasts.children.length > 3) toasts.firstElementChild.remove();
  }
  function desktop(m) {
    if (!("Notification" in window) || Notification.permission !== "granted" || !document.hidden) return;
    try {
      var n = new Notification(m.name + " · " + site, { body: m.text, tag: "ff-chat-" + m.t });
      n.onclick = function () { window.focus(); if (quiet) location.href = chatsUrl + "?t=" + m.t + "#reply"; else openChat(m.t); n.close(); };
    } catch (e) { /* some phones only allow notifications from an installed app */ }
  }

  /* ---------- check for new messages (every 8 s while the tab is open, 20 s in the background) ---------- */
  var busy = false;
  function check() {
    if (busy) return;
    busy = true;
    get({ a: "notify", since: since, here: here() ? "1" : "0" }).then(function (d) {
      unread = +d.unread || 0;
      var fresh = (d.messages || []).filter(function (m) { return m.id > since; });
      since = Math.max(since, +d.latest || 0);
      var openT = quiet ? +((document.querySelector("[data-cs]") || { getAttribute: function () { return 0; } }).getAttribute("data-thread")) : (chat ? chat.t : 0);
      var shown = {};
      fresh.forEach(function (m) {
        if (m.t === openT && !document.hidden) return;   // already looking at this chat
        shown[m.t] = m;   // the newest message per chat
      });
      var keys = Object.keys(shown);
      if (keys.length) {
        ding();
        keys.forEach(function (k) { if (!quiet) toast(shown[k]); desktop(shown[k]); });
      }
      paintUnread();
    }).catch(function () {}).then(function () { busy = false; });
  }
  check();
  (function loop() { setTimeout(function () { check(); loop(); }, document.hidden ? 20000 : 8000); })();

  /* ---------- mini chat window ---------- */
  function bubble(m) {
    var b = el("div", "cm " + (m.from === "admin" ? "cm-me" : "cm-them")); b.setAttribute("data-msg-id", m.id);
    b.appendChild(el("p", null, m.text));
    b.appendChild(el("small", null, new Date(m.at * 1000).toLocaleTimeString([], { hour: "numeric", minute: "2-digit" })));
    return b;
  }
  function receipt(list, readId) {
    var old = list.querySelector(".cm-receipt"); if (old) old.remove();
    var mine = list.querySelectorAll(".cm-me"); if (!mine.length) return;
    var lastMine = mine[mine.length - 1], seen = readId >= +lastMine.getAttribute("data-msg-id");
    lastMine.appendChild(el("small", "cm-receipt" + (seen ? " seen" : ""), seen ? "Seen" : "Sent"));
  }
  function add(list, msgs) {
    var atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
    msgs.forEach(function (m) {
      if (m.id <= chat.last) return;
      list.appendChild(bubble(m)); chat.last = m.id;
    });
    if (atBottom || msgs.length) list.scrollTop = list.scrollHeight;
  }
  function closeChat() {
    if (!chat) return;
    clearInterval(chat.timer);
    chat.box.remove();
    chat = null;
  }
  function openChat(t) {
    if (quiet) { location.href = chatsUrl + "?t=" + t + "#reply"; return; }
    if (chat && chat.t === t) { chat.ta.focus(); return; }
    closeChat();
    var gone = toasts.querySelector('[data-t="' + t + '"]'); if (gone) gone.remove();
    var box = el("section", "al-chat"); box.setAttribute("aria-label", "Chat");
    var head = el("div", "al-head");
    var av = el("span", "al-av", "…");
    var who = el("div", "al-who"), name = el("b", null, "Loading…"), sub = el("small");
    who.appendChild(name); who.appendChild(sub);
    var full = el("a", "al-full", "Open full chat"); full.href = chatsUrl + "?t=" + t + "#reply";
    var x = el("button", "al-x", "×"); x.type = "button"; x.setAttribute("aria-label", "Close chat");
    head.appendChild(av); head.appendChild(who); head.appendChild(full); head.appendChild(x);
    var list = el("div", "al-msgs");
    var typing = el("p", "al-typing"); typing.hidden = true;
    var form = el("form", "al-send");
    var ta = el("textarea"); ta.rows = 2; ta.placeholder = "Type your reply… (Enter sends)"; ta.maxLength = 2000; ta.setAttribute("aria-label", "Your reply");
    var send = el("button", null, "Send"); send.type = "submit";
    var err = el("p", "al-err"); err.hidden = true;
    form.appendChild(ta); form.appendChild(send);
    box.appendChild(head); box.appendChild(list); box.appendChild(typing); box.appendChild(err); box.appendChild(form);
    root.appendChild(box);
    chat = { t: t, last: 0, box: box, list: list, ta: ta, timer: 0 };
    var me = chat;

    function refresh() {
      return get({ a: "admin", t: t, after: me.last }).then(function (d) {
        if (chat !== me) return;
        if (d.who) {
          name.textContent = d.who.name; av.textContent = initials(d.who.name);
          sub.textContent = (d.who.member ? "Customer" : "Visitor") + " · " + d.who.email + (d.here ? " · on the website now" : "") + (d.who.done ? " · marked as done" : "");
          typing.textContent = first(d.who.name) + " is typing…";
        }
        add(list, d.messages || []);
        typing.hidden = !d.typing;
        receipt(list, d.read || 0);
        unread = +d.unread || 0; paintUnread();
      }).catch(function () {});
    }
    refresh().then(function () { ta.focus(); });
    me.timer = setInterval(refresh, 3000);

    x.addEventListener("click", closeChat);
    var typedAt = 0;
    ta.addEventListener("input", function () {   // they see "<name> is typing"
      if (!ta.value.trim() || Date.now() - typedAt < 3000) return;
      typedAt = Date.now();
      post({ a: "typing", t: t }).catch(function () {});
    });
    ta.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event("submit", { cancelable: true })); }
    });
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var text = ta.value.trim();
      if (!text || send.disabled) return;
      send.disabled = true; err.hidden = true;
      post({ a: "reply", t: t, text: text, after: me.last }).then(function (d) {
        if (d.error) { err.textContent = d.error; err.hidden = false; return; }
        ta.value = "";
        add(list, d.messages || []);
        receipt(list, 0);
      }).catch(function () { err.textContent = "Your reply couldn't be sent. Check your connection and try again."; err.hidden = false; })
        .then(function () { send.disabled = false; ta.focus(); });
    });
  }
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && chat && chat.box.contains(document.activeElement)) closeChat(); });
})();
