// assets/js/dashboard.js

console.log("dashboard.js loaded");

// ---------------------------------------------------
// Prepare Variables for Realtime prices tables sorting
// ---------------------------------------------------
let realtimePrices = [];
let currentSort = {
  key: null,
  type: null,
  direction: 'asc'
};

/**
 * Initialize sorting for the Realtime Prices table using event delegation.
 *
 * Listens for click events on the table container and reacts
 * only when a sortable table header (<th.sortable>) is clicked.
 *
 * This approach is robust against DOM re-rendering and works
 * correctly inside Bootstrap .table-responsive containers.
 */
function initRealtimeTableSorting() {
  const container = document.getElementById("realtimeTableContainer");

  if (!container) return;

  container.onclick = (event) => {
    const th = event.target.closest("th.sortable");

    if (!th) return;

    console.log("🔥 SORT CLICK on", th.dataset.sortKey);

    const key = th.dataset.sortKey;
    const type = th.dataset.sortType;

    if (!key) return;

    // Toggle sort direction
    if (currentSort.key === key) {
      currentSort.direction = currentSort.direction === "asc" ? "desc" : "asc";
    } else {
      currentSort.key = key;
      currentSort.type = type;
      currentSort.direction = "asc";
    }

    // Reset icons
    container
      .querySelectorAll("th.sortable")
      .forEach(h => h.classList.remove("sorted-asc", "sorted-desc"));

    // Apply active sort class
    th.classList.add(`sorted-${currentSort.direction}`);

    // Sort in memory
    realtimePrices = sortRealtimePrices(
      realtimePrices,
      currentSort.key,
      currentSort.type,
      currentSort.direction
    );

    // Re-render table
    renderRealtimePricesTable(realtimePrices);
  };
}


/**
 * Sort realtime prices dataset in memory.
 *
 * @param {Array<Object>} data
 * @param {string} key
 * @param {string} type  "string" | "number"
 * @param {string} direction "asc" | "desc"
 * @returns {Array<Object>} sorted copy of data
 */
function sortRealtimePrices(data, key, type, direction) {
  return [...data].sort((a, b) => {
    let v1 = a[key];
    let v2 = b[key];

    if (type === "number") {
      v1 = parseFloat(v1);
      v2 = parseFloat(v2);
      v1 = isNaN(v1) ? -Infinity : v1;
      v2 = isNaN(v2) ? -Infinity : v2;
    } else {
      v1 = v1?.toString().toLowerCase() || "";
      v2 = v2?.toString().toLowerCase() || "";
    }

    if (v1 < v2) return direction === "asc" ? -1 : 1;
    if (v1 > v2) return direction === "asc" ? 1 : -1;
    return 0;
  });
}

/**
 * Load realtime instrument prices (with full debug).
 *
 * This function is responsible for:
 * - Fetching realtime prices from the backend API
 * - Performing basic response validation and error handling
 * - Storing the received data as the canonical in-memory dataset
 * - Delegating all HTML rendering to renderRealtimePricesTable()
 *
 * It does NOT build or manipulate table markup directly.
 */
function loadRealtimePrices(accountId) {
  const container = document.getElementById("realtimeTableContainer");
  if (!container) {
    console.warn("Realtime container not found in DOM");
    return;
  }

  console.log("▶ loadRealtimePrices() called with accountId =", accountId);

  container.innerHTML = "<p class='text-muted'>Loading realtime prices...</p>";

  const url = `/cashcue/api/getRealtimeData.php?broker_account_id=${accountId}`;
  console.log("▶ Fetching URL:", url);

  fetch(url)
    .then(resp => {
      console.log("▶ Raw fetch response:", resp);
      return resp.json();
    })
    .then(data => {
      console.log("▶ Parsed JSON received from getRealtimeData.php:", data);

      if (!data) {
        console.error("❌ ERROR: No JSON data received from backend!");
        container.innerHTML = "<p class='text-danger'>Empty response from server.</p>";
        return;
      }

      if (data.status !== "success") {
        console.error("❌ Backend reported error:", data.message);
        container.innerHTML = `<p class='text-danger'>Server error: ${data.message}</p>`;
        return;
      }

      if (!data.data || data.data.length === 0) {
        console.warn("⚠ No realtime data returned.");
        container.innerHTML = "<p class='text-muted'>No realtime data available.</p>";
        return;
      }

      console.log(`✔ ${data.data.length} realtime entries received.`);

      // --------------------------------------------------
      // Store canonical realtime dataset in memory
      // --------------------------------------------------
      realtimePrices = data.data;
      renderRealtimePricesTable(realtimePrices);
      initRealtimeTableSorting(); // ✅ table EXISTE maintenant

      // --------------------------------------------------
      // Delegate rendering to the dedicated render function
      // --------------------------------------------------
      renderRealtimePricesTable(realtimePrices);

      console.log("✔ Realtime prices fully loaded and rendered.");
    })
    .catch(err => {
      console.error("❌ Exception during fetch:", err);
      container.innerHTML =
        `<p class="text-danger">Error loading realtime data: ${err}</p>`;
    });
}

