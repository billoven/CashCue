// ============================================================
// Cash Account Overview
// assets/js/cash.js
// ============================================================
//
// Responsibilities:
//  - Display cash summary (balance, inflows, outflows)
//  - Display cash transactions table (read-only)
//  - Support date range filtering
//  - React to broker account changes
//
// Architecture:
//  - Uses CashCueAppContext for broker resolution
//  - Uses CashCueTable for sortable, consistent tables
//
// API endpoints:
//  - getCashSummary.php
//  - getCashTransactions.php
// ============================================================

console.log("cash.js loaded");

// ------------------------------------------------------------
// State
// ------------------------------------------------------------

let cashTable = null;

// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------

/**
 * Format number using French locale with 2 decimals
 */
function formatAmount(value) {
  const n = Number(value);
  if (!Number.isFinite(n)) return "—";
  return n.toLocaleString("fr-FR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

/**
 * Compute date range object from selector value
 * Returns object suitable for URLSearchParams
 */
function computeDateRange(value) {
  if (value === "all") return {};

  const days = parseInt(value, 10);
  if (!Number.isFinite(days)) return {};

  const to = new Date();
  const from = new Date();
  from.setDate(to.getDate() - days);

  return {
    from: from.toISOString().slice(0, 10),
    to: to.toISOString().slice(0, 10)
  };
}

// ------------------------------------------------------------
// CashCueTable initialization
// ------------------------------------------------------------

function initCashTable() {
cashTable = new CashCueTable({
  containerId: "cashTableContainer",
  emptyMessage: "No cash movements",
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
        key: "amount",
        label: "Amount (€)",
        sortable: true,
        align: "end",
        render: row => parseFloat(row.amount).toFixed(4)
      },
      {
        key: "reference_id",
        label: "Reference",
        sortable: false,
        render: row => row.reference_id ?? "—"
      },
      {
        key: "comment",
        label: "Comment",
        sortable: false,
        render: row => row.comment ?? ""
      }
    ]
  });
}

// ------------------------------------------------------------
// API loaders
// ------------------------------------------------------------

/**
 * Load cash summary (balance, inflows, outflows)
 */
async function loadCashSummary(brokerAccountId) {
  const res = await fetch(
    `/cashcue/api/getCashSummary.php?broker_account_id=${brokerAccountId}`
  );
  if (!res.ok) {
    throw new Error("Failed to load cash summary");
  }

  const json = await res.json();

  document.getElementById("cashCurrentBalance").textContent =
    formatAmount(json.current_balance);
  document.getElementById("cashInflows").textContent =
    formatAmount(json.total_inflows);
  document.getElementById("cashOutflows").textContent =
    formatAmount(json.total_outflows);
}

/**
 * Load cash transactions into CashCueTable
 */
async function loadCashTransactions(brokerAccountId, range) {
  const params = new URLSearchParams({
    broker_account_id: brokerAccountId,
    ...range
  });

  const res = await fetch(
    `/cashcue/api/getCashTransactions.php?${params.toString()}`
  );
  if (!res.ok) {
    throw new Error("Failed to load cash transactions");
  }

  const json = await res.json();
  const rows = json.data ?? [];

  cashTable.setData(rows);
}

// ------------------------------------------------------------
// Reload logic (single entry point)
// ------------------------------------------------------------

/**
 * Reload all cash data for current broker
 */
async function reloadCash() {
  console.log("cash.js reloadCash() called");

  const brokerAccountId =
    await CashCueAppContext.waitForBrokerAccount();

  console.log("cash.js brokerAccountId =", brokerAccountId);

  if (!brokerAccountId) {
    cashTable.setData([]);
    return;
  }

  const rangeSelect = document.getElementById("cashRange");
  const range = computeDateRange(rangeSelect?.value ?? "all");

  try {
    await loadCashSummary(brokerAccountId);
    await loadCashTransactions(brokerAccountId, range);
  } catch (err) {
    console.error("cash.js reload error:", err);
    cashTable.setData([]);
  }
}

// ------------------------------------------------------------
// Events
// ------------------------------------------------------------

// Broker change (emitted by header)
document.addEventListener("brokerAccountChanged", () => {
  console.log("cash.js detected broker change → reload");
  reloadCash();
});

// Date range change
document.getElementById("cashRange")
  ?.addEventListener("change", reloadCash);

// ------------------------------------------------------------
// Init
// ------------------------------------------------------------

document.addEventListener("DOMContentLoaded", async () => {

  // Init table
  initCashTable();

  // Initial load
  await CashCueAppContext.waitForBrokerAccount();
  reloadCash();
});
