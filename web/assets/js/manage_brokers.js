document.addEventListener('DOMContentLoaded', () => {
  const brokersTableBody = document.getElementById('brokersTableBody');
  const addBrokerBtn = document.getElementById('addBrokerBtn');
  const brokerModal = new bootstrap.Modal(document.getElementById('brokerModal'));
  const brokerForm = document.getElementById('brokerForm');

  let editMode = false;
  let currentBrokerId = null;

  // Fetch and display all brokers
  async function loadBrokers() {
    try {
      const response = await fetch('/cashcue/api/getBrokers.php');
      const brokers = await response.json();
      brokersTableBody.innerHTML = '';

      if (!Array.isArray(brokers) || brokers.length === 0) {
        brokersTableBody.innerHTML = `
          <tr>
            <td colspan="7" class="text-center text-muted py-3">
              No brokers found.
            </td>
          </tr>`;
        return;
      }

      brokers.forEach(b => {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${b.id}</td>
          <td>${b.name}</td>
          <td>${b.account_number || '-'}</td>
          <td>${b.account_type}</td>
          <td>${b.currency}</td>
          <td>${b.created_at}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary me-1 editBrokerBtn" data-id="${b.id}">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger deleteBrokerBtn" data-id="${b.id}">
              <i class="bi bi-trash"></i>
            </button>
          </td>
          `;
        brokersTableBody.appendChild(row);
      });
    } catch (error) {
      console.error('Error loading brokers:', error);
      brokersTableBody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center text-danger py-3">
            Failed to load brokers.
          </td>
        </tr>`;
    }
  }

  // Open modal for adding new broker
  addBrokerBtn.addEventListener('click', () => {
    editMode = false;
    currentBrokerId = null;
    brokerForm.reset();
    document.getElementById('brokerModalLabel').textContent = 'Add Broker';
    brokerModal.show();
  });

  // Handle form submit (add or update)
  brokerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(brokerForm);
    const url = editMode ? '/cashcue/api/updateBroker.php' : '/cashcue/api/addBroker.php';

    if (editMode) {
      formData.append('id', currentBrokerId);
    }

    try {
      const response = await fetch(url, {
        method: 'POST',
        body: formData
      });
      const result = await response.json();

      if (result.success) {
        brokerModal.hide();
        await loadBrokers();
        showAlert(result.message || (editMode ? 'Broker updated!' : 'Broker added!'), 'success');
      } else {
        showAlert(result.message || 'Operation failed.', 'danger');
      }
    } catch (error) {
      console.error('Error saving broker:', error);
      showAlert('Error saving broker.', 'danger');
    }
  });

  // Delegate click events for edit & delete buttons
  brokersTableBody.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.editBrokerBtn');
    const deleteBtn = e.target.closest('.deleteBrokerBtn');

    // Edit broker
    if (editBtn) {
      const id = editBtn.dataset.id;
      try {
        const response = await fetch(`/cashcue/api/getBrokers.php?id=${id}`);
        const broker = await response.json();
        if (broker && broker.id) {
          editMode = true;
          currentBrokerId = broker.id;
          document.getElementById('brokerModalLabel').textContent = 'Edit Broker';
          document.getElementById('brokerName').value = broker.name;
          document.getElementById('accountNumber').value = broker.account_number || '';
          document.getElementById('accountType').value = broker.account_type;
          document.getElementById('currency').value = broker.currency || 'EUR';
          brokerModal.show();
        } else {
          showAlert('Broker not found.', 'warning');
        }
      } catch (error) {
        console.error('Error fetching broker details:', error);
        showAlert('Failed to load broker details.', 'danger');
      }
    }

    // Delete broker
    if (deleteBtn) {
      const id = deleteBtn.dataset.id;
      if (!confirm('Are you sure you want to delete this broker?')) return;

      try {
        const response = await fetch('/cashcue/api/deleteBroker.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `id=${id}`
        });
        const result = await response.json();

        if (result.success) {
          await loadBrokers();
          showAlert(result.message || 'Broker deleted.', 'success');
        } else {
          showAlert(result.message || 'Delete failed.', 'danger');
        }
      } catch (error) {
        console.error('Error deleting broker:', error);
        showAlert('Error deleting broker.', 'danger');
      }
    }
  });

  // Helper: show alert message at top of page
  function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-2`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.querySelector('.container-fluid').prepend(alertDiv);
    setTimeout(() => {
      const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
      alert.close();
    }, 4000);
  }

  // Initial load
  loadBrokers();
});
