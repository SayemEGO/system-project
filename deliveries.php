<?php
// ============================================================
// STEP 17: DELIVERIES PAGE
// File: deliveries.php
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin','factory_manager','delivery']);

$page_title = 'Deliveries';

// Handle mark as delivered
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'mark_delivered') {
    $del_id = (int)$_POST['delivery_id'];

    // Get order_id from delivery
    $d = $conn->query("SELECT order_id FROM deliveries WHERE id=$del_id")->fetch_assoc();

    if ($d) {
        $conn->query("UPDATE deliveries SET status='delivered', delivered_at=NOW() WHERE id=$del_id");
        $conn->query("UPDATE orders SET status='delivered' WHERE id={$d['order_id']}");
        $_SESSION['flash'] = ['msg'=>'Delivery marked as completed! ✅','type'=>'ts'];
    }
    header("Location: deliveries.php"); exit;
}

// Load deliveries (filter by staff if role is delivery)
$where = "1=1";
if ($_SESSION['role'] === 'delivery') {          // ← FIXED: was $role === 'deliveries'
    $where = "d.assigned_to={$_SESSION['user_id']}";
}

$deliveries = $conn->query("
    SELECT d.*, o.total, o.created_at AS order_date,
           outlet.name AS outlet_name,
           u.name AS staff_name,
           d.order_id
    FROM deliveries d
    JOIN orders o ON d.order_id=o.id
    JOIN outlets outlet ON o.outlet_id=outlet.id
    LEFT JOIN users u ON d.assigned_to=u.id
    WHERE $where
    ORDER BY d.assigned_at DESC
");

include 'includes/header.php';
?>

<div class="ph">
  <div><h1>Deliveries</h1><p>Track and manage deliveries</p></div>
</div>

<div class="card">
  <div class="tw">
    <table>
      <thead>
        <tr>
          <th>Delivery ID</th>
          <th>Order</th>
          <th>Outlet</th>
          <th>Staff</th>
          <th>Assigned</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($deliveries && $deliveries->num_rows > 0):
          while ($d = $deliveries->fetch_assoc()): ?>
        <tr>
          <td><strong>#D<?= str_pad($d['id'],4,'0',STR_PAD_LEFT) ?></strong></td>
          <td>
            <a href="order_detail.php?id=<?= $d['order_id'] ?>&back=deliveries" class="btn btn-o xs">
              <?= oid($d['order_id']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($d['outlet_name']) ?></td>
          <td><?= htmlspecialchars($d['staff_name'] ?? '—') ?></td>
          <td><?= date('d M Y H:i', strtotime($d['assigned_at'])) ?></td>
          <td><?= statusBadge($d['status']) ?></td>
          <td>
            <?php if ($d['status'] !== 'delivered'): ?>
            <form method="POST" style="margin:0;display:inline">
              <input type="hidden" name="action" value="mark_delivered">
              <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
              <button type="submit" class="btn btn-g xs">✅ Delivered</button>
            </form>
            <?php else: ?>
            <span style="color:var(--green);font-size:.78rem;font-weight:600">Completed</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="7">
          <div class="es"><div class="ei">🚚</div><h3>No deliveries yet</h3></div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/footer.php'; ?>