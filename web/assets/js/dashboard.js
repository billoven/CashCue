/// assets/js/dashboard.js

console.log("dashboard.js loaded");

// ---------------------------------------------------
// Realtime Prices Table (CashCueTable)
// ---------------------------------------------------
let realtimeTable = null;

/**
 * Initialize Realtime Prices table (once).
 * Sorting and pagination are delegated to CashCueTable.
 */
function initRealtimePricesTable() {
  realtimeTable = new CashCueTable({
    containerId: "realtimeTableContainer",
    searchInput: "#searchInstruments",  // link to search input box
    searchFields: ["symbol", "label"],   // <-- added for live search on these columns
    columns: [
      {
        key: "symbol",
        label: "Symbol",
        sortable: true,
        type: "string"
      },
      {
        key: "label",
        label: "Label",
        sortable: true,
        type: "string"
      },
      {
        key: "price",
        label: "Price",
        sortable: true,
        type: "number",
        render: row =>
          `${parseFloat(row.price).toFixed(3)} ${row.currency}`
      },
      {
        key: "pct_change",
        label: "Change (%)",
        sortable: true,
        type: "number",
        render: row => {
          const v = parseFloat(row.pct_change);
          if (isNaN(v)) return "-";
          return `<span class="${v >= 0 ? "text-success" : "text-danger"}">${v.toFixed(2)}%</span>`;
        }
      },
      {
        key: "captured_at",
        label: "Updated",
        sortable: true,
        type: "date"
      },
      {
        key: "status",
        label: "Status",
        sortable: false,
        html: true,
        render: row => {
          switch (row.status) {
            case "ACTIVE":
              return '<span class="badge bg-success">ACTIVE</span>';
            case "INACTIVE":
              return '<span class="badge bg-secondary">INACTIVE</span>';
            case "SUSPENDED":
              return '<span class="badge bg-warning text-dark">SUSPENDED</span>';
            default:
              return '<span class="badge bg-light text-dark">UNKNOWN</span>';
          }
        }
      }
    ],

    pagination: {
      enabled: true,
      pageSize: 15
    },

    onRowClick: row => {
      loadInstrumentChart(row.instrument_id, row.label);
    }
  });
}

/**
 * Load realtime instrument prices.
 * Fetches data and delegates rendering to CashCueTable.
 */
function loadRealtimePrices(accountId) {
  const container = document.getElementById("realtimeTableContainer");
  if (!container || !realtimeTable) return;

  container.innerHTML = "<p class='text-muted'>Loading realtime prices...</p>";

  fetch(`/cashcue/api/getRealtimeData.php?broker_account_id=${accountId}`)
    .then(resp => resp.json())
    .then(json => {
      if (!json || json.status !== "success") {
        container.innerHTML =
          `<p class="text-danger">${json?.message || "Server error"}</p>`;
        return;
      }

      if (!json.data || json.data.length === 0) {
        container.innerHTML =
          "<p class='text-muted'>No realtime data available.</p>";
        return;
      }

      realtimeTable.setData(json.data);
    })
    .catch(err => {
      console.error("Realtime prices load error:", err);
      container.innerHTML =
        "<p class='text-danger'>Failed to load realtime prices.</p>";
    });
}

// ---------------------------------------------------
// Helpers
// ---------------------------------------------------
function reloadDashboardData() {
  const accountId = window.CashCueAppContext.getBrokerAccountId();

  if (!accountId) {
    console.warn("Dashboard: brokerAccountId not ready");
    return;
  }

  console.log("Dashboard reload with accountId =", accountId);

  loadPortfolioSummary(accountId);
  loadPortfolioHistory(accountId);
  loadRealtimePrices(accountId);
  loadLastOrders(accountId);
}

// ---------------------------------------------------
// DOM Ready
// ---------------------------------------------------
document.addEventListener("DOMContentLoaded", async () => {
  console.log("dashboard.js: DOM ready");

  initRealtimePricesTable();

  await window.CashCueAppContext.waitForBrokerAccount();
  reloadDashboardData();

  document.addEventListener("brokerAccountChanged", () => {
    reloadDashboardData();
  });
});

