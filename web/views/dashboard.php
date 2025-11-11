<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/header.php';
?>

<div class="container-fluid py-4">
  <h2 class="mb-4">💹 Cashcue Dashboard</h2>

  <!-- 🔹 Portfolio Summary -->
  <div class="row text-center mb-4" id="summaryCards">
    <div class="col-md-2 mb-3">
      <div class="card shadow-sm border-primary">
        <div class="card-body">
          <h6 class="text-muted">Total Value</h6>
          <h4 id="totalValue">€0.00</h4>
        </div>
      </div>
    </div>
    <div class="col-md-2 mb-3">
      <div class="card shadow-sm border-success">
        <div class="card-body">
          <h6 class="text-muted">Invested</h6>
          <h4 id="investedAmount">€0.00</h4>
        </div>
      </div>
    </div>
    <div class="col-md-2 mb-3">
      <div class="card shadow-sm border-warning">
        <div class="card-body">
          <h6 class="text-muted">Unrealized P/L</h6>
          <h4 id="unrealizedPL">€0.00</h4>
        </div>
      </div>
    </div>
    <div class="col-md-2 mb-3">
      <div class="card shadow-sm border-info">
        <div class="card-body">
          <h6 class="text-muted">Realized P/L</h6>
          <h4 id="realizedPL">€0.00</h4>
        </div>
      </div>
    </div>
    <div class="col-md-2 mb-3">
      <div class="card shadow-sm border-secondary">
        <div class="card-body">
          <h6 class="text-muted">Dividends</h6>
          <h4 id="dividends">€0.00</h4>
        </div>
      </div>
    </div>
    <div class="col-md-2 mb-3">
      <div class="card shadow-sm border-dark">
        <div class="card-body">
          <h6 class="text-muted">Cash Balance</h6>
          <h4 id="cashBalance">€0.00</h4>
        </div>
      </div>
    </div>
  </div>

  <!-- 🔹 Realtime Prices & Recent Orders -->
  <div class="row">
    <div class="col-md-8">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white fw-bold">Realtime Prices</div>
        <div class="card-body table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
              <tr>
                <th>Symbol</th>
                <th>Label</th>
                <th>Price</th>
                <th>Currency</th>
                <th>% Change</th>
                <th>Updated</th>
              </tr>
            </thead>
            <tbody id="realtimeTableBody"><!-- JS populates --></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white fw-bold">Last Orders</div>
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
            <tbody id="ordersTableBody"><!-- JS populates --></tbody>
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
<script src="/cashcue/assets/js/dashboard.js"></script>

<?php require_once __DIR__ . '/footer.php'; ?>
