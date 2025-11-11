document.addEventListener('DOMContentLoaded', () => {
  loadHoldings();
  loadPortfolioHistory();

  // Handle range selector
  const rangeSelect = document.getElementById('historyRange');
  if (rangeSelect) {
    rangeSelect.addEventListener('change', loadPortfolioHistory);
  }
});

// ---- Load Holdings ----
function loadHoldings() {
  fetch('/cashcue/api/getHoldings.php')
    .then(response => response.json())
    .then(json => {
      if (json.status !== "success") return;
      const tbody = document.getElementById('holdingsTableBody');
      if (!tbody) return;
      tbody.innerHTML = '';

      json.data.forEach(row => {
        const tr = document.createElement('tr');
        const plClass = row.unrealized_pl >= 0 ? 'text-success' : 'text-danger';
        tr.innerHTML = `
          <td>${row.symbol}</td>
          <td>${row.label}</td>
          <td>${row.total_qty}</td>
          <td>${parseFloat(row.avg_buy_price).toFixed(4)}</td>
          <td>${parseFloat(row.last_price).toFixed(4)}</td>
          <td>${parseFloat(row.current_value).toFixed(2)}</td>
          <td class="${plClass}">${parseFloat(row.unrealized_pl).toFixed(2)}</td>
          <td class="${plClass}">${parseFloat(row.unrealized_pl_pct).toFixed(2)}%</td>
        `;
        tbody.appendChild(tr);
      });
    })
    .catch(err => console.error('Holdings fetch error:', err));
}

// ---- Load Portfolio History Chart ----
async function loadPortfolioHistory() {
  const rangeSelect = document.getElementById('historyRange');
  const range = rangeSelect ? rangeSelect.value : '30';
  const chartDom = document.getElementById('portfolioHistoryChart');
  if (!chartDom) return;

  try {
    const res = await fetch(`/cashcue/api/getPortfolioHistory.php?range=${range}`);
    const json = await res.json();
    if (json.status !== 'success') throw new Error(json.message);
    console.log ('Portfolio history data:', json.data);
    const data = json.data;
    const dates = data.map(r => r.date);
    const invested = data.map(r => parseFloat(r.invested));
    const total = data.map(r => parseFloat(r.portfolio));

    const chart = echarts.init(chartDom);
    const option = {
      tooltip: { trigger: 'axis' },
      legend: { data: ['Invested Value', 'Current Value'] },
      xAxis: {
        type: 'category',
        data: dates,
        axisLabel: { rotate: 45 }
      },
      yAxis: {
        type: 'value',
        name: '€'
      },
      series: [
        {
          name: 'Invested Value',
          type: 'line',
          data: invested,
          smooth: true,
          lineStyle: { width: 2, color: '#007bff' }
        },
        {
          name: 'Current Value',
          type: 'line',
          data: total,
          smooth: true,
          lineStyle: { width: 2, color: '#28a745' }
        }
      ]
    };

    chart.setOption(option);
    window.addEventListener('resize', () => chart.resize());
  } catch (err) {
    console.error('Portfolio history fetch error:', err);
  }
}
