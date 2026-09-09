(() => {
  'use strict';

  const parentNav = document.querySelector('[data-parent-nav]');
  const menuToggle = parentNav?.querySelector('[data-parent-menu-toggle]');
  const menuPanel = parentNav?.querySelector('#parent-nav-panel');

  if (menuToggle && menuPanel) {
    // Without JavaScript the links remain visible in a stacked, usable layout.
    const desktop = window.matchMedia('(min-width: 1200px)');
    let menuOpen = false;
    const updateMenu = () => {
      menuToggle.hidden = desktop.matches;
      menuPanel.hidden = !desktop.matches && !menuOpen;
      menuToggle.setAttribute('aria-expanded', String(!menuPanel.hidden));
    };
    const closeMenu = (restoreFocus = false) => {
      menuOpen = false;
      updateMenu();
      if (restoreFocus && !desktop.matches) menuToggle.focus();
    };

    menuToggle.addEventListener('click', () => {
      menuOpen = !menuOpen;
      updateMenu();
    });
    parentNav.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && menuOpen && !desktop.matches) {
        event.preventDefault();
        closeMenu(true);
      }
    });
    menuPanel.addEventListener('click', (event) => {
      if (event.target instanceof Element && event.target.closest('a[href]')) closeMenu();
    });
    document.addEventListener('click', (event) => {
      if (!parentNav.contains(event.target)) closeMenu();
    });
    desktop.addEventListener('change', () => {
      const focusWasInPanel = menuPanel.contains(document.activeElement);
      closeMenu(focusWasInPanel);
    });
    updateMenu();
  }

  const script = document.querySelector('script[data-service-worker-url]');
  if ('serviceWorker' in navigator && script?.dataset.serviceWorkerUrl) {
    window.addEventListener('load', () => {
      const hostname = window.location.hostname;
      const isLocalDevelopment = hostname === 'localhost'
        || hostname === '127.0.0.1'
        || hostname === '[::1]'
        || /^10\./.test(hostname)
        || /^192\.168\./.test(hostname)
        || /^172\.(1[6-9]|2\d|3[01])\./.test(hostname);

      if (isLocalDevelopment) {
        navigator.serviceWorker.getRegistrations()
          .then((registrations) => Promise.all(registrations.map((registration) => registration.unregister())))
          .then(() => caches.keys())
          .then((keys) => Promise.all(keys.filter((key) => key.startsWith('rutinku-')).map((key) => caches.delete(key))))
          .catch(() => {
            // Local development remains usable even if browser storage cannot be cleared.
          });
        return;
      }

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

  document.addEventListener('submit', (event) => {
    const form = event.target instanceof Element ? event.target.closest('form[data-confirm-message]') : null;
    if (form && !window.confirm(form.dataset.confirmMessage || 'Teruskan tindakan ini?')) {
      event.preventDefault();
    }
  });

  window.addEventListener('appinstalled', () => {
    installPrompt = null;
    installButtons.forEach((button) => button.setAttribute('hidden', ''));
  });
})();
