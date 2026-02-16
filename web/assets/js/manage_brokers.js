/**
 * manage_brokers.js — CashCueTable version
 * ----------------------------------------
 * Responsibilities:
 * - Load and display brokers table using CashCueTable
 * - Handle add/edit/delete brokers (modal-based)
 * - Manage Cash Account and Comment fields
 * - Keep all validated business logic intact
 */

document.addEventListener('DOMContentLoaded', () => {

  /* ============================================================
     DOM REFERENCES
  ============================================================ */
  const addBtn = document.getElementById('addBrokerBtn');
  const modalEl = document.getElementById('brokerModal');
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById('brokerForm');

  const brokerIdInput = document.getElementById('brokerId');
  const nameInput = document.getElementById('brokerName');
  const accountNumberInput = document.getElementById('accountNumber');
  const accountTypeInput = document.getElementById('accountType');
  const currencyInput = document.getElementById('currency');
  const hasCashAccountInput = document.getElementById('hasCashAccount');
  const initialDepositInput = document.getElementById('initialDeposit');
  const initialDepositContainer = document.getElementById('initialDepositContainer');
  const commentInput = document.getElementById('brokerComment');
  const modalTitle = document.getElementById('brokerModalLabel');

  const createdAtLabel = document.getElementById('brokerCreatedAt');
  const tableContainerId = "brokersTableContainer";

  /* ============================================================
     STATE
  ============================================================ */
  let editMode = false;
  let currentBrokerId = null;
  let brokersTable;

  /* ============================================================
     UTILITY FUNCTIONS
  ============================================================ */

  //Resets the form to default state for creating a new broker
  function resetForm() {
    form.reset();
    brokerIdInput.value = '';
    currentBrokerId = null;
    hasCashAccountInput.checked = false;
    initialDepositInput.value = '0.00';
    initialDepositContainer.style.display = 'none';
    commentInput.value = '';
  }

  function alertWarning(msg) { showAlert('warning', msg); }

  // toogleInitialDeposit shows/hides the initial deposit field 
  // based on the cash account checkbox
  function toggleInitialDeposit() {
    if (hasCashAccountInput.checked && !editMode) {
      initialDepositContainer.style.display = 'block';
    } else {
      initialDepositContainer.style.display = 'none';
      initialDepositInput.value = '0.00';
    }
  }

  // openCreateModal prepares the modal for creating a new broker
  function openCreateModal() {
    resetForm();
    editMode = false;
    modalTitle.textContent = 'Add Broker';

    createdAtLabel.style.display = 'none';
    createdAtLabel.textContent = '';

    cashAccountSection.style.display = 'block';

    hasCashAccountInput.disabled = false;
    initialDepositInput.disabled = false;

    toggleInitialDeposit();
    modal.show();
  }


  // openEditModal prepares the modal for editing an existing broker
  function openEditModal(broker) {
    resetForm();
    editMode = true;
    currentBrokerId = broker.id;

    modalTitle.textContent = 'Edit Broker';

    brokerIdInput.value = broker.id;
    nameInput.value = broker.name;
    accountNumberInput.value = broker.account_number || '';
    accountTypeInput.value = broker.account_type;
    currencyInput.value = broker.currency || 'EUR';
    commentInput.value = broker.comment || '';

    // Display creation timestamp (edit mode only)
    createdAtLabel.textContent = `Created at: ${broker.created_at}`;
    createdAtLabel.style.display = 'inline';

    // Hide cash configuration in edit mode
    cashAccountSection.style.display = 'none';

    modal.show();
  }

  /* ============================================================
     INITIALIZE CASHCUE TABLE
  ============================================================ */
  function initBrokersTable() {

    brokersTable = new CashCueTable({
      containerId: tableContainerId,
      emptyMessage: "No brokers found",
      pagination: { enabled: true, pageSize: 10 },

      columns: [
        { key: "id", label: "ID", sortable: true, sortType: "number" },
        { key: "name", label: "Name", sortable: true },
        { key: "account_number", label: "Account Number", sortable: true },
        { key: "account_type", label: "Account Type", sortable: true },
        { key: "currency", label: "Currency", sortable: true },
        { key: "created_at", label: "Created At", sortable: true, sortType: "date" },
        {
          key: "has_cash_account",
          label: "Cash Account",
          sortable: true,
          align: "center",
          render: row => row.has_cash_account
            ? '<i class="bi bi-check-circle-fill text-success"></i>'
            : '<i class="bi bi-x-circle-fill text-danger"></i>'
        },
        {
          key: "comment",
          label: "Comment",
          sortable: false,
          render: row => row.comment ?? ''
        },
        {
          key: "actions",
          label: "Actions",
          sortable: false,
          align: "center",
          html: true,
          render: row => `
            <button class="btn btn-sm btn-outline-primary me-1 editBrokerBtn" data-id="${row.id}">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger deleteBrokerBtn" data-id="${row.id}">
              <i class="bi bi-trash"></i>
            </button>
          `
        }
      ]
    });
  }

  /* ============================================================
     LOAD BROKERS
  ============================================================ */
  async function loadBrokers() {
    try {
      const res = await fetch('/cashcue/api/getBrokers.php');
      const data = await res.json();
      brokersTable.setData(data ?? []);
    } catch (err) {
      console.error('Error loading brokers:', err);
      brokersTable.setData([]);
    }
  }

  /* ============================================================
     DELETE BROKER
  ============================================================ */
  async function deleteBroker(id) {
    if (!confirm('Are you sure you want to delete this broker?')) return;

    try {
      const res = await fetch('/cashcue/api/deleteBroker.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
      });
      const result = await res.json();
      if (result.success) {
        showAlert('success', result.message || 'Broker deleted.');
        await loadBrokers();
      } else {
        showAlert('danger', result.message || 'Delete failed.');
      }
    } catch (err) {
      console.error('Error deleting broker:', err);
      showAlert('danger', 'Error deleting broker.');
    }
  }

  /* ============================================================
     EVENT DELEGATION
  ============================================================ */
  document.addEventListener('click', async e => {

    const editBtn = e.target.closest('.editBrokerBtn');
    const deleteBtn = e.target.closest('.deleteBrokerBtn');

    if (editBtn) {
      const id = editBtn.dataset.id;
      try {
        const res = await fetch(`/cashcue/api/getBrokers.php?id=${id}`);
        const broker = await res.json();
        if (!broker || !broker.id) return alertWarning('Broker not found.');
        openEditModal(broker);
      } catch (err) {
        console.error('Error fetching broker:', err);
        showAlert('danger', 'Failed to load broker.');
      }
    }

    if (deleteBtn) deleteBroker(deleteBtn.dataset.id);
  });

  /* ============================================================
     FORM SUBMIT
  ============================================================ */
  form.addEventListener('submit', async e => {
    e.preventDefault();

    if (!commentInput.value.trim()) return alertWarning('Comment is mandatory.');

    const formData = new FormData(form);
    formData.set('has_cash_account', hasCashAccountInput.checked ? 1 : 0);
    formData.set('initial_deposit', initialDepositInput.value || 0);

    if (editMode) formData.set('id', currentBrokerId);

    console.log('Submitting form with data:', Object.fromEntries(formData.entries()));

    const url = editMode ? '/cashcue/api/updateBroker.php' : '/cashcue/api/addBroker.php';

    try {
      const res = await fetch(url, { method: 'POST', body: formData });
      const result = await res.json();
      if (result.success) {
        modal.hide();
        showAlert('success', result.message || (editMode ? 'Broker updated.' : 'Broker added.'));
        await loadBrokers();
      } else {
        showAlert('danger', result.message || 'Operation failed.');
      }
    } catch (err) {
      console.error('Error saving broker:', err);
      showAlert('danger', 'Error saving broker.');
    }
  });

  /* ============================================================
     INITIALIZATION
  ============================================================ */
  addBtn.addEventListener('click', openCreateModal);
  hasCashAccountInput.addEventListener('change', toggleInitialDeposit);

  initBrokersTable();
  loadBrokers();

});

