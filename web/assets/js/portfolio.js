/**
 * portfolio.js
 * ============
 * Portfolio view controller for CashCue.
 *
 * Responsibilities:
 *  - Display current holdings using CashCueTable
 *  - Load holdings from API based on active broker_account
 *  - Display portfolio history chart (invested vs portfolio value)
 *
 * Interfaces:
 *  - window.CashCueAppContext.getBrokerAccountId()
 *  - window.CashCueAppContext.waitForBrokerAccount()
 *  - "brokerAccountChanged" custom DOM event
 *
 * Notes:
 *  - Holdings table is fully client-side (sorting / pagination)
 *  - Portfolio history chart relies on ECharts
 *  - No business logic is embedded here
 *
 * Author: CashCue
 */

console.log("portfolio.js loaded");

// ------------------------------------------------------------
// GLOBAL VARIABLES
// ------------------------------------------------------------ 
let holdingsTable = null;

// ------------------------------------------------------------
// HOLDINGS TABLE (CashCueTable)
// ------------------------------------------------------------
function initHoldingsTable() {
  holdingsTable = new CashCueTable({
    containerId: "holdingsTableContainer",
    pagination: { enabled: true, pageSize: 10 },
    columns: [
      { key: "symbol", label: "Symbol", sortable: true },
      { key: "label", label: "Label", sortable: true },
      {
        key: "total_qty",
        label: "Quantity",
        sortable: true,
        render: row => Number(row.total_qty).toLocaleString()
      },
      {
        key: "avg_buy_price",
        label: "Avg. Buy Price (€)",
        sortable: true,
        type: "number",
        render: row => parseFloat(row.avg_buy_price).toFixed(4)
      },
      {
        key: "last_price",
        label: "Last Price (€)",
        sortable: true,
        type: "number",
        render: row => parseFloat(row.last_price).toFixed(4)
      },
      {
        key: "current_value",
        label: "Value (€)",
        sortable: true,
        type: "number",
        render: row => parseFloat(row.current_value).toFixed(2)
      },
      {
        key: "unrealized_pl",
        label: "Unrealized P/L (€)",
        sortable: true,
        type: "number",
        render: row => {
          const v = parseFloat(row.unrealized_pl);
          const cls = v >= 0 ? "text-success" : "text-danger";
          return `<span class="${cls}">${v.toFixed(2)}</span>`;
        }
      },
      {
        key: "unrealized_pl_pct",
        label: "Unrealized P/L (%)",
        sortable: true,
        type: "number",
        render: row => {
          const v = parseFloat(row.unrealized_pl_pct);
          const cls = v >= 0 ? "text-success" : "text-danger";
          return `<span class="${cls}">${v.toFixed(2)}%</span>`;
        }
      }
    ]
  });
}

// ------------------------------------------------------------
// Load holdings data from API and populate the table.
// Triggered on page load and when broker account changes.
// ------------------------------------------------------------
async function loadHoldings() {
  if (!holdingsTable) return;

  const brokerId = window.CashCueAppContext.getBrokerAccountId();
  if (!brokerId) return;

  try {
    const res = await fetch(`/cashcue/api/getHoldings.php?broker_account_id=${brokerId}`);
    const json = await res.json();

    if (json.status !== "success") {
      console.error("Holdings API error:", json.message);
      holdingsTable.setData([]);
      showAlert("danger", `Failed to load holdings: ${json.message || "Unknown error"}`);
      return;
    }

    holdingsTable.setData(json.data);

  } catch (err) {
    console.error("Holdings fetch error:", err);
    showAlert("danger", "Failed to load holdings. Please try again.");
    holdingsTable.setData([]);
  }
}

// ------------------------------------------------------------
// PORTFOLIO HISTORY CHART
// ------------------------------------------------------------
let portfolioChartInstance = null;

/**
 * Load and render portfolio history chart.
 * Shows:
 *  - Daily invested (bars)
 *  - Cumulative invested (line)
 *  - Portfolio value (line)
 */
