/**
 * manage_instruments.js
 * =====================
 * Client-side controller for Instruments Management.
 *
 * Responsibilities:
 *  - Load instruments from API
 *  - Render instruments table using CashCueTable
 *  - Handle search, pagination, and sorting
 *  - Manage instrument creation and edition via modal
 *  - Enforce controlled lifecycle status transitions
 *
 * Delegated responsibilities:
 *  - Table rendering, sorting, pagination, filtering → CashCueTable
 *
 * Non-responsibilities:
 *  - Business rules enforcement (backend)
 *  - Physical deletion (not supported by design)
 */

document.addEventListener("DOMContentLoaded", () => {

  // --------------------------------------------------
  // Modal & form references
  // --------------------------------------------------
  const modalEl = document.getElementById("instrumentModal");
  const modal   = new bootstrap.Modal(modalEl);
  const form    = document.getElementById("instrumentForm");

  const btnAdd      = document.getElementById("btnAddInstrument");
  const btnSave     = document.getElementById("saveInstrument");

  const statusGroup      = document.getElementById("statusGroup");
  const statusSelect     = document.getElementById("status");
  const statusImpactHint = document.getElementById("statusImpactHint");

  // --------------------------------------------------
  // State
  // --------------------------------------------------
  let instruments = [];
  let instrumentsTable = null;
  let currentInstrumentStatus = null;

  // --------------------------------------------------
  // Status transitions & UI metadata
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
      "Trading and price updates will be suspended.",
    "ACTIVE→DELISTED":
      "The instrument is permanently delisted.",
    "INACTIVE→ACTIVE":
      "The instrument becomes operational again.",
    "SUSPENDED→ACTIVE":
      "Trading and price updates will resume.",
    "DELISTED→ARCHIVED":
      "The instrument becomes historical only."
  };

  const STATUS_COLORS = {
    ACTIVE:    "bg-success",
    INACTIVE:  "bg-secondary",
    SUSPENDED: "bg-warning text-dark",
    DELISTED:  "bg-danger",
    ARCHIVED:  "bg-dark"
  };

  // --------------------------------------------------
  // Status badge renderer (table use)
  // --------------------------------------------------
  function renderStatusBadge(status) {
    const cls = STATUS_COLORS[status] || "bg-secondary";
    return `<span class="badge ${cls}">${status}</span>`;
  }

  // --------------------------------------------------
  // Initialize CashCueTable (once)
  // --------------------------------------------------
  function initInstrumentsTable() {
    instrumentsTable = new CashCueTable({
      containerId: "instrumentsTableContainer",
      searchInput: "#searchInstrument",
      searchFields: ["symbol", "label"],
      pagination: {
        enabled: true,
        pageSize: 10
      },
      columns: [
        { key: "symbol", label: "Symbol", sortable: true },
        { key: "label",  label: "Label",  sortable: true },
        { key: "isin",   label: "ISIN",   sortable: true },
        { key: "type",   label: "Type",   sortable: true },
        { key: "currency", label: "Currency", sortable: true },
        {
          key: "status",
          label: "Status",
          sortable: false,
          html: true,
          render: row => renderStatusBadge(row.status)
        },
        {
          key: "actions",
          label: "Actions",
          sortable: false,
          html: true,
          render: row => `
            <button class="btn btn-sm btn-outline-primary edit-btn"
                    data-id="${row.id}"
                    title="Edit instrument">
              <i class="bi bi-pencil"></i>
            </button>
          `
        }
      ]
    });
  }

  // --------------------------------------------------
  // Load instruments from API
  // --------------------------------------------------
  async function loadInstruments() {
    try {
      const response = await fetch("/cashcue/api/getInstruments.php");
      const json = await response.json();

      if (json.status !== "success") {
        throw new Error(json.message);
      }

      instruments = json.data || [];
      instrumentsTable.setData(instruments);

      bindEditButtons();

    } catch (err) {
      console.error("Error loading instruments:", err);
      instrumentsTable.setData([]);
    }
  }

  // --------------------------------------------------
  // Bind edit buttons after table render
  // --------------------------------------------------
  function bindEditButtons() {
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

      if (json.status !== "success") {
        throw new Error(json.message);
      }

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

      currentInstrumentStatus = i.status;
      setupStatusSelect(i.status);

      modalEl.querySelector(".modal-title").textContent = "✏️ Edit Instrument";
      modal.show();

    } catch (err) {
      console.error("Instrument load error:", err);
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
    const key = `${currentInstrumentStatus}→${statusSelect.value}`;
    statusImpactHint.textContent =
      STATUS_IMPACTS[key] || "Changing status may have system-wide impacts.";
  });

  // --------------------------------------------------
  // Add instrument
  // --------------------------------------------------
  btnAdd.addEventListener("click", () => {
    form.reset();
    document.getElementById("instrumentId").value = "";
    statusGroup.style.display = "none";
    currentInstrumentStatus = "ACTIVE";

    modalEl.querySelector(".modal-title").textContent = "➕ Add Instrument";
    modal.show();
  });

  // --------------------------------------------------
  // Save instrument (add or update)
  // --------------------------------------------------
  btnSave.addEventListener("click", async () => {
    const id = document.getElementById("instrumentId").value;

    const payload = {
      id,
      symbol:   document.getElementById("symbol").value.trim(),
      label:    document.getElementById("label").value.trim(),
      isin:     document.getElementById("isin").value.trim(),
      type:     document.getElementById("type").value,
      currency: document.getElementById("currency").value.trim()
    };

    if (id) {
      payload.status = statusSelect.value;
      const key = `${currentInstrumentStatus}→${payload.status}`;
      if (
        payload.status !== currentInstrumentStatus &&
        !confirm((STATUS_IMPACTS[key] || "Status change impact.") + "\n\nConfirm?")
      ) {
        return;
      }
    }

    try {
      const endpoint = id
        ? "/cashcue/api/updateInstrument.php"
        : "/cashcue/api/addInstrument.php";

      const res = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });

      const json = await res.json();
      if (json.status !== "success") throw new Error(json.message);

      modal.hide();
      await loadInstruments();

    } catch (err) {
      console.error("Save error:", err);
      alert("Failed to save instrument.");
    }
  });

  // --------------------------------------------------
  // Boot
  // --------------------------------------------------
  initInstrumentsTable();
  loadInstruments();

});

