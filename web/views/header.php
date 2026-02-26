<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("Checkpoint 0: Starting execution of header.php");

// ============================================================
// CashCue – Global Header
// ============================================================
// Responsibilities:
// - Load global CSS / JS
// - Define broker scope for the current page
// - Expose configuration to JavaScript
// - Render the global navigation layout
//
// IMPORTANT:
// - No business logic here
// - No broker decision logic here
// - Broker behavior is handled in header.js
// ============================================================
// avoid direct access to this file
// This file is meant to be included in other views, not accessed directly via URL
if (!defined('CASHCUE_APP')) {
  error_log("Direct access to header.php detected. Exiting. CASHCUE_APP is not defined.");  
  exit;
}
// Start session if not already started
// This allows us to check authentication status and user preferences if needed
// Note: We do not enforce authentication here because some pages (like login.php) 
// use this header but should be accessible without authentication. 
// Each page is responsible for including auth.php if it requires authentication.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------------------------------------
// CashCue version helper
// ------------------------------------------------------------
function cashcue_get_version(): string
{
    $versionFile = '/opt/cashcue/VERSION';

    if (!is_readable($versionFile)) {
        return 'version unknown';
    }

    $version = trim(@file_get_contents($versionFile));
    return $version !== '' ? $version : 'version unavailable';
}

$CASHCUE_VERSION = cashcue_get_version();
error_log("CashCue version loaded: " . $CASHCUE_VERSION);

