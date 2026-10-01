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
})();
