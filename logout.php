<?php
// ============================================================
// STEP 4: LOGOUT
// File: logout.php
// ============================================================
require_once 'config.php';

// Destroy the session (log the user out)
session_destroy();

// Send user back to login page
header("Location: login.php");
exit;
?>