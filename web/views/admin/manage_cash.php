<?php
  $page = 'manage_cash';          // identifies active Cash sub-page
  $BROKER_SCOPE = "single";

  require_once __DIR__ . '/../../includes/helpers.php';
  require_once __DIR__ . '/../header.php';
?>

<div class="container-fluid py-4">

  <?php require_once __DIR__ . '/../_cash_nav.php'; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">💰 Cash Movements Management</h2>
    <button class="btn btn-success" id="btnAddCash">
      ➕ Add Cash Movement
    </button>
  </div>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle" id="cashAdminTable">
        <thead class="table-dark">
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th class="text-end">Amount (€)</th>
            <th>Reference</th>
            <th>Comment</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody id="cashAdminBody">
          <!-- JS -->
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../modals/cash_modal.php'; ?>
<script src="/cashcue/assets/js/appContext.js"></script>
<script src="/cashcue/assets/js/manage_cash.js"></script>
<?php require_once __DIR__ . '/../footer.php'; ?>
