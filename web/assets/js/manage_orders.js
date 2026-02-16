/**
 * manage_orders.js
 * ----------------
 * Orders Management module for CashCue Web Application
 *
 * Responsibilities:
 *   - Display all orders for the selected broker account
 *   - Allow adding new orders
 *   - Allow cancelling existing orders
 *   - Allow controlled order updates via a dedicated Update modal
 *
 * Update logic (IMPORTANT):
 * ------------------------------------------------------------------
 * This module supports TWO update modes through updateOrderModal:
 *
 * 1) Comment-only update
 *    - Updates only the comment field
 *    - NO cash recalculation
 *    - Allowed for any order (ACTIVE or CANCELLED)
 *    - No date restriction
 *
 * 2) Financial correction (J0 only)
 *    - Allowed ONLY if:
 *        • order status = ACTIVE
 *        • trade_date = today
 *    - Allowed fields:
 *        • quantity
 *        • price
 *        • fees
 *        • comment (mandatory)
 *    - Triggers cash recalculation through updateOrder.php
 *
 * Safety principles:
 *   - Financial history cannot be modified
 *   - Cancelled orders cannot be financially altered
 *   - Cash consistency is preserved via backend transactions
 *
 * Dependencies:
 *   - Bootstrap 5
 *   - flatpickr
 *   - CashCueTable.js
 *   - appContext.js
 *   - updateOrderModal.js
 *
 * APIs used:
 *   - GET  /cashcue/api/getOrders.php
 *   - POST /cashcue/api/addOrder.php
 *   - POST /cashcue/api/updateOrder.php
 *   - GET  /cashcue/api/cancelOrder.php
 *
 * Author: Pierre
 * Last Modified: 2026-02-09
 */


console.log("manage_orders.js loaded – refactored for CashCueTable & CashCueAppContext");