// ------------------------------------------------------------
// Broker scope (page-level configuration)
// ------------------------------------------------------------
// Allowed values:
//  - disabled
//  - single
//  - single-or-all
//  - portfolio
//
// Each page MAY override $BROKER_SCOPE before including header.php
//
if (!isset($BROKER_SCOPE)) {
    $BROKER_SCOPE = "single-or-all";
}
// ------------------------------------------------------------
// Layout mode (app or public)
// ------------------------------------------------------------
if (!isset($LAYOUT_MODE)) {
    $LAYOUT_MODE = "app";
}
error_log("Header included with BROKER_SCOPE='$BROKER_SCOPE' and LAYOUT_MODE='$LAYOUT_MODE'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CashCue Dashboard</title>

  <!-- ======================================================= -->
  <!-- Vendor CSS -->
  <!-- ======================================================= -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Thème Bootstrap : https://bootswatch.com/pulse/
  <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/pulse/bootstrap.min.css" rel="stylesheet">
  <!-- Icônes Bootstrap 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
-->
  <!-- ======================================================= -->
  <!-- Application CSS -->
  <!-- ======================================================= -->
  <link href="/cashcue/assets/css/style.css" rel="stylesheet">
  <link rel="stylesheet" href="/cashcue/assets/css/notifications.css">


  <!-- ======================================================= -->
  <!-- Vendor JS (head-safe libraries only) -->
  <!-- ======================================================= -->
  <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <!-- ======================================================= -->
  <!-- Global configuration exposed to JavaScript -->
  <!-- ======================================================= -->
  <script>
    window.__BROKER_SCOPE__ = "<?= htmlspecialchars($BROKER_SCOPE, ENT_QUOTES) ?>";
    window.__CASHCUE_VERSION__ = "<?= htmlspecialchars($CASHCUE_VERSION, ENT_QUOTES) ?>";
  </script>

  <!-- ======================================================= -->
  <!-- Custom JS (deferred) session duration variables         -->
  <!-- These are used by header.js to manage session timeout -->
  <!-- ======================================================= -->
  <script>
    window.__SESSION_DURATION__ = <?= $_SESSION['SESSION_DURATION'] ?? 0 ?>;
    window.__LAST_ACTIVITY__   = <?= $_SESSION['LAST_ACTIVITY'] ?? 0 ?>;
  </script>

  <!-- ======================================================= -->
  <!-- Global Header Controller (deferred) -->
  <!-- ======================================================= -->
  <script src="/cashcue/assets/js/header.js" defer></script>
  <script src="/cashcue/assets/js/notifications.js"></script>
</head>

<body>

<?php if ($LAYOUT_MODE === "app"): ?>
<div id="wrapper">

  <!-- ======================================================= -->
  <!-- Sidebar -->
  <!-- ======================================================= -->
  <div class="bg-dark border-end" id="sidebar-wrapper">
    <div class="sidebar-heading text-white p-3 fs-4 text-center">
      <strong>CashCue</strong>
    </div>

    <div class="list-group list-group-flush flex-grow-1">

      <a href="/cashcue/index.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
      </a>

      <a href="/cashcue/views/portfolio.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-briefcase me-2"></i> Portfolio
      </a>
      
      <a href="/cashcue/views/admin/manage_orders.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-currency-exchange me-2"></i> Order
      </a>

      <a href="/cashcue/views/admin/manage_dividends.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-cash-coin me-2"></i> Dividend
      </a>

      <a href="/cashcue/views/cash.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-wallet2 me-2"></i> Cash
      </a>

     <a href="/cashcue/views/admin/manage_brokers.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-bank me-2"></i> Broker Account
     </a>

      <a href="/cashcue/views/admin/manage_instruments.php" 
        class="list-group-item list-group-item-action bg-dark text-white">
          <i class="bi bi-bar-chart-line me-2"></i> 
          Instruments

          <?php if (!isSuperAdmin()): ?>
              <i class="bi bi-lock-fill ms-2 text-warning" 
                title="Modification restricted to administrators"></i>
          <?php endif; ?>
      </a>
      <?php if (isSuperAdmin()): ?>
      <a href="/cashcue/views/admin/admin_users.php" 
        class="list-group-item list-group-item-action bg-dark text-white mt-auto">
        <i class="bi bi-people me-2"></i> Admin Users
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ======================================================= -->
  <!-- Page Content Wrapper -->
  <!-- ======================================================= -->
  <div id="page-content-wrapper" class="w-100">

    <!-- ===================================================== -->
    <!-- Top Navbar -->
    <!-- ===================================================== -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom shadow-sm py-2">
      <div class="container-fluid d-flex justify-content-between align-items-center flex-wrap">

        <!-- Left -->
        <div class="d-flex align-items-center mb-2 mb-lg-0">
          <button class="btn btn-outline-primary me-3" id="menuToggle">
            <i class="bi bi-list"></i> Menu
          </button>
          <span class="navbar-brand fw-bold text-primary fs-5 mb-0">
            CashCue Portfolio Manager
          </span>
        </div>

        <!-- Right -->
        <div class="d-flex align-items-center gap-3">

          <!-- Broker Account Selector (controlled by header.js) -->
          <div id="brokerAccountArea" class="d-flex align-items-center">
            <label for="activeAccountSelect" class="me-2 fw-semibold text-dark">
              Active Account:
            </label>
            <select
              id="activeAccountSelect"
              class="form-select form-select-sm border-primary fw-semibold text-primary"
              style="min-width:220px; font-size:0.95rem;"
            >
              <option>Loading…</option>
            </select>
          </div>

          <!-- CashCue Version -->
          <span
            class="badge rounded-pill bg-light text-secondary border"
            title="CashCue application version"
            style="font-weight:500;"
          >
            <?= htmlspecialchars($CASHCUE_VERSION) ?>
          </span>

          <!-- User Dropdown -->
          <?php if (isset($_SESSION['user_id'])): ?>
          <div class="dropdown">
            <button class="btn btn-light position-relative"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    title="<?= htmlspecialchars($_SESSION['username']) ?>">

                <i class="bi bi-person-circle fs-4"></i>

                <?php if (!empty($_SESSION['is_super_admin'])): ?>
                    <span class="position-absolute top-0 start-100 translate-middle 
                                badge rounded-pill bg-danger"
                          style="font-size:0.6rem;">
                        ADMIN
                    </span>
                <?php endif; ?>

            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width:260px;">

                <li class="dropdown-item-text fw-semibold">
                    <?= htmlspecialchars($_SESSION['username']) ?>
                </li>

                <li class="dropdown-item-text small text-muted">
                    User ID: <?= (int)$_SESSION['user_id'] ?>
                </li>

                <li><hr class="dropdown-divider"></li>

                <li class="dropdown-item-text small text-muted">
                    Session expires in:
                    <span id="sessionTimer" class="fw-bold text-danger">
                        --:--
                    </span>
                </li>

                <li><hr class="dropdown-divider"></li>

                <li>
                    <a class="dropdown-item text-danger"
                      href="/cashcue/views/logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>
                        Logout
                    </a>
                </li>

            </ul>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </nav>
<?php endif; ?>

    <!-- ======================================================= -->
    <!-- Global Application Alerts -->
    <!-- ======================================================= -->
    <div id="alertContainer" class="container-fluid mt-3 px-4"></div>

<?php if ($LAYOUT_MODE === "app"): ?>  
    <!-- ===================================================== -->
    <!-- Main Content Container -->
    <!-- ===================================================== -->
    <div class="container-fluid mt-4">
<?php else: ?>
<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh;">
<?php endif; ?>