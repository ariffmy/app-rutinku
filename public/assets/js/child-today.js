(() => {
  'use strict';
  const withinWindow = (time, now) => {
    if (!time) return true;
    const [hours, minutes] = time.split(':').map(Number);
    return Math.abs(hours * 60 + minutes - (now.getHours() * 60 + now.getMinutes())) <= 60;
  };
  const clockLabel = (minutes) => {
    const total = (minutes + 1440) % 1440;
    const hour = Math.floor(total / 60);
    const minute = total % 60;
    const period = hour === 0 ? 'tengah malam' : hour < 12 ? 'pagi' : hour < 14 ? 'tengah hari' : hour < 19 ? 'petang' : 'malam';
    return `${hour % 12 || 12}${minute ? ':' + String(minute).padStart(2, '0') : ''} ${period}`;
  };
  if (typeof module !== 'undefined') module.exports = { withinWindow, clockLabel };
  if (typeof document === 'undefined') return;
  const root = document.querySelector('[data-child-tasks]');
  if (!root) return;
  const tasks = [...root.querySelectorAll('[data-task]')].sort((a, b) =>
    (a.dataset.time || '99:99').localeCompare(b.dataset.time || '99:99') || Number(a.dataset.id) - Number(b.dataset.id));
  const pending = root.querySelector('[data-pending]');
  const completed = root.querySelector('[data-completed]');
  const modal = document.querySelector('#task-confirm');
  const notice = document.querySelector('[data-task-notice]');
  let selected = null;
  let busy = false;
  const pageDay = new Date().toDateString();
  const arrange = () => {
    const now = new Date();
    const minutes = now.getHours() * 60 + now.getMinutes();
    document.querySelector('[data-filter-clock]').textContent = `Waktu peranti: ${clockLabel(minutes)} · Julat masa mula hari ini: ${clockLabel(Math.max(0, minutes - 60))} hingga ${clockLabel(Math.min(1439, minutes + 60))}`;
    if (now.toDateString() !== pageDay) { location.reload(); return; }
    document.querySelector('[data-today-date]').textContent = new Intl.DateTimeFormat('en-GB', {day:'2-digit', month:'2-digit', year:'numeric'}).format(now);
    for (const task of tasks) {
      const done = task.dataset.completed === '1';
      task.hidden = !withinWindow(task.dataset.time, now);
      task.classList.toggle('task-completed', done);
      const target = done ? completed : pending;
      target.append(task);
      const form = task.querySelector('form');
      form.action = done ? form.dataset.undoUrl : form.dataset.completeUrl;
      form.querySelector('button').textContent = done ? 'Batal selesai' : 'Sudah';
    }
    root.querySelector('[data-empty-pending]').hidden = [...pending.children].some(task => !task.hidden);
    root.querySelector('[data-empty-completed]').hidden = [...completed.children].some(task => !task.hidden);
  };
  const send = async (form) => {
    if (busy) return;
    busy = true;
    root.querySelectorAll('button').forEach(button => { button.disabled = true; });
    notice.textContent = 'Sedang menyimpan…';
    try {
      const body = new FormData(form);
      body.set(root.dataset.csrfName, root.dataset.csrfHash);
      const response = await fetch(form.action, { method: 'POST', body, credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json'} });
      if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Sesi berubah. Muat semula halaman sebelum mencuba lagi.');
      const data = await response.json();
      if (data.csrf) {
        root.dataset.csrfHash = data.csrf;
        root.querySelectorAll('input[type="hidden"]').forEach(input => { if (input.name === root.dataset.csrfName) input.value = data.csrf; });
      }
      if (!response.ok) throw new Error(data.message || 'Tidak dapat menyimpan tugasan.');
      form.closest('[data-task]').dataset.completed = data.completed ? '1' : '0';
      document.querySelector('[data-balance]').textContent = data.balance;
      arrange();
      notice.textContent = data.message;
    } catch (error) {
      notice.textContent = error.message || 'Sambungan terganggu. Muat semula halaman untuk menyemak status.';
    } finally {
      busy = false;
      root.querySelectorAll('button').forEach(button => { button.disabled = false; });
      form.querySelector('button').focus();
    }
  };
  root.addEventListener('submit', event => {
    const form = event.target.closest('[data-task-form]');
    if (!form) return;
    event.preventDefault();
    if (busy) return;
    if (form.closest('[data-task]').dataset.completed === '1') { send(form); return; }
    selected = form;
    modal.querySelector('[data-confirm-task]').textContent = form.closest('[data-task]').querySelector('[data-task-title]').textContent;
    modal.showModal();
    modal.querySelector('[data-confirm-cancel]').focus();
  });
  modal.querySelector('[data-confirm-cancel]').addEventListener('click', () => modal.close());
  modal.querySelector('[data-confirm-yes]').addEventListener('click', () => { const form = selected; modal.close(); if (form) send(form); });
  modal.addEventListener('close', () => { selected?.querySelector('button').focus(); selected = null; });
  arrange();
  setInterval(arrange, 15000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) arrange(); });
  window.addEventListener('focus', arrange);
  window.addEventListener('pageshow', arrange);
})();
