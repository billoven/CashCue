<?php
  // define a constant to indicate that we are in the CashCue app context
  // This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
  define('CASHCUE_APP', true);

  // Include authentication check
  require_once __DIR__ . '/../../includes/auth.php';

  $BROKER_SCOPE = "single";
  require_once __DIR__ . '/../../includes/helpers.php';
  require_once __DIR__ . '/../header.php';
?>

<div class="main-content">
  <div class="container-fluid px-4">
    <h1 class="mt-4">Orders Management</h1>
    <ol class="breadcrumb mb-4">
      <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Manage Orders</li>
    </ol>

    <!-- 🔍 Search + Add -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <input type="text" id="searchOrder" class="form-control w-50" placeholder="Search by symbol or label...">
      <button id="btnAddOrder" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle"></i> Add Order
      </button>
    </div>
    <!-- 🧾 Orders Table (CashCueTable-ready) -->
    <div class="table-responsive mb-3" id="ordersTableContainer">
      <!-- CashCueTable will create the <table> and populate rows here -->
    </div>
  </div>
</div>


<!-- ==========================
     Add Order Modal
=========================== -->
<div class="modal fade" id="orderModal" tabindex="-1"
     aria-labelledby="orderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="orderModalLabel">➕ Add Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="orderForm">

          <input type="hidden" id="order_id" name="order_id">

          <!-- Instrument -->
          <div class="mb-3">
            <label for="instrument_id" class="form-label"><strong>Instrument</strong></label>
            <select id="instrument_id" name="instrument_id" class="form-select" required>
              <option value="">Select instrument...</option>
            </select>
          </div>

          <!-- Order Type -->
          <div class="mb-3">
            <label for="order_type" class="form-label"><strong>Order Type</strong></label>
            <select id="order_type" name="order_type" class="form-select" required>
              <option value="BUY">BUY</option>
              <option value="SELL">SELL</option>
            </select>
          </div>

          <!-- Quantity / Price / Fees -->
          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="quantity" class="form-label"><strong>Quantity</strong></label>
              <input type="number" id="quantity" name="quantity" class="form-control"
                     step="0.01" min="0" required>
            </div>
            <div class="col-md-4 mb-3">
              <label for="price" class="form-label"><strong>Price (€)</strong></label>
              <input type="number" id="price" name="price" class="form-control"
                     step="0.0001" min="0" required>
            </div>
            <div class="col-md-4 mb-3">
              <label for="fees" class="form-label"><strong>Fees (€)</strong></label>
              <input type="number" id="fees" name="fees" class="form-control"
                     step="0.01" min="0" value="0.00">
            </div>
          </div>

          <!-- Trade Date -->
          <div class="mb-3 flatpickr-wrapper position-relative">
            <label for="trade_date" class="form-label"><strong>Trade Date</strong></label>
            <input id="trade_date" name="trade_date" class="form-control"
                   placeholder="Select date & time..." required>
            <button type="button" id="closeCalendar" class="btn btn-sm btn-outline-secondary position-absolute"
                    style="top: 30px; right: 5px; z-index: 1050;">X</button>
          </div>

          <!-- Comment -->
          <div class="mb-3">
            <label for="comment" class="form-label text-success"><strong>Comment</strong></label>
            <textarea id="comment" name="comment" class="form-control" rows="3"
                      placeholder="Optional comment (order context, justification, note…)"></textarea>
          </div>

          <!-- ✅ Settled -->
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="settled" checked>
            <label class="form-check-label" for="settled">Settled</label>
          </div>

        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="saveOrder">Save</button>
      </div>

    </div>
  </div>
</div>

<!-- ==========================
     Update Order Modal
=========================== -->
<div class="modal fade" id="updateOrderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">✏️ Update Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="updateOrderForm">
          <input type="hidden" id="orderId" name="order_id">

          <div class="row mb-3">
            <div class="col">
              <label class="form-label"><strong>Quantity</strong></label>
              <input type="number" step="0.0001" class="form-control" id="orderQuantity" name="quantity">
            </div>
            <div class="col">
              <label class="form-label"><strong>Price (€)</strong></label>
              <input type="number" step="0.0001" class="form-control" id="orderPrice" name="price">
            </div>
            <div class="col">
              <label class="form-label"><strong>Fees (€)</strong></label>
              <input type="number" step="0.01" class="form-control" id="orderFees" name="fees">
            </div>
          </div>

          <!-- Comment -->
          <div class="mb-3">
            <label class="form-label"><strong>Comment</strong></label>
            <textarea class="form-control" id="orderComment" name="comment" rows="3"
                      placeholder="Optional note or mandatory justification"></textarea>
          </div>

          <!-- ✅ Settled (pre-filled via JS) -->
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="orderSettled">
            <label class="form-check-label" for="orderSettled">Settled</label>
          </div>

        </form>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveOrderUpdateBtn">Save changes</button>
      </div>

    </div>
  </div>
</div>

<script src="/cashcue/assets/js/CashCueTable.js"></script>
<script src="/cashcue/assets/js/appContext.js"></script>
<script src="/cashcue/assets/js/updateOrderModal.js"></script>
<script src="/cashcue/assets/js/manage_orders.js"></script>

<?php include __DIR__ . '/../footer.php'; ?>
