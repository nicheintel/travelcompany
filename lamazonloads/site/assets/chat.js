// LamazonLoads live chat: the Chat button for visitors and members, and live updates on Admin -> Support chats.
// Talks to chat.php. Messages are always shown as text (never as HTML).
(function () {
  'use strict';
  function el(tag, cls, text) { var x = document.createElement(tag); if (cls) x.className = cls; if (text != null) x.textContent = text; return x; }
  function when(at) {
    var d = new Date(at * 1000), now = new Date();
    var t = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    return d.toDateString() === now.toDateString() ? t : d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ', ' + t;
  }
  function bubble(m, mine) {
    var b = el('div', 'cm ' + (mine ? 'cm-me' : 'cm-them')); b.setAttribute('data-msg-id', m.id);
    b.appendChild(el('p', null, m.text)); b.appendChild(el('small', null, when(m.at)));
    return b;
  }
  // "Sent" / "Seen" under your own last message (seen = the other side has opened the chat since it arrived)
  function receipt(box, readId) {
    var old = box.querySelector('.cm-receipt'); if (old) old.remove();
    var mine = box.querySelectorAll('.cm-me'); if (!mine.length) return;
    var lastMine = mine[mine.length - 1], seen = readId >= +lastMine.getAttribute('data-msg-id');
    var r = el('small', 'cm-receipt' + (seen ? ' seen' : ''), seen ? 'Seen' : 'Sent'); lastMine.appendChild(r);
  }
  var root = document.querySelector('[data-chat], [data-cs]');
  var API = root ? root.getAttribute('data-endpoint') : 'chat.php';
  function get(params) {
    return fetch(API + '?' + new URLSearchParams(params).toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); });
  }

  /* ---------- the Chat bubble ---------- */
  var box = document.querySelector('[data-chat]');
  if (box) (function () {
    var csrf = box.getAttribute('data-csrf'), member = box.getAttribute('data-member') === '1', has = box.getAttribute('data-has') === '1', admin = box.getAttribute('data-admin') || '';
    var fab = box.querySelector('[data-chat-open]'), dot = box.querySelector('[data-chat-dot]');
    var panel, list, ta, who, err, status, sendBtn, typing, chatForm, endBtn, endCard = null, inThread = false, last = 0, timer = 0, isOpen = false, busy = false, typedAt = 0;

    function build() {
      panel = el('section', 'chat-panel'); panel.id = 'chat-panel'; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', 'Chat with LamazonLoads'); panel.hidden = true;
      var head = el('div', 'chat-head'), title = el('div', 'chat-title');
      title.appendChild(el('b', null, 'Chat with LamazonLoads'));
      status = el('span', 'chat-status', 'Usually replies within a few hours'); title.appendChild(status);
      var x = el('button', 'chat-x', '✕'); x.type = 'button'; x.setAttribute('aria-label', 'Close chat'); x.addEventListener('click', close);
      endBtn = el('button', 'chat-end', 'End chat'); endBtn.type = 'button'; endBtn.hidden = true;
      endBtn.addEventListener('click', function () {
        if (!window.confirm('End this chat? You can start a new one any time.')) return;
        post({ a: 'end' }).then(function () { showEnded('user'); }).catch(function () {});
      });
      var acts = el('div', 'chat-acts'); acts.appendChild(endBtn); acts.appendChild(x);
      head.appendChild(title); head.appendChild(acts); panel.appendChild(head);
      list = el('div', 'chat-msgs'); list.setAttribute('aria-live', 'polite');
      var hi = el('div', 'cm cm-them cm-hi');
      hi.appendChild(el('p', null, 'Hi! 👋 Ask anything about LamazonLoads: loads, daily routes, onboarding or open jobs. ' + (admin ? admin + ' from our team answers' : 'Our team answers') + ' here, and by email if you’ve left.'));
      var help = el('a', 'chat-help', 'Quick answers: our FAQ'); help.href = box.getAttribute('data-help'); hi.appendChild(help);
      list.appendChild(hi); panel.appendChild(list);
      typing = el('p', 'chat-typing'); typing.appendChild(document.createTextNode((admin || 'Support') + ' is typing'));
      var dots = el('span', 'dots'); dots.appendChild(el('i')); dots.appendChild(el('i')); dots.appendChild(el('i')); typing.appendChild(dots);
      typing.hidden = true; panel.appendChild(typing);
      var form = el('form', 'chat-form'); form.noValidate = true; chatForm = form;
      if (!member && !has) {
        who = el('div', 'chat-who');
        [['text', 'name', 80, 'Your name'], ['email', 'email', 190, 'Your email']].forEach(function (f) {
          var i = el('input'); i.type = f[0]; i.name = f[1]; i.maxLength = f[2]; i.placeholder = f[3]; i.autocomplete = f[1]; i.setAttribute('aria-label', f[3]); who.appendChild(i);
        });
        form.appendChild(who);
      }
      var hp = el('label', 'hp'); hp.setAttribute('aria-hidden', 'true'); hp.appendChild(document.createTextNode('Website ')); var hpIn = el('input'); hpIn.type = 'text'; hpIn.name = 'website'; hpIn.tabIndex = -1; hpIn.autocomplete = 'off'; hp.appendChild(hpIn); form.appendChild(hp);
      var row = el('div', 'chat-row');
      ta = el('textarea'); ta.name = 'text'; ta.rows = 2; ta.maxLength = 2000; ta.placeholder = 'Type your message…'; ta.setAttribute('aria-label', 'Your message');
      sendBtn = el('button', 'btn btn-primary chat-send', 'Send'); sendBtn.type = 'submit';
      row.appendChild(ta); row.appendChild(sendBtn); form.appendChild(row);
      err = el('p', 'chat-err'); err.hidden = true; form.appendChild(err);
      form.addEventListener('submit', function (e) { e.preventDefault(); send(form); });
      ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(form); } });
      ta.addEventListener('input', function () {   // tell the admin "… is typing" (at most every 3 seconds)
        if (!has || !ta.value.trim() || Date.now() - typedAt < 3000) return;
        typedAt = Date.now();
        var fd = new FormData(); fd.append('a', 'typing'); fd.append('csrf', box.getAttribute('data-csrf') || csrf);
        fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
      });
      panel.appendChild(form);
      box.appendChild(panel);
    }
    function add(ms) {
      ms.forEach(function (m) { if (m.id > last) { list.appendChild(bubble(m, m.from === 'user')); last = m.id; } });
      list.scrollTop = list.scrollHeight;
    }
    function post(params) {
      var fd = new FormData(); for (var k in params) fd.append(k, params[k]); fd.append('csrf', box.getAttribute('data-csrf') || csrf);
      return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); });
    }
    // The chat ended (they clicked End chat, or the admin marked it as done): ask for a rating instead of the message box
    function showEnded(by) {
      if (endCard) return;
      inThread = false; endBtn.hidden = true; chatForm.hidden = true; typing.hidden = true;
      endCard = el('div', 'chat-rate');
      endCard.appendChild(el('p', 'chat-rate-note', by === 'admin' ? (admin ? admin + ' marked this chat as done.' : 'We marked this chat as done.') : 'You ended the chat.'));
      endCard.appendChild(el('b', null, 'How was your chat?'));
      var stars = el('div', 'chat-stars'), pick = 0, sendFb, comment;
      stars.setAttribute('role', 'radiogroup'); stars.setAttribute('aria-label', 'Rating');
      for (var i = 1; i <= 5; i++) (function (n) {
        var st = el('button', null, '★'); st.type = 'button'; st.setAttribute('role', 'radio'); st.setAttribute('aria-checked', 'false');
        st.setAttribute('aria-label', n + (n === 1 ? ' star' : ' stars'));
        st.addEventListener('click', function () {
          pick = n; sendFb.disabled = false;
          stars.querySelectorAll('button').forEach(function (b, j) { b.classList.toggle('on', j < n); b.setAttribute('aria-checked', String(j + 1 === n)); });
        });
        stars.appendChild(st);
      })(i);
      endCard.appendChild(stars);
      comment = el('textarea'); comment.rows = 2; comment.maxLength = 500; comment.placeholder = 'Anything we could do better? (optional)'; comment.setAttribute('aria-label', 'Your feedback (optional)');
      endCard.appendChild(comment);
      var row = el('div', 'chat-rate-row');
      var skip = el('button', 'btn btn-ghost', 'Skip'); skip.type = 'button';
      sendFb = el('button', 'btn btn-primary', 'Send feedback'); sendFb.type = 'button'; sendFb.disabled = true;
      sendFb.addEventListener('click', function () { post({ a: 'rate', rating: pick, comment: comment.value }).then(function () { thanks(true); }).catch(function () {}); });
      skip.addEventListener('click', function () { post({ a: 'rate', rating: 0, skip: 1 }).then(function () { thanks(false); }).catch(function () {}); });
      row.appendChild(skip); row.appendChild(sendFb); endCard.appendChild(row);
      panel.appendChild(endCard);
      list.scrollTop = list.scrollHeight;
    }
    function thanks(rated) {
      endCard.textContent = ''; endCard.setAttribute('data-done', '1');
      endCard.appendChild(el('b', null, rated ? 'Thanks for your feedback! 🙏' : 'Chat ended.'));
      endCard.appendChild(el('p', 'chat-rate-note', 'Need anything else? You can start a new chat any time.'));
      var again = el('button', 'btn btn-primary', 'Start a new chat'); again.type = 'button';
      again.addEventListener('click', function () {
        endCard.remove(); endCard = null; chatForm.hidden = false;
        list.querySelectorAll('.cm:not(.cm-hi)').forEach(function (m) { m.remove(); });
        ta.focus();
      });
      endCard.appendChild(again);
    }
    function setDot(n) { dot.hidden = !n; dot.textContent = n ? String(n) : ''; }
    function poll() {
      if (!has && !isOpen) return;
      get({ a: 'poll', after: isOpen ? last : 0, open: isOpen ? 1 : 0 }).then(function (d) {
        if (d.thread) has = true;
        if (isOpen) {
          add(d.messages || []); setDot(0); typing.hidden = !d.typing; receipt(list, d.read || 0);
          inThread = !!d.thread && !d.ended; if (!endCard) endBtn.hidden = !inThread;
          if (d.thread && d.ended) showEnded(d.ended);
          else if (d.thread && endCard && !endCard.getAttribute('data-done')) { endCard.remove(); endCard = null; chatForm.hidden = false; endBtn.hidden = false; }   // the admin reopened it
          status.textContent = d.online ? 'Online now' : 'Usually replies within a few hours'; status.classList.toggle('on', !!d.online);
        }
        else setDot(d.unread || 0);
      }).catch(function () {});
    }
    function schedule() { clearInterval(timer); timer = setInterval(poll, isOpen ? 3000 : 30000); }
    function open() {
      if (!panel) build();
      isOpen = true; panel.hidden = false; fab.setAttribute('aria-expanded', 'true'); box.classList.add('is-open'); hideHint(false);
      poll(); schedule(); setTimeout(function () { (who && who.querySelector('input').value === '' ? who.querySelector('input') : ta).focus(); }, 50);
    }
    function close() { isOpen = false; panel.hidden = true; fab.setAttribute('aria-expanded', 'false'); box.classList.remove('is-open'); schedule(); fab.focus(); }
    function send(form) {
      var text = ta.value.trim(); if (!text || busy) return;
      busy = true; sendBtn.disabled = true; err.hidden = true;
      var fd = new FormData(form); fd.append('a', 'send'); fd.append('csrf', box.getAttribute('data-csrf') || csrf); fd.append('after', last);
      fd.append('page', location.pathname.split('/').pop() || 'home');
      fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.error) { err.textContent = d.error; err.hidden = false; return; }
          has = true; ta.value = ''; if (who) { who.remove(); who = null; }
          add(d.messages || []); receipt(list, d.read || 0); inThread = true; endBtn.hidden = false;
          status.textContent = d.online ? 'Online now' : 'Got it. We’ll reply here, and by email if you’ve left.';
          status.classList.toggle('on', !!d.online);
        })
        .catch(function () { err.textContent = 'Your message couldn’t be sent. Check your connection and try again.'; err.hidden = false; })
        .then(function () { busy = false; sendBtn.disabled = false; ta.focus(); });
    }
    // "Need help? Chat with us" above the button, a few seconds after the page opens; ✕ hides it for a day
    var hint = null, HINT_KEY = 'll_chat_hint';
    function hintAllowed() {
      if (document.body.classList.contains('page-auth') && window.matchMedia('(max-width: 560px)').matches) return false;   // the button sits at the end of the page there
      try { return Date.now() - (+localStorage.getItem(HINT_KEY) || 0) > 86400000; } catch (e) { return true; }
    }
    function showHint(online) {
      if (isOpen || hint || !hintAllowed()) return;
      hint = el('div', 'chat-hint');
      var body = el('button', 'chat-hint-body'); body.type = 'button';
      body.appendChild(el('b', null, 'Need help? 👋'));
      body.appendChild(el('span', null, online ? 'We’re online now. Chat with us' : 'Send us a message anytime'));
      body.addEventListener('click', open);
      var x = el('button', 'chat-hint-x', '✕'); x.type = 'button'; x.setAttribute('aria-label', 'Hide this');
      x.addEventListener('click', function () { hideHint(true); });
      hint.appendChild(body); hint.appendChild(x); box.appendChild(hint);
    }
    function hideHint(remember) {
      if (remember) { try { localStorage.setItem(HINT_KEY, String(Date.now())); } catch (e) {} }
      if (hint) { hint.remove(); hint = null; }
    }
    if (hintAllowed()) setTimeout(function () {
      if (isOpen) return;
      get({ a: 'poll' }).then(function (d) { showHint(!!d.online); }).catch(function () { showHint(false); });
    }, 4000);
    fab.addEventListener('click', function () { if (isOpen) close(); else open(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && isOpen) close(); });
    if (new URLSearchParams(location.search).get('chat') === '1') open();
    else if (has) { poll(); schedule(); }
  })();

  /* ---------- the admin's Support chats: new messages appear by themselves ---------- */
  var cs = document.querySelector('[data-cs]');
  if (cs) (function () {
    var t = +cs.getAttribute('data-thread'), last = +cs.getAttribute('data-last'), sig = +cs.getAttribute('data-sig');
    var msgs = cs.querySelector('[data-cs-msgs]'), ta = cs.querySelector('[data-cs-text]'), here = cs.querySelector('[data-cs-here]'), typingEl = cs.querySelector('[data-cs-typing]');
    // Times in the admin's own time zone ("just now" / "12 min ago" in the list)
    cs.querySelectorAll('[data-at]').forEach(function (x) {
      var at = +x.getAttribute('data-at'), ago = Date.now() / 1000 - at;
      var d = new Date(at * 1000), today = d.toDateString() === new Date().toDateString();
      x.textContent = !x.hasAttribute('data-short') ? when(at) : ago < 60 ? 'just now' : ago < 3600 ? Math.floor(ago / 60) + ' min ago'
        : today ? d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    });
    if (msgs) { receipt(msgs, +cs.getAttribute('data-read')); msgs.scrollTop = msgs.scrollHeight; }
    if (ta) {
      if (location.hash === '#reply') ta.focus();
      ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); ta.form.requestSubmit ? ta.form.requestSubmit() : ta.form.submit(); } });
      var typedAt = 0;
      ta.addEventListener('input', function () {   // they see "… is typing" (at most every 3 seconds)
        if (!ta.value.trim() || Date.now() - typedAt < 3000) return;
        typedAt = Date.now();
        var fd = new FormData(); fd.append('a', 'typing'); fd.append('t', t); fd.append('csrf', ta.form.querySelector('[name=csrf]').value);
        fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
      });
    }
    setInterval(function () {
      get({ a: 'admin', t: t, after: last }).then(function (d) {
        (d.messages || []).forEach(function (m) { if (m.id > last) { msgs.appendChild(bubble(m, m.from === 'admin')); last = m.id; msgs.scrollTop = msgs.scrollHeight; } });
        if (here) here.hidden = !d.here;
        if (typingEl) typingEl.hidden = !d.typing;
        if (msgs) receipt(msgs, d.read || 0);
        var s = +d.sig;
        if (s > Math.max(sig, last) && (!ta || ta.value.trim() === '')) location.reload();   // someone else wrote: refresh the list
        sig = Math.max(sig, s);
      }).catch(function () {});
    }, 3000);
  })();
})();
