<?php include __DIR__ . '/../header.php'; ?>

<div class="container-fluid px-4">
  <h1 class="mt-4">Brokers Management</h1>
  <ol class="breadcrumb mb-4">
    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
    <li class="breadcrumb-item active">Manage Brokers</li>
  </ol>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-building me-1"></i> Broker Accounts</span>
      <button id="addBrokerBtn" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Add Broker
      </button>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="brokersTable" class="table table-striped table-bordered align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Account Number</th>
              <th>Account Type</th>
              <th>Currency</th>
              <th>Created At</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="brokersTableBody">
            <!-- Rows loaded dynamically via manage_brokers.js -->
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Broker Modal -->
<div class="modal fade" id="brokerModal" tabindex="-1" aria-labelledby="brokerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="brokerForm">
        <div class="modal-header">
          <h5 class="modal-title" id="brokerModalLabel">Add Broker</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="brokerId" name="id">
          <div class="mb-3">
            <label for="brokerName" class="form-label">Name</label>
            <input type="text" class="form-control" id="brokerName" name="name" required>
          </div>
          <div class="mb-3">
            <label for="accountNumber" class="form-label">Account Number</label>
            <input type="text" class="form-control" id="accountNumber" name="account_number">
          </div>
          <div class="mb-3">
            <label for="accountType" class="form-label">Account Type</label>
            <select class="form-select" id="accountType" name="account_type" required>
              <option value="PEA">PEA</option>
              <option value="CTO">CTO</option>
              <option value="ASSURANCE_VIE">Assurance Vie</option>
              <option value="PER">PER</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="currency" class="form-label">Currency</label>
            <input type="text" class="form-control" id="currency" name="currency" maxlength="3" value="EUR">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/cashcue/assets/js/manage_brokers.js"></script>
<?php include __DIR__ . '/../footer.php'; ?>