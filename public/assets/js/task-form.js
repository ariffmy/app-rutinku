(() => {
  'use strict';
  const form = document.querySelector('[data-task-form]');
  if (!form) return;
  const field = (name) => form.elements.namedItem(name);
  const duration = field('duration_minutes');
  const points = field('points');
  const slider = form.querySelector('[data-points-range]');
  const clamp = (value, min, max) => Math.min(max, Math.max(min, Number(value) || 0));
  const timeLabel = (total) => {
    const hour = Math.floor(total / 60) % 24;
    const minute = total % 60;
    const period = hour === 0 ? 'tengah malam' : hour < 12 ? 'pagi' : hour < 14 ? 'tengah hari' : hour < 19 ? 'petang' : 'malam';
    return `${hour % 12 || 12}${minute ? ':' + String(minute).padStart(2, '0') : ''} ${period}`;
  };
  function refresh() {
    form.querySelectorAll('[data-duration]').forEach(button => button.setAttribute('aria-pressed', String(Number(button.dataset.duration) === Number(duration.value))));
    const time = field('task_hour').value === '' ? '' : `${field('task_hour').value}:${field('task_minute').value}`;
    let preview = '';
    if (time && Number(duration.value) >= 1 && Number(duration.value) <= 1440) {
      const [h, m] = time.split(':').map(Number);
      const end = h * 60 + m + Number(duration.value);
      preview = `${timeLabel(h * 60 + m)} – ${timeLabel(end)}${end >= 1440 ? ' (esok)' : ''}`;
    }
    form.querySelector('[data-time-preview]').textContent = preview;
    const type = field('schedule_type').value;
    form.querySelector('[data-date-section]').hidden = type === 'inherit';
    field('start_date').required = type !== 'inherit';
    form.querySelector('[data-weekly-section]').hidden = type !== 'weekly';
    slider.value = clamp(points.value, 0, 10000);
  }
  form.addEventListener('input', refresh);
  form.addEventListener('change', refresh);
  slider.addEventListener('input', () => { points.value = slider.value; });
  form.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button || !form.contains(button)) return;
    if (button.dataset.duration) duration.value = button.dataset.duration;
    if (button.dataset.pointsChange) points.value = clamp(Number(points.value) + Number(button.dataset.pointsChange), 0, 10000);
    if (button.dataset.idea) {
      field('title').value = button.dataset.idea;
      duration.value = button.dataset.minutes;
      points.value = button.dataset.stars;
    }
    refresh();
  });
  refresh();
})();
