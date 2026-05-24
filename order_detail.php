<?php
// ============================================================
// STEP 9: ORDER DETAIL PAGE
// File: order_detail.php
// ============================================================
require_once 'config.php';
requireLogin();

$id   = (int)($_GET['id'] ?? 0);
$back = $conn->real_escape_string($_GET['back'] ?? 'orders');

// Get order
$order = $conn->query("
    SELECT o.*, outlet.name AS outlet_name, outlet.address AS outlet_addr, outlet.phone AS outlet_phone
    FROM orders o
    JOIN outlets outlet ON o.outlet_id=outlet.id
    WHERE o.id=$id
")->fetch_assoc();

if (!$order) { header("Location: orders.php"); exit; }

// Get order items
$items_query = $conn->query("
    SELECT oi.*, p.name AS prod_name, p.emoji
    FROM order_items oi
    JOIN products p ON oi.product_id=p.id
    WHERE oi.order_id=$id
");

if (!$items_query) {
    die("Items query failed: " . $conn->error);
}

// Get delivery info
$delivery_query = $conn->query("
    SELECT d.*, u.name AS staff_name
    FROM deliveries d
    JOIN users u ON d.assigned_to=u.id
    WHERE d.order_id=$id
");

$delivery = $delivery_query ? $delivery_query->fetch_assoc() : null;

$page_title = 'Order ' . oid($id);
include 'includes/header.php';
?>

<div class="ph">
  <div>
    <h1>Order <?= oid($id) ?></h1>
    <p><?= date('d M Y H:i', strtotime($order['created_at'])) ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <?= statusBadge($order['status']) ?>
    <a href="<?= htmlspecialchars($back) ?>.php" class="btn btn-s sm">← Back</a>
  </div>
</div>

<div class="g2" style="margin-bottom:18px">
  <!-- Order Info -->
  <div class="card" style="margin:0">
    <div class="ch"><h3>📋 Order Info</h3></div>
    <div class="cb">
      <table class="itbl">
        <tr><td>Order ID</td><td><strong><?= oid($id) ?></strong></td></tr>
        <tr><td>Status</td><td><?= statusBadge($order['status']) ?></td></tr>
        <tr><td>Date</td><td><?= date('d M Y H:i', strtotime($order['created_at'])) ?></td></tr>
        <tr><td>Total</td><td><strong style="color:var(--red);font-size:1.05rem"><?= tk($order['total']) ?></strong></td></tr>
        <?php if ($order['notes']): ?>
        <tr><td>Notes</td><td><?= htmlspecialchars($order['notes']) ?></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- Outlet Info -->
  <div class="card" style="margin:0">
    <div class="ch"><h3>🏪 Outlet Info</h3></div>
    <div class="cb">
      <table class="itbl">
        <tr><td>Outlet</td><td><strong><?= htmlspecialchars($order['outlet_name']) ?></strong></td></tr>
        <tr><td>Address</td><td><?= htmlspecialchars($order['outlet_addr']) ?></td></tr>
        <tr><td>Phone</td><td><?= htmlspecialchars($order['outlet_phone']) ?></td></tr>
        <?php if ($delivery): ?>
        <tr><td>Delivery By</td><td><?= htmlspecialchars($delivery['staff_name']) ?></td></tr>
        <tr><td>Delivery Status</td><td><?= statusBadge($delivery['status']) ?></td></tr>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<!-- Order Items -->
<div class="card">
  <div class="ch"><h3>🛒 Order Items</h3></div>
  <div class="tw">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Product</th>
          <th>Unit Price</th>
          <th>Qty</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0; while ($item = $items_query->fetch_assoc()): $i++; ?>
        <tr>
          <td><?= $i ?></td>
          <td><?= $item['emoji'] ?> <strong><?= htmlspecialchars($item['prod_name']) ?></strong></td>
          <td><?= tk($item['unit_price']) ?></td>
          <td><?= $item['qty'] ?></td>
          <td><strong><?= tk($item['qty'] * $item['unit_price']) ?></strong></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" style="text-align:right;padding:11px 14px;font-weight:700;color:var(--g600)">TOTAL AMOUNT</td>
          <td style="padding:11px 14px">
            <span style="font-family:'Barlow Condensed';font-size:1.3rem;font-weight:800;color:var(--red)">
              <?= tk($order['total']) ?>
            </span>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>