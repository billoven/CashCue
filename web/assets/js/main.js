document.addEventListener('DOMContentLoaded', () => {
  
  // Load realtime data if we’re on the dashboard
  if (document.getElementById('realtimeTableBody')) {
    loadRealtimeData();
    loadRecentOrders();
  }
});

// ---- Realtime Prices ----
function loadRealtimeData() {
  fetch('/cashcue/api/getRealtimeData.php')
    .then(response => response.json())
    .then(json => {
      console.log("✅ Parsed response:", json);
      if (json.status !== "success") return;
      const tbody = document.getElementById('realtimeTableBody');
      console.log('Realtime data loaded:', json.data.length);
      tbody.innerHTML = '';

      json.data.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${row.symbol}</td>
          <td class="text-primary fw-bold" style="cursor:pointer">${row.label}</td>
          <td>${parseFloat(row.price).toFixed(4)}</td>
          <td>${row.currency}</td>
          <td>${row.pct_change ?? '-'}</td>
          <td>${new Date(row.captured_at).toLocaleTimeString()}</td>
        `;

        // ⬇️ Attach click event to label cell to load the chart
        const labelCell = tr.querySelector('td:nth-child(2)');
        labelCell.addEventListener('click', (e) => {
          e.stopPropagation(); // prevent row click if added later
          console.log("Clicked instrument:", row.instrument_id, row.label);
          loadInstrumentChart(row.instrument_id, row.label);
        });

        tbody.appendChild(tr);
      });
    })
    .catch(err => console.error('Realtime fetch error:', err));
}

// ---- Instrument Chart Loader ----
function loadInstrumentChart(instrument_id, label) {
  console.log("Loading chart for instrument:", instrument_id);

  fetch(`/cashcue/api/getInstrumentHistory.php?instrument_id=${instrument_id}`)
    .then(response => response.json())
    .then(json => {
      const chartDiv = document.getElementById('portfolioChart');
      const chart = echarts.init(chartDiv);
      chart.clear();

      if (!json.data || json.data.length === 0) {
        chart.setOption({
          title: { text: `No intraday data for ${label}`, left: 'center' }
        });
        return;
      }

      const times = json.data.map(p => p.captured_at);
      const prices = json.data.map(p => parseFloat(p.price));

      chart.setOption({
        title: { text: `${label} — Intraday Price`, left: 'center' },
        tooltip: { trigger: 'axis' },
        xAxis: { type: 'category', data: times, axisLabel: { show: true } },
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

// ---- Recent Orders ----
function loadRecentOrders() {
  fetch('/cashcue/api/getOrders.php?limit=10')
    .then(response => response.json())
    .then(json => {
      if (json.status !== "success") return;
      const tbody = document.getElementById('ordersTableBody');
      tbody.innerHTML = '';
      json.data.forEach(o => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${o.symbol}</td>
          <td>${o.order_type}</td>
          <td>${o.quantity}</td>
          <td>${o.price}</td>
          <td>${o.trade_date}</td>
        `;
        tbody.appendChild(tr);
      });
    })
    .catch(err => console.error('Orders fetch error:', err));
}

