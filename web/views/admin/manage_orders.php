<?php
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

    <!-- 🧾 Orders Table -->
    <div class="table-responsive mb-3">
      <table class="table table-striped table-hover align-middle" id="ordersTable">
        <thead class="table-dark">
          <tr>
            <th>Symbol</th>
            <th>Label</th>
            <th>Broker</th>
            <th>Type</th>
            <th>Quantity</th>
            <th>Price (€)</th>
            <th>Fees (€)</th>
            <th>Total (€)</th>
            <th>Trade Date</th>
            <th>Status</th>
            <th>Cancelled at</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- JS populates here -->
        </tbody>
      </table>
    </div>


    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center mt-3 mb-5">
      <button id="prevPage" class="btn btn-outline-secondary btn-sm" disabled>Previous</button>
      <span id="pageInfo" class="mx-2">Page 1</span>
      <button id="nextPage" class="btn btn-outline-secondary btn-sm">Next</button>
    </div>
  </div>
</div>

<!-- 🧩 Modal: Add / Edit Order -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="orderModalLabel">➕ Add Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="orderForm">
          <input type="hidden" id="order_id">
          <div class="mb-3">
            <label for="instrument_id" class="form-label">Instrument</label>
            <select id="instrument_id" class="form-select" required>
              <option value="">Select instrument...</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="order_type" class="form-label">Order Type</label>
            <select id="order_type" class="form-select" required>
              <option value="BUY">BUY</option>
              <option value="SELL">SELL</option>
            </select>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="quantity" class="form-label">Quantity</label>
              <input type="number" id="quantity" class="form-control" step="0.01" min="0" required>
            </div>
            <div class="col-md-4 mb-3">
              <label for="price" class="form-label">Price (€)</label>
              <input type="number" id="price" class="form-control" step="0.0001" min="0" required>
            </div>
            <div class="col-md-4 mb-3">
              <label for="fees" class="form-label">Fees (€)</label>
              <input type="number" id="fees" class="form-control" step="0.01" min="0" value="0.00">
            </div>
          </div>
          <div class="mb-3 flatpickr-wrapper position-relative">
            <label for="trade_date" class="form-label">Trade Date</label>
            <input id="trade_date" class="form-control" placeholder="Select date & time..." required>
            <button type="button" id="closeCalendar" class="btn btn-sm btn-outline-secondary position-absolute" 
                    style="top: 30px; right: 5px; z-index: 1050;">X</button>
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
<script src="/cashcue/assets/js/appContext.js"></script>
<script src="/cashcue/assets/js/manage_orders.js"></script>
<?php include __DIR__ . '/../footer.php'; ?>
