(() => {
  'use strict';

  const script = document.querySelector('script[data-service-worker-url]');
  if ('serviceWorker' in navigator && script?.dataset.serviceWorkerUrl) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register(script.dataset.serviceWorkerUrl).catch(() => {
        // The application remains fully usable when service-worker registration is unavailable.
      });
    });
  }

  let installPrompt = null;
  const installButtons = document.querySelectorAll('[data-install-app]');

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installPrompt = event;
    installButtons.forEach((button) => button.removeAttribute('hidden'));
  });

  installButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      if (installPrompt === null) {
        return;
      }
      installPrompt.prompt();
      await installPrompt.userChoice;
      installPrompt = null;
      installButtons.forEach((item) => item.setAttribute('hidden', ''));
    });
  });

  window.addEventListener('appinstalled', () => {
    installPrompt = null;
    installButtons.forEach((button) => button.setAttribute('hidden', ''));
  });
})();
