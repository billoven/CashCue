console.log("manage_orders.js loaded VERSION OPTION A FIXED");

/**
 * manage_orders.js
 * - Utilise header.js as single source of truth for broker_account_id
 * - Waits for header.js to be ready via waitForBrokerSelector()
 * - Reacts to broker changes via onBrokerAccountChange(callback)
 * - Preserves existing Add/Edit/Delete + flatpickr logic
 */

document.addEventListener("DOMContentLoaded", () => {

  // ============================================================
  //   DOM ELEMENTS
  // ============================================================

  const tableBody = document.querySelector("#ordersTable tbody");
  const modalEl = document.getElementById("orderModal");
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById("orderForm");

  const btnAdd = document.getElementById("btnAddOrder");
  const btnSave = document.getElementById("saveOrder");
  const searchInput = document.getElementById("searchOrder");

  const prevBtn = document.getElementById("prevPage");
  const nextBtn = document.getElementById("nextPage");
  const pageInfo = document.getElementById("pageInfo");

  let orders = [];
  let instruments = [];
  let limit = 10;
  let offset = 0;
  let currentPage = 1;

  let tradeDatePicker = null;

  // ------------------------------------------------------------
  // Helper safe accessors (in case header.js is not present)
  // ------------------------------------------------------------
  function safeGetActiveBrokerAccountId() {
    if (typeof window.getActiveBrokerAccountId === "function") {
      return window.getActiveBrokerAccountId();
    }
    // fallback: "all" to avoid breaking
    console.warn("getActiveBrokerAccountId() not available, defaulting to 'all'");
    return "all";
  }

  // ------------------------------------------------------------
  // FLATPICKR: init when modal shown (unchanged)
  // ------------------------------------------------------------
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

      console.log("Flatpickr initialized");

      const closeBtn = document.getElementById("closeCalendar");
      if (closeBtn) {
        closeBtn.addEventListener("click", () => {
          if (tradeDatePicker) tradeDatePicker.close();
        });
      }
    }
  });

  // ============================================================
  //   LOAD INSTRUMENTS
  // ============================================================
  async function loadInstruments() {
    try {
      const response = await fetch("/cashcue/api/getInstruments.php");
      const json = await response.json();
      if (json.status !== "success") throw new Error(json.message);

      instruments = json.data;
      const select = document.getElementById("instrument_id");
      if (!select) return;
      select.innerHTML = '<option value="">Select instrument...</option>';

      instruments.forEach(i => {
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
  //   LOAD ORDERS
  // ============================================================
  async function loadOrders() {
    try {
      const broker_account_id = safeGetActiveBrokerAccountId();
      console.log("DEBUG loadOrders(): broker_account_id =", broker_account_id);

      const url =
        `/cashcue/api/getOrders.php?broker_account_id=${broker_account_id}` +
        `&limit=${limit}&offset=${offset}`;

      console.log("DEBUG Fetch URL =", url);

      const response = await fetch(url);
      console.log("DEBUG Fetch response status =", response.status);

      const json = await response.json();
      console.log("DEBUG JSON received =", json);

      if (json.status !== "success" && !json.success) {
        throw new Error(json.message || json.error);
      }

      orders = json.data || json.orders || [];
      renderTable(orders);

      pageInfo.textContent = `Page ${currentPage}`;
      prevBtn.disabled = offset === 0;
      nextBtn.disabled = orders.length < limit;

    } catch (err) {
      console.error("Error loading orders:", err);
      if (tableBody) {
        tableBody.innerHTML =
          `<tr><td colspan="10" class="text-danger text-center">Failed to load orders</td></tr>`;
      }
    }
  }

  // ============================================================
  //   RENDER TABLE
  // ============================================================
  function renderTable(data) {
    if (!tableBody) return;
    tableBody.innerHTML = "";

    if (!data || data.length === 0) {
      tableBody.innerHTML =
        `<tr><td colspan="10" class="text-center text-muted">No orders found.</td></tr>`;
      return;
    }

    data.forEach(o => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${o.symbol}</td>
        <td>${o.label}</td>
        <td>${o.broker_full_name || "-"}</td>
        <td>${o.order_type}</td>
        <td>${o.quantity}</td>
        <td>${parseFloat(o.price).toFixed(4)}</td>
        <td>${parseFloat(o.fees || 0).toFixed(2)}</td>
        <td>${parseFloat(o.total_value || o.total || 0).toFixed(2)}</td>
        <td>${o.trade_date}</td>
        <td>
          <button class="btn btn-sm btn-outline-primary me-1 edit-btn" data-id="${o.id}">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${o.id}">
            <i class="bi bi-trash"></i>
          </button>
        </td>
      `;
      tableBody.appendChild(tr);
    });

    document.querySelectorAll(".edit-btn")
      .forEach(btn => btn.addEventListener("click", handleEdit));

    document.querySelectorAll(".delete-btn")
      .forEach(btn => btn.addEventListener("click", handleDelete));
  }

  // ============================================================
  //   ADD ORDER
  // ============================================================
  if (btnAdd) {
    btnAdd.addEventListener("click", (evt) => {
      evt.preventDefault();

      form.reset();

      const orderIdEl = document.getElementById("order_id");
      if (orderIdEl) orderIdEl.value = "";

      if (tradeDatePicker && typeof tradeDatePicker.clear === "function") {
        tradeDatePicker.clear();
      }

      modalEl.querySelector(".modal-title").textContent = "➕ Add Order";
      modal.show();

      setTimeout(() => {
        const firstInput = form.querySelector("input:not([type=hidden]), select, textarea");
        if (firstInput) firstInput.focus();
      }, 150);
    });
  }

  // ============================================================
  //   EDIT ORDER
  // ============================================================
  async function handleEdit(e) {
    const id = e.currentTarget.dataset.id;

    try {
      const res = await fetch(`/cashcue/api/getOrderDetails.php?id=${id}`);
      const json = await res.json();
      if (json.status !== "success") throw new Error(json.message);

      const o = json.data;

      document.getElementById("order_id").value = o.id;
      document.getElementById("instrument_id").value = o.instrument_id;
      document.getElementById("order_type").value = o.order_type;
      document.getElementById("quantity").value = o.quantity;
      document.getElementById("price").value = o.price;
      document.getElementById("fees").value = o.fees ?? 0;

      setTimeout(() => {
        if (tradeDatePicker && typeof tradeDatePicker.setDate === "function") {
          tradeDatePicker.setDate(o.trade_date, true, "Y-m-d H:i:S");
        } else {
          // If datepicker not initialized yet, set the input value directly
          const inp = document.getElementById("trade_date");
          if (inp) inp.value = o.trade_date;
        }
      }, 50);

      modalEl.querySelector(".modal-title").textContent = "✏️ Edit Order";
      modal.show();

    } catch (err) {
      console.error("Error loading order details:", err);
    }
  }

  // ============================================================
  //   DELETE ORDER
  // ============================================================
  async function handleDelete(e) {
    const id = e.currentTarget.dataset.id;

    if (!confirm("Delete this order?")) return;

    try {
      const res = await fetch(`/cashcue/api/deleteOrder.php?id=${id}`, { method: "DELETE" });
      const json = await res.json();

      if (!json.success && json.status !== "success") {
        throw new Error(json.message || json.error);
      }

      loadOrders();

    } catch (err) {
      console.error("Error deleting order:", err);
      alert("Error deleting order.");
    }
  }

  // ============================================================
  //   PAGINATION
  // ============================================================
  if (prevBtn) {
    prevBtn.addEventListener("click", () => {
      if (offset === 0) return;
      offset -= limit;
      currentPage--;
      loadOrders();
    });
  }
  if (nextBtn) {
    nextBtn.addEventListener("click", () => {
      offset += limit;
      currentPage++;
      loadOrders();
    });
  }

  // ============================================================
  //   EXPOSE refreshPageData() FOR header.js
  // ============================================================
  window.refreshPageData = function () {
    console.log("manage_orders.js: refreshPageData() called");
    offset = 0;
    currentPage = 1;
    loadOrders();
  };

  // ============================================================
  //   REACT TO BROKER CHANGE (header.js)
  //   Use the correct public API name: onBrokerAccountChange
  // ============================================================
  if (typeof window.onBrokerAccountChange === "function") {
    window.onBrokerAccountChange(() => {
      console.log("manage_orders.js: broker account changed → reload orders");
      window.refreshPageData();
    });
  } else if (typeof window.addEventListener === "function") {
    // Backward-compatible fallback: listen for a custom event if header.js prefers dispatchEvent
    window.addEventListener("brokerAccountChanged", () => {
      console.log("manage_orders.js: brokerAccountChanged event received");
      window.refreshPageData();
    });
  } else {
    console.warn("manage_orders.js: no broker change hook available (onBrokerAccountChange missing).");
  }

  // ============================================================
  //   INITIAL LOAD — wait for header.js to be ready if provided
  // ============================================================
  if (typeof window.waitForBrokerSelector === "function") {
    window.waitForBrokerSelector().then(() => {
      loadInstruments();
      window.refreshPageData();
    });
  } else {
    // Immediate fallback
    loadInstruments();
    window.refreshPageData();
  }

});

