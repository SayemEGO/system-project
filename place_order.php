<?php
// ============================================================
// STEP 10: PLACE ORDER PAGE
// File: place_order.php
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['outlet_manager']);

// Find this manager's outlet
$outlet = $conn->query("SELECT * FROM outlets WHERE manager_id={$_SESSION['user_id']} AND active=1")->fetch_assoc();
if (!$outlet) {
    echo "<p style='padding:20px;color:red'>❌ No outlet assigned to your account. Contact admin.</p>";
    exit;
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $notes = $conn->real_escape_string(trim($_POST['notes'] ?? ''));
    $items = $_POST['items'] ?? [];  // array of [product_id => qty]

    // Filter out zero quantities
    $cart = [];
    foreach ($items as $pid => $qty) {
        $pid = (int)$pid;
        $qty = (int)$qty;
        if ($pid > 0 && $qty > 0) $cart[$pid] = $qty;
    }

    if (empty($cart)) {
        $_SESSION['flash'] = ['msg'=>'Your cart is empty!','type'=>'tw'];
    } else {
        // Calculate total
        $total = 0;
        foreach ($cart as $pid => $qty) {
            $p = $conn->query("SELECT price FROM products WHERE id=$pid AND active=1")->fetch_assoc();
            if ($p) $total += $p['price'] * $qty;
        }

        // Insert order
        $oid_val = $outlet['id'];
        $uid = $_SESSION['user_id'];
        $conn->query("INSERT INTO orders (outlet_id,placed_by,notes,total) VALUES ($oid_val,$uid,'$notes',$total)");
        $new_order_id = $conn->insert_id;

        // Insert order items
        foreach ($cart as $pid => $qty) {
            $p = $conn->query("SELECT price FROM products WHERE id=$pid")->fetch_assoc();
            if ($p) {
                $price = $p['price'];
                $conn->query("INSERT INTO order_items (order_id,product_id,qty,unit_price) VALUES ($new_order_id,$pid,$qty,$price)");
            }
        }

        $_SESSION['flash'] = ['msg'=>'Order '.oid($new_order_id).' placed! Factory notified. 🎉','type'=>'ts'];
        header("Location: my_orders.php");
        exit;
    }
}

// Load products grouped by category
$categories = $conn->query("SELECT * FROM categories ORDER BY id");
$products   = $conn->query("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON p.category_id=c.id WHERE p.active=1 ORDER BY p.category_id, p.name");

$prods_by_cat = [];
$all_prods = [];
while ($p = $products->fetch_assoc()) {
    $prods_by_cat[$p['category_id']][] = $p;
    $all_prods[] = $p;
}

$page_title = 'Place New Order';
include 'includes/header.php';
?>

<div class="ph">
  <div><h1>Place New Order</h1><p>Ordering for: <strong><?= htmlspecialchars($outlet['name']) ?></strong></p></div>
</div>

<form method="POST" id="order-form">
  <input type="hidden" name="action" value="place_order">
  <div class="g2" style="align-items:start">

    <!-- Left: Product selector -->
    <div>
      <div class="card">
        <div class="ch"><h3>🧁 Select Products</h3></div>
        <div class="cb">
          <!-- Category filter buttons -->
          <div class="cat-btns">
            <button type="button" class="cb-btn act" onclick="filterCat(0,this)">All</button>
            <?php foreach ($prods_by_cat as $catId => $ps):
              $cname = $ps[0]['cat_name'];
            ?>
            <button type="button" class="cb-btn" onclick="filterCat(<?= $catId ?>,this)"><?= htmlspecialchars($cname) ?></button>
            <?php endforeach; ?>
          </div>

          <!-- Product tiles -->
          <div class="pgrid" id="product-grid">
            <?php foreach ($all_prods as $p): ?>
            <div class="ptile <?= $p['stock']<=0?'oos':'' ?>" data-cat="<?= $p['category_id'] ?>" id="tile-<?= $p['id'] ?>"
                 onclick="<?= $p['stock']>0 ? "addItem({$p['id']})" : '' ?>">
              <div class="pe"><?= $p['emoji'] ?></div>
              <div class="pn"><?= htmlspecialchars($p['name']) ?></div>
              <div class="pp">৳<?= number_format($p['price'],2) ?>/<?= $p['unit'] ?></div>
              <div class="ps"><?= $p['stock']>0 ? $p['stock'].' in stock' : 'Out of stock' ?></div>
              <!-- Hidden input for quantity, updated by JS -->
              <input type="hidden" name="items[<?= $p['id'] ?>]" id="qty-<?= $p['id'] ?>" value="0">
              <div class="qb" id="qb-<?= $p['id'] ?>" style="display:none;position:absolute;top:-8px;right:-8px;background:var(--red);color:#fff;width:20px;height:20px;border-radius:50%;font-size:.65rem;font-weight:700;align-items:center;justify-content:center">0</div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: Cart -->
    <div>
      <div class="card" style="position:sticky;top:70px">
        <div class="ch">
          <h3>🛒 Order Cart</h3>
          <span id="cart-total-top" style="font-family:'Barlow Condensed';font-size:1.15rem;font-weight:800;color:var(--red)">৳0.00</span>
        </div>
        <div class="cb">
          <div id="cart-items">
            <div class="cempty"><span class="cei">🛒</span>Click products to add them.</div>
          </div>
          <div class="fg" style="margin-top:14px">
            <label class="fl">Order Notes (optional)</label>
            <textarea name="notes" class="fc" rows="2" placeholder="Special instructions…"></textarea>
          </div>
          <button type="submit" id="submit-btn" class="btn btn-p" style="width:100%;justify-content:center;padding:11px;font-size:.95rem;margin-top:4px" disabled>
            ✅ Place Order
          </button>
          <p style="font-size:.7rem;color:var(--g400);text-align:center;margin-top:7px">Your order will be sent to the factory.</p>
        </div>
      </div>
    </div>

  </div>
