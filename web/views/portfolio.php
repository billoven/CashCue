<?php
  $BROKER_SCOPE = "portfolio";
  require_once __DIR__ . '/../includes/helpers.php';
  require_once __DIR__ . '/header.php';
?>

<div class="container-fluid py-4">
  <h2 class="mb-4">📊 Portfolio Overview</h2>

  <!-- 🔹 Portfolio Value Over Time -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">📈 Portfolio Value Over Time</h5>
      <select id="historyRange" class="form-select w-auto">
        <option value="30">Last 30 days</option>
        <option value="90">3 months</option>
        <option value="180">6 months</option>
        <option value="365">1 year</option>
        <option value="all">All</option>
      </select>
    </div>
    <div class="card-body">
      <div id="portfolioHistoryChart" style="height: 400px;"></div>
    </div>
  </div>

  <!-- 🔹 Current Holdings -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white fw-bold">Current Holdings</div>
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover align-middle" id="holdingsTable">
        <thead class="table-dark">
          <tr>
            <th>Symbol</th>
            <th>Label</th>
            <th>Quantity</th>
            <th>Avg. Buy Price (€)</th>
            <th>Last Price (€)</th>
            <th>Value (€)</th>
            <th>Unrealized P/L (€)</th>
            <th>Unrealized P/L (%)</th>
          </tr>
        </thead>
        <tbody id="holdingsTableBody"><!-- JS populates --></tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
<script src="/cashcue/assets/js/appContext.js"></script>
<script src="/cashcue/assets/js/portfolio.js"></script>

<?php require_once __DIR__ . '/footer.php'; ?>

