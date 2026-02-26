/**
 * manage_brokers.js — CashCueTable version (SECURED CLOSE VERSION)
 * ----------------------------------------------------------------
 * Responsibilities:
 * - Load and display brokers table using CashCueTable
 * - Handle add/edit brokers (modal-based)
 * - Securely CLOSE brokers (no physical delete)
 * - Manage Cash Account and Comment fields
 * - Preserve validated business logic
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
     FORM UTILITIES
  ============================================================ */

  function resetForm() {
    form.reset();
    brokerIdInput.value = '';
    currentBrokerId = null;
    hasCashAccountInput.checked = false;
    initialDepositInput.value = '0.00';
    initialDepositContainer.style.display = 'none';
    commentInput.value = '';
  }

  function toggleInitialDeposit() {
    if (hasCashAccountInput.checked && !editMode) {
      initialDepositContainer.style.display = 'block';
    } else {
      initialDepositContainer.style.display = 'none';
      initialDepositInput.value = '0.00';
    }
  }

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

  function openEditModal(broker) {
    if (broker.status === 'CLOSED') {
      return showAlert('warning', 'Closed broker accounts cannot be edited.');
    }

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

    createdAtLabel.textContent = `Created at: ${broker.created_at}`;
    createdAtLabel.style.display = 'inline';

    cashAccountSection.style.display = 'none';

    modal.show();
  }

  /* ============================================================
     TABLE INITIALIZATION
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
          key: "status",
          label: "Status",
          sortable: true,
          align: "center",
          render: row =>
            row.status === 'CLOSED'
              ? '<span class="badge bg-secondary">CLOSED</span>'
              : '<span class="badge bg-success">ACTIVE</span>'
        },

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
          render: row => {

            if (row.status === 'CLOSED') {
              return `
                <button class="btn btn-sm btn-outline-secondary me-1" disabled>
                  <i class="bi bi-pencil"></i>
                </button>
              `;
            }

            return `
              <button class="btn btn-sm btn-outline-primary me-1 editBrokerBtn" data-id="${row.id}">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger closeBrokerBtn" data-id="${row.id}">
                <i class="bi bi-x-octagon"></i>
              </button>
            `;
          }
        }
      ]
    });
  }

  /* ============================================================
     DATA LOADING
  ============================================================ */

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

  /* ============================================================
     SECURE CLOSE FLOW
  ============================================================ */

  async function closeBroker(broker) {

    if (!broker || !broker.id) {
      return showAlert('warning', 'Invalid broker data.');
    }

    try {
      const checkRes = await fetch('/cashcue/api/checkBrokerClosable.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(broker.id)}`
      });

      const checkResult = await checkRes.json();

      if (!checkResult.success) {
        return showAlert('danger', checkResult.message);
      }

      if (!checkResult.closable) {

        let message = `
Broker cannot be closed.

Name: ${broker.name}
Account number: ${broker.account_number}
`;

        if (parseFloat(checkResult.cash_balance) !== 0) {
          message += `\nRemaining cash balance: ${parseFloat(checkResult.cash_balance).toFixed(2)} ${broker.currency}`;
        }

        if (checkResult.open_positions > 0) {
          message += `\nOpen positions remaining: ${checkResult.open_positions}`;
        }

        return alert(message);
      }

      if (!confirm(`Close broker "${broker.name}"?\nThis action is irreversible.`)) return;

      const closeRes = await fetch('/cashcue/api/closeBroker.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${encodeURIComponent(broker.id)}`
      });

      const result = await closeRes.json();

      if (result.success) {
        showAlert('success', result.message || 'Broker closed.');
        await loadBrokers();
      } else {
        showAlert('danger', result.message);
      }

    } catch (err) {
      console.error('Error closing broker:', err);
      showAlert('danger', 'Error closing broker.');
    }
  }

  /* ============================================================
     EVENT DELEGATION
  ============================================================ */

  document.addEventListener('click', async e => {

    const editBtn  = e.target.closest('.editBrokerBtn');
    const closeBtn = e.target.closest('.closeBrokerBtn');

    if (!editBtn && !closeBtn) return;

    const id = (editBtn || closeBtn).dataset.id;

    try {
      const res = await fetch(`/cashcue/api/getBrokers.php?id=${id}`);
      const broker = await res.json();

      if (!broker || !broker.id) {
        return showAlert('warning', 'Broker not found.');
      }

      if (editBtn) openEditModal(broker);
      if (closeBtn) closeBroker(broker);

    } catch (err) {
      console.error('Error fetching broker:', err);
      showAlert('danger', 'Failed to load broker.');
    }
  });

  /* ============================================================
     FORM SUBMISSION
  ============================================================ */

  form.addEventListener('submit', async e => {
    e.preventDefault();

    if (!commentInput.value.trim())
      return showAlert('warning', 'Comment is required for audit purposes.');

    const formData = new FormData(form);
    formData.set('has_cash_account', hasCashAccountInput.checked ? 1 : 0);
    formData.set('initial_deposit', initialDepositInput.value || 0);

    if (editMode) formData.set('id', currentBrokerId);

    const url = editMode
      ? '/cashcue/api/updateBroker.php'
      : '/cashcue/api/addBroker.php';

    try {
      const res = await fetch(url, { method: 'POST', body: formData });
      const result = await res.json();

      if (result.success) {
        modal.hide();
        showAlert('success', result.message || (editMode ? 'Broker updated.' : 'Broker added.'));
        await loadBrokers();
      } else {
        showAlert('danger', result.message);
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

