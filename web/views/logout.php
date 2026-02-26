<?php
// ------------------------------------------------------------
// Logout script
//// This script destroys the user session and redirects to the login page.
// ------------------------------------------------------------
    // Start session and destroy it
    session_start();
    $_SESSION = [];
    session_destroy();

    // Redirect to login page after logout
    header("Location: /cashcue/views/login.php");
    exit;