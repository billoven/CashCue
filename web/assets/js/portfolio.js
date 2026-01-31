/**
 * portfolio.js - adapted for CashCueAppContext
 * -------------------------------------------
 * Handles:
 *   - Holdings table (loadHoldings)
 *   - Portfolio history chart (loadPortfolioHistory)
 *
 * Interfaces:
 *   - window.CashCueAppContext.getBrokerAccountId() → current broker_account_id
 *   - brokerAccountChanged event → reload holdings/chart on broker switch
 *   - historyRange select → adjust portfolio history chart range
 *
 * Notes:
 *   - Initial load waits for CashCueAppContext.waitForBrokerAccount()
 *   - All API calls include broker_account_id
 *   - ECharts used for portfolio history visualization
 */

console.log("portfolio.js loaded - using CashCueAppContext");

/* ============================================================
 * HOLDINGS TABLE
 * ============================================================ */
async function loadHoldings() {
  const brokerId = window.CashCueAppContext.getBrokerAccountId();
  const tbody = document.getElementById('holdingsTableBody');
  if (!tbody) return;

  try {
    const res = await fetch(`/cashcue/api/getHoldings.php?broker_account_id=${brokerId}`);
    const json = await res.json();
    if (json.status !== 'success') return;

    tbody.innerHTML = '';
    console.log('Holdings data:', json.data);

    json.data.forEach(row => {
      const plClass = parseFloat(row.unrealized_pl) >= 0 ? 'text-success' : 'text-danger';
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${row.symbol}</td>
        <td>${row.label}</td>
        <td class="text-end">${row.total_qty}</td>
        <td class="text-end">${parseFloat(row.avg_buy_price).toFixed(4)}</td>
        <td class="text-end">${parseFloat(row.last_price).toFixed(4)}</td>
        <td class="text-end">${parseFloat(row.current_value).toFixed(2)}</td>
        <td class="text-end ${plClass}">${parseFloat(row.unrealized_pl).toFixed(2)}</td>
        <td class="text-end ${plClass}">${parseFloat(row.unrealized_pl_pct).toFixed(2)}%</td>
      `;
      tbody.appendChild(tr);
    });
  } catch (err) {
    console.error('Holdings fetch error:', err);
  }
}

/* ============================================================
 * PORTFOLIO HISTORY CHART
 * ============================================================ */
let portfolioChartInstance = null;

async function loadPortfolioHistory() {
  const rangeSelect = document.getElementById('historyRange');
  const range = rangeSelect ? rangeSelect.value : 30;

  const brokerId = window.CashCueAppContext.getBrokerAccountId();
  const chartDom = document.getElementById('portfolioHistoryChart');
  if (!chartDom) return;

  try {
    const res = await fetch(`/cashcue/api/getPortfolioHistory.php?range=${range}&broker_account_id=${brokerId}`);
    const json = await res.json();
    if (json.status !== 'success') throw new Error(json.message || 'API error');

    const data = json.data;
    console.log('Portfolio history data:', data);

    const dates = data.map(r => r.date);
    const dailyInvested = data.map(r => parseFloat(r.daily_invested));
    const cumulativeInvested = data.map(r => parseFloat(r.cum_invested));
    const portfolioValue = data.map(r => parseFloat(r.portfolio));

    const barColors = dailyInvested.map(v => v >= 0 ? '#28a745' : '#dc3545');

    if (!portfolioChartInstance) {
      portfolioChartInstance = echarts.init(chartDom);
      window.addEventListener('resize', () => portfolioChartInstance.resize());
    }

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
      xAxis: { type: 'category', data: dates, axisLabel: { rotate: 45 } },
      yAxis: [
        { type: 'value', name: 'Portfolio (€)', position: 'left' },
        { type: 'value', name: 'Daily Invested (€)', position: 'right', axisLine: { show: true }, splitLine: { show: false } }
      ],
      series: [
        { name: 'Daily Invested', type: 'bar', yAxisIndex: 1, data: dailyInvested, barWidth: '50%', itemStyle: { color: params => barColors[params.dataIndex] } },
        { name: 'Cumulative Invested', type: 'line', data: cumulativeInvested, smooth: true, showSymbol: false, lineStyle: { width: 2, color: '#007bff' } },
        { name: 'Portfolio Value', type: 'line', data: portfolioValue, smooth: true, showSymbol: false, lineStyle: { width: 2, color: '#ffc107' } }
      ]
    };

    portfolioChartInstance.setOption(option);

  } catch (err) {
    console.error('Portfolio history fetch error:', err);
  }
}

/* ============================================================
 * INITIAL LOAD
 * ============================================================ */
document.addEventListener('DOMContentLoaded', async () => {
  // Wait for brokerAccountId from CashCueAppContext
  await window.CashCueAppContext.waitForBrokerAccount();

  // Load initial holdings and portfolio history
  loadHoldings();
  loadPortfolioHistory();

  // React to brokerAccountChanged event
  document.addEventListener('brokerAccountChanged', () => {
    console.log('portfolio.js: brokerAccountChanged event received');
    loadHoldings();
    loadPortfolioHistory();
  });

  // Listener for range selector changes (chart only)
  const rangeSelect = document.getElementById('historyRange');
  if (rangeSelect) {
    rangeSelect.addEventListener('change', loadPortfolioHistory);
  }
});
