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
  // ------------------------------------------------
  // initBrokersTable initializes the CashCueTable with appropriate columns and settings
  // It defines how each column should be rendered, including custom rendering for the cash account status and action buttons.
  // The table is configured to support pagination and sorting on relevant columns.
  // ------------------------------------------------
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

  // ------------------------------------------------
  // loadBrokers fetches the list of brokers from the server and populates the CashCueTable
  // It handles any errors that may occur during the fetch operation and ensures the table is updated accordingly.
  // ------------------------------------------------
  async function loadBrokers() {
    try {
      const res = await fetch('/cashcue/api/getBrokers.php');
      const data = await res.json();
      brokersTable.setData(data ?? []);
    } catch (err) {
      console.error('Error loading brokers:', err);
      showAlert('danger', 'Failed to load brokers.');
      brokersTable.setData([]);
    }
  }

  // ------------------------------------------------
  // deleteBroker handles the deletion of a broker account
  // It prompts the user for confirmation, sends a delete request to the server, and refreshes the table upon success.
  // ------------------------------------------------
  async function deleteBroker(broker) {

    if (!broker || !broker.id) {
      return showAlert('warning', 'Invalid broker data.');
    }

    const confirmationMessage = `
  Delete broker account?

  Name: ${broker.name}
  Account number: ${broker.account_number}
  Account type: ${broker.account_type}

  This action cannot be undone.
  `;

    if (!confirm(confirmationMessage)) return;

    try {
      const res = await fetch('/cashcue/api/deleteBroker.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(broker.id)}`
      });

      if (!res.ok) {
        throw new Error(`HTTP error ${res.status}`);
      }

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


  // ------------------------------------------------
  // Event delegation for edit and delete buttons
  // Listens for clicks on the edit and delete buttons within the table 
  // and triggers the appropriate actions (open edit modal or delete broker).
  // ------------------------------------------------
  document.addEventListener('click', async e => {

    const editBtn   = e.target.closest('.editBrokerBtn');
    const deleteBtn = e.target.closest('.deleteBrokerBtn');

    if (!editBtn && !deleteBtn) return;

    const id = (editBtn || deleteBtn).dataset.id;

    try {
      const res = await fetch(`/cashcue/api/getBrokers.php?id=${id}`);

      if (!res.ok) {
        throw new Error(`HTTP error ${res.status}`);
      }

      const broker = await res.json();

      if (!broker || !broker.id) {
        return showAlert('warning', 'Broker account with this ID not found.');
      }

      if (editBtn) {
        openEditModal(broker);
      }

      if (deleteBtn) {
        deleteBroker(broker);   
      }

    } catch (err) {
      console.error('Error fetching broker:', err);
      showAlert('danger', 'Failed to load broker.');
    }
  });


  // ------------------------------------------------
  // Form submission handler for adding/editing brokers
  // Validates the comment field, constructs form data, and sends it to the appropriate API endpoint based on the mode (add or edit).
  // Handles the response and updates the UI accordingly.
  // ------------------------------------------------
  form.addEventListener('submit', async e => {
    e.preventDefault();

    if (!commentInput.value.trim()) return showAlert('warning', 'Comment is required for audit purposes.');
    
    const formData = new FormData(form);
    formData.set('has_cash_account', hasCashAccountInput.checked ? 1 : 0);
    formData.set('initial_deposit', initialDepositInput.value || 0);

    if (editMode) formData.set('id', currentBrokerId);

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

  // ------------------------------------------------
  // Initial setup: attach event listeners and initialize the table
  // Sets up the click listener for the add button, change listener for the cash account checkbox, initializes the CashCueTable, and loads the initial data.
  // ------------------------------------------------
  addBtn.addEventListener('click', openCreateModal);
  hasCashAccountInput.addEventListener('change', toggleInitialDeposit);

  initBrokersTable();
  loadBrokers();

});

