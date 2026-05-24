<?php
// ============================================================
// EXTRA 3: NOTIFICATIONS / ALERTS CENTER
// File: notifications.php
// Shows all important system alerts in one place:
//   - Low stock products
//   - Pending orders (unattended)
//   - Orders stuck in processing
//   - Unassigned deliveries
//   - Outlets with no manager
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin', 'factory_manager']);

$page_title = 'Notifications';

// ── 1. LOW STOCK PRODUCTS ─────────────────────────────────────
$low_stock = $conn->query("
    SELECT p.*, c.name AS cat_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stock < p.min_stock AND p.active = 1
    ORDER BY p.stock ASC
");

// ── 2. OUT OF STOCK ───────────────────────────────────────────
$out_of_stock = $conn->query("
    SELECT p.*, c.name AS cat_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stock = 0 AND p.active = 1
");

// ── 3. PENDING ORDERS OLDER THAN 2 HOURS ─────────────────────
$old_pending = $conn->query("
    SELECT o.*, outlet.name AS outlet_name
    FROM orders o
    JOIN outlets outlet ON o.outlet_id = outlet.id
    WHERE o.status = 'pending'
      AND o.created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY o.created_at ASC
");

// ── 4. ORDERS STUCK IN PROCESSING > 4 HOURS ──────────────────
$stuck_processing = $conn->query("
    SELECT o.*, outlet.name AS outlet_name
    FROM orders o
    JOIN outlets outlet ON o.outlet_id = outlet.id
    WHERE o.status = 'processing'
      AND o.created_at < DATE_SUB(NOW(), INTERVAL 4 HOUR)
    ORDER BY o.created_at ASC
");

// ── 5. READY ORDERS WITH NO DELIVERY ASSIGNED ─────────────────
$no_delivery = $conn->query("
    SELECT o.*, outlet.name AS outlet_name
    FROM orders o
    JOIN outlets outlet ON o.outlet_id = outlet.id
    WHERE o.status = 'ready'
      AND NOT EXISTS (SELECT 1 FROM deliveries d WHERE d.order_id = o.id)
    ORDER BY o.created_at ASC
");

// ── 6. OUTLETS WITH NO MANAGER ────────────────────────────────
$no_manager = $conn->query("
    SELECT * FROM outlets
    WHERE manager_id IS NULL AND active = 1
");

// ── COUNT TOTALS FOR SUMMARY ──────────────────────────────────
$counts = [
    'low_stock'        => $low_stock->num_rows,
    'out_of_stock'     => $out_of_stock->num_rows,
    'old_pending'      => $old_pending->num_rows,
    'stuck_processing' => $stuck_processing->num_rows,
    'no_delivery'      => $no_delivery->num_rows,
    'no_manager'       => $no_manager->num_rows,
];
$total_alerts = array_sum($counts);

include 'includes/header.php';
?>

<div class="ph">
  <div><h1>🔔 Notifications</h1><p>System alerts that need your attention</p></div>
  <?php if ($total_alerts > 0): ?>
  <span class="badge b-pend" style="font-size:.8rem;padding:5px 12px"><?= $total_alerts ?> Total Alerts</span>
  <?php else: ?>
  <span class="badge b-ok" style="font-size:.8rem;padding:5px 12px">✅ All Clear</span>
  <?php endif; ?>
</div>

<?php if ($total_alerts === 0): ?>
<!-- All clear -->
<div class="card">
  <div class="cb">
    <div class="es">
      <div class="ei">✅</div>
      <h3>No alerts right now!</h3>
      <p>Everything looks good. Stock levels are healthy and all orders are on track.</p>
    </div>
  </div>
</div>
<?php endif; ?>


<!-- ── OUT OF STOCK ── -->
<?php if ($counts['out_of_stock'] > 0): ?>
<div class="card">
  <div class="ch">
    <h3>🚨 Out of Stock <span class="badge b-canc" style="margin-left:6px"><?= $counts['out_of_stock'] ?></span></h3>
    <a href="inventory.php" class="btn btn-p sm">Fix Now</a>
  </div>
  <div class="tw">
    <table>
      <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Min Level</th><th>Action</th></tr></thead>
      <tbody>
        <?php $out_of_stock->data_seek(0); while ($p = $out_of_stock->fetch_assoc()): ?>
        <tr>
          <td><?= $p['emoji'] ?> <strong><?= htmlspecialchars($p['name']) ?></strong></td>
          <td><?= htmlspecialchars($p['cat_name']) ?></td>
          <td><strong style="color:var(--red)">0</strong></td>
          <td><?= $p['min_stock'] ?></td>
          <td><a href="inventory.php" class="btn btn-p xs">+ Restock</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>


<!-- ── LOW STOCK ── -->
<?php if ($counts['low_stock'] > 0): ?>
<div class="card">
  <div class="ch">
    <h3>⚠️ Low Stock <span class="badge b-pend" style="margin-left:6px"><?= $counts['low_stock'] ?></span></h3>
    <a href="inventory.php?filter=low" class="btn btn-w sm">View All</a>
  </div>
  <div class="tw">
    <table>
      <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Min Level</th><th>Shortfall</th><th>Action</th></tr></thead>
      <tbody>
        <?php $low_stock->data_seek(0); while ($p = $low_stock->fetch_assoc()):
          $pct = min(100, round(($p['stock'] / max(1,$p['min_stock'])) * 50));
          $short = $p['min_stock'] - $p['stock'];
        ?>
        <tr>
          <td><?= $p['emoji'] ?> <strong><?= htmlspecialchars($p['name']) ?></strong></td>
          <td><?= htmlspecialchars($p['cat_name']) ?></td>
          <td>
            <strong style="color:var(--orange)"><?= $p['stock'] ?></strong>
            <div class="sbar"><div class="sf sf-low" style="width:<?= $pct ?>%"></div></div>
          </td>
          <td><?= $p['min_stock'] ?></td>
          <td><span style="color:var(--red);font-weight:700">-<?= $short ?></span></td>
          <td><a href="inventory.php" class="btn btn-o xs">+ Restock</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>


<!-- ── PENDING ORDERS > 2 HOURS ── -->
<?php if ($counts['old_pending'] > 0): ?>
<div class="card">
  <div class="ch">
    <h3>⏰ Pending Orders (Over 2 Hours Old) <span class="badge b-pend" style="margin-left:6px"><?= $counts['old_pending'] ?></span></h3>
    <a href="factory.php" class="btn btn-p sm">Go to Factory</a>
  </div>
  <div class="tw">
    <table>
      <thead><tr><th>Order ID</th><th>Outlet</th><th>Placed At</th><th>Amount</th><th>Action</th></tr></thead>
      <tbody>
        <?php while ($o = $old_pending->fetch_assoc()): ?>
        <tr>
          <td><strong><?= oid($o['id']) ?></strong></td>
          <td><?= htmlspecialchars($o['outlet_name']) ?></td>
          <td style="color:var(--orange)"><?= date('d M Y H:i', strtotime($o['created_at'])) ?></td>
          <td><strong><?= tk($o['total']) ?></strong></td>
          <td>
            <a href="order_detail.php?id=<?= $o['id'] ?>&back=notifications" class="btn btn-o xs">👁 View</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>


<!-- ── STUCK IN PROCESSING > 4 HOURS ── -->
<?php if ($counts['stuck_processing'] > 0): ?>
<div class="card">
  <div class="ch">
    <h3>🔄 Stuck in Processing (Over 4 Hours) <span class="badge b-proc" style="margin-left:6px"><?= $counts['stuck_processing'] ?></span></h3>
    <a href="factory.php" class="btn btn-p sm">Go to Factory</a>
  </div>
  <div class="tw">
    <table>
      <thead><tr><th>Order ID</th><th>Outlet</th><th>Started At</th><th>Amount</th><th>Action</th></tr></thead>
      <tbody>
        <?php while ($o = $stuck_processing->fetch_assoc()): ?>
        <tr>
          <td><strong><?= oid($o['id']) ?></strong></td>
          <td><?= htmlspecialchars($o['outlet_name']) ?></td>
          <td style="color:var(--blue)"><?= date('d M Y H:i', strtotime($o['created_at'])) ?></td>
          <td><strong><?= tk($o['total']) ?></strong></td>
          <td>
            <a href="order_detail.php?id=<?= $o['id'] ?>&back=notifications" class="btn btn-o xs">👁 View</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>


<!-- ── READY BUT NO DELIVERY ── -->
<?php if ($counts['no_delivery'] > 0): ?>
<div class="card">
  <div class="ch">
    <h3>🚚 Ready But No Delivery Assigned <span class="badge b-ready" style="margin-left:6px"><?= $counts['no_delivery'] ?></span></h3>
    <a href="deliveries.php" class="btn btn-p sm">Manage Deliveries</a>
  </div>
  <div class="tw">
    <table>
      <thead><tr><th>Order ID</th><th>Outlet</th><th>Date</th><th>Amount</th><th>Action</th></tr></thead>
      <tbody>
        <?php while ($o = $no_delivery->fetch_assoc()): ?>
        <tr>
          <td><strong><?= oid($o['id']) ?></strong></td>
          <td><?= htmlspecialchars($o['outlet_name']) ?></td>
          <td><?= date('d M Y H:i', strtotime($o['created_at'])) ?></td>
          <td><strong><?= tk($o['total']) ?></strong></td>
          <td>
            <a href="order_detail.php?id=<?= $o['id'] ?>&back=notifications" class="btn btn-o xs">👁 View</a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>


<!-- ── OUTLETS WITH NO MANAGER ── -->
<?php if ($counts['no_manager'] > 0): ?>
<div class="card">
  <div class="ch">
    <h3>🏪 Outlets Without a Manager <span class="badge b-pend" style="margin-left:6px"><?= $counts['no_manager'] ?></span></h3>
    <a href="outlets.php" class="btn btn-p sm">Manage Outlets</a>
  </div>
  <div class="tw">
    <table>
      <thead><tr><th>Outlet</th><th>Address</th><th>Phone</th><th>Action</th></tr></thead>
      <tbody>
        <?php while ($o = $no_manager->fetch_assoc()): ?>
        <tr>
          <td><strong>🏪 <?= htmlspecialchars($o['name']) ?></strong></td>
          <td><?= htmlspecialchars($o['address']) ?></td>
          <td><?= htmlspecialchars($o['phone']) ?></td>
          <td><a href="outlets.php" class="btn btn-o xs">✏ Assign Manager</a></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>