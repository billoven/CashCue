
// ============================================================
// Cash Account Overview
// assets/js/cash.js
// ============================================================

document.addEventListener("DOMContentLoaded", () => {

  console.log("cash.js loaded");

  const rangeSelect = document.getElementById("cashRange");
  const tbody       = document.getElementById("cashTransactionsBody");

  const elCurrent = document.getElementById("cashCurrentBalance");
  const elIn      = document.getElementById("cashInflows");
  const elOut     = document.getElementById("cashOutflows");

  if (!rangeSelect || !tbody) {
    console.warn("cash.js: required DOM elements not found");
    return;
  }

  // ------------------------------------------------------------
  // Helpers
  // ------------------------------------------------------------

  function formatAmount(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return "—";
    return n.toLocaleString("fr-FR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function computeDateRange(value) {
    if (value === "all") return {};

    const days = parseInt(value, 10);
    if (!Number.isFinite(days)) return {};

    const to   = new Date();
    const from = new Date();
    from.setDate(to.getDate() - days);

    return {
      from: from.toISOString().slice(0, 10),
      to:   to.toISOString().slice(0, 10)
    };
  }

  function getActiveBrokerId() {
    if (typeof window.getActiveBrokerAccountId === "function") {
      return window.getActiveBrokerAccountId(); // number | null
    }
    return null;
  }

  // ------------------------------------------------------------
  // API calls
  // ------------------------------------------------------------

  async function loadCashSummary(brokerAccountId) {
    const res = await fetch(
      `/cashcue/api/getCashSummary.php?broker_id=${brokerAccountId}`
    );

    if (!res.ok) {
      throw new Error("Failed to load cash summary");
    }

    const json = await res.json();

    elCurrent.textContent = formatAmount(json.current_balance);
    elIn.textContent      = formatAmount(json.total_inflows);
    elOut.textContent     = formatAmount(json.total_outflows);
  }

  async function loadCashTransactions(brokerAccountId, range) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="text-center text-muted">Loading…</td>
      </tr>
    `;

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

    rows.forEach(row => {
      const amount = Number(row.amount);
      const cls    = amount >= 0 ? "text-success" : "text-danger";

      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${row.date}</td>
        <td>${row.type}</td>
        <td class="text-end fw-bold ${cls}">
          ${formatAmount(amount)}
        </td>
        <td>${row.reference_id ?? "—"}</td>
        <td>${row.comment ?? ""}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  // ------------------------------------------------------------
  // Reload logic
  // ------------------------------------------------------------

  async function reloadCash() {
    const brokerAccountId = getActiveBrokerId();

    if (!brokerAccountId) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="text-center text-muted">
            Select a broker account to view cash movements
          </td>
        </tr>
      `;
      return;
    }

    const range = computeDateRange(rangeSelect.value);

    try {
      await loadCashSummary(brokerAccountId);
      await loadCashTransactions(brokerAccountId, range);
    } catch (err) {
      console.error("cash.js reload error:", err);
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="text-center text-danger">
            Error loading cash data
          </td>
        </tr>
      `;
    }
  }

  // ------------------------------------------------------------
  // Events
  // ------------------------------------------------------------
  // Broker change listener — enregistré immédiatement
  document.addEventListener("brokerChanged", (e) => {
    console.log("cash.js: brokerChanged →", e.detail.brokerId);
    reloadCash();
  });

  // Range select change
  rangeSelect?.addEventListener("change", reloadCash);

  // Initial load — après que le broker selector soit prêt
  window.waitForBrokerSelector?.().then(() => {
    reloadCash();
  });

});
