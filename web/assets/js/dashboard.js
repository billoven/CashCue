// assets/js/dashboard.js
document.addEventListener("DOMContentLoaded", () => {
  loadPortfolioSummary();   // 🔹 new
  loadPortfolioHistory();
  loadRealtimePrices();
  loadLastOrders();

  // Refresh button
  const refreshBtn = document.getElementById("refreshRealtime");
  if (refreshBtn) refreshBtn.addEventListener("click", loadRealtimePrices);
});

/**
 * Load realtime instrument prices
 */
function loadRealtimePrices() {
  const container = document.getElementById("realtimeTableContainer");
  if (!container) return;
  container.innerHTML = "<p class='text-muted'>Loading realtime prices...</p>";

  fetch("/cashcue/api/getRealtimeData.php")
    .then(resp => resp.json())
    .then(data => {
      if (data.status !== "success" || !data.data || data.data.length === 0) {
        container.innerHTML = "<p class='text-muted'>No realtime data available.</p>";
        return;
      }

      const table = document.createElement("table");
      table.className = "table table-striped table-hover align-middle";
      table.innerHTML = `
        <thead class="table-dark">
          <tr>
            <th>Symbol</th>
            <th>Label</th>
            <th>Price</th>
            <th>Change (%)</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          ${data.data.map(row => `
            <tr class="instrument-row" data-id="${row.instrument_id}" style="cursor:pointer;">
              <td>${row.symbol}</td>
              <td>${row.label}</td>
              <td>${parseFloat(row.price).toFixed(2)} ${row.currency}</td>
              <td class="${row.pct_change >= 0 ? 'text-success' : 'text-danger'}">
                ${row.pct_change != null ? row.pct_change.toFixed(2) + '%' : '-'}
              </td>
              <td>${row.captured_at}</td>
            </tr>`).join("")}
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
    })
    .catch(err => {
      container.innerHTML = `<p class="text-danger">Error loading realtime data: ${err}</p>`;
    });
}

/**
 * Load recent orders
 */
// ---- Load Last Orders ----
async function loadLastOrders() {
  try {
    const res = await fetch("/cashcue/api/getOrders.php");
    const json = await res.json();

    if (json.status !== "success") throw new Error(json.message);
    const tableBody = document.getElementById("ordersTableBody");

    console.log("LOAD LAST ORDERS");

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
        <td>${o.label}</td>
        <td class="${typeClass} fw-bold">${o.order_type}</td>
        <td>${Number(o.quantity || 0).toFixed(2)}</td>
        <td>${parseFloat(o.price).toFixed(4)}</td>
        <td>${parseFloat(o.fees ?? 0).toFixed(2)}</td>
        <td>${parseFloat(o.total).toFixed(2)}</td>
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
      const chart = echarts.init(chartDiv);
      chart.clear();

      chartCard.style.display = 'block';
      chartTitle.textContent = `${label} — Intraday Price`;

      if (!json.data || json.data.length === 0) {
        chart.setOption({
          title: { text: `No intraday data for ${label}`, left: 'center' }
        });
        return;
      }

      const times = json.data.map(p => p.captured_at);
      const prices = json.data.map(p => parseFloat(p.price));

      chart.setOption({
        tooltip: { trigger: 'axis' },
        xAxis: { type: 'category', data: times },
        yAxis: { type: 'value', scale: true },
        series: [{
          data: prices,
          type: 'line',
          smooth: true,
          color: '#007bff'
        }]
      });
    })
    .catch(err => console.error('Chart load error:', err));
}

// ---- Portfolio Summary ----
async function loadPortfolioSummary() {
  fetch('/cashcue/api/getPortfolioSummary.php')
    .then(res => res.json())
    .then(json => {
      if (json.status !== 'success') {
        console.error('Summary API failed:', json.message);
        return;
      }
      const d = json.data;
      document.getElementById('totalValue').innerText = `€${Number(d.total_value).toFixed(2)}`;
      document.getElementById('investedAmount').innerText = `€${Number(d.invested_amount).toFixed(2)}`;
      document.getElementById('unrealizedPL').innerText = `€${Number(d.unrealized_pl).toFixed(2)}`;
      document.getElementById('realizedPL').innerText = `€${Number(d.realized_pl).toFixed(2)}`;
      document.getElementById('dividendsGross').textContent = `€${Number(d.dividends_gross).toFixed(2)}`;
      document.getElementById('dividendsNet').textContent = `€${number(d.dividends_net).toFixed(2)}`;
      document.getElementById('cashBalance').innerText = `€${Number(d.cash_balance).toFixed(2)}`;
    })
    .catch(err => console.error('Summary fetch error:', err));
}


// ---- Portfolio Value Over Time ----
async function loadPortfolioHistory() {
  try {
    const res = await fetch("/cashcue/api/getPortfolioHistory.php");
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

