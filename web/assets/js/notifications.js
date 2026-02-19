// notifications.js
// ------------------------------------------------------------
// This file contains a simple notification system for displaying alerts to the user.
// It defines a global `showAlert` function that can be called from anywhere in the app to display a notification with a specified type (info, success, warning, danger) and message.
// The notifications are displayed in a container that is created dynamically if it doesn't already exist. Each alert includes an icon, the message, and a close button. Alerts automatically disappear after a specified timeout (default 5 seconds) or can be dismissed manually by the user.
// The system also limits the number of visible alerts to prevent overwhelming the user, removing the oldest alert when the limit is exceeded. The styling and icons are based on Bootstrap's alert classes and icons for consistency with the rest of the app's UI.
// Usage example:
// showAlert("success", "Order added successfully!");
// showAlert("danger", "Failed to add order. Please try again.", 7000);
// -----------------------------------------------------------

// Define the icons for each alert type using Bootstrap icon classes
const ALERT_ICONS = {
  info: 'bi-info-circle-fill',
  success: 'bi-check-circle-fill',
  warning: 'bi-exclamation-triangle-fill',
  danger: 'bi-x-circle-fill'
};

// Maximum number of alerts to display at once
const MAX_ALERTS = 4;

// Function to get the alert container element, or create it if it doesn't exist
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

// Global function to show an alert with a specified type, message, and optional timeout
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

// Function to close and remove an alert element
function closeAlert(alert) {
  alert.classList.remove('show');
  alert.classList.add('hide');
  setTimeout(() => alert.remove(), 400);
}
