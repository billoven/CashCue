/**
 * CashCueTable
 * ============
 * Generic, lightweight table controller for CashCue.
 *
 * Responsibilities:
 *  - Client-side sorting (ASC/DESC)
 *  - Optional pagination
 *  - Declarative column definitions with 'render' support
 *  - Bootstrap-compatible rendering
 *  - Optional live search/filtering on specified columns
 *
 * Non-responsibilities:
 *  - Data fetching
 *  - Business logic
 *  - Backend coupling
 *
 * Usage:
 *  const table = new CashCueTable({
 *    containerId: "ordersTableContainer",
 *    columns: [
 *      { key: "symbol", label: "Symbol", sortable: true },
 *      { key: "status", label: "Status", sortable: false, render: row => ... }
 *    ],
 *    data: ordersArray,
 *    searchInput: "#searchOrder",       // optional input selector
 *    searchFields: ["symbol","label"],  // optional columns to search
 *    pagination: { enabled: true, pageSize: 20 },
 *    onRowClick: row => { ... }         // optional row click callback
 *  });
 *
 * Author: CashCue
 */

class CashCueTable {

  constructor(config) {
    // --- Mandatory ---
    this.containerId = config.containerId;
    this.columns = config.columns || [];
    this.originalData = Array.isArray(config.data) ? config.data : [];

    // --- Optional ---
    this.pagination = Object.assign({
      enabled: false,
      pageSize: 10
    }, config.pagination || {});

    this.onRowClick = typeof config.onRowClick === "function"
      ? config.onRowClick
      : null;

    this.searchInput = config.searchInput || null;        // selector for search input
    this.searchFields = Array.isArray(config.searchFields) ? config.searchFields : [];

    // --- Internal state ---
    this.currentSort = { key: null, type: null, direction: "asc" };
    this.currentPage = 1;
    this.filteredData = [...this.originalData];           // filtered + sorted view

    // Initialize table and optional search
    this.render();
    this._initSearch();
  }

  // Public method to update table data and re-render
  setData(data) {
    this.originalData = Array.isArray(data) ? data : [];
    this.currentPage = 1;
    this.filteredData = [...this.originalData];
    this.render();
  }

  // Main render method: applies search, sorting, pagination, and updates the DOM
  render() {
    const container = document.getElementById(this.containerId);
    if (!container) return;

    // Apply search + sorting
    let data = this._applySearch(this.originalData);
    data = this._applySorting(data);
    this.filteredData = data;

    const totalRows = data.length;

    if (this.pagination.enabled) {
      data = this._applyPagination(data);
    }

    container.innerHTML = "";
    container.appendChild(this._buildTable(data));

    if (this.pagination.enabled) {
      container.appendChild(this._buildPagination(totalRows));
    }
  }

  // --------------------------------------------------
  // applies current sorting state to the data and returns a new sorted array
  // --------------------------------------------------
  _applySorting(data) {
    if (!this.currentSort.key) return [...data];

    const { key, type, direction } = this.currentSort;

    return [...data].sort((a, b) => {
      let v1 = a[key];
      let v2 = b[key];

      if (type === "number") {
        v1 = parseFloat(v1); v2 = parseFloat(v2);
        v1 = isNaN(v1) ? -Infinity : v1;
        v2 = isNaN(v2) ? -Infinity : v2;
      } else if (type === "date") {
        v1 = new Date(v1).getTime();
        v2 = new Date(v2).getTime();
      } else {
        v1 = v1?.toString().toLowerCase() || "";
        v2 = v2?.toString().toLowerCase() || "";
      }

      if (v1 < v2) return direction === "asc" ? -1 : 1;
      if (v1 > v2) return direction === "asc" ? 1 : -1;
      return 0;
    });
  }

  // --------------------------------------------------
  // Search/filtering
  // - Filters original data based on search input and specified search fields
  // - Returns a new filtered array without mutating original data
  // --------------------------------------------------
  _initSearch() {
    if (!this.searchInput || !this.searchFields.length) return;

    const inputEl = document.querySelector(this.searchInput);
    if (!inputEl) return;

    inputEl.addEventListener("input", () => {
      this.currentPage = 1;  // reset pagination on search
      this.render();
    });
  }

