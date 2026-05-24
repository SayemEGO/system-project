<?php
// ============================================================
// STEP 18: REPORTS & ANALYTICS PAGE
// File: reports.php
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin','factory_manager']);

$page_title = 'Reports & Analytics';

// ── SUMMARY STATS ─────────────────────────────────────────────
$total_revenue  = $conn->query("SELECT COALESCE(SUM(total),0) as s FROM orders WHERE status='delivered'")->fetch_assoc()['s'];
$total_orders   = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$delivered      = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='delivered'")->fetch_assoc()['c'];
$avg_order      = $delivered > 0 ? round($total_revenue / $delivered, 2) : 0;

// ── TOP PRODUCTS BY QTY ORDERED ────────────────────────────────
$top_products = $conn->query("
    SELECT p.name, p.emoji,
           SUM(oi.qty) AS total_qty,
           SUM(oi.qty * oi.unit_price) AS revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    GROUP BY oi.product_id, p.name, p.emoji
    ORDER BY total_qty DESC
    LIMIT 6
");

// ── OUTLET PERFORMANCE ────────────────────────────────────────
$outlet_perf = $conn->query("
    SELECT ol.name,
           COUNT(o.id) AS total_orders,
           SUM(o.status='delivered') AS delivered_count,
           COALESCE(SUM(CASE WHEN o.status='delivered' THEN o.total ELSE 0 END),0) AS revenue
    FROM outlets ol
    LEFT JOIN orders o ON o.outlet_id = ol.id
    GROUP BY ol.id, ol.name
    ORDER BY revenue DESC
");

// ── ORDER STATUS BREAKDOWN ────────────────────────────────────
$status_data = [];
$statuses    = ['pending','processing','ready','delivered','cancelled'];
foreach ($statuses as $s) {
    $cnt = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='$s'")->fetch_assoc()['c'];
    $status_data[$s] = $cnt;
}
$total_for_pct = array_sum($status_data) ?: 1;

// ── MONTHLY REVENUE (last 6 months) ───────────────────────────
$monthly = $conn->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') AS month_label,
           DATE_FORMAT(created_at, '%Y-%m') AS month_sort,
           COALESCE(SUM(total),0) AS revenue
    FROM orders
    WHERE status='delivered'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_sort, month_label
    ORDER BY month_sort ASC
");
$monthly_data = [];
while ($row = $monthly->fetch_assoc()) {
    $monthly_data[] = $row;
}

include 'includes/header.php';
?>

<!-- ── SUMMARY STATS ── -->
<div class="ph">
  <div><h1>Reports & Analytics</h1><p>Business performance overview</p></div>
</div>

<div class="stats">
  <div class="sc"><div class="si si-g">💰</div><div><div class="sv" style="font-size:1.25rem"><?= tk($total_revenue) ?></div><div class="sl">Total Revenue</div></div></div>
  <div class="sc"><div class="si si-b">📦</div><div><div class="sv"><?= $total_orders ?></div><div class="sl">Total Orders</div></div></div>
  <div class="sc"><div class="si si-g">✅</div><div><div class="sv"><?= $delivered ?></div><div class="sl">Delivered Orders</div></div></div>
  <div class="sc"><div class="si si-gold">📊</div><div><div class="sv" style="font-size:1.2rem"><?= tk($avg_order) ?></div><div class="sl">Avg Order Value</div></div></div>
</div>

<!-- ── MONTHLY REVENUE CHART ── -->
<?php if (!empty($monthly_data)):
  $max_rev = max(array_column($monthly_data, 'revenue')) ?: 1;
?>
<div class="card">
  <div class="ch"><h3>📅 Monthly Revenue (Last 6 Months)</h3></div>
  <div class="cb">
    <div class="mchart" style="height:120px">
      <?php foreach ($monthly_data as $m):
        $h = round(($m['revenue'] / $max_rev) * 110);
      ?>
      <div class="mcw">
        <div class="mcv" style="font-size:.6rem"><?= tk($m['revenue']) ?></div>
        <div class="mcb" style="height:<?= $h ?>px"></div>
        <div class="mcl"><?= $m['month_label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="g2">

  <!-- ── TOP PRODUCTS ── -->
  <div class="card">
    <div class="ch"><h3>🏆 Top Products by Orders</h3></div>
    <div class="tw">
      <table>
        <thead><tr><th>Product</th><th>Total Qty</th><th>Revenue</th></tr></thead>
        <tbody>
          <?php if ($top_products->num_rows > 0):
            while ($p = $top_products->fetch_assoc()): ?>
          <tr>
            <td><?= $p['emoji'] ?> <strong><?= htmlspecialchars($p['name']) ?></strong></td>
            <td><strong><?= $p['total_qty'] ?></strong></td>
            <td><strong><?= tk($p['revenue']) ?></strong></td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="3"><div class="es" style="padding:16px"><p>No data yet.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── OUTLET PERFORMANCE ── -->
  <div class="card">
    <div class="ch"><h3>🏪 Outlet Performance</h3></div>
    <div class="tw">
      <table>
        <thead><tr><th>Outlet</th><th>Orders</th><th>Delivered</th><th>Revenue</th></tr></thead>
        <tbody>
          <?php if ($outlet_perf->num_rows > 0):
            while ($o = $outlet_perf->fetch_assoc()): ?>
          <tr>
            <td><strong><?= htmlspecialchars($o['name']) ?></strong></td>
            <td><?= $o['total_orders'] ?></td>
            <td><?= $o['delivered_count'] ?></td>
            <td><strong><?= tk($o['revenue']) ?></strong></td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="4"><div class="es" style="padding:16px"><p>No data yet.</p></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ── ORDER STATUS BREAKDOWN ── -->
<div class="card">
  <div class="ch"><h3>📦 Order Status Breakdown</h3></div>
  <div class="cb">
    <?php
    $status_colors = [
      'pending'    => 'var(--orange)',
      'processing' => 'var(--blue)',
      'ready'      => 'var(--gold)',
      'delivered'  => 'var(--green)',
      'cancelled'  => 'var(--red)',
    ];
    foreach ($status_data as $s => $cnt):
      $pct = round(($cnt / $total_for_pct) * 100);
      $color = $status_colors[$s] ?? 'var(--g400)';
    ?>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <div style="width:90px;font-size:.78rem;font-weight:600;text-transform:capitalize"><?= $s ?></div>
      <div style="flex:1;background:var(--g200);border-radius:4px;height:14px;overflow:hidden">
        <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:4px;transition:width .5s"></div>
      </div>
      <div style="min-width:70px;font-size:.78rem;color:var(--g600)"><?= $cnt ?> (<?= $pct ?>%)</div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ── INVENTORY SUMMARY ── -->
<div class="card">
  <div class="ch"><h3>📦 Inventory Summary</h3></div>
  <div class="tw">
    <table>
      <thead><tr><th>Product</th><th>Category</th><th>Current Stock</th><th>Min Level</th><th>Status</th></tr></thead>
      <tbody>
        <?php
        $inv = $conn->query("
            SELECT p.*, c.name AS cat_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.active = 1
            ORDER BY p.stock ASC
        ");
        while ($p = $inv->fetch_assoc()):
          $ok  = $p['stock'] >= $p['min_stock'];
          $pct = min(100, round(($p['stock'] / max(1,$p['min_stock'])) * 50));
          $sf  = $p['stock'] <= 0 ? 'sf-crit' : ($ok ? 'sf-ok' : 'sf-low');
        ?>
        <tr>
          <td><?= $p['emoji'] ?> <strong><?= htmlspecialchars($p['name']) ?></strong></td>
          <td><?= htmlspecialchars($p['cat_name']) ?></td>
          <td>
            <strong style="color:<?= $ok ? 'var(--green)' : 'var(--orange)' ?>"><?= $p['stock'] ?></strong>
            <div class="sbar"><div class="sf <?= $sf ?>" style="width:<?= $pct ?>%"></div></div>
          </td>
          <td><?= $p['min_stock'] ?></td>
          <td><span class="badge <?= $ok ? 'b-ok' : 'b-low' ?>"><?= $ok ? '✓ OK' : '⚠ Low' ?></span></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>