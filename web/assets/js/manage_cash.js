// ============================================================
// Cash Movements Management — Admin Controller
// assets/js/manage_cash.js
// ============================================================
//
// Responsibilities:
//  - Load cash movements for active broker account
//  - Render cash table using CashCueTable abstraction
//  - Handle add / edit / delete cash movements
//  - Enforce business rules on editable vs computed entries
//
// Dependencies:
//  - CashCueAppContext (broker resolution)
//  - CashCueTable (generic sortable table)
//  - Bootstrap Modal
//  - Flatpickr
//
// API endpoints:
//  - getCashTransactions.php
//  - addCashTransaction.php
//  - updateCashTransaction.php
//  - deleteCashTransaction.php
// ============================================================

console.log("manage_cash.js loaded");

// ------------------------------------------------------------
// State
// ------------------------------------------------------------

let cashDatePicker = null;
let cashModal = null;
let cashTable = null;
let currentMode = "add"; // "add" | "edit"

// ------------------------------------------------------------
// Business rules
// ------------------------------------------------------------

// All possible cash movement types
const ALL_TYPES = [
  "BUY",
  "SELL",
  "DIVIDEND",
  "DEPOSIT",
  "ADJUSTMENT",
  "WITHDRAWAL",
  "FEES"
];

// Types that can be manually edited
const MANUAL_TYPES = [
  "DEPOSIT",
  "ADJUSTMENT",
  "WITHDRAWAL",
  "FEES"
];

// Computed types must be locked in UI
const LOCKED_TYPES = ALL_TYPES.filter(t => !MANUAL_TYPES.includes(t));

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------

/**
 * Format numeric amount with 2 decimals
 */
function fmtAmount(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n.toFixed(2) : "—";
}

/**
 * Render actions column depending on cash type
 */
function renderActions(row) {
  const type = String(row.type).toUpperCase();
  const isLocked = LOCKED_TYPES.includes(type);

  if (isLocked) {
    return `<span class="text-muted fst-italic">Computed</span>`;
  }

  return `
    <button class="btn btn-sm btn-outline-primary me-1"
            onclick="editCash(${row.id})"
            title="Edit cash movement">
      <i class="bi bi-pencil"></i>
    </button>
    <button class="btn btn-sm btn-outline-danger"
            onclick="deleteCash(${row.id})"
            title="Delete cash movement">
      <i class="bi bi-trash"></i>
    </button>
  `;
}

// ------------------------------------------------------------
// Table initialization
// ------------------------------------------------------------

function initCashTable() {
  cashTable = new CashCueTable({
    emptyMessage: "No cash movements",
    containerId: "cashAdminTableContainer",
    columns: [
      {
        key: "date",
        label: "Date",
        sortable: true
      },
      {
        key: "type",
        label: "Type",
        sortable: true
      },
      {
        key: "amount",
        label: "Amount (€)",
        sortable: true,
        align: "end",
        type: "number",
        render: row => parseFloat(row.amount).toFixed(4)
      },
      {
        key: "reference_id",
        label: "Order ID",
        sortable: false,
        render: row => row.reference_id ?? "—"
      },
      {
        key: "comment",
        label: "Comment",
        sortable: false,
        render: row => row.comment ?? ""
      },
      {
        key: "actions",
        label: "Actions",
        sortable: false,
        align: "center",
        html: true,
        render: renderActions
      }
    ]
  });
}

// ------------------------------------------------------------
// Data loading
// ------------------------------------------------------------

async function reloadPageData() {
  console.log("CashCue: reloading cash movements");

  const brokerAccountId = await CashCueAppContext.waitForBrokerAccount();
  console.log("manage_cash.js: current brokerAccountId =", brokerAccountId);

  try {
    const res = await fetch(
      `/cashcue/api/getCashTransactions.php?broker_account_id=${brokerAccountId}`
    );
    const json = await res.json();
    const rows = json.data ?? [];

    cashTable.setData(rows);

  } catch (err) {
    console.error("CashCue: failed to load cash movements", err);
    cashTable.setData([]);
  }
}

