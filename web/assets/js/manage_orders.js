document.addEventListener("DOMContentLoaded", () => {
  const tableBody = document.querySelector("#ordersTable tbody");
  const modalEl = document.getElementById("orderModal");
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById("orderForm");

  const btnAdd = document.getElementById("btnAddOrder");
  const btnSave = document.getElementById("saveOrder");
  const searchInput = document.getElementById("searchOrder");

  // Pagination elements
  const prevBtn = document.getElementById("prevPage");
  const nextBtn = document.getElementById("nextPage");
  const pageInfo = document.getElementById("pageInfo");

  let orders = [];
  let instruments = [];
  let limit = 10; // number of records per page
  let offset = 0; // pagination offset
  let currentPage = 1;

  // ---- Load instruments for dropdown ----
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

  // ---- Load orders ----
  async function loadOrders() {
    try {
      const response = await fetch(`/cashcue/api/getOrders.php?limit=${limit}&offset=${offset}`);
      const json = await response.json();
      if (json.status !== "success" && !json.success) throw new Error(json.message || json.error);

      // Support both "status":"success" and "success":true formats
      orders = json.data || json.orders || [];

      renderTable(orders);

      // Update pagination display
      pageInfo.textContent = `Page ${currentPage}`;
      prevBtn.disabled = offset === 0;
      nextBtn.disabled = orders.length < limit;

    } catch (err) {
      console.error("Error loading orders:", err);
      tableBody.innerHTML =
        `<tr><td colspan="10" class="text-danger text-center">Failed to load orders</td></tr>`;
    }
  }

  // ---- Render table ----
  function renderTable(data) {
    tableBody.innerHTML = "";

    if (!data || data.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="10" class="text-center text-muted">No orders found.</td></tr>`;
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
          <button class="btn btn-sm btn-outline-primary me-1 edit-btn" data-id="${o.id}"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${o.id}"><i class="bi bi-trash"></i></button>
        </td>
      `;
      tableBody.appendChild(tr);
    });

    document.querySelectorAll(".edit-btn").forEach(btn =>
      btn.addEventListener("click", handleEdit)
    );
    document.querySelectorAll(".delete-btn").forEach(btn =>
      btn.addEventListener("click", handleDelete)
    );
  }

  // ---- Add button ----
  btnAdd.addEventListener("click", () => {
    form.reset();
    document.getElementById("order_id").value = "";
    modalEl.querySelector(".modal-title").textContent = "➕ Add Order";
    modal.show();
  });

  // ---- Edit order ----
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
      document.getElementById("trade_date").value = o.trade_date;

      modalEl.querySelector(".modal-title").textContent = "✏️ Edit Order";
      modal.show();
    } catch (err) {
      console.error("Error loading order details:", err);
      alert("Failed to load order details.");
    }
  }

  // ---- Delete order ----
  async function handleDelete(e) {
    const id = e.currentTarget.dataset.id;
    if (!confirm("Delete this order?")) return;
    try {
      const res = await fetch(`/cashcue/api/deleteOrder.php?id=${id}`);
      const json = await res.json();
      if (json.status !== "success") throw new Error(json.message);
      await loadOrders();
    } catch (err) {
      console.error("Delete error:", err);
      alert("Failed to delete order.");
    }
  }

  // ---- Save order (Add or Update) ----
  btnSave.addEventListener("click", async () => {
    const id = document.getElementById("order_id").value;
    const payload = {
      id,
      instrument_id: document.getElementById("instrument_id").value,
      order_type: document.getElementById("order_type").value,
      quantity: parseFloat(document.getElementById("quantity").value),
      price: parseFloat(document.getElementById("price").value),
      fees: parseFloat(document.getElementById("fees").value) || 0,
      trade_date: document.getElementById("trade_date").value
    };

    if (!payload.instrument_id || !payload.order_type || !payload.quantity || !payload.price || !payload.trade_date) {
      alert("Please fill in all required fields.");
      return;
    }

    try {
      const endpoint = id
        ? "/cashcue/api/updateOrder.php"
        : "/cashcue/api/addOrder.php";

      const response = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });

      const json = await response.json();
      if (json.status !== "success") throw new Error(json.message);

      modal.hide();
      await loadOrders();
    } catch (err) {
      console.error("Save error:", err);
      alert("Failed to save order.");
    }
  });

  // ---- Search ----
  searchInput.addEventListener("input", e => {
    const term = e.target.value.toLowerCase();
    const filtered = orders.filter(o =>
      o.symbol.toLowerCase().includes(term) ||
      o.label.toLowerCase().includes(term) ||
      (o.broker_name && o.broker_name.toLowerCase().includes(term))
    );
    renderTable(filtered);
  });

  // ---- Pagination buttons ----
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

  // ---- Init ----
  loadInstruments();
  loadOrders();
});