</form>

<script>
// Product data from PHP
const PRODUCTS = <?= json_encode($all_prods) ?>;
const cart = {}; // pid => qty

function getProduct(pid) {
    return PRODUCTS.find(p => p.id == pid);
}

function filterCat(catId, btn) {
    document.querySelectorAll('.cb-btn').forEach(b => b.classList.remove('act'));
    btn.classList.add('act');
    document.querySelectorAll('.ptile').forEach(tile => {
        const tc = tile.dataset.cat;
        tile.style.display = (catId == 0 || tc == catId) ? '' : 'none';
    });
}

function addItem(pid) {
    cart[pid] = (cart[pid] || 0) + 1;
    updateTile(pid);
    renderCart();
}

function changeQty(pid, d) {
    cart[pid] = (cart[pid] || 0) + d;
    if (cart[pid] <= 0) delete cart[pid];
    updateTile(pid);
    renderCart();
}

function removeItem(pid) {
    delete cart[pid];
    updateTile(pid);
    renderCart();
}

function updateTile(pid) {
    const tile = document.getElementById('tile-' + pid);
    const qb   = document.getElementById('qb-' + pid);
    const inp  = document.getElementById('qty-' + pid);
    const qty  = cart[pid] || 0;
    if (tile) tile.classList.toggle('sel', qty > 0);
    if (tile) tile.style.border = qty > 0 ? '2px solid var(--red)' : '';
    if (qb)  { qb.textContent = qty; qb.style.display = qty > 0 ? 'flex' : 'none'; }
    if (inp) inp.value = qty;
}

function renderCart() {
    const keys = Object.keys(cart).filter(k => cart[k] > 0);
    let total = 0;
    keys.forEach(k => {
        const p = getProduct(k);
        if (p) total += p.price * cart[k];
    });
    document.getElementById('cart-total-top').textContent = '৳' + total.toLocaleString('en', {minimumFractionDigits:2});
    document.getElementById('submit-btn').disabled = keys.length === 0;

    if (!keys.length) {
        document.getElementById('cart-items').innerHTML = '<div class="cempty"><span class="cei">🛒</span>Click products to add them.</div>';
        return;
    }

    let html = keys.map(k => {
        const p = getProduct(k);
        if (!p) return '';
        return `<div class="ci">
            <span class="cin">${p.emoji} ${p.name}</span>
            <div class="qctrl">
                <button type="button" class="qb2" onclick="changeQty(${p.id},-1)">−</button>
                <span class="qdsp">${cart[k]}</span>
                <button type="button" class="qb2" onclick="changeQty(${p.id},1)">+</button>
            </div>
            <span class="cit">৳${(cart[k]*p.price).toFixed(2)}</span>
            <button type="button" class="crm" onclick="removeItem(${p.id})">✕</button>
        </div>`;
    }).join('');

    html += `<div class="ctrow"><span class="ctl">Total Amount</span><span class="ctv">৳${total.toFixed(2)}</span></div>`;
    document.getElementById('cart-items').innerHTML = html;
}
</script>

<?php include 'includes/footer.php'; ?>