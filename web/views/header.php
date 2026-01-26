<?php
// views/header.php
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CashCue Dashboard</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link href="/cashcue/assets/css/style.css" rel="stylesheet">

  <!-- ECharts -->
  <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>

  <!-- Flatpickr CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

  <!-- Flatpickr JS -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <!-- Optional : Bootstrap theme Dark for better visibility -->
  <link rel="stylesheet" href="https://npmcdn.com/flatpickr/dist/themes/dark.css">

<?php
    if (!isset($BROKER_SCOPE)) {
        $BROKER_SCOPE = "single-or-all"; // valeur par défaut
    }
  ?>

  <!-- Configuration exposée au JavaScript -->
  <script>
    window.__BROKER_SCOPE__ = "<?php echo $BROKER_SCOPE; ?>";
    window.__CASHCUE_VERSION__ = "<?php echo htmlspecialchars($CASHCUE_VERSION); ?>";
  </script>

  <!-- Charger header.js APRES la configuration -->
  <script src="/cashcue/assets/js/header.js" defer></script>

</head>

<body>
  <div id="wrapper">

  <!-- Sidebar -->
    <div class="bg-dark border-end" id="sidebar-wrapper">
      <div class="sidebar-heading text-white p-3 fs-4 text-center">
        <strong>CashCue</strong>
      </div>

      <div class="list-group list-group-flush">

        <a href="/cashcue/index.php"
          class="list-group-item list-group-item-action bg-dark text-white">
          <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>

        <a href="/cashcue/views/admin/manage_orders.php"
          class="list-group-item list-group-item-action bg-dark text-white">
          <i class="bi bi-currency-exchange me-2"></i> Orders
        </a>

        <a href="/cashcue/views/admin/manage_instruments.php"
          class="list-group-item list-group-item-action bg-dark text-white">
          <i class="bi bi-bar-chart-line me-2"></i> Instruments
        </a>

        <a href="/cashcue/views/portfolio.php"
          class="list-group-item list-group-item-action bg-dark text-white">
          <i class="bi bi-briefcase me-2"></i> Portfolio
        </a>

        <!-- Cash domain -->
        <a href="/cashcue/views/cash.php"
          class="list-group-item list-group-item-action bg-dark text-white">
          <i class="bi bi-wallet2 me-2"></i> Cash
        </a>

        <a href="/cashcue/views/admin/manage_brokers.php"
          class="list-group-item list-group-item-action bg-dark text-white">
          <i class="bi bi-bank me-2"></i> Brokers
        </a>

        <a href="/cashcue/views/admin/manage_dividends.php"
          class="list-group-item list-group-item-action bg-dark text-white">
          <i class="bi bi-cash-coin me-2"></i> Dividends
        </a>

      </div>
    </div>
    <!-- /#sidebar-wrapper -->


    <!-- Page content wrapper -->
    <div id="page-content-wrapper" class="w-100">

      <!-- Top Navbar -->
      <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom shadow-sm py-2">
        <div class="container-fluid d-flex justify-content-between align-items-center flex-wrap">

          <!-- Left section -->
          <div class="d-flex align-items-center mb-2 mb-lg-0">
            <button class="btn btn-outline-primary me-3" id="menuToggle">
              <i class="bi bi-list"></i> Menu
            </button>
            <span class="navbar-brand fw-bold text-primary fs-5 mb-0">
              CashCue Portfolio Manager
            </span>
          </div>
          <!-- Right section: Account selector among Brokers -->
          <div class="d-flex align-items-center gap-3">

            <!-- Broker account selector -->
            <div id="brokerAccountArea" class="d-flex align-items-center">
              <label for="activeAccountSelect" class="me-2 fw-semibold text-dark">Active Account:</label>
              <select id="activeAccountSelect"
                      class="form-select form-select-sm border-primary fw-semibold text-primary"
                      style="min-width:220px; font-size:0.95rem;">
                <option value="all" selected>All Accounts</option>
              </select>
            </div>

            <!-- CashCue Version Badge -->
            <span id="cashcueVersionBadge"
                  class="badge rounded-pill bg-light text-secondary border"
                  title="CashCue application version"
                  style="font-weight:500;">
              <?= htmlspecialchars($CASHCUE_VERSION) ?>
            </span>
          </div>
        </div>
      </nav>

    <!-- Main Container -->
    <div class="container-fluid mt-4">

