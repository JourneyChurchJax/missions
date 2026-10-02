// Journey Missions: small interactions. No libraries.
(function () {
  const toast = document.querySelector('.toast');
  function say(msg, error) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.toggle('error', !!error);
    toast.classList.add('show');
    clearTimeout(say.t);
    say.t = setTimeout(() => toast.classList.remove('show'), error ? 6000 : 3500);
  }
  // A message from the last save fades away on its own
  if (toast && toast.classList.contains('show')) say.t = setTimeout(() => toast.classList.remove('show'), toast.classList.contains('error') ? 7000 : 4000);

  // "Are you sure?" for forms and buttons that do something big
  document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => {
    if (!confirm(f.dataset.confirm)) { e.preventDefault(); e.stopImmediatePropagation(); }
  }));
  document.querySelectorAll('[data-confirm-btn]').forEach(b => b.addEventListener('click', e => {
    if (!confirm(b.dataset.confirmBtn)) { e.preventDefault(); e.stopImmediatePropagation(); }
  }));

  // No double submits: the button shows it's working until the page changes
  document.querySelectorAll('form').forEach(f => f.addEventListener('submit', e => {
    if (e.defaultPrevented || f.method.toLowerCase() === 'get' || f.hasAttribute('data-chat-form') || f.hasAttribute('data-mark')) return;
    const btn = e.submitter || f.querySelector('button[type=submit],button:not([type])');
    if (f.dataset.sending) { e.preventDefault(); return; }
    // Wait a moment: another check (a confirm box, a missing signature) may stop this submit
    setTimeout(() => {
      if (e.defaultPrevented) return;
      f.dataset.sending = '1';
      if (btn) { btn.classList.add('busy'); btn.setAttribute('aria-busy', 'true'); }
      setTimeout(() => { delete f.dataset.sending; if (btn) { btn.classList.remove('busy'); btn.removeAttribute('aria-busy'); } }, 8000);
    }, 0);
  }));

  // Copy buttons
  document.querySelectorAll('[data-copy]').forEach(el => el.addEventListener('click', async e => {
    e.preventDefault();
    try { await navigator.clipboard.writeText(el.getAttribute('data-copy')); say('Link copied'); }
    catch { prompt('Copy this link:', el.getAttribute('data-copy')); }
  }));
  document.querySelectorAll('[data-print]').forEach(el => el.addEventListener('click', () => window.print()));

  // The account menu closes when you click elsewhere or press Escape
  document.addEventListener('click', e => document.querySelectorAll('details.acct[open]').forEach(d => { if (!d.contains(e.target)) d.removeAttribute('open'); }));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('details.acct[open]').forEach(d => d.removeAttribute('open')); });

  // Gift form: show new-donor fields only when no saved donor is picked
  document.querySelectorAll('select[data-newdonor]').forEach(sel => {
    const box = sel.closest('form').querySelector('.newdonor');
    const sync = () => { if (box) box.hidden = sel.value !== '0'; };
    sel.addEventListener('change', sync); sync();
  });

  // Give form: typing another amount picks "other", and the button shows the total
  document.querySelectorAll('form[data-give]').forEach(f => {
    const btn = f.querySelector('[data-give-btn]'); if (!btn || btn.disabled) return;
    const other = f.querySelector('[data-other]'), cover = f.querySelector('[data-cover]'), monthly = f.querySelector('[name=monthly]');
    const update = () => {
      const pick = f.querySelector('input[name=amount]:checked');
      let amt = pick && pick.value === 'other' ? parseFloat(other.value || '0') : parseFloat(pick ? pick.value : '0');
      if (!(amt >= 5)) { btn.textContent = 'Give securely'; return; }
      if (cover && cover.checked) amt = (amt + 0.30) / (1 - 0.029);
      btn.textContent = 'Give $' + amt.toFixed(2) + (monthly && monthly.checked ? ' a month' : '');
    };
    if (other) other.addEventListener('input', () => { const r = f.querySelector('input[name=amount][value=other]'); if (r) r.checked = true; update(); });
    f.addEventListener('change', update); update();
  });

  // Chat: send without reloading, fetch new messages every 8 seconds while the page is visible
  document.querySelectorAll('[data-chat]').forEach(box => {
    const form = box.parentElement.querySelector('[data-chat-form]');
    let pulling = false;
    box.scrollTop = box.scrollHeight;
    async function pull() {
      if (pulling || document.hidden) return;
      pulling = true;
      try {
        const r = await fetch(box.dataset.chat + '&after=' + box.dataset.after, { credentials: 'same-origin' });
        if (!r.ok) return;
        const tmp = document.createElement('div'); tmp.innerHTML = (await r.text()).trim();
        let added = false;
        tmp.querySelectorAll('[data-id]').forEach(el => {
          if (box.querySelector('[data-id="' + el.dataset.id + '"]')) return;   // already showing
          box.appendChild(el); added = true;
          box.dataset.after = Math.max(+box.dataset.after, +el.dataset.id);
        });
        if (added) { box.querySelector('[data-empty]')?.remove(); box.scrollTop = box.scrollHeight; }
      } catch (e) {} finally { pulling = false; }
    }
    setInterval(pull, 8000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) pull(); });
    if (form) form.addEventListener('submit', async e => {
      e.preventDefault();
      const input = form.querySelector('input[name=body]');
      const text = input.value.trim(); if (!text) return;
      const fd = new FormData(form); fd.append('ajax', '1');
      input.disabled = true;
      try {
        const r = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' });
        if (!r.ok) throw new Error();
        input.value = '';
      } catch (err) { say("Couldn't send. Your message is still in the box. Try again.", true); }
      input.disabled = false; input.focus();
      pull();
    });
  });

  // Headcount: mark here or missing without reloading (and undo the mark if saving fails)
  document.querySelectorAll('[data-mark]').forEach(f => f.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = e.submitter; const clearing = btn.classList.contains('btn-dark');
    const before = [...f.querySelectorAll('button')].map(b => b.classList.contains('btn-dark'));
    f.querySelectorAll('button').forEach(b => b.classList.toggle('btn-dark', b === btn && !clearing));
    const row = f.closest('[data-row]'); const prev = row ? row.dataset.state : '';
    if (row) row.dataset.state = clearing ? '' : btn.value;
    const recount = () => { const root = f.closest('[data-checkin]'); if (!root) return; const rows = [...root.querySelectorAll('[data-row]')];
      root.querySelector('[data-here]').textContent = rows.filter(r => r.dataset.state === 'here').length;
      root.querySelector('[data-missing]').textContent = rows.filter(r => r.dataset.state === 'missing').length; };
    recount();
    const fd = new FormData(f); fd.set('status', clearing ? '' : btn.value); fd.append('ajax', '1');
    try { const r = await fetch(f.action, { method: 'POST', body: fd, credentials: 'same-origin' }); if (!r.ok) throw new Error(); }
    catch (err) {
      f.querySelectorAll('button').forEach((b, i) => b.classList.toggle('btn-dark', before[i]));
      if (row) row.dataset.state = prev; recount();
      say("Couldn't save that. Check your connection and tap again.", true);
    }
  }));

  // Signature: draw with a finger, pen or mouse, or type your name instead
  document.querySelectorAll('form[data-sign]').forEach(form => {
    const c = form.querySelector('canvas.sigpad'), out = form.querySelector('[name=sig_image]'), mode = form.querySelector('[name=mode]');
    const drawArea = form.querySelector('[data-draw-area]'), typedArea = form.querySelector('[data-typed-area]');
    const preview = form.querySelector('[data-typed-preview]'), signer = form.querySelector('[data-signer]');
    const ctx = c.getContext('2d');
    let drawing = false, inked = false, last = null;
    const pos = e => { const r = c.getBoundingClientRect(); return [(e.clientX - r.left) * c.width / r.width, (e.clientY - r.top) * c.height / r.height]; };
    ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0a0a0a';
    c.addEventListener('pointerdown', e => { drawing = true; last = pos(e); c.setPointerCapture(e.pointerId); });
    c.addEventListener('pointermove', e => { if (!drawing) return; const p = pos(e); ctx.beginPath(); ctx.moveTo(...last); ctx.lineTo(...p); ctx.stroke(); last = p; inked = true; });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(t => c.addEventListener(t, () => { drawing = false; }));
    form.querySelector('[data-clear]').addEventListener('click', () => { ctx.clearRect(0, 0, c.width, c.height); inked = false; });
    const showTyped = typed => { mode.value = typed ? 'typed' : 'draw'; drawArea.hidden = typed; typedArea.hidden = !typed; if (typed) preview.textContent = signer.value; };
    form.querySelector('[data-type-instead]').addEventListener('click', () => showTyped(true));
    form.querySelector('[data-draw-instead]').addEventListener('click', () => showTyped(false));
    signer.addEventListener('input', () => { preview.textContent = signer.value; });
    form.addEventListener('submit', e => {
      if (mode.value === 'draw' && !inked) { e.preventDefault(); e.stopImmediatePropagation(); say('Draw your signature in the box, or choose "Type my name instead".', true); return; }
      out.value = mode.value === 'draw' ? c.toDataURL('image/png') : '';
    });
  });

  // Announcements: say exactly who will get an email or text, and ask before sending
  document.querySelectorAll('form[data-announce]').forEach(f => {
    const c = JSON.parse(f.dataset.counts || '{}'), reach = f.querySelector('[data-reach]');
    const pick = () => { const parents = f.parents.checked; return { e: f.email.checked ? (parents ? c.pe : c.e) : 0, t: f.text.checked ? (parents ? c.pt : c.t) : 0 }; };
    const update = () => { const n = pick(); reach.textContent = 'Shows in every traveler\'s Messages' + (n.e || n.t ? ', and sends ' + [n.e ? n.e + ' email' + (n.e === 1 ? '' : 's') : '', n.t ? n.t + ' text' + (n.t === 1 ? '' : 's') : ''].filter(Boolean).join(' and ') : '') + '.'; };
    f.addEventListener('change', update); update();
    f.addEventListener('submit', e => { const n = pick(); if ((n.e || n.t) && !confirm('Send ' + [n.e ? n.e + ' email' + (n.e === 1 ? '' : 's') : '', n.t ? n.t + ' text' + (n.t === 1 ? '' : 's') : ''].filter(Boolean).join(' and ') + ' now? Messages can\'t be recalled.')) { e.preventDefault(); e.stopImmediatePropagation(); } });
  });
})();
