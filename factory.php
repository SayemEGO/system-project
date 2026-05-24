<?php
// ============================================================
// STEP 12: FACTORY PANEL PAGE
// File: factory.php
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin','factory_manager']);

$page_title = 'Factory Panel';

// Handle factory actions (start processing / mark ready)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = (int)$_POST['order_id'];

    if ($_POST['action'] === 'start_processing') {
        // Update order status
        $conn->query("UPDATE orders SET status='processing' WHERE id=$order_id");

        // Deduct stock for each item
        $items = $conn->query("SELECT * FROM order_items WHERE order_id=$order_id");
        while ($item = $items->fetch_assoc()) {
            $pid = $item['product_id'];
            $qty = $item['qty'];
            $p   = $conn->query("SELECT stock FROM products WHERE id=$pid")->fetch_assoc();
            $bef = $p['stock'];
            $aft = max(0, $bef - $qty);
            $chg = $aft - $bef;  // negative number
            $conn->query("UPDATE products SET stock=$aft WHERE id=$pid");
            $uid = $_SESSION['user_id'];
            $conn->query("INSERT INTO inventory_log (product_id,type,change_qty,before_qty,after_qty,notes,done_by)
                          VALUES ($pid,'sale',$chg,$bef,$aft,'Order ".oid($order_id)."',$uid)");
        }
        $_SESSION['flash'] = ['msg'=>'Order '.oid($order_id).' is now being processed','type'=>'ti'];

    } elseif ($_POST['action'] === 'mark_ready') {
        $conn->query("UPDATE orders SET status='ready' WHERE id=$order_id");

        // Auto-assign delivery if not already assigned
        $existing = $conn->query("SELECT id FROM deliveries WHERE order_id=$order_id")->num_rows;
        if (!$existing) {
            $emp = $conn->query("SELECT id FROM users WHERE role='delivery' AND active=1 LIMIT 1")->fetch_assoc();
            if ($emp) {
                $eid = $emp['id'];
                $conn->query("INSERT INTO deliveries (order_id,assigned_to) VALUES ($order_id,$eid)");
            }
        }
        $_SESSION['flash'] = ['msg'=>'Order '.oid($order_id).' ready for delivery! 🚚','type'=>'ts'];
    }

    header("Location: factory.php");
    exit;
}

// Get pending and processing orders
$active_orders = $conn->query("
    SELECT o.*, outlet.name AS outlet_name
    FROM orders o
    JOIN outlets outlet ON o.outlet_id=outlet.id
    WHERE o.status IN ('pending','processing')
    ORDER BY o.created_at ASC
");

include 'includes/header.php';
?>

<div class="ph">
  <div><h1>🏭 Factory Panel</h1><p>Manage order preparation and production workflow</p></div>
</div>

<?php if ($active_orders->num_rows === 0): ?>
<div class="card">
  <div class="cb">
    <div class="es"><div class="ei">✅</div><h3>All caught up!</h3><p>No pending or in-progress orders.</p></div>
  </div>
</div>

<?php else:
  while ($order = $active_orders->fetch_assoc()):
    // Get items for this order
    $items = $conn->query("
        SELECT oi.*, p.name AS prod_name, p.emoji, p.stock AS avail_stock
        FROM order_items oi
        JOIN products p ON oi.product_id=p.id
        WHERE oi.order_id={$order['id']}
    ");
?>

<div class="card">
  <div class="ch">
    <div>
      <h3>Order <?= oid($order['id']) ?> — <?= htmlspecialchars($order['outlet_name']) ?></h3>
      <div style="font-size:.75rem;color:var(--g600);margin-top:2px">
        <?= date('d M Y H:i', strtotime($order['created_at'])) ?> · <?= tk($order['total']) ?>
      </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <?= statusBadge($order['status']) ?>

      <?php if ($order['status'] === 'pending'): ?>
      <form method="POST" style="margin:0">
        <input type="hidden" name="action" value="start_processing">
        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
        <button type="submit" class="btn btn-p sm">▶ Start Processing</button>
      </form>
      <?php elseif ($order['status'] === 'processing'): ?>
      <form method="POST" style="margin:0">
        <input type="hidden" name="action" value="mark_ready">
        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
        <button type="submit" class="btn btn-g sm">✅ Mark Ready</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Items stock check -->
  <div class="tw">
    <table>
      <thead>
        <tr>
          <th>Product</th>
          <th>Qty Ordered</th>
          <th>Stock Available</th>
          <th>Need to Produce</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($item = $items->fetch_assoc()):
          $need = max(0, $item['qty'] - $item['avail_stock']);
        ?>
        <tr>
          <td><?= $item['emoji'] ?> <strong><?= htmlspecialchars($item['prod_name']) ?></strong></td>
          <td><strong><?= $item['qty'] ?></strong></td>
          <td style="color:<?= $item['avail_stock'] < $item['qty'] ? 'var(--orange)' : 'var(--green)' ?>;font-weight:700">
            <?= $item['avail_stock'] ?>
          </td>
          <td>
            <?php if ($need > 0): ?>
              <span style="color:var(--red);font-weight:700">+<?= $need ?> more</span>
            <?php else: ?>
              <span style="color:var(--green)">✓ Sufficient</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($need > 0): ?>
              <span class="badge b-pend">Produce More</span>
            <?php else: ?>
              <span class="badge b-deliv">Ready</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endwhile; endif; ?>

<?php include 'includes/footer.php'; ?>