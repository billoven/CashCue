/**
 * manage_instruments.js
 * ---------------------
 * Client-side logic for managing financial instruments.
 *
 * Responsibilities:
 * - Load and display instruments
 * - Handle creation and edition
 * - Manage instrument lifecycle status changes with confirmations
 *
 * No physical deletion is supported.
 */

document.addEventListener("DOMContentLoaded", () => {

  const modalEl = document.getElementById("instrumentModal");
  const modal   = new bootstrap.Modal(modalEl);
  const form    = document.getElementById("instrumentForm");

  const btnAdd      = document.getElementById("btnAddInstrument");
  const btnSave     = document.getElementById("saveInstrument");
  const searchInput = document.getElementById("searchInstrument");

  const statusGroup      = document.getElementById("statusGroup");
  const statusSelect     = document.getElementById("status");
  const statusImpactHint = document.getElementById("statusImpactHint");

  let instruments = [];
  let currentInstrumentStatus = null;

  // --------------------------------------------------
  // Status transitions & impact messages (UI only)
  // --------------------------------------------------
  const STATUS_TRANSITIONS = {
    ACTIVE:     ["INACTIVE", "SUSPENDED", "DELISTED"],
    INACTIVE:   ["ACTIVE"],
    SUSPENDED:  ["ACTIVE"],
    DELISTED:   ["ARCHIVED"],
    ARCHIVED:   []
  };

  const STATUS_IMPACTS = {
    "ACTIVE→INACTIVE":
      "The instrument will no longer be selectable for new operations.",
    "ACTIVE→SUSPENDED":
      "Trading and price updates will be suspended. Valuation will rely on the last known price.",
    "ACTIVE→DELISTED":
      "The instrument is permanently delisted. No future price updates will occur.",
    "INACTIVE→ACTIVE":
      "The instrument will become fully operational again.",
    "SUSPENDED→ACTIVE":
      "Trading and price updates will resume.",
    "DELISTED→ARCHIVED":
      "The instrument becomes historical only and will be hidden from default views."
  };

  const STATUS_COLORS = {
    ACTIVE:    "bg-success",
    INACTIVE:  "bg-secondary",
    SUSPENDED: "bg-warning",
    DELISTED:  "bg-danger",
    ARCHIVED:  "bg-dark"
  };

  // --------------------------------------------------
  // Render status badge
  // --------------------------------------------------
  function renderStatusBadge(status) {
    const color = STATUS_COLORS[status] || "bg-secondary";
    return `<span class="badge ${color}">${status}</span>`;
  }

  // --------------------------------------------------
  // Load instruments
  // --------------------------------------------------
  async function loadInstruments() {
    try {
      const response = await fetch("/cashcue/api/getInstruments.php");
      const json     = await response.json();

      if (json.status !== "success") throw new Error(json.message);

      instruments = json.data;
      renderTable(instruments);

    } catch (err) {
      console.error("Error loading instruments:", err);
      document.getElementById("instrumentsTableBody").innerHTML =
        `<tr><td colspan="7" class="text-danger text-center">Failed to load instruments</td></tr>`;
    }
  }

  // --------------------------------------------------
  // Render table
  // --------------------------------------------------
  function renderTable(data) {
    const tableBody = document.getElementById("instrumentsTableBody");
    tableBody.innerHTML = "";

    if (!data || data.length === 0) {
      tableBody.innerHTML =
        `<tr><td colspan="7" class="text-center text-muted">No instruments found.</td></tr>`;
      return;
    }

    data.forEach(i => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${i.symbol}</td>
        <td>${i.label}</td>
        <td>${i.isin ?? ""}</td>
        <td>${i.type}</td>
        <td>${i.currency}</td>
        <td>${renderStatusBadge(i.status)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary edit-btn" data-id="${i.id}">
            <i class="bi bi-pencil"></i>
          </button>
        </td>
      `;
      tableBody.appendChild(tr);
    });

    document.querySelectorAll(".edit-btn").forEach(btn =>
      btn.addEventListener("click", handleEditInstrument)
    );
  }

  // --------------------------------------------------
  // Edit instrument
  // --------------------------------------------------
  async function handleEditInstrument(e) {
    const id = e.currentTarget.dataset.id;

    try {
      const res  = await fetch(`/cashcue/api/getInstrumentDetails.php?id=${id}`);
      const json = await res.json();

      if (json.status !== "success") throw new Error(json.message);

      const i = json.data;

      document.getElementById("instrumentId").value = i.id;
      document.getElementById("symbol").value       = i.symbol;
      document.getElementById("label").value        = i.label;
      document.getElementById("isin").value         = i.isin ?? "";
      document.getElementById("currency").value     = i.currency ?? "EUR";

      const typeSelect = document.getElementById("type");
      typeSelect.value = [...typeSelect.options].some(o => o.value === i.type)
        ? i.type
        : "stock";

      // Status handling
      currentInstrumentStatus = i.status;
      setupStatusSelect(i.status);

      modalEl.querySelector(".modal-title").textContent = "✏️ Edit Instrument";
      modal.show();

    } catch (err) {
      console.error("Error loading instrument details:", err);
      alert("Failed to load instrument details.");
    }
  }

  // --------------------------------------------------
  // Status select setup
  // --------------------------------------------------
  function setupStatusSelect(currentStatus) {
    statusGroup.style.display = "block";
    statusSelect.innerHTML = "";

    const allowed = STATUS_TRANSITIONS[currentStatus] || [];

    // Current status (disabled)
    const currentOpt = document.createElement("option");
    currentOpt.value = currentStatus;
    currentOpt.textContent = currentStatus;
    currentOpt.selected = true;
    statusSelect.appendChild(currentOpt);

    allowed.forEach(status => {
      const opt = document.createElement("option");
      opt.value = status;
      opt.textContent = status;
      statusSelect.appendChild(opt);
    });

    statusImpactHint.textContent = "";
  }

  statusSelect.addEventListener("change", () => {
    const newStatus = statusSelect.value;
    const key = `${currentInstrumentStatus}→${newStatus}`;
    statusImpactHint.textContent = STATUS_IMPACTS[key] || "Changing status may have system-wide impacts.";
  });

  // --------------------------------------------------
  // Add instrument
  // --------------------------------------------------
  btnAdd.addEventListener("click", () => {
    form.reset();
    document.getElementById("instrumentId").value = "";
    statusGroup.style.display = "none";
    statusSelect.innerHTML = "";
    currentInstrumentStatus = "ACTIVE"; // default new instrument

    modalEl.querySelector(".modal-title").textContent = "➕ Add Instrument";
    modal.show();
  });

  // --------------------------------------------------
  // Save (add or update)
  // --------------------------------------------------
  btnSave.addEventListener("click", async () => {
    const id = document.getElementById("instrumentId").value;

    const payload = {
      id,
      symbol:   document.getElementById("symbol").value.trim(),
      isin:     document.getElementById("isin").value.trim(),
      label:    document.getElementById("label").value.trim(),
      type:     document.getElementById("type").value,
      currency: document.getElementById("currency").value.trim()
    };

    if (id) {
      payload.status = statusSelect.value;
      const transitionKey = `${currentInstrumentStatus}→${payload.status}`;
      const impactMsg = STATUS_IMPACTS[transitionKey] || "Changing status may have system-wide impacts.";
      if (payload.status !== currentInstrumentStatus && !confirm(impactMsg + "\n\nDo you confirm?")) {
        return;
      }
    }

    if (!payload.symbol || !payload.label || !payload.type || !payload.currency) {
      alert("Please fill in all required fields.");
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
      alert("Failed to save instrument: " + err.message);
    }
  });

  // --------------------------------------------------
  // Search
  // --------------------------------------------------
  searchInput.addEventListener("input", e => {
    const term = e.target.value.toLowerCase();
    renderTable(
      instruments.filter(inst =>
        inst.symbol.toLowerCase().includes(term) ||
        inst.label.toLowerCase().includes(term)
      )
    );
  });

  // --------------------------------------------------
  // Initial load
  // --------------------------------------------------
  loadInstruments();

});
