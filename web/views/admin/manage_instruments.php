<?php
/**
 * manage_instruments.php
 * ----------------------
 * Admin interface for managing financial instruments.
 *
 * Features:
 * - Instrument listing
 * - Instrument creation
 * - Instrument edition (including lifecycle status with impacts)
 *
 * No physical deletion is allowed.
 * Instrument lifecycle is driven exclusively by status changes.
 */

$BROKER_SCOPE = "disabled";
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../header.php';
?>

<div class="container-fluid px-4">
  <h1 class="mt-4">Instruments Management</h1>
  <ol class="breadcrumb mb-4">
    <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Manage Instruments</li>
  </ol>

  <div class="mb-3 d-flex justify-content-between align-items-center">
    <input
      type="text"
      id="searchInstrument"
      class="form-control w-50"
      placeholder="Search by symbol or label..."
    >
    <button id="btnAddInstrument" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-circle"></i> Add Instrument
    </button>
  </div>
  <div class="table-responsive" id="instrumentsTableContainer">
    <!-- CashCueTable renders the table here -->
  </div>
</div>

<!-- Instrument Modal -->
<div class="modal fade" id="instrumentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="instrumentModalTitle">
          Add / Edit Instrument
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="instrumentForm">
          <input type="hidden" id="instrumentId">

          <div class="mb-3">
            <label class="form-label">Symbol</label>
            <input type="text" class="form-control" id="symbol" required>
          </div>

          <div class="mb-3">
            <label class="form-label">ISIN</label>
            <input type="text" class="form-control" id="isin" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Label</label>
            <input type="text" class="form-control" id="label" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Type</label>
            <select id="type" class="form-select" required>
              <option value="stock">Stock</option>
              <option value="etf">ETF</option>
              <option value="bond">Bond</option>
              <option value="fund">Fund</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Currency</label>
            <input type="text" class="form-control" id="currency" value="EUR">
          </div>

          <!-- Status (Edit mode only, managed by JS) -->
          <div class="mb-3" id="statusGroup" style="display:none;">
            <label class="form-label">Status</label>
            <select id="status" class="form-select"></select>
            <div class="form-text" id="statusImpactHint"></div>
          </div>

        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button class="btn btn-primary" id="saveInstrument">
          Save
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Load JS -->
 <script src="/cashcue/assets/js/CashCueTable.js"></script>
<script src="/cashcue/assets/js/manage_instruments.js"></script>

<?php include __DIR__ . '/../footer.php'; ?>
