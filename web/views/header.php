<?php
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
  <!-- Global Header Controller (deferred) -->
  <!-- ======================================================= -->
  <script src="/cashcue/assets/js/header.js" defer></script>
  <script src="/cashcue/assets/js/notifications.js"></script>
</head>

<body>
<div id="wrapper">

  <!-- ======================================================= -->
  <!-- Sidebar -->
  <!-- ======================================================= -->
  <div class="bg-dark border-end" id="sidebar-wrapper">
    <div class="sidebar-heading text-white p-3 fs-4 text-center">
      <strong>CashCue</strong>
    </div>

    <div class="list-group list-group-flush">

      <a href="/cashcue/index.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-speedometer2 me-2"></i> Dashboard
      </a>

      <a href="/cashcue/views/admin/manage_orders.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-currency-exchange me-2"></i> Order
      </a>

      <a href="/cashcue/views/admin/manage_instruments.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-bar-chart-line me-2"></i> Instrument
      </a>

      <a href="/cashcue/views/portfolio.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-briefcase me-2"></i> Portfolio
      </a>

      <a href="/cashcue/views/cash.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-wallet2 me-2"></i> Cash
      </a>

      <a href="/cashcue/views/admin/manage_brokers.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-bank me-2"></i> Broker Account
      </a>

      <a href="/cashcue/views/admin/manage_dividends.php" class="list-group-item list-group-item-action bg-dark text-white">
        <i class="bi bi-cash-coin me-2"></i> Dividend
      </a>

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

        </div>
      </div>
    </nav>

    <!-- ======================================================= -->
    <!-- Global Application Alerts -->
    <!-- ======================================================= -->
    <div id="alertContainer" class="container-fluid mt-3 px-4"></div>

  
    <!-- ===================================================== -->
    <!-- Main Content Container -->
    <!-- ===================================================== -->
    <div class="container-fluid mt-4">
