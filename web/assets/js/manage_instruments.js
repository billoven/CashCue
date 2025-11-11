document.addEventListener("DOMContentLoaded", () => {
  //const tableBody = document.querySelector("#instrumentsTable tbody");
  const modalEl = document.getElementById("instrumentModal");
  const modal = new bootstrap.Modal(modalEl);
  const form = document.getElementById("instrumentForm");

  const btnAdd = document.getElementById("btnAddInstrument");
  const btnSave = document.getElementById("saveInstrument");
  const searchInput = document.getElementById("searchInstrument");

  let instruments = [];

  // ---- Load instruments from API ----
  async function loadInstruments() {
    try {
      const response = await fetch("/cashcue/api/getInstruments.php");
      const json = await response.json();

      if (json.status !== "success") throw new Error(json.message);
      instruments = json.data;
      renderTable(instruments);
    } catch (err) {
      console.error("Error loading instruments:", err);
      tableBody.innerHTML =
        `<tr><td colspan="6" class="text-danger text-center">Failed to load instruments</td></tr>`;
    }
  }

  // ---- Render instruments table and reattach handlers ----
  function renderTable(data) {
    const tableBody = document.getElementById("instrumentsTableBody");
    tableBody.innerHTML = "";

    if (!data || data.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No instruments found.</td></tr>`;
      return;
    }

    data.forEach(i => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${i.symbol}</td>
        <td>${i.label}</td>
        <td>${i.isin ?? ''}</td>
        <td>${i.type}</td>
        <td>${i.currency}</td>
        <td>
          <button class="btn btn-sm btn-primary edit-btn" data-id="${i.id}">✏️</button>
          <button class="btn btn-sm btn-danger delete-btn" data-id="${i.id}">🗑️</button>
        </td>
      `;
      tableBody.appendChild(tr);
    });

    // 🧩 reattach handlers *after* rendering
    document.querySelectorAll(".edit-btn").forEach(btn =>
      btn.addEventListener("click", handleEditInstrument)
    );

    document.querySelectorAll(".delete-btn").forEach(btn =>
      btn.addEventListener("click", handleDeleteInstrument)
    );
  }

  // ---- Edit handler ----
  async function handleEditInstrument(e) {
    const id = e.currentTarget.dataset.id;
    try {
      const res = await fetch(`/cashcue/api/getInstrumentDetails.php?id=${id}`);
      const json = await res.json();
      if (json.status !== "success") throw new Error(json.message);

      const i = json.data;
      document.getElementById("instrumentId").value = i.id;
      document.getElementById("symbol").value = i.symbol;
      document.getElementById("label").value = i.label;
      document.getElementById("isin").value = i.isin ?? "";
      const typeSelect = document.getElementById("type");
      if ([...typeSelect.options].some(opt => opt.value === i.type)) {
        typeSelect.value = i.type;  // set it only if valid
      } else {
        typeSelect.value = "stock"; // default fallback
      }
      document.getElementById("currency").value = i.currency ?? "EUR";

      modalEl.querySelector(".modal-title").textContent = "✏️ Edit Instrument";
      modal.show();
    } catch (err) {
      console.error("Error loading instrument details:", err);
      alert("Failed to load instrument details.");
    }
  }

  // ---- Delete handler ----
  async function handleDeleteInstrument(e) {
    const id = e.currentTarget.dataset.id;
    if (!confirm("Delete this instrument?")) return;
    try {
      const res = await fetch(`/cashcue/api/deleteInstrument.php?id=${id}`);
      const json = await res.json();
      if (json.status !== "success") throw new Error(json.message);
      await loadInstruments();
    } catch (err) {
      console.error("Delete error:", err);
      alert("Failed to delete instrument.");
    }
  }

  // ---- Add button ----
  btnAdd.addEventListener("click", () => {
    form.reset();
    document.getElementById("instrumentId").value = "";
    modalEl.querySelector(".modal-title").textContent = "➕ Add Instrument";
    modal.show();
  });

  // ---- Save (Add or Update) ----
  btnSave.addEventListener("click", async () => {
    const id = document.getElementById("instrumentId").value;
    const payload = {
      id,
      symbol: document.getElementById("symbol").value.trim(),
      isin: document.getElementById("isin").value.trim(),
      label: document.getElementById("label").value.trim(),
      type: document.getElementById("type").value,
      currency: document.getElementById("currency").value.trim()
    };

    if (!payload.symbol || !payload.label || !payload.type || !payload.currency) {
      alert("Please fill in all required fields: Symbol, Label, Type, and Currency.");
      return;
    }

    try {
      const endpoint = id
        ? "/cashcue/api/updateInstrument.php"
        : "/cashcue/api/addInstrument.php";

      const response = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });

      const json = await response.json();
      if (json.status !== "success") throw new Error(json.message);

      modal.hide();
      await loadInstruments();
    } catch (err) {
      console.error("Save error:", err);
      alert("Failed to save instrument: " + (err.message || "Unknown error."));
    }

  });

  // ---- Search bar ----
  searchInput.addEventListener("input", e => {
    const term = e.target.value.toLowerCase();
    const filtered = instruments.filter(inst =>
      inst.symbol.toLowerCase().includes(term) ||
      inst.label.toLowerCase().includes(term)
    );
    renderTable(filtered);
  });

  // ---- Initial load ----
  loadInstruments();
});
