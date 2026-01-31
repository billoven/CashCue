console.log("manage_orders.js loaded - adapted for CashCueAppContext");

/**
 * manage_orders.js – adapted to use CashCueAppContext
 *
 * Changes:
 * - All broker_account_id accesses now go through CashCueAppContext.getBrokerAccountId()
 * - Initial load waits for brokerAccountId via waitForBrokerAccount()
 * - Reacts to broker changes using brokerAccountChanged event
 * - Preserves all existing Add/Edit/Delete + flatpickr + pagination logic
 */

document.addEventListener("DOMContentLoaded", async () => {

  // ------------------------------------------------------------
  // DOM ELEMENTS
  // ------------------------------------------------------------
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

  // ============================================================
  // FLATPICKR: init when modal shown
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
      if (closeBtn) {
        closeBtn.addEventListener("click", () => tradeDatePicker.close());
      }
    }
  });

  // ============================================================
  // LOAD INSTRUMENTS
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
  // LOAD ORDERS
  // ============================================================
  async function loadOrders() {
    try {
      const broker_account_id = window.CashCueAppContext.getBrokerAccountId();
      if (!broker_account_id) {
        console.warn("Broker not ready, cannot load orders");
        return;
      }

      const url =
        `/cashcue/api/getOrders.php?broker_account_id=${broker_account_id}` +
        `&limit=${limit}&offset=${offset}`;

      const response = await fetch(url);
      const json = await response.json();
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
  // RENDER TABLE
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
          ${o.status === 'ACTIVE'
            ? '<span class="badge bg-success">ACTIVE</span>'
            : '<span class="badge bg-secondary">CANCELLED</span>'}
        </td>
        <td>${o.cancelled_at ?? '—'}</td>
        <td class="text-center">
          <span
            class="cancel-action ${o.status === 'CANCELLED' ? 'is-disabled' : 'is-active'}"
            data-id="${o.id}"
            role="button"
            aria-label="Cancel order"
            title="${o.status === 'CANCELLED'
              ? 'Order already cancelled'
              : 'Cancel order (creates a cash reversal)'}"
          >
            <i class="bi bi-x-circle-fill"></i>
          </span>
        </td>
      `;
      tableBody.appendChild(tr);
    });
  }

  // ============================================================
  // ADD / SAVE / CANCEL ORDER
  // ============================================================
  if (btnAdd) {
    btnAdd.addEventListener("click", (evt) => {
      evt.preventDefault();
      form.reset();
      document.getElementById("order_id").value = "";
      tradeDatePicker?.clear();
      modalEl.querySelector(".modal-title").textContent = "➕ Add Order";
      modal.show();
      setTimeout(() => {
        form.querySelector("input:not([type=hidden]), select, textarea")?.focus();
      }, 150);
    });
  }

  if (btnSave) {
    btnSave.addEventListener("click", async (e) => {
      e.preventDefault();
      try {
        const broker_account_id = window.CashCueAppContext.getBrokerAccountId();
        if (!broker_account_id) {
          alert("Please select a broker account.");
          return;
        }

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

        for (const k of ["instrument_id", "order_type", "quantity", "price", "trade_date"]) {
          if (!payload[k]) throw new Error(`Missing field: ${k}`);
        }

        const res = await fetch("/cashcue/api/addOrder.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (!json.success) throw new Error(json.error || "Failed to add order");

        modal.hide();
        window.refreshPageData();

      } catch (err) {
        console.error("Error adding order:", err);
        alert(err.message || "Error while saving order");
      }
    });
  }

  async function cancelOrder(orderId) {
    if (!confirm(
      "Cancelling this order will create a cash reversal.\n" +
      "This action is irreversible.\n\n" +
      "Do you want to continue?"
    )) return;

    try {
      const res = await fetch(`/cashcue/api/cancelOrder.php?id=${orderId}`);
      const json = await res.json();
      if (!json.success) throw new Error(json.error || "Cancel failed");
      loadOrders();
    } catch (err) {
      console.error("Error cancelling order:", err);
      alert(err.message || "Error cancelling order.");
    }
  }

  // ============================================================
  // PAGINATION
  // ============================================================
  prevBtn?.addEventListener("click", () => {
    if (offset === 0) return;
    offset -= limit;
    currentPage--;
    loadOrders();
  });
  nextBtn?.addEventListener("click", () => {
    offset += limit;
    currentPage++;
    loadOrders();
  });

  // ============================================================
  // refreshPageData exposed for header.js / other modules
  // ============================================================
  window.refreshPageData = function () {
    offset = 0;
    currentPage = 1;
    loadOrders();
  };

  // ============================================================
  // REACT TO BROKER CHANGE (via appContext)
  // ============================================================
  document.addEventListener("brokerAccountChanged", () => {
    console.log("manage_orders.js: brokerAccountChanged event received");
    window.refreshPageData();
  });

  // ============================================================
  // INITIAL LOAD — wait for brokerAccountId
  // ============================================================
  await window.CashCueAppContext.waitForBrokerAccount();
  loadInstruments();
  window.refreshPageData();

  // ============================================================
  // Cancel order handler (event delegation)
  // ============================================================
  document.addEventListener("click", (e) => {
    const el = e.target.closest(".cancel-action");
    if (!el || el.classList.contains("disabled")) return;
    cancelOrder(el.dataset.id);
  });

});
