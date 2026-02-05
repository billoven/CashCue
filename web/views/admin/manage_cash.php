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
    <div class="card-body">
      <div class="table-responsive" id="cashAdminTableContainer">
        <!-- CashCueTable renders the table here -->
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../modals/cash_modal.php'; ?>
<script src="/cashcue/assets/js/CashCueTable.js"></script>
<script src="/cashcue/assets/js/appContext.js"></script>
<script src="/cashcue/assets/js/manage_cash.js"></script>
<?php require_once __DIR__ . '/../footer.php'; ?>
