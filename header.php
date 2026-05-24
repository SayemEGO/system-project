<?php
// ============================================================
// SHARED HEADER + SIDEBAR  (FINAL COMPLETE VERSION)
// File: includes/header.php
// ============================================================

$role       = $_SESSION['role'];
$user_name  = $_SESSION['user_name'];
$user_id    = $_SESSION['user_id'];
$initials   = strtoupper(substr($user_name, 0, 1));

$role_labels = [
    'admin'           => 'Administrator',
    'factory_manager' => 'Factory Manager',
    'outlet_manager'  => 'Outlet Manager',
    'delivery'        => 'Delivery Staff',
];
$role_label = $role_labels[$role] ?? $role;

// ── NAVIGATION MENU ─────────────────────────────────────────
// Format: [ 'file.php', 'icon', 'Label', ['allowed', 'roles'] ]
$nav_sections = [
    'MAIN' => [
        ['dashboard.php',       '📊', 'Dashboard',       ['admin','factory_manager','outlet_manager','delivery']],
        ['notifications.php',   '🔔', 'Notifications',   ['admin','factory_manager']],
        ['search.php',          '🔍', 'Search',           ['admin','factory_manager','outlet_manager']],
    ],
    'ORDERS' => [
        ['place_order.php',     '🛒', 'Place Order',      ['outlet_manager']],
        ['my_orders.php',       '📋', 'My Orders',        ['outlet_manager']],
        ['orders.php',          '📦', 'All Orders',       ['admin','factory_manager']],
        ['factory.php',         '🏭', 'Factory Panel',    ['admin','factory_manager']],
        ['deliveries.php',      '🚚', 'Deliveries',       ['admin','factory_manager','delivery']],
    ],
    'MANAGEMENT' => [
        ['inventory.php',       '📦', 'Inventory',        ['admin','factory_manager']],
        ['products.php',        '🧁', 'Products',         ['admin','factory_manager']],
        ['categories.php',      '📂', 'Categories',       ['admin']],
        ['outlets.php',         '🏪', 'Outlets',          ['admin']],
        ['users.php',           '👥', 'Users',            ['admin']],
    ],
    'ANALYTICS' => [
        ['reports.php',         '📈', 'Reports',          ['admin','factory_manager']],
        ['export.php',          '📥', 'Export Data',      ['admin','factory_manager']],
    ],
    'ACCOUNT' => [
        ['profile.php',         '👤', 'My Profile',       ['admin','factory_manager','outlet_manager','delivery']],
    ],
];

// ── COUNT PENDING ORDERS for badge ─────────────────────────
$pending_count = 0;
$res = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'");
if ($res) $pending_count = (int)$res->fetch_assoc()['c'];

// ── COUNT NOTIFICATIONS (low stock + old pending) ───────────
$notif_count = 0;
if (in_array($role, ['admin','factory_manager'])) {
    $ls  = $conn->query("SELECT COUNT(*) as c FROM products WHERE stock < min_stock AND active=1")->fetch_assoc()['c'];
    $op  = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)")->fetch_assoc()['c'];
    $nd  = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='ready' AND id NOT IN (SELECT order_id FROM deliveries)")->fetch_assoc()['c'];
    $notif_count = $ls + $op + $nd;
}

$current_file = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title ?? 'Bakery System') ?> — Bakery Management</title>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div id="app">

  <!-- ══════════════════════════
       SIDEBAR
  ══════════════════════════ -->
  <aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sb-brand">
      <div class="ico">🍞</div>
      <div>
        <h2>Bakery System</h2>
        <span>Smart Distribution</span>
      </div>
    </div>

    <!-- User Info -->
    <div class="sb-user">
      <div class="sb-av"><?= $initials ?></div>
      <div>
        <div class="sb-uname"><?= htmlspecialchars($user_name) ?></div>
        <div class="sb-urole"><?= htmlspecialchars($role_label) ?></div>
      </div>
    </div>

    <!-- Nav Links -->
    <nav class="sb-nav">
      <?php foreach ($nav_sections as $section_name => $items):
        $visible = array_filter($items, fn($i) => in_array($role, $i[3]));
        if (empty($visible)) continue;
      ?>
        <div class="sb-sec"><?= $section_name ?></div>

        <?php foreach ($visible as [$file, $icon, $label, $roles]):
          $is_active = ($current_file === $file) ? 'act' : '';
        ?>
          <a href="<?= $file ?>" class="<?= $is_active ?>">
            <span style="font-size:.9rem;width:17px;text-align:center"><?= $icon ?></span>
            <?= htmlspecialchars($label) ?>

            <?php if ($file === 'orders.php' && $pending_count > 0): ?>
              <span class="nb"><?= $pending_count ?></span>
            <?php endif; ?>

            <?php if ($file === 'notifications.php' && $notif_count > 0): ?>
              <span class="nb" style="background:var(--orange)"><?= $notif_count ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>

      <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="sb-foot">
      <a href="logout.php">🚪 Logout</a>
    </div>

  </aside>

  <!-- ══════════════════════════
       MAIN AREA
  ══════════════════════════ -->
  <div class="main">

    <!-- Sticky Topbar -->
    <header class="topbar">
      <button class="mob-tog" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
      <h2><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h2>

      <!-- Topbar right: search + notifications shortcut -->
      <div style="display:flex;align-items:center;gap:10px;margin-left:auto">
        <?php if (in_array($role,['admin','factory_manager','outlet_manager'])): ?>
        <a href="search.php" class="btn btn-s sm" style="padding:5px 10px">🔍</a>
        <?php endif; ?>

        <?php if (in_array($role,['admin','factory_manager']) && $notif_count > 0): ?>
        <a href="notifications.php" class="btn btn-s sm" style="padding:5px 10px;position:relative">
          🔔
          <span style="position:absolute;top:-5px;right:-5px;background:var(--red);color:#fff;border-radius:50%;width:16px;height:16px;font-size:.6rem;display:flex;align-items:center;justify-content:center;font-weight:700"><?= $notif_count ?></span>
        </a>
        <?php endif; ?>

        <a href="profile.php" style="display:flex;align-items:center;gap:7px;text-decoration:none;color:var(--black)">
          <div style="width:30px;height:30px;background:var(--red);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.78rem;flex-shrink:0"><?= $initials ?></div>
        </a>
      </div>

      <span class="tdate" style="margin-left:12px"><?= date('D, d M Y') ?></span>
    </header>

    <!-- Page Content -->
    <div class="content">