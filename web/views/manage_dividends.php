<?php
  $BROKER_SCOPE = "single-or-all";
  require_once __DIR__ . '/../includes/helpers.php';
  require_once __DIR__ . '/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Dividends Management</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Manage Dividends</li>
    </ol>
    
    <!-- 🔍 Search + Add -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <input type="text" id="searchDividend" class="form-control w-50" placeholder="Search by symbol or label...">
      <button id="addDividendBtn" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle"></i> Add Dividend
      </button>
    </div>


    <div class="table-responsive">
        <table id="dividendsTable" class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>Instrument</th>
                    <th>Broker</th>
                    <th>Gross Amount</th>
                    <th>Taxes</th>
                    <th>Net Received</th>
                    <th>Currency</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Modal: Add/Edit Dividend -->
<div class="modal fade" id="dividendModal" tabindex="-1" aria-labelledby="dividendModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content rounded-3 shadow">
      <div class="modal-header">
        <h5 class="modal-title" id="dividendModalLabel">Add Dividend</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="dividendForm">
            <input type="hidden" id="dividend_id" name="id">

            <div class="mb-3">
                <label class="form-label">Broker</label>
                <select id="broker_id" name="broker_id" class="form-select" required></select>
            </div>

            <div class="mb-3">
                <label class="form-label">Instrument</label>
                <select id="instrument_id" name="instrument_id" class="form-select" required></select>
            </div>

            <div class="mb-3">
                <label class="form-label">Payment Date</label>
                <input type="date" id="payment_date" name="payment_date" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Gross Amount</label>
                <input type="number" step="0.0001" id="gross_amount" name="gross_amount" class="form-control">
            </div>

            <div class="mb-3">
                <label class="form-label">Taxes Withheld</label>
                <input type="number" step="0.0001" id="taxes_withheld" name="taxes_withheld" class="form-control" value="0.0000">
            </div>

            <div class="mb-3">
                <label class="form-label">Net Amount (auto)</label>
                <input type="number" step="0.0001" id="amount" name="amount" class="form-control" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label">Currency</label>
                <input type="text" id="currency" name="currency" maxlength="3" class="form-control" value="EUR">
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="saveDividendBtn" class="btn btn-primary">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="/cashcue/assets/js/manage_dividends.js"></script>

<?php require_once __DIR__ . '/footer.php'; ?>

