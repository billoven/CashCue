<?php
  $BROKER_SCOPE = "single-or-all";
  require_once __DIR__ . '/../includes/helpers.php';
  require_once __DIR__ . '/header.php';
?>
<div class="container-fluid py-4">
  <h2 class="mb-4">💹 Cashcue Dashboard</h2>

  <!-- 🔹 Portfolio Summary (compact layout) -->
  <div class="row text-center mb-4 g-2" id="summaryCards">
    <div class="col-6 col-md-1-7">
      <div class="card summary-card border-primary">
        <div class="card-body p-2">
          <h6 class="summary-title text-muted">Total Value</h6>
          <h5 id="totalValue" class="summary-value">€0.00</h5>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-1-7">
      <div class="card summary-card border-success">
        <div class="card-body p-2">
          <h6 class="summary-title text-muted">Invested</h6>
          <h5 id="investedAmount" class="summary-value">€0.00</h5>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-1-7">
      <div class="card summary-card border-warning">
        <div class="card-body p-2">
          <h6 class="summary-title text-muted">Unrealized P/L</h6>
          <h5 id="unrealizedPL" class="summary-value">€0.00</h5>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-1-7">
      <div class="card summary-card border-info">
        <div class="card-body p-2">
          <h6 class="summary-title text-muted">Realized P/L</h6>
          <h5 id="realizedPL" class="summary-value">€0.00</h5>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-1-7">
      <div class="card summary-card border-success">
        <div class="card-body p-2">
          <h6 class="summary-title text-muted" title="Before taxes">Gross Dividends</h6>
          <h5 id="dividendsGross" class="summary-value">€0.00</h5>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-1-7">
      <div class="card summary-card border-secondary">
        <div class="card-body p-2">
          <h6 class="summary-title text-muted" title="After taxes">Net Dividends</h6>
          <h5 id="dividendsNet" class="summary-value">€0.00</h5>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-1-7">
      <div class="card summary-card border-dark">
        <div class="card-body p-2">
          <h6 class="summary-title text-muted">Cash Balance</h6>
          <h5 id="cashBalance" class="summary-value">€0.00</h5>
        </div>
      </div>
    </div>
  </div>

  <!-- 🔹 Realtime Prices & Recent Orders -->
  <div class="row">

    <!-- 🔹 Realtime Prices (triable) -->
    <div class="col-md-8">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold">
          Realtime Prices
        </div>
        <div class="card-body table-responsive" id="realtimeTableContainer">
          <!-- CashCueTable will create the <table> and populate rows here -->
        </div>
      </div>
    </div>

    <!-- 🔹 Last Orders (INCHANGÉ) -->
    <div class="col-md-4">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white fw-bold">
          Last Orders
        </div>
        <div class="card-body table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Symbol</th>
                <th>Type</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody id="ordersTableBody">
              <!-- JS populates -->
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
  
  <!-- 🔹 Instrument Intraday Chart -->
  <div class="card shadow-sm mb-4" id="instrumentChartCard" style="display:none;">
    <div class="card-header bg-info text-white fw-bold" id="instrumentChartTitle">Instrument Intraday Chart</div>
    <div class="card-body">
      <div id="instrumentChart" style="height: 400px;"></div>
    </div>
  </div>

  <!-- 🔹 Portfolio Evolution Chart -->
  <div class="card shadow-sm">
    <div class="card-header bg-secondary text-white fw-bold">Portfolio Value Over Time</div>
    <div class="card-body">
      <div id="portfolioChart" style="height: 400px;"></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
<script src="/cashcue/assets/js/CashCueTable.js"></script>
<script src="/cashcue/assets/js/appContext.js"></script>
<script src="/cashcue/assets/js/dashboard.js"></script>

<?php require_once __DIR__ . '/footer.php'; ?>