  // Applies search filtering to the data based on current search input and specified fields
  _applySearch(data) {
    if (!this.searchInput || !this.searchFields.length) return [...data];

    const inputEl = document.querySelector(this.searchInput);
    if (!inputEl || !inputEl.value) return [...data];

    const q = inputEl.value.toLowerCase();

    return data.filter(row =>
      this.searchFields.some(key => String(row[key] ?? "").toLowerCase().includes(q))
    );
  }

  // --------------------------------------------------
  // Pagination
  // - Slices the sorted/filtered data array based on current page and page size
  // - Returns a new paginated array without mutating original data
  // --------------------------------------------------
  _applyPagination(data) {
    const start = (this.currentPage - 1) * this.pagination.pageSize;
    return data.slice(start, start + this.pagination.pageSize);
  }

  // --------------------------------------------------
  // Table construction
  // - Builds the table header and body based on column definitions and current data
  // - Uses Bootstrap classes for styling
  // --------------------------------------------------
  _buildTable(data) {
    const table = document.createElement("table");
    table.className = "table table-striped table-hover align-middle";

    table.appendChild(this._buildHeader());
    table.appendChild(this._buildBody(data));

    return table;
  }

  // Builds the table header with sortable columns and appropriate icons
  _buildHeader() {
    const thead = document.createElement("thead");
    thead.className = "table-dark";

    const tr = document.createElement("tr");

    this.columns.forEach(col => {
      const th = document.createElement("th");

      if (col.sortable) {
        th.classList.add("sortable");
        th.dataset.sortKey = col.key;
        th.dataset.sortType = col.type || "string";

        if (this.currentSort.key === col.key) {
          th.classList.add(`sorted-${this.currentSort.direction}`);
        }

        th.innerHTML = `
          <div class="th-content">
            <span class="th-label">${col.label}</span>
            <span class="sort-icons">
              <i class="bi bi-caret-up-fill"></i>
              <i class="bi bi-caret-down-fill"></i>
            </span>
          </div>
        `;

        th.onclick = () => this._onSortClick(col);
      } else {
        th.textContent = col.label;
      }

      tr.appendChild(th);
    });

    thead.appendChild(tr);
    return thead;
  }

  // Builds the table body by iterating over the data and applying column renderers if provided
  _buildBody(data) {
    const tbody = document.createElement("tbody");

    data.forEach(row => {
      const tr = document.createElement("tr");

      if (this.onRowClick) {
        tr.style.cursor = "pointer";
        tr.onclick = () => this.onRowClick(row);
      }

      this.columns.forEach(col => {
        const td = document.createElement("td");

        if (typeof col.render === "function") {
          td.innerHTML = col.render(row);
        } else {
          td.textContent = row[col.key] ?? "";
        }

        tr.appendChild(td);
      });

      tbody.appendChild(tr);
    });

    return tbody;
  }

  // --------------------------------------------------
  // Pagination controls construction
  // - Builds pagination navigation based on total rows and page size
  // - Adds event listeners to page links to update current page and re-render
  // --------------------------------------------------
  _buildPagination(totalRows) {
    const totalPages = Math.ceil(totalRows / this.pagination.pageSize);
    if (totalPages <= 1) return document.createElement("div");

    const nav = document.createElement("nav");
    const ul = document.createElement("ul");
    ul.className = "pagination justify-content-center mt-2";

    for (let p = 1; p <= totalPages; p++) {
      const li = document.createElement("li");
      li.className = `page-item ${p === this.currentPage ? "active" : ""}`;

      const a = document.createElement("a");
      a.className = "page-link";
      a.href = "#";
      a.textContent = p;

      a.onclick = e => {
        e.preventDefault();
        this.currentPage = p;
        this.render();
      };

      li.appendChild(a);
      ul.appendChild(li);
    }

    nav.appendChild(ul);
    return nav;
  }

  // --------------------------------------------------
  // Sort click handler
  // - Updates current sort state based on clicked column and toggles direction
  // - Calls render to update the table display
  // --------------------------------------------------
  _onSortClick(col) {
    if (this.currentSort.key === col.key) {
      this.currentSort.direction =
        this.currentSort.direction === "asc" ? "desc" : "asc";
    } else {
      this.currentSort.key = col.key;
      this.currentSort.type = col.type || "string";
      this.currentSort.direction = "asc";
    }

    this.render();
  }
}
