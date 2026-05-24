<?php
// ============================================================
// STEP 8: ALL ORDERS PAGE
// File: orders.php
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin','factory_manager']);

$page_title = 'All Orders';

// Handle status update form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $order_id  = (int)$_POST['order_id'];
        $new_status = $conn->real_escape_string($_POST['new_status']);

        // Get current status
        $old = $conn->query("SELECT status FROM orders WHERE id=$order_id")->fetch_assoc();

        // Update order status
        $conn->query("UPDATE orders SET status='$new_status' WHERE id=$order_id");

        // If moving to 'processing', deduct stock
        if ($new_status === 'processing' && $old['status'] !== 'processing') {
            $items = $conn->query("SELECT * FROM order_items WHERE order_id=$order_id");
            while ($item = $items->fetch_assoc()) {
                $pid = $item['product_id'];
                $qty = $item['qty'];
                $p = $conn->query("SELECT * FROM products WHERE id=$pid")->fetch_assoc();
                $before = $p['stock'];
                $after  = max(0, $before - $qty);
                $conn->query("UPDATE products SET stock=$after WHERE id=$pid");
                $conn->query("INSERT INTO inventory_log (product_id,type,change_qty,before_qty,after_qty,notes,done_by)
                              VALUES ($pid,'sale',-".($before-$after).",$before,$after,'Order ".oid($order_id)."',{$_SESSION['user_id']})");
            }
        }

        // If moving to 'ready', auto-assign delivery
        if ($new_status === 'ready') {
            $existing = $conn->query("SELECT id FROM deliveries WHERE order_id=$order_id")->num_rows;
            if (!$existing) {
                $emp = $conn->query("SELECT id FROM users WHERE role='delivery' AND active=1 LIMIT 1")->fetch_assoc();
                if ($emp) {
                    $eid = $emp['id'];
                    $conn->query("INSERT INTO deliveries (order_id, assigned_to) VALUES ($order_id,$eid)");
                }
            }
        }

        $_SESSION['flash'] = ['msg'=>'Order '.oid($order_id).' updated to "'.$new_status.'"','type'=>'ts'];
        header("Location: orders.php");
        exit;
    }
}

// ── SEARCH & FILTER ──────────────────────────────────────────
$search = $conn->real_escape_string(trim($_GET['search'] ?? ''));
$filter = $conn->real_escape_string($_GET['status'] ?? '');

$where = "1=1";
if ($filter) $where .= " AND o.status='$filter'";
if ($search) $where .= " AND (outlet.name LIKE '%$search%' OR o.id LIKE '%$search%')";

$orders = $conn->query("
    SELECT o.*, outlet.name AS outlet_name,
           (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) AS item_count
    FROM orders o
    JOIN outlets outlet ON o.outlet_id=outlet.id
    WHERE $where
    ORDER BY o.created_at DESC
");

include 'includes/header.php';
?>

<div class="ph">
  <div><h1>All Orders</h1><p>View and manage all outlet orders</p></div>
</div>

<!-- Search & Filter Bar -->
<div class="tb">
  <form method="GET" style="display:flex;gap:9px;flex:1;flex-wrap:wrap">
    <div class="sw-input">
      <span>🔍</span>
      <input type="text" name="search" placeholder="Search orders…" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
    </div>
    <select class="fsel" name="status" onchange="this.form.submit()">
      <option value="">All Status</option>
      <?php foreach(['pending','processing','ready','delivered','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= ($_GET['status']??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-s sm">Filter</button>
    <a href="orders.php" class="btn btn-s sm">Clear</a>
  </form>
</div>

<div class="card">
  <div class="tw">
    <table>
      <thead>
        <tr><th>Order ID</th><th>Outlet</th><th>Date</th><th>Items</th><th>Amount</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($orders->num_rows > 0):
          while ($o = $orders->fetch_assoc()): ?>
        <tr>
          <td><strong><?= oid($o['id']) ?></strong></td>
          <td><?= htmlspecialchars($o['outlet_name']) ?></td>
          <td><?= date('d M Y H:i', strtotime($o['created_at'])) ?></td>
          <td><?= $o['item_count'] ?> items</td>
          <td><strong><?= tk($o['total']) ?></strong></td>
          <td><?= statusBadge($o['status']) ?></td>
          <td style="display:flex;gap:5px;flex-wrap:wrap;padding:8px 14px">
            <a href="order_detail.php?id=<?= $o['id'] ?>&back=orders" class="btn btn-o xs">👁 View</a>
            <?php if (!in_array($o['status'], ['delivered','cancelled'])): ?>
            <button class="btn xs btn-s" onclick="openStatusModal(<?= $o['id'] ?>, '<?= $o['status'] ?>')">✏ Status</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="7"><div class="es"><div class="ei">📭</div><p>No orders found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── STATUS UPDATE MODAL ── -->
<div class="moverlay" id="modal-status">
  <div class="modal">
    <div class="mh"><h3>📝 Update Order Status</h3><button class="mcl2" onclick="closeModal()">✕</button></div>
    <div class="mb2">
      <form method="POST" id="status-form">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="order_id" id="modal-order-id">
        <p style="margin-bottom:14px">Update status for <strong id="modal-order-label"></strong>:</p>
        <div class="fg">
          <label class="fl">New Status</label>
          <select name="new_status" id="new-status-sel" class="fc">
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="ready">Ready</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
      </form>
    </div>
    <div class="mf">
      <button class="btn btn-s" onclick="closeModal()">Cancel</button>
      <button class="btn btn-p" onclick="document.getElementById('status-form').submit()">Save</button>
    </div>
  </div>
</div>

<script>
function openStatusModal(id, currentStatus) {
    document.getElementById('modal-order-id').value = id;
    document.getElementById('modal-order-label').textContent = '#' + String(id).padStart(4,'0');
    document.getElementById('new-status-sel').value = currentStatus;
    document.getElementById('modal-status').classList.add('open');
}
function closeModal() {
    document.getElementById('modal-status').classList.remove('open');
}
// Close modal on backdrop click
document.getElementById('modal-status').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include 'includes/footer.php'; ?>