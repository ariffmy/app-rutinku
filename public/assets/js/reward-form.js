(() => {
  'use strict';
  const input = document.querySelector('[data-reward-image-input]');
  const preview = document.querySelector('[data-reward-image-preview]');
  if (!input || !preview) return;

  let objectUrl = null;
  input.addEventListener('change', () => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    const file = input.files?.[0];
    if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      preview.hidden = true;
      preview.removeAttribute('src');
      return;
    }
    objectUrl = URL.createObjectURL(file);
    preview.src = objectUrl;
    preview.hidden = false;
  });
  window.addEventListener('pagehide', () => {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
  });
})();