async function loadPortfolioHistory() {
  const rangeSelect = document.getElementById("historyRange");
  const range = rangeSelect ? rangeSelect.value : 30;

  const brokerId = window.CashCueAppContext.getBrokerAccountId();
  const chartDom = document.getElementById("portfolioHistoryChart");
  if (!chartDom || !brokerId) return;

  console.log(`Loading portfolio history for range: ${range} days, broker_account_id: ${brokerId}`);

  try {
    const res = await fetch(
      `/cashcue/api/getPortfolioHistory.php?range=${range}&broker_account_id=${brokerId}`
    );
    const json = await res.json();

    if (json.status !== "success") {
      showAlert("danger", `Failed to load portfolio history: ${json.message || "Unknown error"}`);
      throw new Error(json.message || "API error");
    }

    const data = json.data;

    const dates = data.map(r => r.date);
    const dailyInvested = data.map(r => parseFloat(r.daily_invested));
    const cumulativeInvested = data.map(r => parseFloat(r.cum_invested));
    const portfolioValue = data.map(r => parseFloat(r.portfolio));

    const barColors = dailyInvested.map(v => v >= 0 ? "#28a745" : "#dc3545");

    if (!portfolioChartInstance) {
      portfolioChartInstance = echarts.init(chartDom);
      window.addEventListener("resize", () => portfolioChartInstance.resize());
    }

    const option = {
      tooltip: {
        trigger: "axis",
        backgroundColor: "rgba(50,50,50,0.9)",
        borderWidth: 0,
        textStyle: { color: "#fff" },
        formatter: params => {
          const date = params[0].axisValue;
          const daily = params.find(p => p.seriesName === "Daily Invested");
          const cum = params.find(p => p.seriesName === "Cumulative Invested");
          const port = params.find(p => p.seriesName === "Portfolio Value");

          return `
            <strong>${date}</strong><br/>
            ${daily ? `Daily Invested: <span style="color:${daily.color}">${daily.value.toFixed(2)} €</span><br/>` : ""}
            ${cum ? `Cumulative Invested: <span style="color:${cum.color}">${cum.value.toFixed(2)} €</span><br/>` : ""}
            ${port ? `Portfolio Value: <span style="color:${port.color}">${port.value.toFixed(2)} €</span>` : ""}
          `;
        }
      },
      legend: {
        data: ["Daily Invested", "Cumulative Invested", "Portfolio Value"]
      },
      xAxis: {
        type: "category",
        data: dates,
        axisLabel: { rotate: 45 }
      },
      yAxis: [
        { type: "value", name: "Portfolio (€)", position: "left" },
        {
          type: "value",
          name: "Daily Invested (€)",
          position: "right",
          axisLine: { show: true },
          splitLine: { show: false }
        }
        
      ],
      series: [
        {
          name: "Daily Invested",
          type: "bar",
          yAxisIndex: 1,
          data: dailyInvested,
          barWidth: "50%",
          color: "#28a745",   // couleur officielle série
          itemStyle: {
            color: params => barColors[params.dataIndex]
          }
        },
        {
          name: "Cumulative Invested",
          type: "line",
          data: cumulativeInvested,
          smooth: true,
          showSymbol: false,
          color: "#007bff",
          lineStyle: { width: 2 }
        },
        {
          name: "Portfolio Value",
          type: "line",
          data: portfolioValue,
          smooth: true,
          showSymbol: false,
          color: "#ffc107",
          lineStyle: { width: 2 }
        }
      ]
    };

    portfolioChartInstance.setOption(option);

  } catch (err) {
    showAlert("danger", "Failed to load portfolio history. Please try again.");
    console.error("Portfolio history fetch error:", err);
  }
}

// ------------------------------------------------------------
// INITIALIZATION
// ------------------------------------------------------------
document.addEventListener("DOMContentLoaded", async () => {
  // Wait for broker_account_id to be available
  await window.CashCueAppContext.waitForBrokerAccount();

  // Init UI components
  initHoldingsTable();

  // Initial load
  loadHoldings();
  loadPortfolioHistory();

  // Reload on broker change
  document.addEventListener("brokerAccountChanged", () => {
    console.log("portfolio.js: brokerAccountChanged");
    loadHoldings();
    loadPortfolioHistory();
  });

  // History range selector (chart only)
  const rangeSelect = document.getElementById("historyRange");
  if (rangeSelect) {
    rangeSelect.addEventListener("change", loadPortfolioHistory);
  }
});