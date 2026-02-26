<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

error_log("Checkpoint 0 index.php: Starting execution");

    // Path to the auth.php file
    $authFilePath = __DIR__ . '/includes/auth.php';

    // Check if the auth.php file exists
    if (!file_exists($authFilePath)) {
        error_log("Error: The file 'auth.php' does not exist at path: " . $authFilePath);
        die("Error : The file 'auth.php' does not exist at path: " . $authFilePath);
    }

    // Include the authentication check script
    require_once $authFilePath;
error_log("Checkpoint 1 index.php: Authentication check included successfully");

    // Set layout mode for the dashboard
    // This variable can be used in the included header.php to adjust the layout (e.g., show/hide sidebar)
    // "app" means the user is authenticated and should see the full dashboard layout
    $LAYOUT_MODE = "app";

    // path to the dashboard.php file
    $dashboardFilePath = __DIR__ . '/views/dashboard.php';

    error_log("Dashboard file path: " . $dashboardFilePath);

    // Verify that the dashboard.php file exists before including it
    if (!file_exists($dashboardFilePath)) {
        error_log("Error: The file 'dashboard.php' does not exist in the 'views' directory. Check the path: " . $dashboardFilePath);
        die("Error : The file 'dashboard.php' does not exist in the 'views' directory. Check the path: " . $dashboardFilePath);
    }

    // Include the dashboard file
    include $dashboardFilePath;
error_log("Checkpoint 2 index.php: Dashboard included successfully");
?>
