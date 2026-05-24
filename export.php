<?php
// ============================================================
// EXTRA 5: EXPORT TO CSV
// File: export.php
// Download orders or inventory as a CSV file
// that can be opened in Excel
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin', 'factory_manager']);

$type = $_GET['type'] ?? '';

// ── EXPORT ORDERS ─────────────────────────────────────────────
if ($type === 'orders') {
    $status = $conn->real_escape_string($_GET['status'] ?? '');
    $where  = $status ? "WHERE o.status='$status'" : '';

    $rows = $conn->query("
        SELECT o.id, outlet.name AS outlet, o.status, o.total, o.created_at,
               COUNT(oi.id) AS item_count
        FROM orders o
        JOIN outlets outlet ON o.outlet_id = outlet.id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        $where
        GROUP BY o.id, outlet.name, o.status, o.total, o.created_at
        ORDER BY o.created_at DESC
    ");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fputs($out, "\xEF\xBB\xBF");
    // Header row
    fputcsv($out, ['Order ID', 'Outlet', 'Status', 'Items', 'Total (BDT)', 'Date']);
    // Data rows
    while ($r = $rows->fetch_assoc()) {
        fputcsv($out, [
            '#' . str_pad($r['id'], 4, '0', STR_PAD_LEFT),
            $r['outlet'],
            $r['status'],
            $r['item_count'],
            number_format($r['total'], 2),
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ── EXPORT INVENTORY ──────────────────────────────────────────
if ($type === 'inventory') {
    $rows = $conn->query("
        SELECT p.name, c.name AS category, p.price, p.stock,
               p.min_stock, p.unit,
               CASE WHEN p.stock < p.min_stock THEN 'Low' ELSE 'OK' END AS stock_status,
               CASE WHEN p.active=1 THEN 'Active' ELSE 'Inactive' END AS product_status
        FROM products p
        JOIN categories c ON p.category_id = c.id
        ORDER BY p.name
    ");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Product', 'Category', 'Price (BDT)', 'Stock', 'Min Stock', 'Unit', 'Stock Status', 'Product Status']);
    while ($r = $rows->fetch_assoc()) {
        fputcsv($out, [
            $r['name'],
            $r['category'],
            number_format($r['price'], 2),
            $r['stock'],
            $r['min_stock'],
            $r['unit'],
            $r['stock_status'],
            $r['product_status'],
        ]);
    }
    fclose($out);
    exit;
}

// ── EXPORT INVENTORY LOG ──────────────────────────────────────
if ($type === 'inv_log') {
    $rows = $conn->query("
        SELECT p.name AS product, il.type, il.change_qty, il.before_qty,
               il.after_qty, il.notes, u.name AS done_by, il.created_at
        FROM inventory_log il
        JOIN products p ON il.product_id = p.id
        LEFT JOIN users u ON il.done_by = u.id
        ORDER BY il.created_at DESC
    ");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_log_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Product', 'Type', 'Change', 'Before', 'After', 'Notes', 'Done By', 'Time']);
    while ($r = $rows->fetch_assoc()) {
        fputcsv($out, [
            $r['product'], $r['type'], $r['change_qty'],
            $r['before_qty'], $r['after_qty'],
            $r['notes'], $r['done_by'], $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ── EXPORT DELIVERIES ─────────────────────────────────────────
if ($type === 'deliveries') {
    $rows = $conn->query("
        SELECT d.id, o.id AS order_id, outlet.name AS outlet, u.name AS staff,
               d.status, d.assigned_at, d.delivered_at
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        JOIN outlets outlet ON o.outlet_id = outlet.id
        LEFT JOIN users u ON d.assigned_to = u.id
        ORDER BY d.assigned_at DESC
    ");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="deliveries_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Delivery ID', 'Order ID', 'Outlet', 'Delivery Staff', 'Status', 'Assigned At', 'Delivered At']);
    while ($r = $rows->fetch_assoc()) {
        fputcsv($out, [
            '#D' . str_pad($r['id'], 4, '0', STR_PAD_LEFT),
            '#' . str_pad($r['order_id'], 4, '0', STR_PAD_LEFT),
            $r['outlet'], $r['staff'], $r['status'],
            $r['assigned_at'], $r['delivered_at'] ?? '—',
        ]);
    }
    fclose($out);
    exit;
}

// ── EXPORT PAGE (choose what to download) ─────────────────────
$page_title = 'Export Data';
include 'includes/header.php';
?>

<div class="ph">
  <div><h1>📥 Export Data</h1><p>Download system data as CSV files (opens in Excel)</p></div>
</div>

<div class="g2">

  <!-- Orders -->
  <div class="card">
    <div class="ch"><h3>📦 Export Orders</h3></div>
    <div class="cb">
      <p style="color:var(--g600);font-size:.84rem;margin-bottom:16px">
        Download all orders as a spreadsheet. You can filter by status.
      </p>
      <div class="fg">
        <label class="fl">Filter by Status (optional)</label>
        <select id="order-status-sel" class="fc">
          <option value="">All Orders</option>
          <option value="pending">Pending</option>
          <option value="processing">Processing</option>
          <option value="ready">Ready</option>
          <option value="delivered">Delivered</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <a id="orders-dl-btn" href="export.php?type=orders" class="btn btn-p" style="width:100%;justify-content:center;padding:10px">
        ⬇ Download Orders CSV
      </a>
    </div>
  </div>

  <!-- Inventory -->
  <div class="card">
    <div class="ch"><h3>📦 Export Inventory</h3></div>
    <div class="cb">
      <p style="color:var(--g600);font-size:.84rem;margin-bottom:16px">
        Download current stock levels for all products.
      </p>
      <a href="export.php?type=inventory" class="btn btn-p" style="width:100%;justify-content:center;padding:10px;margin-bottom:10px">
        ⬇ Download Inventory CSV
      </a>
      <a href="export.php?type=inv_log" class="btn btn-s" style="width:100%;justify-content:center;padding:10px">
        ⬇ Download Inventory Log CSV
      </a>
    </div>
  </div>

  <!-- Deliveries -->
  <div class="card">
    <div class="ch"><h3>🚚 Export Deliveries</h3></div>
    <div class="cb">
      <p style="color:var(--g600);font-size:.84rem;margin-bottom:16px">
        Download all delivery records including staff and timestamps.
      </p>
      <a href="export.php?type=deliveries" class="btn btn-p" style="width:100%;justify-content:center;padding:10px">
        ⬇ Download Deliveries CSV
      </a>
    </div>
  </div>

  <!-- How to open CSV -->
  <div class="card">
    <div class="ch"><h3>💡 How to Open CSV in Excel</h3></div>
    <div class="cb" style="font-size:.84rem;color:var(--g600);line-height:1.7">
      <p style="margin-bottom:8px"><strong style="color:var(--black)">Method 1 (Easy):</strong></p>
      <p>Double-click the downloaded .csv file — it should open in Excel automatically.</p>
      <p style="margin-top:12px;margin-bottom:8px"><strong style="color:var(--black)">Method 2 (If text appears in one column):</strong></p>
      <ol style="padding-left:18px;line-height:2">
        <li>Open Excel → File → Open → Select the CSV file</li>
        <li>Choose "Delimited" → Next</li>
        <li>Check "Comma" as delimiter → Finish</li>
      </ol>
    </div>
  </div>

</div>

<script>
// Update download link when status filter changes
document.getElementById('order-status-sel').addEventListener('change', function() {
    const val = this.value;
    const btn = document.getElementById('orders-dl-btn');
    btn.href = 'export.php?type=orders' + (val ? '&status=' + val : '');
});
</script>

<?php include 'includes/footer.php'; ?>