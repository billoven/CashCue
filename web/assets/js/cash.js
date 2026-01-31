// ============================================================
// Cash Account Overview
// assets/js/cash.js
// ============================================================

console.log("cash.js loaded");

// ------------------------------------------------------------
// HELPERS
// ------------------------------------------------------------

/**
 * Format number to French locale with 2 decimals
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
 * Returns object usable as URLSearchParams
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
// API LOADERS
// ------------------------------------------------------------

/**
 * Load cash summary (balance, inflows, outflows)
 */
async function loadCashSummary(brokerAccountId) {
  
  const res = await fetch(`/cashcue/api/getCashSummary.php?broker_account_id=${brokerAccountId}`);
  if (!res.ok) throw new Error("Failed to load cash summary");

  const json = await res.json();

  document.getElementById("cashCurrentBalance").textContent =
    formatAmount(json.current_balance);
  document.getElementById("cashInflows").textContent =
    formatAmount(json.total_inflows);
  document.getElementById("cashOutflows").textContent =
    formatAmount(json.total_outflows);
}

/**
 * Load cash transactions table
 */
async function loadCashTransactions(brokerAccountId, range) {
  
  const tbody = document.getElementById("cashTransactionsBody");
  if (!tbody) return;

  // Show temporary loading row
  tbody.innerHTML = `
    <tr>
      <td colspan="5" class="text-center text-muted">Loading…</td>
    </tr>
  `;

  const params = new URLSearchParams({
    broker_account_id: brokerAccountId,
    ...range
  });

  const res = await fetch(`/cashcue/api/getCashTransactions.php?${params.toString()}`);
  if (!res.ok) throw new Error("Failed to load cash transactions");

  const json = await res.json();
  const rows = json.data ?? [];

  if (!rows.length) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="text-center text-muted">
          No cash movements
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = "";

  // Populate table rows
  rows.forEach(row => {
    const amount = Number(row.amount);
    const cls = amount >= 0 ? "text-success" : "text-danger";

    tbody.insertAdjacentHTML("beforeend", `
      <tr>
        <td>${row.date}</td>
        <td>${row.type}</td>
        <td class="text-end fw-bold ${cls}">
          ${formatAmount(amount)}
        </td>
        <td>${row.reference_id ?? "—"}</td>
        <td>${row.comment ?? ""}</td>
      </tr>
    `);
  });
}

// ------------------------------------------------------------
// RELOAD LOGIC (single entry point)
// ------------------------------------------------------------

/**
 * Reload all cash data for current broker
 * Uses CashCueAppContext to retrieve brokerAccountId
 */
async function reloadCash() {
  console.log("cash.js Function reloadCash called");

  // Get brokerAccountId from appContext.js
  const brokerAccountId = await CashCueAppContext.waitForBrokerAccount();
  console.log("cash.js reloadCash() brokerAccountId:", brokerAccountId);

  if (!brokerAccountId) {
    const tbody = document.getElementById("cashTransactionsBody");
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="text-center text-muted">
            Select a broker account
          </td>
        </tr>
      `;
    }
    return;
  }

  // Get date range from selector
  const rangeSelect = document.getElementById("cashRange");
  const range = computeDateRange(rangeSelect?.value ?? "all");

  try {
    await loadCashSummary(brokerAccountId);
    await loadCashTransactions(brokerAccountId, range);
  } catch (err) {
    console.error("cash.js reload error:", err);
  }
}

// ------------------------------------------------------------
// EVENTS
// ------------------------------------------------------------

// Listen to broker changes globally (header triggers event)
document.addEventListener("brokerAccountChanged", () => {
  console.log("cash.js detected broker change → reloading data");
  reloadCash();
});

// Listen to range selector change
document.getElementById("cashRange")?.addEventListener("change", reloadCash);

// ------------------------------------------------------------
// INITIAL LOAD
// ------------------------------------------------------------

// Reload immediately on page load, using appContext
reloadCash();
