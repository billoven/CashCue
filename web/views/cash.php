<?php
  $page = 'cash';                 // identifies active Cash sub-page
  $BROKER_SCOPE = "single-or-all";

  require_once __DIR__ . '/../includes/helpers.php';
  require_once __DIR__ . '/header.php';
?>

<div class="container-fluid py-4">

  <?php require_once __DIR__ . '/_cash_nav.php'; ?>

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">💶 Cash Account Overview</h2>

    <select id="cashRange" class="form-select w-auto">
      <option value="all">All</option>
      <option value="30">Last 30 days</option>
      <option value="90">3 months</option>
      <option value="180">6 months</option>
      <option value="365">1 year</option>
    </select>
  </div>

  <!-- Cash Summary -->
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="text-muted">Current Cash Balance</h6>
          <h3 class="fw-bold" id="cashCurrentBalance">—</h3>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="text-muted">Total Inflows</h6>
          <h4 class="text-success fw-bold" id="cashInflows">—</h4>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="text-muted">Total Outflows</h6>
          <h4 class="text-danger fw-bold" id="cashOutflows">—</h4>
        </div>
      </div>
    </div>
  </div>

  <!-- Cash Transactions -->
  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white fw-bold">
      Cash Movements
    </div>

    <div class="card-body table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead class="table-dark">
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th class="text-end">Amount (€)</th>
            <th>Reference</th>
            <th>Comment</th>
          </tr>
        </thead>
        <tbody id="cashTransactionsBody">
          <!-- populated by JS -->
        </tbody>
      </table>
    </div>
  </div>

</div>

<script src="/cashcue/assets/js/cash.js"></script>

<?php require_once __DIR__ . '/footer.php'; ?>
