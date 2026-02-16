// notifications.js

const ALERT_ICONS = {
  info: 'bi-info-circle-fill',
  success: 'bi-check-circle-fill',
  warning: 'bi-exclamation-triangle-fill',
  danger: 'bi-x-circle-fill'
};

const MAX_ALERTS = 4;

function getOrCreateAlertContainer() {
  let container = document.getElementById('globalAlertContainer');

  if (!container) {
    container = document.createElement('div');
    container.id = 'globalAlertContainer';
    container.className = 'global-alert-container';
    document.body.appendChild(container);
  }

  return container;
}

window.showAlert = function(type, message, timeout = 5000) {
  const container = getOrCreateAlertContainer();

  if (container.children.length >= MAX_ALERTS) {
    container.firstChild.remove();
  }

  const iconClass = ALERT_ICONS[type] || ALERT_ICONS.info;

  const alert = document.createElement('div');
  alert.className = `custom-alert alert-${type}`;

  alert.innerHTML = `
    <div class="alert-content">
      <i class="bi ${iconClass} me-2"></i>
      <div class="alert-message">${message}</div>
      <button class="alert-close">&times;</button>
    </div>
  `;

  container.appendChild(alert);
  void alert.offsetWidth;
  alert.classList.add('show');

  alert.querySelector('.alert-close')
       .addEventListener('click', () => closeAlert(alert));

  if (timeout) {
    setTimeout(() => closeAlert(alert), timeout);
  }
};

function closeAlert(alert) {
  alert.classList.remove('show');
  alert.classList.add('hide');
  setTimeout(() => alert.remove(), 400);
}
