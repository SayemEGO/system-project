<?php
// ============================================================
// STEP 2: DATABASE CONNECTION
// File: config.php
// Save this in: C:/xampp/htdocs/bakery/config.php
// ============================================================

// Change these if your XAMPP settings are different
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // default XAMPP username
define('DB_PASS', '');           // default XAMPP password (empty)
define('DB_NAME', 'bakery_system');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
}

// Set character encoding
$conn->set_charset("utf8");

// ── SESSION START ────────────────────────────────────────────
// This keeps the user logged in across pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── HELPER: Check if user is logged in ───────────────────────
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

// ── HELPER: Check if user has a specific role ─────────────────
function requireRole($roles) {
    if (!in_array($_SESSION['role'], (array)$roles)) {
        header("Location: dashboard.php");
        exit;
    }
}

// ── HELPER: Format money in Taka ──────────────────────────────
function tk($amount) {
    return '৳' . number_format($amount, 2);
}

// ── HELPER: Format order ID like #0001 ────────────────────────
function oid($id) {
    return '#' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

// ── HELPER: Get outlet name by ID ─────────────────────────────
function getOutletName($conn, $id) {
    $r = $conn->query("SELECT name FROM outlets WHERE id=$id");
    $row = $r->fetch_assoc();
    return $row ? $row['name'] : '?';
}

// ── HELPER: Status badge HTML ─────────────────────────────────
function statusBadge($status) {
    $classes = [
        'pending'    => 'b-pend',
        'processing' => 'b-proc',
        'ready'      => 'b-ready',
        'delivered'  => 'b-deliv',
        'cancelled'  => 'b-canc',
        'assigned'   => 'b-proc',
        'in_transit' => 'b-ready',
        'failed'     => 'b-canc',
    ];
    $class = $classes[$status] ?? 'b-pend';
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class='badge $class'>$label</span>";
}

// ── HELPER: Role badge HTML ────────────────────────────────────
function roleBadge($role) {
    $classes = [
        'admin'           => 'b-admin',
        'factory_manager' => 'b-fact',
        'outlet_manager'  => 'b-outl',
        'delivery'        => 'b-deli',
    ];
    $labels = [
        'admin'           => 'Admin',
        'factory_manager' => 'Factory Mgr',
        'outlet_manager'  => 'Outlet Mgr',
        'delivery'        => 'Delivery',
    ];
    $class = $classes[$role] ?? '';
    $label = $labels[$role] ?? $role;
    return "<span class='badge $class'>$label</span>";
}
?>