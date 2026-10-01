// Journey Missions — small interactions for the preview. No libraries.
(function () {
  const toast = document.querySelector('.toast');
  function say(msg) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add('show');
    clearTimeout(say.t);
    say.t = setTimeout(() => toast.classList.remove('show'), 2200);
  }

  // Segmented options (Hidden / View / Edit, filters): one active per group
  document.querySelectorAll('[data-choice]').forEach(group => {
    group.addEventListener('click', e => {
      const b = e.target.closest('button');
      if (!b) return;
      group.querySelectorAll('button').forEach(x => { x.classList.remove('on'); x.setAttribute('aria-pressed', 'false'); });
      b.classList.add('on'); b.setAttribute('aria-pressed', 'true');
    });
  });

  // Switches
  document.querySelectorAll('.sw').forEach(s => s.addEventListener('click', () => {
    s.setAttribute('aria-checked', s.getAttribute('aria-checked') === 'true' ? 'false' : 'true');
  }));

  // Amount / frequency pickers and anything with data-say show a friendly preview message
  document.querySelectorAll('[data-say]').forEach(el => el.addEventListener('click', e => {
    e.preventDefault();
    say(el.getAttribute('data-say'));
  }));

  // Copy link buttons
  document.querySelectorAll('[data-copy]').forEach(el => el.addEventListener('click', async e => {
    e.preventDefault();
    try { await navigator.clipboard.writeText(el.getAttribute('data-copy')); say('Link copied'); }
    catch { say('Copy the link: ' + el.getAttribute('data-copy')); }
  }));

  // Clickable table rows (People): fill the side panel
  document.querySelectorAll('tr[data-person]').forEach(tr => tr.addEventListener('click', () => {
    document.querySelectorAll('tr[data-person]').forEach(r => r.classList.remove('sel'));
    tr.classList.add('sel');
    const p = JSON.parse(tr.getAttribute('data-person'));
    const panel = document.querySelector('[data-person-panel]');
    if (!panel) return;
    panel.querySelector('[data-f=name]').textContent = p.name;
    panel.querySelector('[data-f=role]').textContent = p.role + (p.trip !== '—' ? ' · ' + p.trip + ' 2027' : '');
    panel.querySelector('[data-f=av]').textContent = p.initials;
    panel.querySelector('[data-f=ready]').textContent = p.ready;
    panel.querySelector('[data-f=raised]').textContent = p.raised;
  }));

  // Message composer: add the bubble locally
  const composer = document.querySelector('[data-composer]');
  if (composer) composer.addEventListener('submit', e => {
    e.preventDefault();
    const input = composer.querySelector('input');
    const text = input.value.trim();
    if (!text) return;
    const b = document.createElement('div');
    b.className = 'mine'; b.textContent = text;
    document.querySelector('.bubbles').appendChild(b);
    input.value = '';
    say('Sent (preview only)');
  });

  // Gift form: show new-donor fields only when no saved donor is picked
  document.querySelectorAll('select[data-newdonor]').forEach(sel => {
    const box = sel.closest('form').querySelector('.newdonor');
    const sync = () => { if (box) box.hidden = sel.value !== '0'; };
    sel.addEventListener('change', sync); sync();
  });

  // Chat: send without reloading, then fetch anything new every 8 seconds
  document.querySelectorAll('[data-chat]').forEach(box => {
    const form = box.parentElement.querySelector('[data-chat-form]');
    box.scrollTop = box.scrollHeight;
    async function pull() {
      try {
        const r = await fetch(box.dataset.chat + '&after=' + box.dataset.after, { credentials: 'same-origin' });
        if (!r.ok) return;
        const html = (await r.text()).trim();
        if (!html) return;
        box.querySelector('[data-empty]')?.remove();
        box.insertAdjacentHTML('beforeend', html);
        const ids = [...box.querySelectorAll('[data-id]')].map(x => +x.dataset.id);
        box.dataset.after = Math.max(+box.dataset.after, ...ids);
        box.scrollTop = box.scrollHeight;
      } catch (e) {}
    }
    setInterval(pull, 8000);
    if (form) form.addEventListener('submit', async e => {
      e.preventDefault();
      const fd = new FormData(form); fd.append('ajax', '1');
      const input = form.querySelector('input[name=body]');
      if (!input.value.trim()) return;
      input.value = '';
      try { await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' }); } catch (e) { say('Could not send. Try again.'); }
      pull();
    });
  });

  // Check-in: mark here or missing without reloading
  document.querySelectorAll('[data-mark]').forEach(f => f.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = e.submitter; const clearing = btn.classList.contains('btn-dark');
    const fd = new FormData(f); fd.append('status', clearing ? '' : btn.value); fd.append('ajax', '1');
    await fetch(f.action, { method: 'POST', body: fd, credentials: 'same-origin' });
    f.querySelectorAll('button').forEach(b => b.classList.toggle('btn-dark', b === btn && !clearing));
    const row = f.closest('[data-row]'); if (row) row.dataset.state = clearing ? '' : btn.value;
    const root = f.closest('[data-checkin]'); if (root) {
      const rows = [...root.querySelectorAll('[data-row]')];
      root.querySelector('[data-here]').textContent = rows.filter(r => r.dataset.state === 'here').length;
      root.querySelector('[data-missing]').textContent = rows.filter(r => r.dataset.state === 'missing').length;
    }
  }));

  // Signature pad: draw with a finger, pen or mouse; the PNG goes into a hidden field
  document.querySelectorAll('form[data-sign]').forEach(form => {
    const c = form.querySelector('canvas.sigpad'), ctx = c.getContext('2d'), out = form.querySelector('[name=sig_image]');
    let drawing = false, inked = false, last = null;
    const pos = e => { const r = c.getBoundingClientRect(); return [(e.clientX - r.left) * c.width / r.width, (e.clientY - r.top) * c.height / r.height]; };
    ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0a0a0a';
    c.addEventListener('pointerdown', e => { drawing = true; last = pos(e); c.setPointerCapture(e.pointerId); });
    c.addEventListener('pointermove', e => { if (!drawing) return; const p = pos(e); ctx.beginPath(); ctx.moveTo(...last); ctx.lineTo(...p); ctx.stroke(); last = p; inked = true; });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(t => c.addEventListener(t, () => { drawing = false; }));
    form.querySelector('[data-clear]').addEventListener('click', () => { ctx.clearRect(0, 0, c.width, c.height); inked = false; });
    form.addEventListener('submit', e => {
      if (!inked) { e.preventDefault(); say('Draw your signature in the box'); return; }
      out.value = c.toDataURL('image/png');
    });
  });

  // Give form: typing another amount picks "other"
  document.querySelectorAll('[data-other]').forEach(inp => inp.addEventListener('input', () => {
    const r = inp.closest('form').querySelector('input[name=amount][value=other]'); if (r) r.checked = true;
  }));
})();
