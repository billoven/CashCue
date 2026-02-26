<?php
/**
 * manage_brokers.php
 * --------------------------------------------------
 * Broker Account Administration View – CashCue
 *
 * Features:
 *  - List all broker accounts (CashCueTable)
 *  - Create new broker account
 *  - Edit existing broker account
 *
 * Business Rules:
 *  - has_cash_account & initial_deposit editable ONLY at creation
 *  - Mandatory comment for traceability
 *  - JS handles create/edit mode behaviour
 */

// define a constant to indicate that we are in the CashCue app context
// This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
define('CASHCUE_APP', true);

// Include authentication check
require_once __DIR__ . '/../../includes/auth.php';

$BROKER_SCOPE = "disabled";

require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../header.php';
?>

<div class="container-fluid px-4">
  <h1 class="mt-4">Broker Account Management</h1>

  <ol class="breadcrumb mb-4">
    <li class="breadcrumb-item">
      <a href="../../index.php">Dashboard</a>
    </li>
    <li class="breadcrumb-item active">
      Manage Broker Accounts
    </li>
  </ol>

  <!-- ============================================================
       Broker Accounts Table
  ============================================================ -->
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>
        <i class="fas fa-building me-1"></i> Broker Accounts
      </span>

      <button id="addBrokerBtn" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Add Broker
      </button>
    </div>

    <div class="card-body">
      <div class="table-responsive" id="brokersTableContainer">
        <!-- CashCueTable renders here -->
      </div>
    </div>
  </div>

  <!-- ============================================================
       Broker Modal (Add / Edit)
       Compact Horizontal Layout
  ============================================================ -->
  <div class="modal fade"
       id="brokerModal"
       tabindex="-1"
       aria-labelledby="brokerModalLabel"
       aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">

        <form id="brokerForm">

          <!-- ======================================================
               Modal Header
          ======================================================= -->
          <div class="modal-header d-flex justify-content-between align-items-center">

            <!-- Left: Title -->
            <h5 class="modal-title" id="brokerModalLabel">
              Add Broker
            </h5>

            <!-- Right: Created At (visible only in edit mode) -->
            <small id="brokerCreatedAt"
                class="text-muted fw-semibold"
                style="display: none;">
          </small>

            <button type="button"
                    class="btn-close ms-2"
                    data-bs-dismiss="modal"
                    aria-label="Close"></button>
          </div>

          <!-- ======================================================
               Modal Body
          ======================================================= -->
          <div class="modal-body">

            <!-- Hidden ID (used only in edit mode) -->
            <input type="hidden" id="brokerId" name="id">

            <!-- ==================================================
                 Broker Information Section
            =================================================== -->
            <h6 class="fw-bold mb-4">Broker Information</h6>

            <!-- Name -->
            <div class="row mb-3">
              <label for="brokerName" class="col-md-4 col-form-label">
                Name
              </label>
              <div class="col-md-8">
                <input type="text"
                       class="form-control"
                       id="brokerName"
                       name="name"
                       required>
              </div>
            </div>

            <!-- Account Number -->
            <div class="row mb-3">
              <label for="accountNumber" class="col-md-4 col-form-label">
                Account Number
              </label>
              <div class="col-md-8">
                <input type="text"
                       class="form-control"
                       id="accountNumber"
                       name="account_number">
              </div>
            </div>

            <!-- Account Type -->
            <div class="row mb-3">
              <label for="accountType" class="col-md-4 col-form-label">
                Account Type
              </label>
              <div class="col-md-8">
                <select class="form-select"
                        id="accountType"
                        name="account_type"
                        required>
                  <option value="PEA">PEA</option>
                  <option value="CTO">CTO</option>
                  <option value="ASSURANCE_VIE">Assurance Vie</option>
                  <option value="PER">PER</option>
                  <option value="OTHER">Other</option>
                </select>
              </div>
            </div>

            <!-- Currency -->
            <div class="row mb-4">
              <label for="currency" class="col-md-4 col-form-label">
                Currency
              </label>
              <div class="col-md-8">
                <input type="text"
                       class="form-control"
                       id="currency"
                       name="currency"
                       maxlength="3"
                       value="EUR">
              </div>
            </div>

            <!-- ==================================================
                 Cash Account Configuration
                 (Visible ONLY in Create Mode via JS)
            =================================================== -->
            <hr>
            <div id="cashAccountSection">

              <h6 class="fw-bold mb-4">Cash Account Configuration</h6>

              <!-- Cash Account Toggle -->
              <div class="row mb-3">
                <label class="col-md-4 col-form-label">
                  Cash Account
                </label>
                <div class="col-md-8">
                  <div class="form-check form-switch">
                    <input class="form-check-input"
                           type="checkbox"
                           id="hasCashAccount"
                           name="has_cash_account">
                    <label class="form-check-label"
                           for="hasCashAccount">
                      Create Cash Account
                    </label>
                  </div>
                </div>
              </div>

              <!-- Initial Deposit -->
              <div class="row mb-3" id="initialDepositContainer">
                <label for="initialDeposit" class="col-md-4 col-form-label">
                  Initial Deposit
                </label>
                <div class="col-md-8">
                  <input type="number"
                         step="0.01"
                         min="0"
                         class="form-control"
                         id="initialDeposit"
                         name="initial_deposit"
                         value="0.00">
                  <div class="form-text">
                    Applied only at creation time.
                  </div>
                </div>
              </div>

            </div>

            <!-- ==================================================
                 Mandatory Operation Comment
            =================================================== -->
            <hr>
            <h6 class="fw-bold mb-4">Operation Comment</h6>

            <div class="row mb-3">
              <label for="brokerComment" class="col-md-4 col-form-label">
                Comment <span class="text-danger">*</span>
              </label>
              <div class="col-md-8">
                <textarea class="form-control"
                          id="brokerComment"
                          name="comment"
                          rows="2"
                          required></textarea>
                <div class="form-text">
                  Mandatory for audit and traceability.
                </div>
              </div>
            </div>

          </div>

          <!-- ======================================================
               Modal Footer
          ======================================================= -->
          <div class="modal-footer">
            <button type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">
              Cancel
            </button>
            <button type="submit"
                    class="btn btn-primary">
              Save
            </button>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     Page JS
============================================================= -->
<script src="/cashcue/assets/js/CashCueTable.js"></script>
<script src="/cashcue/assets/js/manage_brokers.js"></script>

<?php include __DIR__ . '/../footer.php'; ?>
