console.log("manage_orders.js loaded VERSION FINAL");

/**
 * This script manages:
 * - Orders list
 * - Add/Edit/Delete modal
 * - Linking orders to the global broker selector (from header.js)
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


  // ============================================================
  //   INIT FLATPICKR WHEN MODAL IS SHOWN
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

      console.log("Flatpickr initialized");

      // Optional: calendar close button
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
  //   LOAD ORDERS — Called via header.js → refreshPageData()
  // ============================================================

  async function loadOrders() {
    try {
      const selector = document.getElementById("activeAccountSelect");
      if (!selector) {
        console.error("ERROR: activeAccountSelect not found — header.js must run first.");
        return;
      }

      const rawId = selector.value;
      const broker_account_id = rawId && rawId !== "" ? rawId : "all";

      console.log("DEBUG: Loading orders for broker_account_id =", broker_account_id);

      const url =
        `/cashcue/api/getOrders.php?broker_account_id=${broker_account_id}` +
        `&limit=${limit}&offset=${offset}`;

      console.log("DEBUG: Fetch URL =", url);

      const response = await fetch(url);
      console.log("DEBUG: Fetch response status =", response.status);

      const json = await response.json();
      console.log("DEBUG: JSON received =", json);

      if (json.status !== "success" && !json.success) {
        throw new Error(json.message || json.error);
      }

      orders = json.data || json.orders || [];
      console.log("DEBUG: Number of orders loaded =", orders.length);

      renderTable(orders);

      pageInfo.textContent = `Page ${currentPage}`;
      prevBtn.disabled = offset === 0;
      nextBtn.disabled = orders.length < limit;

    } catch (err) {
      console.error("Error loading orders:", err);
      tableBody.innerHTML =
        `<tr><td colspan="10" class="text-danger text-center">Failed to load orders</td></tr>`;
    }
  }


  // ============================================================
  //   RENDER TABLE
  // ============================================================

  function renderTable(data) {
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


    // -----------------------------
  // Add Order button handler
  // -----------------------------
  if (btnAdd) {
    btnAdd.addEventListener("click", (evt) => {
      evt.preventDefault();
      console.log("ACTION: btnAddOrder clicked");

      try {
        // Reset HTML form fields
        if (form) {
          form.reset();
        } else {
          console.warn("Add Order: form element not found");
        }

        // Ensure order_id empty (new record)
        const orderIdEl = document.getElementById("order_id");
        if (orderIdEl) orderIdEl.value = "";

        // Clear the flatpickr value if it's already initialized
        // (if not initialized, this is a no-op)
        try {
          if (tradeDatePicker && typeof tradeDatePicker.clear === "function") {
            tradeDatePicker.clear();
          }
        } catch (err) {
          console.warn("Add Order: could not clear tradeDatePicker", err);
        }

        // Update modal title
        const titleEl = modalEl && modalEl.querySelector && modalEl.querySelector(".modal-title");
        if (titleEl) titleEl.textContent = "➕ Add Order";

        // Show the modal (use existing bootstrap modal instance if available)
        if (typeof modal !== "undefined" && modal && typeof modal.show === "function") {
          modal.show();
        } else {
          // Fallback: try to create a bootstrap modal instance on the fly
          if (typeof bootstrap !== "undefined" && document.getElementById("orderModal")) {
            try {
              const tmp = new bootstrap.Modal(document.getElementById("orderModal"));
              tmp.show();
              // store temporary instance in case we need to hide it later
              window.__tempModal = tmp;
            } catch (err) {
              console.error("Add Order: unable to instantiate bootstrap modal", err);
            }
          } else {
            console.error("Add Order: bootstrap modal not available");
          }
        }

        // Focus the first usable input in the form for faster data entry
        try {
          const firstInput = form && form.querySelector && form.querySelector("input:not([type=hidden]), select, textarea");
          if (firstInput) {
            // small delay to allow modal animation to finish
            setTimeout(() => firstInput.focus(), 150);
          }
        } catch (err) {
          console.warn("Add Order: cannot focus first input", err);
        }

      } catch (err) {
        console.error("Add Order: unexpected error preparing modal", err);
        alert("An error occurred while preparing the Add Order form. See console for details.");
      }
    });
  } else {
    console.warn("Add Order button (#btnAddOrder) not found — handler not attached.");
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
        tradeDatePicker.setDate(o.trade_date, true, "Y-m-d H:i:S");
      }, 50);

      modalEl.querySelector(".modal-title").textContent = "✏️ Edit Order";
      modal.show();

    } catch (err) {
      console.error("Error loading order details:", err);
      alert("Failed to load order details.");
    }
  }


  // ============================================================
  //   DELETE ORDER
  // ============================================================

  async function handleDelete(e) {
    const id = e.currentTarget.dataset.id;
    if (!id) return alert("Order ID missing");
    if (!confirm("Delete this order?")) return;

    try {
      const res = await fetch(`/cashcue/api/deleteOrder.php?id=${id}`);
      const json = await res.json();

      if (!(json.status === "success" || json.success === true)) {
        const msg = json.error || json.message || JSON.stringify(json);
        throw new Error(msg);
      }

      alert("Order deleted successfully.");
      await loadOrders();

    } catch (err) {
      console.error("Delete error:", err);
      alert("Failed to delete order: " + (err.message || err));
    }
  }


  // ============================================================
  //   SAVE (ADD / UPDATE)
  // ============================================================

  btnSave.addEventListener("click", async () => {
    const id = document.getElementById("order_id")?.value;
    const broker_account_id = document.getElementById("activeAccountSelect")?.value;

    const payload = {
      id,
      broker_account_id,
      instrument_id: document.getElementById("instrument_id")?.value,
      order_type: document.getElementById("order_type")?.value,
      quantity: parseFloat(document.getElementById("quantity")?.value),
      price: parseFloat(document.getElementById("price")?.value),
      fees: parseFloat(document.getElementById("fees")?.value) || 0,
      trade_date:
        tradeDatePicker?.selectedDates.length
          ? tradeDatePicker.formatDate(tradeDatePicker.selectedDates[0], "Y-m-d H:i:S")
          : ""
    };

    if (
      !payload.instrument_id ||
      !payload.order_type ||
      !Number.isFinite(payload.quantity) ||
      !Number.isFinite(payload.price) ||
      !payload.trade_date
    ) {
      alert("Please fill all fields.");
      return;
    }

    const endpoint = id ? "/cashcue/api/updateOrder.php" : "/cashcue/api/addOrder.php";

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });

      const text = await response.text();
      const json = JSON.parse(text);

      if (!json.success) throw new Error(json.error || json.message);

      modal.hide();
      await loadOrders();

      alert("Order saved successfully.");

    } catch (err) {
      console.error("Save error:", err);
      alert("Failed to save order: " + err.message);
    }
  });


  // ============================================================
  //   SEARCH
  // ============================================================

  searchInput.addEventListener("input", e => {
    const term = e.target.value.toLowerCase();
    const filtered = orders.filter(o =>
      o.symbol.toLowerCase().includes(term) ||
      o.label.toLowerCase().includes(term) ||
      (o.broker_name && o.broker_name.toLowerCase().includes(term))
    );
    renderTable(filtered);
  });


  // ============================================================
  //   PAGINATION
  // ============================================================

  if (prevBtn && nextBtn) {
    prevBtn.addEventListener("click", () => {
      if (offset >= limit) {
        offset -= limit;
        currentPage--;
        loadOrders();
      }
    });

    nextBtn.addEventListener("click", () => {
      offset += limit;
      currentPage++;
      loadOrders();
    });
  }


  // ============================================================
  //   INIT ONLY INSTRUMENTS
  //   (orders are now loaded via header.js → refreshPageData())
  // ============================================================

  loadInstruments();


  // ============================================================
  //   EXPOSE GLOBAL REFRESH FUNCTION
  //   Called automatically by header.js
  // ============================================================

  window.refreshPageData = () => {
    console.log("refreshPageData() triggered → loadOrders()");
    loadOrders();
  };

});