function escapeHtml(str) {
  if (str == null) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

document.addEventListener("DOMContentLoaded", async () => {

  // ------------------------------------------------------------
  // DOM ELEMENTS
  // ------------------------------------------------------------
  const modalEl = document.getElementById("orderModal");
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById("orderForm");

  const btnAdd = document.getElementById("btnAddOrder");
  const btnSave = document.getElementById("saveOrder");
  const searchInput = document.getElementById("searchOrder");

  let tradeDatePicker = null;
  let ordersTable = null; // ✅ Persistent CashCueTable instance
  let ordersData = [];

  // ============================================================
  // FLATPICKR
  // ============================================================
  modalEl.addEventListener("shown.bs.modal", () => {
    if (!tradeDatePicker) {
      tradeDatePicker = flatpickr("#trade_date", {
        enableTime: true,
        time_24hr: true,
        enableSeconds: true,
        altInput: true,
        altFormat: "d/m/Y H:i:S",
        dateFormat: "Y-m-d H:i:S",
        allowInput: true,
        appendTo: modalEl
      });
      const closeBtn = document.getElementById("closeCalendar");
      closeBtn?.addEventListener("click", () => tradeDatePicker.close());
    }
  });

  // ============================================================
  // LOAD INSTRUMENTS
  // ============================================================
  async function loadInstruments() {
    try {
      const resp = await fetch("/cashcue/api/getInstruments.php");
      const json = await resp.json();
      if (json.status !== "success") throw new Error(json.message);
      const instruments = json.data || [];

      const select = document.getElementById("instrument_id");
      if (!select) return;

      select.innerHTML = '<option value="">Select instrument...</option>';
      instruments.filter(i => i.status === "ACTIVE")
                 .forEach(i => {
                   const opt = document.createElement("option");
                   opt.value = i.id;
                   opt.textContent = `${i.symbol} - ${i.label}`;
                   select.appendChild(opt);
                 });
    } catch (err) {
      console.error("Error loading instruments:", err);
    }
  }

  // ============================================================
  // INIT ORDERS TABLE (once)
  // ============================================================
  function initOrdersTable() {
    ordersTable = new CashCueTable({
      containerId: "ordersTableContainer",
      searchInput: "#searchOrder",
      searchFields: ["symbol", "label"],   // <-- added for live search on these columns
      pagination: { enabled: true, pageSize: 5 },
      columns: [
        { key: "id", label: "ID", sortable: true, type: "number"},
        { key: "symbol", label: "Symbol", sortable: true, type: "string" },
        { key: "label", label: "Label", sortable: true, type: "string" },
        { key: "order_type", label: "Type", sortable: true, type: "string" },

        { key: "quantity", label: "Quantity", sortable: true, type: "number" },

        {
          key: "price",
          label: "Price (€)",
          sortable: true,
          type: "number",
          render: row => parseFloat(row.price).toFixed(4)
        },
        {
          key: "fees",
          label: "Fees (€)",
          sortable: true,
          type: "number",
          render: row => parseFloat(row.fees || 0).toFixed(2)
        },
        {
          key: "total_value",
          label: "Total (€)",
          sortable: true,
          type: "number",
          render: row => parseFloat(row.total || 0).toFixed(2)
        },

        {
          key: "trade_date",
          label: "Trade Date",
          sortable: true,
          type: "date"
        },

        {
          key: "status",
          label: "Status",
          sortable: false,
          html: true,
          render: row =>
            row.status === "ACTIVE"
              ? '<span class="badge bg-success">ACTIVE</span>'
              : '<span class="badge bg-secondary">CANCELLED</span>'
        },

        {
          key: "cancelled_at",
          label: "Cancelled at",
          sortable: true,
          type: "date"
        },
        {
          key: "comment",
          label: "Comment",
          sortable: false,
          align: "start",
          render: row => {
            if (!row.comment || row.comment.trim() === "") {
              return '<span class="text-muted">—</span>';
            }
            return escapeHtml(row.comment);
          }
        },
        {
          key: "settled", 
          label: "Settled", 
          sortable: false, 
          type: "boolean",
          render: row => row.settled ? '<i class="bi bi-check-lg text-success"></i>' : ''
        },
        {
          key: "actions",
          label: "Actions",
          sortable: false,
          html: true,
          render: row => `
            <span class="me-2 edit-action text-primary"
                  role="button"
                  title="Edit / annotate order"
                  data-id="${row.id}">
              <i class="bi bi-pencil-square"></i>
            </span>

            <span class="cancel-action ${row.status === 'CANCELLED' ? 'is-disabled' : 'is-active'}"
                  data-id="${row.id}" role="button"
                  title="${row.status === 'CANCELLED'
                    ? 'Order already cancelled'
                    : 'Cancel order'}">
              <i class="bi bi-x-circle-fill"></i>
            </span>
          `
        }
      ]

    });
  }

  // ============================================================
  // LOAD ORDERS DATA
  // ============================================================
  async function loadOrders() {
    try {
      const broker_account_id = window.CashCueAppContext.getBrokerAccountId();
      if (!broker_account_id) return;

      const resp = await fetch(`/cashcue/api/getOrders.php?broker_account_id=${broker_account_id}`);
      const json = await resp.json();
      if (json.status !== "success") throw new Error(json.message);
      ordersData = json.data || [];

      console.log("▶ Orders data:", ordersData);

      if (!ordersTable) initOrdersTable();
      ordersTable.setData(ordersData); // ✅ update table rows
    } catch (err) {
      console.error("Error loading orders:", err);
    }
  }

  // ============================================================
  // EDIT / UPDATE ORDER (delegated)
  // ============================================================
  document.addEventListener("click", e => {
    const editEl = e.target.closest(".edit-action");
    if (!editEl) return;

    const orderId = parseInt(editEl.dataset.id, 10);
    const order = ordersData.find(o => o.id === orderId);
    if (!order) return;

    console.log("Edit action clicked for order:", order);
    // Delegate to updateOrderModal.js
    CashCue.openUpdateOrderModal(order);
  });

  // ============================================================
  // ADD / SAVE ORDER
  // ============================================================
  btnAdd?.addEventListener("click", e => {
    e.preventDefault();
    form.reset();
    document.getElementById("order_id").value = "";
    tradeDatePicker?.clear();
    modalEl.querySelector(".modal-title").textContent = "➕ Add Order";
    modal.show();
    setTimeout(() => {
      form.querySelector("input:not([type=hidden]), select, textarea")?.focus();
    }, 150);
  });

// Handle form submission for adding new order
btnSave?.addEventListener("click", async e => {
  e.preventDefault();
  try {
    const broker_account_id = window.CashCueAppContext.getBrokerAccountId();
    if (!broker_account_id) return alert("Please select a broker account.");

    const payload = {
      broker_account_id,
      instrument_id: document.getElementById("instrument_id").value,
      order_type: document.getElementById("order_type").value,
      quantity: document.getElementById("quantity").value,
      price: document.getElementById("price").value,
      fees: document.getElementById("fees").value || 0,
      trade_date: document.getElementById("trade_date").value,
      comment: document.getElementById("comment")?.value.trim() || "",
      settled: document.getElementById("settled")?.checked ? 1 : 0
    };

    for (const k of ["instrument_id","order_type","quantity","price","trade_date"])
      if (!payload[k]) throw new Error(`Missing field: ${k}`);

    const res = await fetch("/cashcue/api/addOrder.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || "Failed to add order");

    modal.hide();
    loadOrders();
  } catch (err) {
    console.error("Error adding order:", err);
    alert(err.message || "Error while saving order");
  }
});
 


  // ============================================================
  // CANCEL ORDER (delegated)
  // ============================================================
  document.addEventListener("click", e => {
    const el = e.target.closest(".cancel-action");
    if (!el || el.classList.contains("is-disabled")) return;

    if (!confirm("Cancelling this order will create a cash reversal.\nThis action is irreversible.\nContinue?"))
      return;

    fetch(`/cashcue/api/cancelOrder.php?id=${el.dataset.id}`)
      .then(r => r.json())
      .then(json => {
        if (!json.success) throw new Error(json.error || "Cancel failed");
        loadOrders();
      })
      .catch(err => {
        console.error("Error cancelling order:", err);
        alert(err.message || "Error cancelling order");
      });
  });

  // ============================================================
  // BROKER ACCOUNT READY / CHANGED
  // ============================================================
  await window.CashCueAppContext.waitForBrokerAccount();
  loadInstruments();
  loadOrders();

  // ============================================================
  // ORDER UPDATED EVENT (from updateOrderModal.js)
  // ============================================================
  document.addEventListener('cashcue:order-updated', () => {
    console.log("manage_orders.js received cashcue:order-updated");

    loadOrders();   // reload from API → ALWAYS safer than table-only refresh
  });

  document.addEventListener("brokerAccountChanged", () => {
    console.log("manage_orders.js: brokerAccountChanged event");
    loadInstruments();
    loadOrders();
  });

});
