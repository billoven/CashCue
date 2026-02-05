/**
 * manage_orders.js
 * ----------------
 * Orders Management module for CashCue Web Application
 *
 * Purpose:
 *   - Display all orders for the selected broker account
 *   - Allow adding new orders via modal form
 *   - Allow cancelling orders directly from the table
 *   - Support searching, sorting, and pagination via CashCueTable
 *
 * Dependencies:
 *   - Bootstrap 5 (for modal and buttons)
 *   - flatpickr (for trade date input)
 *   - CashCueTable.js (table rendering, search, sort, pagination)
 *   - appContext.js (CashCueAppContext for broker_account_id)
 *
 * Key Features:
 *   1. Persistent CashCueTable instance for orders
 *   2. Dynamic loading of instruments and orders from API
 *   3. Status column with badges (ACTIVE / CANCELLED)
 *   4. Actions column with cancel icon and conditional disabling
 *   5. Pagination and search integrated with CashCueTable
 *   6. Modal form for Add/Edit order, with date picker
 *   7. Automatic refresh on broker change via event brokerAccountChanged
 *
 * Implementation Notes:
 *   - Orders are fetched from `/cashcue/api/getOrders.php?broker_account_id=...`
 *   - Instruments are fetched from `/cashcue/api/getInstruments.php`
 *   - Add/Edit uses `/cashcue/api/addOrder.php`
 *   - Cancel uses `/cashcue/api/cancelOrder.php?id=...`
 *
 * Usage:
 *   1. Include this script after CashCueTable.js and appContext.js
 *   2. HTML should provide:
 *        - <div id="ordersTableContainer"></div> for table
 *        - Modal structure with id="orderModal" and form id="orderForm"
 *        - Buttons: #btnAddOrder, #saveOrder
 *        - Search input: #searchOrder
 *   3. CashCueAppContext must be initialized before calling loadOrders()
 *
 * Author: Pierre
 * Created: 2026-02-03
 * Last Modified: 2026-02-03
 */

console.log("manage_orders.js loaded – refactored for CashCueTable & CashCueAppContext");

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
          render: row => parseFloat(row.total_value || 0).toFixed(2)
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
          key: "actions",
          label: "Actions",
          sortable: false,
          html: true,
          render: row => `
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

  document.addEventListener("brokerAccountChanged", () => {
    console.log("manage_orders.js: brokerAccountChanged event");
    loadInstruments();
    loadOrders();
  });

});
