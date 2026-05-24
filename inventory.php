<?php
// ============================================================
// STEP 13: INVENTORY MANAGEMENT PAGE
// File: inventory.php
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin','factory_manager']);

$page_title = 'Inventory Management';

// Handle Restock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'restock') {
    $pid = (int)$_POST['product_id'];
    $qty = (int)$_POST['qty'];
    if ($qty > 0) {
        $p   = $conn->query("SELECT stock FROM products WHERE id=$pid")->fetch_assoc();
        $bef = $p['stock'];
        $aft = $bef + $qty;
        $uid = $_SESSION['user_id'];
        $conn->query("UPDATE products SET stock=$aft WHERE id=$pid");
        $conn->query("INSERT INTO inventory_log (product_id,type,change_qty,before_qty,after_qty,notes,done_by)
                      VALUES ($pid,'production',$qty,$bef,$aft,'Restocked by {$_SESSION['user_name']}',$uid)");
        $_SESSION['flash'] = ['msg'=>"Restocked $qty units",'type'=>'ts'];
    }
    header("Location: inventory.php"); exit;
}

// Handle Adjust
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'adjust') {
    $pid = (int)$_POST['product_id'];
    $qty = (int)$_POST['qty'];
    if ($qty >= 0) {
        $p   = $conn->query("SELECT stock FROM products WHERE id=$pid")->fetch_assoc();
        $bef = $p['stock'];
        $chg = $qty - $bef;
        $uid = $_SESSION['user_id'];
        $conn->query("UPDATE products SET stock=$qty WHERE id=$pid");
        $conn->query("INSERT INTO inventory_log (product_id,type,change_qty,before_qty,after_qty,notes,done_by)
                      VALUES ($pid,'adjustment',$chg,$bef,$qty,'Manual adjust by {$_SESSION['user_name']}',$uid)");
        $_SESSION['flash'] = ['msg'=>'Stock adjusted','type'=>'ts'];
    }
    header("Location: inventory.php"); exit;
}

// ── NEW: Handle Price Update ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_price') {
    $pid       = (int)$_POST['product_id'];
    $new_price = (float)$_POST['price'];
    if ($new_price >= 0) {
        $old = $conn->query("SELECT price, name FROM products WHERE id=$pid")->fetch_assoc();
        $conn->query("UPDATE products SET price=$new_price WHERE id=$pid");
        $uid = $_SESSION['user_id'];
        // Log the price change in inventory_log using type='adjustment' with a note
        $note = $conn->real_escape_string("Price changed from {$old['price']} to {$new_price} by {$_SESSION['user_name']}");
        $conn->query("INSERT INTO inventory_log (product_id,type,change_qty,before_qty,after_qty,notes,done_by)
                      VALUES ($pid,'adjustment',0,0,0,'$note',$uid)");
        $_SESSION['flash'] = ['msg'=>'Price updated successfully','type'=>'ts'];
    }
    header("Location: inventory.php"); exit;
}
// ─────────────────────────────────────────────────────────────

// Search & filter
$search = $conn->real_escape_string(trim($_GET['search'] ?? ''));
$filter = $_GET['filter'] ?? '';

$where = "p.active=1";
if ($filter === 'low') $where .= " AND p.stock < p.min_stock";
if ($filter === 'ok')  $where .= " AND p.stock >= p.min_stock";
if ($search)           $where .= " AND p.name LIKE '%$search%'";

$products = $conn->query("
    SELECT p.*, c.name AS cat_name
    FROM products p JOIN categories c ON p.category_id=c.id
    WHERE $where ORDER BY p.name
");

// Inventory log (last 10)
$log = $conn->query("
    SELECT il.*, p.name AS prod_name, p.emoji, u.name AS done_by_name
    FROM inventory_log il
    JOIN products p ON il.product_id=p.id
    LEFT JOIN users u ON il.done_by=u.id
    ORDER BY il.created_at DESC LIMIT 10
");

include 'includes/header.php';
?>

<div class="ph">
  <div><h1>Inventory Management</h1><p>Monitor and manage product stock levels</p></div>
</div>

<!-- Search & Filter -->
<div class="tb">
  <form method="GET" style="display:flex;gap:9px;flex:1;flex-wrap:wrap">
    <div class="sw-input">
      <span>🔍</span>
      <input type="text" name="search" placeholder="Search products…" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
    </div>
    <select class="fsel" name="filter" onchange="this.form.submit()">
      <option value="">All Stock</option>
      <option value="low" <?= $filter==='low'?'selected':'' ?>>⚠ Low Stock</option>
      <option value="ok"  <?= $filter==='ok'?'selected':'' ?>>✓ OK Stock</option>
    </select>
    <button type="submit" class="btn btn-s sm">Filter</button>
    <a href="inventory.php" class="btn btn-s sm">Clear</a>
  </form>
</div>

<!-- Products Table -->
<div class="card">
  <div class="tw">
    <table>
      <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Min Level</th><th>Status</th><th>Price</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if ($products->num_rows > 0):
          while ($p = $products->fetch_assoc()):
            $pct = min(100, round(($p['stock'] / max(1,$p['min_stock'])) * 50));
            $sf  = $p['stock'] <= 0 ? 'sf-crit' : ($p['stock'] < $p['min_stock'] ? 'sf-low' : 'sf-ok');
            $status_ok = $p['stock'] >= $p['min_stock'];
        ?>
        <tr>
          <td><?= $p['emoji'] ?> <strong><?= htmlspecialchars($p['name']) ?></strong></td>
          <td><?= htmlspecialchars($p['cat_name']) ?></td>
          <td>
            <strong><?= $p['stock'] ?></strong>
            <div class="sbar"><div class="sf <?= $sf ?>" style="width:<?= $pct ?>%"></div></div>
          </td>
          <td><?= $p['min_stock'] ?></td>
          <td><span class="badge <?= $status_ok?'b-ok':'b-low' ?>"><?= $status_ok?'✓ OK':'⚠ Low' ?></span></td>
          <td><?= tk($p['price']) ?></td>
          <td style="display:flex;gap:5px;padding:8px 14px;flex-wrap:wrap">
            <button class="btn btn-p xs" onclick="openRestock(<?= $p['id'] ?>,'<?= addslashes($p['name']) ?>',<?= $p['stock'] ?>)">+ Restock</button>
            <button class="btn btn-o xs" onclick="openAdjust(<?= $p['id'] ?>,'<?= addslashes($p['name']) ?>',<?= $p['stock'] ?>)">✏ Adjust</button>
            <!-- NEW: Price edit button -->
            <button class="btn btn-w xs" onclick="openPrice(<?= $p['id'] ?>,'<?= addslashes($p['name']) ?>',<?= $p['price'] ?>)">💰 Price</button>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="7"><div class="es"><div class="ei">📦</div><p>No products found.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Inventory Log -->
<div class="card">
  <div class="ch"><h3>📜 Inventory Log</h3></div>
  <div class="tw">
    <table>
      <thead><tr><th>Product</th><th>Type</th><th>Change</th><th>Before</th><th>After</th><th>Notes</th><th>Time</th></tr></thead>
      <tbody>
        <?php if ($log->num_rows > 0):
          while ($l = $log->fetch_assoc()):
            $type_badge = ['production'=>'b-deliv','sale'=>'b-pend','adjustment'=>'b-proc','return'=>'b-ready'][$l['type']] ?? 'b-pend';
        ?>
        <tr>
          <td><?= $l['emoji'] ?> <?= htmlspecialchars($l['prod_name']) ?></td>
          <td><span class="badge <?= $type_badge ?>"><?= $l['type'] ?></span></td>
          <td style="color:<?= $l['change_qty']>=0?'var(--green)':'var(--red)' ?>;font-weight:700">
            <?= $l['change_qty']>=0?'+'.$l['change_qty']:$l['change_qty'] ?>
          </td>
          <td><?= $l['before_qty'] ?></td>
          <td><?= $l['after_qty'] ?></td>
          <td style="font-size:.75rem;color:var(--g600)"><?= htmlspecialchars($l['notes']) ?></td>
          <td style="font-size:.74rem"><?= date('d M Y H:i', strtotime($l['created_at'])) ?></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="7"><div class="es" style="padding:16px"><p>No log entries yet.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Restock Modal -->
<div class="moverlay" id="modal-restock">
  <div class="modal">
    <div class="mh"><h3>📦 Restock Product</h3><button class="mcl2" onclick="closeModal('modal-restock')">✕</button></div>
    <div class="mb2">
      <form method="POST" id="restock-form">
        <input type="hidden" name="action" value="restock">
        <input type="hidden" name="product_id" id="restock-pid">
        <div class="alert al-i" id="restock-info"></div>
        <div class="fg">
          <label class="fl">Add Quantity</label>
          <input type="number" name="qty" id="restock-qty" class="fc" min="1" placeholder="Enter quantity to add" autofocus>
        </div>
      </form>
    </div>
    <div class="mf">
      <button class="btn btn-s" onclick="closeModal('modal-restock')">Cancel</button>
      <button class="btn btn-g" onclick="document.getElementById('restock-form').submit()">✅ Restock</button>
    </div>
  </div>
</div>

<!-- Adjust Modal -->
<div class="moverlay" id="modal-adjust">
  <div class="modal">
    <div class="mh"><h3>✏️ Adjust Stock</h3><button class="mcl2" onclick="closeModal('modal-adjust')">✕</button></div>
    <div class="mb2">
      <form method="POST" id="adjust-form">
        <input type="hidden" name="action" value="adjust">
        <input type="hidden" name="product_id" id="adjust-pid">
        <p style="margin-bottom:14px" id="adjust-info"></p>
        <div class="fg">
          <label class="fl">New Stock Quantity</label>
          <input type="number" name="qty" id="adjust-qty" class="fc" min="0" autofocus>
        </div>
      </form>
    </div>
    <div class="mf">
      <button class="btn btn-s" onclick="closeModal('modal-adjust')">Cancel</button>
      <button class="btn btn-p" onclick="document.getElementById('adjust-form').submit()">💾 Save</button>
    </div>
  </div>
</div>

<!-- ── NEW: Price Modal ──────────────────────────────────────── -->
<div class="moverlay" id="modal-price">
  <div class="modal">
    <div class="mh"><h3>💰 Update Price</h3><button class="mcl2" onclick="closeModal('modal-price')">✕</button></div>
    <div class="mb2">
      <form method="POST" id="price-form">
        <input type="hidden" name="action" value="update_price">
        <input type="hidden" name="product_id" id="price-pid">
        <div class="alert al-i" id="price-info"></div>
        <div class="fg">
          <label class="fl">New Price</label>
          <input type="number" name="price" id="price-val" class="fc" min="0" step="0.01" placeholder="Enter new price" autofocus>
        </div>
      </form>
    </div>
    <div class="mf">
      <button class="btn btn-s" onclick="closeModal('modal-price')">Cancel</button>
      <button class="btn btn-g" onclick="document.getElementById('price-form').submit()">💾 Update Price</button>
    </div>
  </div>
</div>
<!-- ─────────────────────────────────────────────────────────── -->

<script>
function openRestock(pid, name, stock) {
    document.getElementById('restock-pid').value = pid;
    document.getElementById('restock-info').textContent = 'Restocking: ' + name + ' — Current stock: ' + stock;
    document.getElementById('restock-qty').value = '';
    document.getElementById('modal-restock').classList.add('open');
}
function openAdjust(pid, name, stock) {
    document.getElementById('adjust-pid').value = pid;
    document.getElementById('adjust-info').textContent = 'Set exact stock for: ' + name;
    document.getElementById('adjust-qty').value = stock;
    document.getElementById('modal-adjust').classList.add('open');
}
// NEW: open price modal
function openPrice(pid, name, price) {
    document.getElementById('price-pid').value = pid;
    document.getElementById('price-info').textContent = 'Updating price for: ' + name + ' — Current price: ' + price;
    document.getElementById('price-val').value = price;
    document.getElementById('modal-price').classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
document.querySelectorAll('.moverlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>

<?php include 'includes/footer.php'; ?>