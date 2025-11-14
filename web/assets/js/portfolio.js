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
console.log('Holding data:', json.data);
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

    const data = json.data;
    console.log('Portfolio history data:', data);

    // Extract arrays
    const dates = data.map(r => r.date);
    const dailyInvested = data.map(r => parseFloat(r.daily_invested));
    const cumulativeInvested = data.map(r => parseFloat(r.cum_invested));
    const totalValue = data.map(r => parseFloat(r.portfolio));

    // Define bar colors (green for buy, red for sell)
    const barColors = dailyInvested.map(v => (v >= 0 ? '#28a745' : '#dc3545'));

    const chart = echarts.init(chartDom);
    const option = {
      tooltip: {
        trigger: 'axis',
        backgroundColor: 'rgba(50,50,50,0.9)',
        borderWidth: 0,
        textStyle: { color: '#fff' },
        formatter: params => {
          const date = params[0].axisValue;
          const daily = params.find(p => p.seriesName === 'Daily Invested');
          const cum = params.find(p => p.seriesName === 'Cumulative Invested');
          const port = params.find(p => p.seriesName === 'Portfolio Value');

          return `
            <strong>${date}</strong><br/>
            ${daily ? `Daily Invested: <span style="color:${daily.color}">${daily.value.toFixed(2)} €</span><br/>` : ''}
            ${cum ? `Cumulative Invested: <span style="color:${cum.color}">${cum.value.toFixed(2)} €</span><br/>` : ''}
            ${port ? `Portfolio Value: <span style="color:${port.color}">${port.value.toFixed(2)} €</span>` : ''}
          `;
        }
      },
      legend: { data: ['Daily Invested', 'Cumulative Invested', 'Portfolio Value'] },
      xAxis: {
        type: 'category',
        data: dates,
        axisLabel: { rotate: 45 }
      },
      yAxis: [
        {
          type: 'value',
          name: 'Portfolio (€)',
          position: 'left'
        },
        {
          type: 'value',
          name: 'Daily Invested (€)',
          position: 'right',
          axisLine: { show: true },
          splitLine: { show: false }
        }
      ],
      series: [
        {
          name: 'Daily Invested',
          type: 'bar',
          yAxisIndex: 1,
          data: dailyInvested,
          itemStyle: {
            color: (params) => barColors[params.dataIndex]
          },
          barWidth: '50%'
        },
        {
          name: 'Cumulative Invested',
          type: 'line',
          data: cumulativeInvested,
          smooth: true,
          lineStyle: { width: 2, color: '#007bff' },
          showSymbol: false
        },
        {
          name: 'Portfolio Value',
          type: 'line',
          data: totalValue,
          smooth: true,
          lineStyle: { width: 2, color: '#ffc107' },
          showSymbol: false
        }
      ]
    };

    chart.setOption(option);
    window.addEventListener('resize', () => chart.resize());
  } catch (err) {
    console.error('Portfolio history fetch error:', err);
  }
}
