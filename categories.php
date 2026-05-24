<?php
// ============================================================
// EXTRA 2: CATEGORY MANAGEMENT PAGE
// File: categories.php
// Admin can add, edit, delete product categories
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin']);

$page_title = 'Category Management';

// ── ADD CATEGORY ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $name = $conn->real_escape_string(trim($_POST['name']));
    if ($name) {
        $dup = $conn->query("SELECT id FROM categories WHERE name='$name'")->num_rows;
        if ($dup) {
            $_SESSION['flash'] = ['msg' => 'Category already exists', 'type' => 'tw'];
        } else {
            $conn->query("INSERT INTO categories (name) VALUES ('$name')");
            $_SESSION['flash'] = ['msg' => "Category '$name' added", 'type' => 'ts'];
        }
    }
    header("Location: categories.php"); exit;
}

// ── EDIT CATEGORY ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    $id   = (int)$_POST['cat_id'];
    $name = $conn->real_escape_string(trim($_POST['name']));
    if ($id && $name) {
        $conn->query("UPDATE categories SET name='$name' WHERE id=$id");
        $_SESSION['flash'] = ['msg' => 'Category updated', 'type' => 'ts'];
    }
    header("Location: categories.php"); exit;
}

// ── DELETE CATEGORY ───────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Check if any products use this category
    $used = $conn->query("SELECT COUNT(*) as c FROM products WHERE category_id=$id")->fetch_assoc()['c'];
    if ($used > 0) {
        $_SESSION['flash'] = ['msg' => "Cannot delete — $used product(s) use this category", 'type' => 'td'];
    } else {
        $conn->query("DELETE FROM categories WHERE id=$id");
        $_SESSION['flash'] = ['msg' => 'Category deleted', 'type' => 'ts'];
    }
    header("Location: categories.php"); exit;
}

// ── LOAD CATEGORIES WITH PRODUCT COUNT ────────────────────────
$categories = $conn->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name
");

include 'includes/header.php';
?>

<div class="ph">
  <div><h1>Category Management</h1><p>Manage product categories</p></div>
  <button class="btn btn-p" onclick="openAddModal()">+ Add Category</button>
</div>

<div class="card">
  <div class="tw">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Category Name</th>
          <th>Products</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($categories->num_rows > 0):
          while ($c = $categories->fetch_assoc()): ?>
        <tr>
          <td style="color:var(--g400)"><?= $c['id'] ?></td>
          <td>
            <strong style="font-size:.92rem">📂 <?= htmlspecialchars($c['name']) ?></strong>
          </td>
          <td>
            <span class="badge b-proc"><?= $c['product_count'] ?> products</span>
          </td>
          <td style="display:flex;gap:6px;padding:8px 14px">
            <button class="btn btn-o xs"
              onclick="openEditModal(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['name'])) ?>')">
              ✏ Edit
            </button>
            <?php if ($c['product_count'] == 0): ?>
            <a href="categories.php?delete=<?= $c['id'] ?>"
               class="btn btn-s xs"
               onclick="return confirm('Delete category: <?= addslashes($c['name']) ?>?')">
              🗑 Delete
            </a>
            <?php else: ?>
            <button class="btn btn-s xs" disabled title="Has products — cannot delete" style="opacity:.4;cursor:not-allowed">🗑 Delete</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="4">
          <div class="es"><div class="ei">📂</div><p>No categories yet. Add one!</p></div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="moverlay" id="modal-add">
  <div class="modal">
    <div class="mh"><h3>📂 Add Category</h3><button class="mcl2" onclick="closeModal('modal-add')">✕</button></div>
    <div class="mb2">
      <form method="POST" id="add-form">
        <input type="hidden" name="action" value="add">
        <div class="fg">
          <label class="fl">Category Name *</label>
          <input type="text" name="name" id="add-name" class="fc" placeholder="e.g. Pastries" autofocus required>
        </div>
      </form>
    </div>
    <div class="mf">
      <button class="btn btn-s" onclick="closeModal('modal-add')">Cancel</button>
      <button class="btn btn-p" onclick="document.getElementById('add-form').submit()">✅ Add</button>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="moverlay" id="modal-edit">
  <div class="modal">
    <div class="mh"><h3>✏️ Edit Category</h3><button class="mcl2" onclick="closeModal('modal-edit')">✕</button></div>
    <div class="mb2">
      <form method="POST" id="edit-form">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="cat_id" id="edit-id">
        <div class="fg">
          <label class="fl">Category Name *</label>
          <input type="text" name="name" id="edit-name" class="fc" required>
        </div>
      </form>
    </div>
    <div class="mf">
      <button class="btn btn-s" onclick="closeModal('modal-edit')">Cancel</button>
      <button class="btn btn-p" onclick="document.getElementById('edit-form').submit()">💾 Save</button>
    </div>
  </div>
</div>

<script>
function openAddModal() {
    document.getElementById('add-name').value = '';
    document.getElementById('modal-add').classList.add('open');
    setTimeout(() => document.getElementById('add-name').focus(), 100);
}
function openEditModal(id, name) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-name').value = name;
    document.getElementById('modal-edit').classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
</script>

<?php include 'includes/footer.php'; ?>