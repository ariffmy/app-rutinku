(() => {
  'use strict';
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
    if (now.toDateString() !== pageDay) { location.reload(); return; }
    document.querySelector('[data-today-date]').textContent = new Intl.DateTimeFormat('en-GB', {day:'2-digit', month:'2-digit', year:'numeric'}).format(now);
    document.querySelector('[data-today-day]').textContent = ['Ahad', 'Isnin', 'Selasa', 'Rabu', 'Khamis', 'Jumaat', 'Sabtu'][now.getDay()];
    for (const task of tasks) {
      const status = task.dataset.status || (task.dataset.completed === '1' ? 'completed' : 'not_completed');
      const done = status === 'completed';
      task.hidden = false;
      task.classList.toggle('task-completed', done);
      const target = done ? completed : pending;
      target.append(task);
      const form = task.querySelector('form');
      if (form) {
        form.hidden = status === 'pending' || status === 'rejected';
        form.action = done ? form.dataset.undoUrl : form.dataset.completeUrl;
        form.querySelector('button').textContent = done ? 'Batal selesai' : 'Sudah';
      }
      const label = task.querySelector('[data-status-label]');
      if (label) {
        label.hidden = status === 'not_completed';
        label.textContent = { completed: 'Selesai', pending: 'Menunggu kelulusan', rejected: 'Ditolak' }[status] || '';
        label.className = `badge ${status === 'pending' ? 'text-bg-warning' : status === 'rejected' ? 'text-bg-danger' : 'text-bg-success'}`;
      }
    }
    const hasPendingTasks = [...pending.children].some(task => !task.hidden);
    root.querySelector('[data-empty-pending]').hidden = hasPendingTasks;
    root.querySelector('#pending-heading').hidden = !hasPendingTasks;
    root.querySelector('[data-completed-section]').hidden = ![...completed.children].some(task => !task.hidden);
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
        document.querySelectorAll('input[type="hidden"]').forEach(input => { if (input.name === root.dataset.csrfName) input.value = data.csrf; });
      }
      if (!response.ok) throw new Error(data.message || 'Tidak dapat menyimpan tugasan.');
      form.closest('[data-task]').dataset.completed = data.completed ? '1' : '0';
      form.closest('[data-task]').dataset.status = data.status || (data.completed ? 'completed' : 'not_completed');
      document.querySelector('[data-balance]').textContent = data.balance;
      const perfectDay = document.querySelector('[data-perfect-day]');
      if (perfectDay) {
        perfectDay.hidden = !data.perfect_day;
        perfectDay.querySelector('[data-perfect-day-bonus]').textContent = data.perfect_day?.bonus_points ?? 0;
      }
      const daily = document.querySelector('[data-daily-progress]');
      if (daily && data.daily_progress) {
        daily.querySelector('[data-daily-completed]').textContent = data.daily_progress.completed_count;
        daily.querySelector('[data-daily-total]').textContent = data.daily_progress.total_count;
        daily.querySelector('[data-daily-percentage]').textContent = `${data.daily_progress.percentage}%`;
        daily.querySelector('[data-daily-bar]').value = data.daily_progress.percentage;
        daily.querySelector('[data-daily-bar]').textContent = `${data.daily_progress.percentage}%`;
      }
      const goal = document.querySelector('[data-reward-goal]');
      if (goal) {
        const required = Number(goal.dataset.required);
        const percentage = Math.min(100, Math.max(0, Math.floor(data.balance * 100 / Math.max(1, required))));
        goal.querySelector('[data-goal-balance]').textContent = data.balance;
        goal.querySelector('[data-goal-remaining]').textContent = Math.max(0, required - data.balance);
        goal.querySelector('[data-goal-percentage]').textContent = `${percentage}%`;
        goal.querySelector('[data-goal-progress]').value = percentage;
        goal.querySelector('[data-goal-progress]').textContent = `${percentage}%`;
      }
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
    const undo = form.closest('[data-task]').dataset.completed === '1';
    selected = form;
    modal.querySelector('#confirm-title').textContent = undo ? 'Pasti mahu batalkan selesai?' : 'Pasti ke sudah?';
    modal.querySelector('[data-confirm-cancel]').textContent = undo ? 'Tidak, kekalkan' : 'Belum lagi';
    modal.querySelector('[data-confirm-yes]').textContent = undo ? 'Ya, batalkan' : 'Ya, sudah!';
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
