/**
 * manage_instruments.js
 * =====================
 * Client-side controller for Instruments Management.
*
* DOM Context:
*  The DOM (Document Object Model) represents the HTML structure of the page as a tree of elements.
*  This script manipulates the DOM by:
*   - Selecting HTML elements (modal, form, buttons) 
*   - Reading and writing their values and content
*   - Attaching event listeners to respond to user interactions
*   - Updating display attributes and styling
*  When "DOM ready" occurs (DOMContentLoaded event), all HTML elements are loaded and safe to access. 
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
 *
 * Execution Flow:
 *  1. On DOM ready, establish references to modal, form, and button elements
 *  2. Define status transition rules, impact messages, and color mappings
 *  3. Initialize the CashCueTable with columns for symbol, label, ISIN, type, currency, status badge, and actions
 *  4. Fetch instruments from API and populate the table with data
 *  5. Bind click handlers to edit buttons in the rendered table rows
 *  6. When user clicks edit, fetch instrument details and populate the modal form
 *  7. Setup the status dropdown with allowed transitions based on current status
 *  8. When user clicks add, reset form and hide status controls (new instruments default to ACTIVE)
 *  9. When user clicks save, validate form data and POST to appropriate endpoint (add or update)
 *  10. If updating and status changed, show confirmation dialog with impact message
 *  11. On success, hide modal, reload instruments table, and display success notification
 *  12. On error, display error notification with failure reason
 *
 */
 
// --------------------------------------------------
// DOMContentLoaded - main entry point
// --------------------------------------------------
document.addEventListener("DOMContentLoaded", () => {

  // --------------------------------------------------
  // Modal & form references
  // --------------------------------------------------
  const modalEl          = document.getElementById("instrumentModal");
  const modal            = new bootstrap.Modal(modalEl);
  const form             = document.getElementById("instrumentForm");
  const btnAdd           = document.getElementById("btnAddInstrument");
  const btnSave          = document.getElementById("saveInstrument");
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
  // Status management configuration
  // Defines allowed status transitions, user-facing impact messages for each transition, 
  // and corresponding badge colors for display in the table
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
    const columns = [
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
      }
    ];

    // Only show actions column to admin users, as it contains edit buttons
    if (window.CASHCUE_USER.isAdmin) {
      columns.push({
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
      });
    }

    instrumentsTable = new CashCueTable({
      containerId: "instrumentsTableContainer",
      searchInput: "#searchInstrument",
      searchFields: ["symbol", "label"],
      pagination: {
        enabled: true,
        pageSize: 20
      },
      columns: columns
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
      sowAlert("danger", "Failed to load instruments.");

      instrumentsTable.setData([]);
    }
  }

  // --------------------------------------------------
  // Bind edit buttons after table render
  // Must be called after each table data update to ensure buttons are interactive
  // --------------------------------------------------
  function bindEditButtons() {
    document.querySelectorAll(".edit-btn").forEach(btn =>
      btn.addEventListener("click", handleEditInstrument)
    );
  }

  // --------------------------------------------------
  // Edit instrument
  // Fetches instrument details, populates the modal form, 
  // sets up status options based on current status, and shows the modal for editing
  // --------------------------------------------------
  async function handleEditInstrument(e) {
    const id = e.currentTarget.dataset.id;

    try {
      const res  = await fetch(`/cashcue/api/getInstrumentDetails.php?id=${id}`);
      const json = await res.json();

      if (json.status !== "success") {
        show
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
      showAlert("danger", "Failed to load instrument details.");
    }
  }

  // --------------------------------------------------
  // Setup status select options based on current status
  // Displays allowed status transitions in the dropdown 
  // and shows impact hints when selection changes
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
  // Resets form, loads instruments dropdown, sets modal title, 
  // and shows the modal when the add button is clicked
  // btnAdd is only visible to admin users, as non-admins are not allowed to add instruments
  // ------------------------------------------
  if (btnAdd) {
    btnAdd.addEventListener("click", () => {
      form.reset();
      document.getElementById("instrumentId").value = "";
      statusGroup.style.display = "none";
      currentInstrumentStatus = "ACTIVE";

      modalEl.querySelector(".modal-title").textContent = "➕ Add Instrument";
      modal.show();
    });
  }

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
      if (json.status !== "success") {
        showAlert("danger", json.error || "Failed to save instrument.");
        throw new Error(json.message);
    }

      modal.hide();
      await loadInstruments();
      showAlert("success", `Instrument ${id ? "updated" : "added"} successfully.`);

    } catch (err) {
      console.error("Save error:", err);
      showAlert("danger", err.message || "Failed to save instrument.");
    }
  });

  // --------------------------------------------------
  // Initial setup: attach event listeners and initialize the table
  // Sets up the click listener for the add button, 
  // change listener for the status select, 
  // initializes the CashCueTable, and loads the initial data.
  // --------------------------------------------------
  initInstrumentsTable();
  loadInstruments();

});