/**
 * Render the Realtime Prices table in the dashboard.
 *
 * This function is responsible ONLY for rendering the HTML table
 * from an already-loaded dataset. It does NOT perform any data fetching.
 *
 * Responsibilities:
 * - Build the realtime prices table markup
 * - Safely format numeric values (price, percentage change)
 * - Apply visual indicators (green/red) for positive/negative changes
 * - Attach click handlers on instrument rows to load the price chart
 *
 * Data contract (expected fields per row):
 * - instrument_id   (number)
 * - symbol          (string)
 * - label           (string)
 * - price           (number|string)
 * - currency        (string)
 * - pct_change      (number|string|null)
 * - captured_at     (datetime string)
 *
 * @param {Array<Object>} data
 *   Array of realtime instrument price objects returned by the backend.
 */
function renderRealtimePricesTable(data) {
  const container = document.getElementById("realtimeTableContainer");
  if (!container) return;

  const table = document.createElement("table");
  table.className = "table table-striped table-hover align-middle";

  table.innerHTML = `
    <thead class="table-dark">
      <tr>
        <th class="sortable ${currentSort.key === 'symbol' ? 'sorted-' + currentSort.direction : ''}" data-sort-key="symbol" data-sort-type="string">
        <div class="th-content">
          <span class="th-label">Symbol</span>
          <span class="sort-icons">
            <i class="bi bi-caret-up-fill"></i>
            <i class="bi bi-caret-down-fill"></i>
          </span>
        </div>
        </th>
        <th class="sortable ${currentSort.key === 'label' ? 'sorted-' + currentSort.direction : ''}" data-sort-key="label" data-sort-type="string">
        <div class="th-content">
          <span class="th-label">Label</span>
          <span class="sort-icons">
            <i class="bi bi-caret-up-fill"></i>
            <i class="bi bi-caret-down-fill"></i>
          </span>
        </div>
        </th>
        <th class="sortable ${currentSort.key === 'price' ? 'sorted-' + currentSort.direction : ''}" data-sort-key="price" data-sort-type="number">
        <div class="th-content">
          <span class="th-label">Price</span>
          <span class="sort-icons">
            <i class="bi bi-caret-up-fill"></i>
            <i class="bi bi-caret-down-fill"></i>
          </span>
        </div>
        </th>
        <th class="sortable ${currentSort.key === 'pct_change' ? 'sorted-' + currentSort.direction : ''}" data-sort-key="pct_change" data-sort-type="number">
        <div class="th-content">
          <span class="th-label">Change (%)</span>
          <span class="sort-icons">
            <i class="bi bi-caret-up-fill"></i>
            <i class="bi bi-caret-down-fill"></i>
          </span>
        </div>
        </th>
        <th>Updated</th>
      </tr>
    </thead>
    <tbody>
      ${data.map(row => {
        let pct = parseFloat(row.pct_change);
        const hasPct = !isNaN(pct);
        const pctFormatted = hasPct ? pct.toFixed(2) + '%' : '-';
        const pctClass = hasPct
          ? (pct >= 0 ? 'text-success' : 'text-danger')
          : '';

        return `
          <tr class="instrument-row" data-id="${row.instrument_id}" style="cursor:pointer;">
            <td>${row.symbol}</td>
            <td>${row.label}</td>
            <td>${parseFloat(row.price).toFixed(3)} ${row.currency}</td>
            <td class="${pctClass}">${pctFormatted}</td>
            <td>${row.captured_at}</td>
          </tr>`;
      }).join("")}
    </tbody>
  `;

  container.innerHTML = "";
  container.appendChild(table);

  // Click on row → show chart
  document.querySelectorAll(".instrument-row").forEach(row => {
    row.addEventListener("click", () => {
      const id = row.dataset.id;
      const name = row.children[1].textContent;
      loadInstrumentChart(id, name);
    });
  });
}


