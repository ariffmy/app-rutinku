(() => {
  'use strict';
  const trigger = document.querySelector('[data-open-photo]');
  const dialog = document.querySelector('#profile-photo-dialog');
  if (!trigger || !dialog) return;
  trigger.addEventListener('click', () => dialog.showModal());
  dialog.querySelector('[data-close-photo]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => trigger.focus());
})();