// ------------------------------------------------------------
// Modals (Add / Edit)
// ------------------------------------------------------------

function openAddCashModal() {
  currentMode = "add";

  document.getElementById("cashModalTitle").innerText = "Add Cash Movement";
  document.getElementById("cash_id").value = "";
  document.getElementById("cash_amount").value = "";
  document.getElementById("cash_comment").value = "";

  const typeSelect = document.getElementById("cash_type");
  typeSelect.disabled = false;
  typeSelect.value = "DEPOSIT";

  cashDatePicker.setDate(new Date(), true);
  cashModal.show();
}

async function editCash(id) {
  currentMode = "edit";
  console.log("CashCue: editing cash movement", id);

  try {
    const res = await fetch(
      `/cashcue/api/getCashTransactions.php?broker_account_id=all`
    );
    const json = await res.json();
    const rows = json.data ?? [];
    const row = rows.find(r => r.id == id);

    if (!row) {
      alert("Cash movement not found.");
      return;
    }

    const type = String(row.type).toUpperCase();

    document.getElementById("cashModalTitle").innerText = "Edit Cash Movement";
    document.getElementById("cash_id").value = row.id;

    cashDatePicker.setDate(row.date.substring(0, 16), true);

    const typeSelect = document.getElementById("cash_type");
    typeSelect.value = type;
    typeSelect.disabled = LOCKED_TYPES.includes(type);

    document.getElementById("cash_amount").value = row.amount;
    document.getElementById("cash_comment").value = row.comment ?? "";

    cashModal.show();

  } catch (err) {
    console.error("CashCue: failed to load cash movement", err);
    alert("Error loading cash movement.");
  }
}

// ------------------------------------------------------------
// Save / Delete
// ------------------------------------------------------------

async function saveCash() {
  const payload = {
    date: document.getElementById("cash_date").value,
    type: document.getElementById("cash_type").value,
    amount: document.getElementById("cash_amount").value,
    comment: document.getElementById("cash_comment").value
  };

  let url = "/cashcue/api/addCashTransaction.php";

  if (currentMode === "edit") {
    payload.id = document.getElementById("cash_id").value;
    url = "/cashcue/api/updateCashTransaction.php";
  }

  try {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    const json = await res.json();
    if (!json.success) {
      throw new Error(json.error || "Unknown API error");
    }

    cashModal.hide();
    reloadPageData();

  } catch (err) {
    console.error("CashCue: save cash failed", err);
    alert(`Cash save failed: ${err.message}`);
  }
}

async function deleteCash(id) {
  if (!confirm("Delete this cash movement?")) return;

  try {
    const res = await fetch("/cashcue/api/deleteCashTransaction.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id })
    });

    const json = await res.json();
    if (!json.success) {
      throw new Error(json.error || "Delete failed");
    }

    reloadPageData();

  } catch (err) {
    console.error("CashCue: delete cash failed", err);
    alert(`Delete failed: ${err.message}`);
  }
}

// ------------------------------------------------------------
// Init
// ------------------------------------------------------------

document.addEventListener("DOMContentLoaded", async () => {

  // Flatpickr
  cashDatePicker = flatpickr("#cash_date", {
    enableTime: true,
    time_24hr: true,
    enableSeconds: false,
    altInput: true,
    altFormat: "d/m/Y H:i",
    dateFormat: "Y-m-d H:i",
    allowInput: true
  });

  // Modal
  cashModal = new bootstrap.Modal(
    document.getElementById("cashModal")
  );

  // Buttons
  document.getElementById("btnAddCash")
    ?.addEventListener("click", openAddCashModal);

  document.getElementById("cashSaveBtn")
    ?.addEventListener("click", saveCash);

  // Table
  initCashTable();

  // Initial load
  await CashCueAppContext.waitForBrokerAccount();
  reloadPageData();

  // Broker change
  document.addEventListener("brokerAccountChanged", () => {
    console.log("manage_cash.js: broker changed → reload data");
    reloadPageData();
  });
});

