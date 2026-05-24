<?php
// ============================================================
// STEP 7: DASHBOARD PAGE
// File: dashboard.php
// ============================================================
require_once 'config.php';
requireLogin();

$page_title = 'Dashboard';

// ── STATS ────────────────────────────────────────────────────
$total_orders   = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$pending_orders = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='pending'")->fetch_assoc()['c'];
$today_orders   = $conn->query("SELECT COUNT(*) as c FROM orders WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$revenue        = $conn->query("SELECT SUM(total) as s FROM orders WHERE status='delivered'")->fetch_assoc()['s'] ?? 0;
$low_stock      = $conn->query("SELECT COUNT(*) as c FROM products WHERE stock < min_stock AND active=1")->fetch_assoc()['c'];
$active_outlets = $conn->query("SELECT COUNT(*) as c FROM outlets WHERE active=1")->fetch_assoc()['c'];
$active_outlets = $conn->query("SELECT COUNT(*) as c FROM outlets WHERE active=1")->fetch_assoc()['c'];

// ── RECENT ORDERS ─────────────────────────────────────────────
$recent_orders = $conn->query("
    SELECT o.*, outlet.name AS outlet_name
    FROM orders o
    JOIN outlets outlet ON o.outlet_id = outlet.id
    ORDER BY o.created_at DESC LIMIT 6
");

// ── LOW STOCK ITEMS ───────────────────────────────────────────
$low_stock_items = $conn->query("
    SELECT p.*, c.name AS cat_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stock < p.min_stock AND p.active=1
    ORDER BY p.stock ASC
");

// ── WEEKLY ORDER COUNTS (last 7 days) ─────────────────────────
$weekly = $conn->query("
    SELECT DAYNAME(created_at) as day, COUNT(*) as cnt
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at), DAYNAME(created_at)
    ORDER BY DATE(created_at)
");
$chart_data = [];
while ($row = $weekly->fetch_assoc()) {
    $chart_data[] = $row;
}

include 'includes/header.php';
?>

<!-- ── STATS ROW ── -->
<div class="stats">
  <div class="sc"><div class="si si-b">📦</div><div><div class="sv"><?= $total_orders ?></div><div class="sl">Total Orders</div></div></div>
  <div class="sc"><div class="si si-o">⏳</div><div><div class="sv"><?= $pending_orders ?></div><div class="sl">Pending Orders</div></div></div>
  <div class="sc"><div class="si si-b">📅</div><div><div class="sv"><?= $today_orders ?></div><div class="sl">Today's Orders</div></div></div>
  <div class="sc"><div class="si si-g">💰</div><div><div class="sv" style="font-size:1.3rem"><?= tk($revenue) ?></div><div class="sl">Total Revenue</div></div></div>
  <div class="sc"><div class="si si-r">⚠️</div><div><div class="sv"><?= $low_stock ?></div><div class="sl">Low Stock Items</div></div></div>
  <div class="sc"><div class="si si-gold">🏪</div><div><div class="sv"><?= $active_outlets ?></div><div class="sl">Active Outlets</div></div></div>
<div class="sc"><div class="si si-gold">🏪</div><div><div class="sv"><?= $active_outlets ?></div><div class="sl">Active Outlets</div></div></div>
</div>

<div class="g2">
  <!-- Chart -->
  <div class="card">
    <div class="ch"><h3>📊 Orders This Week</h3></div>
    <div class="cb">
      <?php if (!empty($chart_data)):
        $max = max(array_column($chart_data, 'cnt')) ?: 1;
      ?>
      <div class="mchart">
        <?php foreach ($chart_data as $d): ?>
          <?php $h = round(($d['cnt'] / $max) * 90); ?>
          <div class="mcw">
            <div class="mcv"><?= $d['cnt'] ?></div>
            <div class="mcb" style="height:<?= $h ?>%"></div>
            <div class="mcl"><?= substr($d['day'],0,3) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <div class="es" style="padding:16px"><div class="ei">📊</div><p>No data this week yet.</p></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Activity Feed -->
  <div class="card">
    <div class="ch"><h3>🔔 Activity Feed</h3></div>
    <div class="cb">
      <div class="acts">
        <?php
        $feed = $conn->query("
            SELECT o.id, o.status, o.created_at, outlet.name AS outlet_name
            FROM orders o JOIN outlets outlet ON o.outlet_id=outlet.id
            ORDER BY o.created_at DESC LIMIT 5
        ");
        while ($f = $feed->fetch_assoc()):
            $dot = $f['status']==='delivered' ? 'g' : ($f['status']==='pending' ? '' : 'o');
        ?>
        <div class="ait">
          <div class="adot <?= $dot ?>"></div>
          <div>
            <div class="atxt">Order <?= oid($f['id']) ?> — <strong><?= htmlspecialchars($f['outlet_name']) ?></strong> <?= statusBadge($f['status']) ?></div>
            <div class="atime"><?= date('d M Y H:i', strtotime($f['created_at'])) ?></div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>
</div>

<!-- Recent Orders Table -->
<div class="card">
  <div class="ch"><h3>📋 Recent Orders</h3><a href="orders.php" class="btn btn-o sm">View All</a></div>
  <div class="tw">
    <table>
      <thead><tr><th>Order ID</th><th>Outlet</th><th>Date</th><th>Status</th><th>Amount</th><th>Action</th></tr></thead>
      <tbody>
        <?php while ($o = $recent_orders->fetch_assoc()): ?>
        <tr>
          <td><strong><?= oid($o['id']) ?></strong></td>
          <td><?= htmlspecialchars($o['outlet_name']) ?></td>
          <td><?= date('d M Y H:i', strtotime($o['created_at'])) ?></td>
          <td><?= statusBadge($o['status']) ?></td>
          <td><strong><?= tk($o['total']) ?></strong></td>
          <td><a href="order_detail.php?id=<?= $o['id'] ?>&back=dashboard" class="btn btn-o xs">👁 View</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Low Stock (only for admin/factory_manager) -->
<?php if (in_array($role, ['admin','factory_manager'])): ?>
<div class="card">
  <div class="ch"><h3>⚠️ Low Stock Alerts</h3><a href="inventory.php" class="btn btn-o sm">Manage</a></div>
  <div class="tw">
    <table>
      <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Min Level</th><th>Status</th></tr></thead>
      <tbody>
        <?php if ($low_stock_items->num_rows > 0):
          while ($p = $low_stock_items->fetch_assoc()):
            $pct = min(100, round(($p['stock'] / $p['min_stock']) * 50));
        ?>
        <tr>
          <td><?= $p['emoji'] ?> <strong><?= htmlspecialchars($p['name']) ?></strong></td>
          <td><?= htmlspecialchars($p['cat_name']) ?></td>
          <td><strong style="color:var(--orange)"><?= $p['stock'] ?></strong>
            <div class="sbar"><div class="sf sf-low" style="width:<?= $pct ?>%"></div></div></td>
          <td><?= $p['min_stock'] ?></td>
          <td><span class="badge b-low">⚠ Low</span></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="5"><div class="es" style="padding:16px"><div class="ei">✅</div><p>All stock levels are healthy!</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>