<?php
// ============================================================
// STEP 14: PRODUCT MANAGEMENT PAGE
// File: products.php
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin','factory_manager']);

$page_title = 'Product Management';

// Handle Add / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_product') {
    $id    = (int)($_POST['product_id'] ?? 0);
    $name  = $conn->real_escape_string(trim($_POST['name']));
    $cat   = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $min   = (int)$_POST['min_stock'];
    $unit  = $conn->real_escape_string($_POST['unit']);
    $emoji = $conn->real_escape_string(trim($_POST['emoji'] ?: '🛍'));

    if (!$name || !$cat || !$price) {
        $_SESSION['flash'] = ['msg'=>'Fill all required fields','type'=>'tw'];
    } elseif ($id) {
        $conn->query("UPDATE products SET name='$name',category_id=$cat,price=$price,min_stock=$min,unit='$unit',emoji='$emoji' WHERE id=$id");
        $_SESSION['flash'] = ['msg'=>'Product updated','type'=>'ts'];
    } else {
        $conn->query("INSERT INTO products (name,category_id,price,stock,min_stock,unit,emoji) VALUES ('$name',$cat,$price,$stock,$min,'$unit','$emoji')");
        $_SESSION['flash'] = ['msg'=>'Product added','type'=>'ts'];
    }
    header("Location: products.php"); exit;
}

// Handle toggle active
if (isset($_GET['toggle'])) {
    $pid = (int)$_GET['toggle'];
    $conn->query("UPDATE products SET active = 1-active WHERE id=$pid");
    $_SESSION['flash'] = ['msg'=>'Product status updated','type'=>'ti'];
    header("Location: products.php"); exit;
}

// Load all products
$products = $conn->query("
    SELECT p.*, c.name AS cat_name
    FROM products p JOIN categories c ON p.category_id=c.id
    ORDER BY p.name
");

// Load categories for the form
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// If editing, load product data
$edit_product = null;
if (isset($_GET['edit'])) {
    $edit_product = $conn->query("SELECT * FROM products WHERE id=".(int)$_GET['edit'])->fetch_assoc();
}

include 'includes/header.php';
?>

<div class="ph">
  <div><h1>Product Management</h1><p>Manage your product catalogue</p></div>
  <button class="btn btn-p" onclick="openModal()">+ Add Product</button>
</div>

<div class="card">
  <div class="tw">
    <table>
      <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Min Stock</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php while ($p = $products->fetch_assoc()): ?>
        <tr>
          <td><?= $p['emoji'] ?> <strong><?= htmlspecialchars($p['name']) ?></strong></td>
          <td><?= htmlspecialchars($p['cat_name']) ?></td>
          <td><strong><?= tk($p['price']) ?></strong></td>
          <td><?= $p['stock'] ?> <?= $p['unit'] ?>s</td>
          <td><?= $p['min_stock'] ?></td>
          <td><span class="badge <?= $p['active']?'b-ok':'b-canc' ?>"><?= $p['active']?'Active':'Inactive' ?></span></td>
          <td style="display:flex;gap:5px;padding:8px 14px;flex-wrap:wrap">
            <button class="btn btn-o xs" onclick="openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)">✏ Edit</button>
            <a href="products.php?toggle=<?= $p['id'] ?>" class="btn btn-s xs"><?= $p['active']?'Deactivate':'Activate' ?></a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="moverlay" id="modal-product">
  <div class="modal modal-lg">
    <div class="mh"><h3 id="prod-modal-title">Add Product</h3><button class="mcl2" onclick="closeModal()">✕</button></div>
    <div class="mb2">
      <form method="POST" id="prod-form">
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="product_id" id="pf-id" value="0">
        <div class="frow">
          <div class="fg">
            <label class="fl">Product Name *</label>
            <input type="text" name="name" id="pf-name" class="fc" placeholder="e.g. Chocolate Cake Slice">
          </div>
          <div class="fg">
            <label class="fl">Category *</label>
            <select name="category_id" id="pf-cat" class="fc">
              <option value="">— Select —</option>
              <?php
              $categories->data_seek(0);
              while ($c = $categories->fetch_assoc()): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
        <div class="frow">
          <div class="fg">
            <label class="fl">Price (৳) *</label>
            <input type="number" name="price" id="pf-price" class="fc" step="0.01" min="0" placeholder="0.00">
          </div>
          <div class="fg">
            <label class="fl">Initial Stock</label>
            <input type="number" name="stock" id="pf-stock" class="fc" min="0" value="0">
          </div>
        </div>
        <div class="frow">
          <div class="fg">
            <label class="fl">Min Stock Level</label>
            <input type="number" name="min_stock" id="pf-min" class="fc" min="1" value="50">
          </div>
          <div class="fg">
            <label class="fl">Unit</label>
            <select name="unit" id="pf-unit" class="fc">
              <option value="piece">Piece</option>
              <option value="pack">Pack</option>
              <option value="bottle">Bottle</option>
              <option value="kg">Kg</option>
            </select>
          </div>
        </div>
        <div class="fg">
          <label class="fl">Emoji Icon</label>
          <input type="text" name="emoji" id="pf-emoji" class="fc" style="font-size:1.4rem;width:80px" maxlength="4" value="🛍">
        </div>
      </form>
    </div>
    <div class="mf">
      <button class="btn btn-s" onclick="closeModal()">Cancel</button>
      <button class="btn btn-p" onclick="document.getElementById('prod-form').submit()">💾 Save</button>
    </div>
  </div>
</div>

<script>
function openModal() {
    document.getElementById('prod-modal-title').textContent = 'Add New Product';
    document.getElementById('pf-id').value = 0;
    document.getElementById('pf-name').value = '';
    document.getElementById('pf-price').value = '';
    document.getElementById('pf-stock').value = '0';
    document.getElementById('pf-min').value = '50';
    document.getElementById('pf-emoji').value = '🛍';
    document.getElementById('pf-cat').value = '';
    document.getElementById('modal-product').classList.add('open');
}
function openEditModal(p) {
    document.getElementById('prod-modal-title').textContent = 'Edit: ' + p.name;
    document.getElementById('pf-id').value = p.id;
    document.getElementById('pf-name').value = p.name;
    document.getElementById('pf-price').value = p.price;
    document.getElementById('pf-stock').value = p.stock;
    document.getElementById('pf-min').value = p.min_stock;
    document.getElementById('pf-unit').value = p.unit;
    document.getElementById('pf-emoji').value = p.emoji;
    document.getElementById('pf-cat').value = p.category_id;
    document.getElementById('modal-product').classList.add('open');
}
function closeModal() {
    document.getElementById('modal-product').classList.remove('open');
}
document.getElementById('modal-product').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include 'includes/footer.php'; ?>