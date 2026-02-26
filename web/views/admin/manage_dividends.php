<?php
  // define a constant to indicate that we are in the CashCue app context
  // This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
  define('CASHCUE_APP', true);

  // Include authentication check
  require_once __DIR__ . '/../../includes/auth.php';
  
  // Dividends Management
  // Logical lifecycle: ACTIVE / CANCELLED (append-only, no delete)

  $BROKER_SCOPE = "single-or-all";
  require_once __DIR__ . '/../../includes/helpers.php';
  require_once __DIR__ . '/../header.php';
?>

<div class="container-fluid px-4">

    <!-- Page title -->
    <h1 class="mt-4">Dividends Management</h1>

    <!-- Breadcrumb -->
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item">
            <a href="../../index.php">Dashboard</a>
        </li>
        <li class="breadcrumb-item active">
            Manage Dividends
        </li>
    </ol>

    <!-- Search + Add -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <input
            type="text"
            id="searchDividend"
            class="form-control w-50"
            placeholder="Search by symbol or broker..."
        >
        <button id="addDividendBtn" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i>
            Add Dividend
        </button>
    </div>
    <!-- Dividends Table -->
    <div class="table-responsive" id="dividendsTableContainer">
        <!-- CashCueTable renders the table here -->
    </div>
</div>

<!-- ========================================================= -->
<!-- Modal: Add / Edit Dividend                                 -->
<!-- ========================================================= -->

<div
    class="modal fade"
    id="dividendModal"
    tabindex="-1"
    aria-labelledby="dividendModalLabel"
    aria-hidden="true"
>
  <div class="modal-dialog">
    <div class="modal-content rounded-3 shadow">

      <div class="modal-header">
        <h5 class="modal-title" id="dividendModalLabel">
            Add Dividend
        </h5>
        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="modal"
            aria-label="Close"
        ></button>
      </div>

      <div class="modal-body">
        <form id="dividendForm">

            <!-- Hidden ID (edit only) -->
            <input type="hidden" id="dividend_id" name="id">
            <div class="mb-3">
                <label class="form-label">Instrument</label>
                <select
                    id="instrument_id"
                    name="instrument_id"
                    class="form-select"
                    required
                ></select>
            </div>

            <div class="mb-3">
                <label class="form-label">Payment Date</label>
                <input
                    type="date"
                    id="payment_date"
                    name="payment_date"
                    class="form-control"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Gross Amount</label>
                <input
                    type="number"
                    step="0.0001"
                    id="gross_amount"
                    name="gross_amount"
                    class="form-control"
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Taxes Withheld</label>
                <input
                    type="number"
                    step="0.0001"
                    id="taxes_withheld"
                    name="taxes_withheld"
                    class="form-control"
                    value="0.0000"
                >
            </div>

            <div class="mb-3">
                <label class="form-label">
                    Net Amount (auto-calculated)
                </label>
                <input
                    type="number"
                    step="0.0001"
                    id="amount"
                    name="amount"
                    class="form-control"
                    readonly
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Currency</label>
                <input
                    type="text"
                    id="currency"
                    name="currency"
                    maxlength="3"
                    class="form-control"
                    value="EUR"
                >
            </div>

        </form>
      </div>

      <div class="modal-footer">
        <button
            type="button"
            class="btn btn-secondary"
            data-bs-dismiss="modal"
        >
            Cancel
        </button>
        <button
            type="button"
            id="saveDividendBtn"
            class="btn btn-primary"
        >
            Save
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Page JS -->
<script src="/cashcue/assets/js/CashCueTable.js"></script>
<script src="/cashcue/assets/js/appContext.js"></script>
<script src="/cashcue/assets/js/manage_dividends.js"></script>

<?php require_once __DIR__ . '/../footer.php'; ?>

