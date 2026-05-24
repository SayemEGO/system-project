<?php
// ============================================================
// STEP 11: MY ORDERS PAGE (Outlet Manager)
// File: my_orders.php
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['outlet_manager']);

$page_title = 'My Orders';

// Get this manager's outlet
$outlet = $conn->query("SELECT * FROM outlets WHERE manager_id={$_SESSION['user_id']} AND active=1")->fetch_assoc();

// Get orders for this outlet
$orders = [];
if ($outlet) {
    $oid_val = $outlet['id'];
    $result  = $conn->query("
        SELECT o.*,
               (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) AS item_count
        FROM orders o
        WHERE o.outlet_id=$oid_val
        ORDER BY o.created_at DESC
    ");
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

include 'includes/header.php';
?>

<div class="ph">
  <div>
    <h1>My Orders</h1>
    <p><?= $outlet ? 'Orders from '.htmlspecialchars($outlet['name']) : 'Your outlet orders' ?></p>
  </div>
  <a href="place_order.php" class="btn btn-p">🛒 Place New Order</a>
</div>

<div class="card">
  <div class="tw">
    <table>
      <thead>
        <tr><th>Order ID</th><th>Date</th><th>Items</th><th>Amount</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php if (!empty($orders)):
          foreach ($orders as $o): ?>
        <tr>
          <td><strong><?= oid($o['id']) ?></strong></td>
          <td><?= date('d M Y H:i', strtotime($o['created_at'])) ?></td>
          <td><?= $o['item_count'] ?> items</td>
          <td><strong><?= tk($o['total']) ?></strong></td>
          <td><?= statusBadge($o['status']) ?></td>
          <td><a href="order_detail.php?id=<?= $o['id'] ?>&back=my_orders" class="btn btn-o xs">👁 View</a></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6">
          <div class="es">
            <div class="ei">📭</div>
            <h3>No orders yet</h3>
            <p><a href="place_order.php">Place your first order!</a></p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>