// ---------------------------------------------------
//  Helpers
// ---------------------------------------------------
function reloadDashboardData() {
    const accountId = getActiveBrokerAccountId();

    console.log("Dashboard: reload with accountId =", accountId);

    loadPortfolioSummary(accountId);
    loadPortfolioHistory(accountId);
    loadRealtimePrices(accountId);
    loadLastOrders(accountId);
}

// ---------------------------------------------------
//  DOM Ready
// ---------------------------------------------------
document.addEventListener("DOMContentLoaded", async () => {
    console.log("dashboard.js: DOM ready");

    // Attendre que header.js ait fini de charger la liste des comptes
    await waitForBrokerSelector();
    console.log("dashboard.js: Broker selector is ready");

    // Chargement initial du dashboard
    reloadDashboardData();

    // Recharger quand l'utilisateur change de broker
    onBrokerAccountChange((newId) => {
        console.log("dashboard.js: broker changed →", newId);
        reloadDashboardData();
    });
});





/**
 * Load recent orders
 */
// ---- Load Last Orders ----
async function loadLastOrders(accountId) {
  try {
    const res = await fetch(`/cashcue/api/getOrders.php?broker_account_id=${accountId}`);
    const json = await res.json();

    if (json.status !== "success") throw new Error(json.message);
    const tableBody = document.getElementById("ordersTableBody");

    console.log("LOAD LAST ORDERS with accountId:", accountId, json);

    if (!tableBody) return;
    tableBody.innerHTML = "";

    if (!json.data || json.data.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="7" class="text-center text-muted">No recent orders</td></tr>`;
      return;
    }

    json.data.forEach(o => {
      const typeClass = o.order_type === "BUY" ? "text-success" : "text-danger";
      const row = document.createElement("tr");

      row.innerHTML = `
        <td>${o.symbol}</td>
        <td class="${typeClass} fw-bold">${o.order_type}</td>
        <td>${Number(o.quantity || 0).toFixed(2)}</td>
        <td>${parseFloat(o.price).toFixed(4)}</td>
        <td>${o.trade_date}</td>
      `;

      tableBody.appendChild(row);
    });
  } catch (err) {
    console.error("Error loading last orders:", err);
    const tableBody = document.getElementById("lastOrdersBody");
    if (tableBody)
      tableBody.innerHTML = `<tr><td colspan="7" class="text-danger text-center">Failed to load last orders</td></tr>`;
  }
}

function loadInstrumentChart(instrument_id, label) {
  console.log("Loading chart for instrument:", instrument_id);

  fetch(`/cashcue/api/getInstrumentHistory.php?instrument_id=${instrument_id}`)
    .then(response => response.json())
    .then(json => {
      const chartCard = document.getElementById('instrumentChartCard');
      const chartTitle = document.getElementById('instrumentChartTitle');
      const chartDiv = document.getElementById('instrumentChart');

      // Rendre visible AVANT initialisation du chart
      chartCard.style.display = 'block';
      chartTitle.textContent = `${label} — Intraday Price`;

      if (!json.data || json.data.length === 0) {
        const chart = echarts.init(chartDiv);
        chart.setOption({
          title: { text: `No intraday data for ${label}`, left: 'center' }
        });
        return;
      }

      const times = json.data.map(p => p.captured_at);
      const prices = json.data.map(p => parseFloat(p.price));

      console.log("Instrument history data prices ", prices);

      // IMPORTANT : laisser le navigateur recalculer la taille
      setTimeout(() => {
        const chart = echarts.init(chartDiv);

        chart.setOption({
          tooltip: { trigger: 'axis' },
          xAxis: { type: 'category', data: times },
          yAxis: { type: 'value', scale: true },
          series: [{
            data: prices,
            type: 'line',
            smooth: true
          }]
        });
      }, 50);
    })
    .catch(err => console.error('Chart load error:', err));
}


// ---- Portfolio Summary ----
async function loadPortfolioSummary(accountId) {
  try {
    const res = await fetch(`/cashcue/api/getPortfolioSummary.php?range=10&broker_account_id=${accountId}`);
    const json = await res.json();

    if (!json || json.status !== 'success') {
      console.error('Summary API failed:', json && json.message ? json.message : 'no response');
      return;
    }

    const d = json.data;

    // Defensive parse helpers
    const toNum = v => {
      if (v === null || v === undefined || v === '') return 0;
      const n = Number(v);
      return Number.isFinite(n) ? n : 0;
    };

    // Format as Euro
    const formatEUR = v => new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(toNum(v));

    // Populate DOM (guarding for missing elements)
    const setText = (id, text) => {
      const el = document.getElementById(id);
      if (el) el.textContent = text;
    };

    setText('totalValue', formatEUR(d.total_value));
    setText('investedAmount', formatEUR(d.invested_amount));
    setText('unrealizedPL', formatEUR(d.unrealized_pl));
    setText('realizedPL', formatEUR(d.realized_pl));
    setText('dividendsGross', formatEUR(d.dividends_gross));
    setText('dividendsNet', formatEUR(d.dividends_net));
    setText('cashBalance', formatEUR(d.cash_balance));

  } catch (err) {
    console.error('Summary fetch error:', err);
  }
}



// ---- Portfolio Value Over Time ----
async function loadPortfolioHistory(accountId) {
  try {
    const res = await fetch(`/cashcue/api/getPortfolioHistory.php?broker_account_id=${accountId}`);
    const json = await res.json();

    if (json.status !== "success" || !json.data?.length) {
      console.warn("No portfolio history found.");
      return;
    }

    const dates = json.data.map(d => d.date);
    const invested = json.data.map(d => parseFloat(d.invested || 0));
    const portfolio = json.data.map(d => parseFloat(d.portfolio || 0));

    const chartDom = document.getElementById("portfolioChart");
    if (!chartDom) {
      console.log("getElementById portfolioChart not found!");  
      return;
    }
    const chart = echarts.init(chartDom);

    const option = {
      title: {
        text: "Portfolio Value Over Time",
        left: "center",
        textStyle: { fontSize: 16, fontWeight: "bold" }
      },
      tooltip: {
        trigger: "axis",
        formatter: params => {
          let html = `<strong>${params[0].axisValue}</strong><br/>`;
          params.forEach(p => {
            const value = p.data?.toLocaleString("fr-FR", {
              style: "currency",
              currency: "EUR"
            });
            html += `${p.marker} ${p.seriesName}: <b>${value}</b><br/>`;
          });
          return html;
        },
        backgroundColor: "#fff",
        borderColor: "#ccc",
        borderWidth: 1,
        textStyle: { color: "#000" }
      },
      legend: {
        bottom: 0,
        data: ["Invested (€)", "Portfolio (€)"]
      },
      grid: { top: 60, left: 70, right: 30, bottom: 60 },
      xAxis: {
        type: "category",
        data: dates,
        axisLabel: { rotate: 30 }
      },
      yAxis: {
        type: "value",
        axisLabel: {
          formatter: val =>
            val.toLocaleString("fr-FR", {
              style: "currency",
              currency: "EUR",
              maximumFractionDigits: 0
            })
        },
        name: "Value (€)"
      },
      series: [
        {
          name: "Invested (€)",
          type: "line",
          data: invested,
          smooth: true,
          symbol: "circle",
          itemStyle: { color: "#007bff" },
          lineStyle: { width: 2 }
        },
        {
          name: "Portfolio (€)",
          type: "line",
          data: portfolio,
          smooth: true,
          symbol: "circle",
          itemStyle: { color: "#28a745" },
          lineStyle: { width: 2 }
        }
      ]
    };

    chart.setOption(option);
    window.addEventListener("resize", chart.resize);
  } catch (err) {
    console.error("Portfolio history fetch error:", err);
  }
}