// ---------------------------------------------------
// Last Orders
// ---------------------------------------------------
async function loadLastOrders(accountId) {
  try {
    const res = await fetch(`/cashcue/api/getOrders.php?broker_account_id=${accountId}`);
    const json = await res.json();

    if (json.status !== "success") throw new Error(json.message);

    const tableBody = document.getElementById("ordersTableBody");
    if (!tableBody) return;

    tableBody.innerHTML = "";

    if (!json.data || json.data.length === 0) {
      tableBody.innerHTML =
        `<tr><td colspan="7" class="text-center text-muted">No recent orders</td></tr>`;
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
  }
}

// ---------------------------------------------------
// Instrument Chart
// ---------------------------------------------------
function loadInstrumentChart(instrument_id, label) {
  fetch(`/cashcue/api/getInstrumentHistory.php?instrument_id=${instrument_id}`)
    .then(r => r.json())
    .then(json => {
      const chartCard = document.getElementById("instrumentChartCard");
      const chartTitle = document.getElementById("instrumentChartTitle");
      const chartDiv = document.getElementById("instrumentChart");

      chartCard.style.display = "block";
      chartTitle.textContent = `${label} — Intraday Price`;

      if (!json.data || json.data.length === 0) {
        echarts.init(chartDiv).setOption({
          title: { text: `No intraday data for ${label}`, left: "center" }
        });
        return;
      }

      const times = json.data.map(p => p.captured_at);
      const prices = json.data.map(p => parseFloat(p.price));

      setTimeout(() => {
        echarts.init(chartDiv).setOption({
          tooltip: { trigger: "axis" },
          xAxis: { type: "category", data: times },
          yAxis: { type: "value", scale: true },
          series: [{ data: prices, type: "line", smooth: true }]
        });
      }, 50);
    })
    .catch(err => console.error("Chart load error:", err));
}

// ---------------------------------------------------
// Portfolio Summary
// ---------------------------------------------------
async function loadPortfolioSummary(accountId) {
  try {
    const res = await fetch(`/cashcue/api/getPortfolioSummary.php?range=10&broker_account_id=${accountId}`);
    const json = await res.json();

    if (!json || json.status !== "success") return;

    const d = json.data;
    const formatEUR = v =>
      new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: "EUR",
        minimumFractionDigits: 2
      }).format(Number(v || 0));

    const setText = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };

    setText("totalValue", formatEUR(d.total_value));
    setText("investedAmount", formatEUR(d.invested_amount));
    setText("unrealizedPL", formatEUR(d.unrealized_pl));
    setText("realizedPL", formatEUR(d.realized_pl));
    setText("dividendsGross", formatEUR(d.dividends_gross));
    setText("dividendsNet", formatEUR(d.dividends_net));
    setText("cashBalance", formatEUR(d.cash_balance));
  } catch (err) {
    console.error("Summary fetch error:", err);
  }
}

// ---------------------------------------------------
// Portfolio History
// ---------------------------------------------------
async function loadPortfolioHistory(accountId) {
  try {
    const res = await fetch(`/cashcue/api/getPortfolioHistory.php?broker_account_id=${accountId}`);
    const json = await res.json();

    if (json.status !== "success" || !json.data?.length) return;

    const chartDom = document.getElementById("portfolioChart");
    if (!chartDom) return;

    const dates = json.data.map(d => d.date);
    const invested = json.data.map(d => +d.invested || 0);
    const portfolio = json.data.map(d => +d.portfolio || 0);

    const chart = echarts.init(chartDom);
    chart.setOption({
      tooltip: { trigger: "axis" },
      legend: { bottom: 0 },
      xAxis: { type: "category", data: dates },
      yAxis: { type: "value" },
      series: [
        { name: "Invested (€)", data: invested, type: "line", smooth: true },
        { name: "Portfolio (€)", data: portfolio, type: "line", smooth: true }
      ]
    });

    window.addEventListener("resize", chart.resize);
  } catch (err) {
    console.error("Portfolio history fetch error:", err);
  }
}
