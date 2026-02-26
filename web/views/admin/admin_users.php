<?php
/**
 * admin_users.php
 * --------------------------------------------------
 * CashCue – Super Admin User & API Token Management
 * --------------------------------------------------
 * Features:
 *   - Display user statistics in cards (total, active, suspended, super admins)
 *   - List all users in a responsive table
 *   - Toggle user active/suspended, delete user
 *   - Manage API tokens for selected user (list, revoke)
 *
 * Access:
 *   - SUPER ADMIN only
 *
 * Notes:
 *   - JS logic in admin_users.js
 *   - Vanilla JS, no jQuery
 *   - Broker selector disabled for Super Admin pages
 */

// CashCue app context
define('CASHCUE_APP', true);

// Authentication check
require_once __DIR__ . '/../../includes/auth.php';

// Restrict access to Super Admins
if (!isSuperAdmin()) {
    http_response_code(403);
    exit('Forbidden - Super Admin only');
}

// Disable broker selector
$BROKER_SCOPE = "disabled";

// Include helpers and header
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../header.php';
?>

<!-- Page Title -->
<div class="mb-4">
    <h1 class="h3">Super Admin – Users & API Tokens Management</h1>
    <p class="text-muted">Manage application users and their API tokens</p>
</div>

<!-- -------------------------------
     User Statistics Cards
     ------------------------------- -->
<div class="row mb-4" id="userStats">
    <!-- Populated dynamically by admin_users.js -->
    <div class="col-md-3">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <h5 class="card-title">Total Users</h5>
                <p class="card-text fw-bold" id="statTotalUsers">0</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <h5 class="card-title">Active</h5>
                <p class="card-text fw-bold" id="statActiveUsers">0</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger mb-3">
            <div class="card-body">
                <h5 class="card-title">Suspended</h5>
                <p class="card-text fw-bold" id="statSuspendedUsers">0</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning mb-3">
            <div class="card-body">
                <h5 class="card-title">Super Admins</h5>
                <p class="card-text fw-bold" id="statSuperAdmins">0</p>
            </div>
        </div>
    </div>
</div>

<!-- -------------------------------
     Users Table
     ------------------------------- -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Users</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0" id="usersTable">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Super Admin</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated dynamically by admin_users.js -->
            </tbody>
        </table>
    </div>
</div>

<!-- -------------------------------
     Selected User Info
     ------------------------------- -->
<div id="selectedUserInfo" class="mb-2 fst-italic text-primary">
    Select a user to manage their API tokens
</div>

<!-- -------------------------------
     API Tokens Table
     ------------------------------- -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">API Tokens</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0" id="tokensTable">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Expires At</th>
                    <th>Last Used At</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated dynamically by admin_users.js -->
            </tbody>
        </table>
    </div>
</div>

<!-- -------------------------------
     Action Buttons
     ------------------------------- -->
<div class="mb-3">
    <button id="btnAddUser" class="btn btn-success me-2">Add New User</button>
    <button id="btnAddToken" class="btn btn-secondary" disabled>Create Token for Selected User</button>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="addUserForm">

          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="newUsername" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="newEmail" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" id="newPassword" required>
          </div>

          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="newSuperAdmin" name="newSuperAdmin" value="1">
            <label class="form-check-label">Super Admin</label>
          </div>

          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="newIsActive" checked>
            <label class="form-check-label">Active</label>
          </div>

        </form>

        <div id="addUserAlert" class="alert alert-danger d-none"></div>
      </div>

      <div class="modal-footer">
        <button type="button" id="btnConfirmAddUser" class="btn btn-primary">
          Add User
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Close
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Create API Token Modal -->
<div class="modal fade" id="createTokenModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create API Token</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <!-- Input for Token Name -->
        <div class="mb-3">
          <label for="newTokenName" class="form-label">Token Name</label>
          <input type="text" id="newTokenName" class="form-control" placeholder="Enter a unique name">
        </div>

        <!-- Input for generated token (readonly) -->
        <div class="mb-3">
          <label for="newTokenValue" class="form-label">Generated Token</label>
          <input type="text" id="newTokenValue" class="form-control" readonly placeholder="Token will appear here after creation">
        </div>

        <p class="text-muted small">
          Once created, the token value is displayed only once. Copy it to a safe place.
        </p>
      </div>

      <div class="modal-footer">
        <button type="button" id="btnConfirmCreateToken" class="btn btn-primary">Create Token</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>

<?php
    require_once __DIR__ . '/../footer.php';
?>

<!-- -------------------------------
     Include JS
     ------------------------------- -->
<script src="/cashcue/assets/js/admin_users.js"></script>
