// ============================================================
// Cash Movements Management — Admin Controller
// assets/js/manage_cash.js
// ============================================================

console.log("manage_cash.js loaded");

let cashDatePicker = null;
let cashModal = null;
let currentMode = "add"; // "add" | "edit"

// ------------------------------------------------------------
// Business rules
// ------------------------------------------------------------

// All possible cash movement types
const ALL_TYPES = ["BUY", "SELL", "DIVIDEND", "DEPOSIT", "ADJUSTMENT", "WITHDRAWAL", "FEES"];

// Types that can be manually edited
const MANUAL_TYPES = ["DEPOSIT", "ADJUSTMENT", "WITHDRAWAL", "FEES"];

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

// ------------------------------------------------------------
// Core data loading logic
// ------------------------------------------------------------

/**
 * Reload all cash movements table for current broker
 */
async function reloadPageData() {
  console.log("CashCue: reloading cash movements");

  // Wait until the brokerAccountId is available
  const brokerAccountId = await CashCueAppContext.waitForBrokerAccount();
  console.log("manage_cash.js: current brokerAccountId =", brokerAccountId);

  const tbody = document.getElementById("cashAdminBody");
  if (!tbody) return;

  tbody.innerHTML = "";

  try {
    const res = await fetch(`/cashcue/api/getCashTransactions.php?broker_account_id=${brokerAccountId}`);
    const json = await res.json();
    const rows = json.data ?? [];

    if (!rows.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" class="text-center text-muted">
            No cash movements
          </td>
        </tr>
      `;
      return;
    }
    console.log('CashCue: loaded cash movements', rows);
    // Populate table
    rows.forEach(r => {
      const type = String(r.type).toUpperCase();
      const isLocked = LOCKED_TYPES.includes(type);

      const actions = isLocked
        ? `<span class="text-muted fst-italic">Computed</span>`
        : `
          <button class="btn btn-sm btn-outline-primary me-1"
                  onclick="editCash(${r.id})">
              <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger"
                  onclick="deleteCash(${r.id})">
              <i class="bi bi-trash"></i>
          </button>
        `;

      tbody.insertAdjacentHTML("beforeend", `
        <tr>
          <td>${r.date}</td>
          <td>${r.type}</td>
          <td class="text-end">${fmtAmount(r.amount)}</td>
          <td>${r.reference_id ?? "—"}</td>
          <td>${r.comment ?? ""}</td>
          <td class="text-center">${actions}</td>
        </tr>
      `);
    });

  } catch (err) {
    console.error("CashCue: failed to load cash movements", err);
    tbody.innerHTML = `
      <tr>
        <td colspan="6" class="text-center text-danger">
          Error loading cash movements
        </td>
      </tr>
    `;
  }
}

// ------------------------------------------------------------
// Modals (Add / Edit Cash Movements)
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
    const res = await fetch(`/cashcue/api/getCashTransactions.php?broker_account_id=all`);
    const json = await res.json();
    const rows = json.data ?? [];
    const row = rows.find(r => r.id == id);
    console.log("CashCue: loaded cash movement", row);
    if (!row) {
      alert("Cash movement not found.");
      return;
    }

    const type = String(row.type).toUpperCase();

    document.getElementById("cashModalTitle").innerText = "Edit Cash Movement";
    document.getElementById("cash_id").value = row.id;

    // Truncate seconds to minute precision
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
// Save / Delete API calls
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
    if (!json.success) throw new Error(json.error || "Unknown API error");

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
    if (!json.success) throw new Error(json.error || "Delete failed");

    reloadPageData();

  } catch (err) {
    console.error("CashCue: delete cash failed", err);
    alert(`Delete failed: ${err.message}`);
  }
}

// ------------------------------------------------------------
// Init — DOMContentLoaded
// ------------------------------------------------------------

document.addEventListener("DOMContentLoaded", async () => {

  // Init Flatpickr for date selection
  cashDatePicker = flatpickr("#cash_date", {
    enableTime: true,
    time_24hr: true,
    enableSeconds: false,
    altInput: true,
    altFormat: "d/m/Y H:i",
    dateFormat: "Y-m-d H:i",
    allowInput: true
  });

  // Init Bootstrap Modal
  cashModal = new bootstrap.Modal(document.getElementById("cashModal"));

  // Button events
  document.getElementById("btnAddCash")?.addEventListener("click", openAddCashModal);
  document.getElementById("cashSaveBtn")?.addEventListener("click", saveCash);

  // ------------------------------------------------------------
  // Reload page data after broker resolved
  // ------------------------------------------------------------

  const brokerAccountId = await CashCueAppContext.waitForBrokerAccount();
  console.log("manage_cash.js: initial load with brokerAccountId =", brokerAccountId);

  reloadPageData();

  // Listen to broker changes
  document.addEventListener("brokerAccountChanged", () => {
    console.log("manage_cash.js: broker changed → reload data");
    reloadPageData();
  });
});
