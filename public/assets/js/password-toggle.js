document.querySelectorAll('[data-password-toggle]').forEach((button) => {
  const input = document.getElementById(button.getAttribute('aria-controls'));
  const icon = button.querySelector('[data-password-toggle-icon]');
  const label = button.querySelector('[data-password-toggle-label]');
  if (!input || input.tagName !== 'INPUT') return;

  button.addEventListener('click', () => {
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    button.setAttribute('aria-pressed', showing ? 'false' : 'true');
    button.setAttribute('aria-label', showing ? 'Tunjukkan kata laluan' : 'Sembunyikan kata laluan');
    if (label) label.textContent = showing ? 'Tunjukkan kata laluan' : 'Sembunyikan kata laluan';
    if (icon) {
      icon.classList.toggle('fa-eye', showing);
      icon.classList.toggle('fa-eye-slash', !showing);
    }
  });
});
