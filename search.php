<?php
// ============================================================
// EXTRA 4: GLOBAL SEARCH PAGE
// File: search.php
// ============================================================
require_once 'config.php';
requireLogin();

// Read session variables
$user_id = $_SESSION['user_id'] ?? 0;
$role    = $_SESSION['role']    ?? '';

$page_title = 'Search';
$q      = trim($_GET['q'] ?? '');
$safe_q = $conn->real_escape_string($q);

$results = [
    'orders'   => [],
    'products' => [],
    'outlets'  => [],
    'users'    => [],
];
$total = 0;

if (strlen($q) >= 2) {

    // ── SEARCH ORDERS ───────────────────────────────────────
    $res = $conn->query("
        SELECT o.id, o.status, o.total, o.created_at, outlet.name AS outlet_name
        FROM orders o
        JOIN outlets outlet ON o.outlet_id = outlet.id
        WHERE o.id LIKE '%$safe_q%'
           OR outlet.name LIKE '%$safe_q%'
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    if ($res) {
        while ($r = $res->fetch_assoc()) $results['orders'][] = $r;
    }

    // ── SEARCH PRODUCTS ─────────────────────────────────────
    if (in_array($role, ['admin', 'factory_manager'])) {
        $res = $conn->query("
            SELECT p.*, c.name AS cat_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            WHERE p.name LIKE '%$safe_q%'
               OR c.name LIKE '%$safe_q%'
            LIMIT 10
        ");
        if ($res) {
            while ($r = $res->fetch_assoc()) $results['products'][] = $r;
        }
    }

    // ── SEARCH OUTLETS ──────────────────────────────────────
    if (in_array($role, ['admin', 'factory_manager'])) {
        $res = $conn->query("
            SELECT o.*, u.name AS mgr_name
            FROM outlets o
            LEFT JOIN users u ON o.manager_id = u.id
            WHERE o.name LIKE '%$safe_q%'
               OR o.address LIKE '%$safe_q%'
               OR o.phone LIKE '%$safe_q%'
            LIMIT 10
        ");
        if ($res) {
            while ($r = $res->fetch_assoc()) $results['outlets'][] = $r;
        }
    }

    // ── SEARCH USERS (admin only) ───────────────────────────
    if ($role === 'admin') {
        $res = $conn->query("
            SELECT id, name, role, username, email, phone, active
            FROM users
            WHERE name LIKE '%$safe_q%'
               OR username LIKE '%$safe_q%'
               OR email LIKE '%$safe_q%'
            LIMIT 10
        ");
        if ($res) {
            while ($r = $res->fetch_assoc()) $results['users'][] = $r;
        }
    }

    $total = count($results['orders'])
           + count($results['products'])
           + count($results['outlets'])
           + count($results['users']);
}

include 'includes/header.php';
?>

<div class="ph">
  <div>
    <h1>🔍 Global Search</h1>
    <p>Search across orders, products, outlets<?= $role === 'admin' ? ', and users' : '' ?></p>
  </div>
</div>

<!-- Search Bar -->
<form method="GET" style="margin-bottom:22px">
  <div style="display:flex;gap:10px;max-width:560px">
    <div class="sw-input" style="flex:1">
      <span>🔍</span>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
             placeholder="Type at least 2 characters…" autofocus>
    </div>
    <button type="submit" class="btn btn-p" style="padding:9px 20px">Search</button>
    <?php if ($q): ?>
    <a href="search.php" class="btn btn-s">Clear</a>
    <?php endif; ?>
  </div>
</form>

<?php if ($q && strlen($q) < 2): ?>
  <div class="alert al-w">Please enter at least 2 characters to search.</div>

<?php elseif ($q && $total === 0): ?>
  <div class="card">
    <div class="cb">
      <div class="es">
        <div class="ei">🔍</div>
        <h3>No results found</h3>
        <p>Nothing matched "<strong><?= htmlspecialchars($q) ?></strong>". Try a different keyword.</p>
      </div>
    </div>
  </div>

<?php elseif ($q): ?>

  <div class="alert al-i" style="margin-bottom:16px">
    Found <strong><?= $total ?></strong> result<?= $total !== 1 ? 's' : '' ?> for
    "<strong><?= htmlspecialchars($q) ?></strong>"
  </div>

  <!-- ── ORDERS ── -->
  <?php if (!empty($results['orders'])): ?>
  <div class="card">
    <div class="ch">
      <h3>📦 Orders <span class="badge b-proc" style="margin-left:6px"><?= count($results['orders']) ?></span></h3>
    </div>
    <div class="tw">
      <table>
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Outlet</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results['orders'] as $o): ?>
          <tr>
            <td><strong><?= oid($o['id']) ?></strong></td>
            <td><?= htmlspecialchars($o['outlet_name']) ?></td>
            <td><?= date('d M Y H:i', strtotime($o['created_at'])) ?></td>
            <td><strong><?= tk($o['total']) ?></strong></td>
            <td><?= statusBadge($o['status']) ?></td>
            <td><a href="order_detail.php?id=<?= $o['id'] ?>&back=search" class="btn btn-o xs">👁 View</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── PRODUCTS ── -->
  <?php if (!empty($results['products'])): ?>
  <div class="card">
    <div class="ch">
      <h3>🧁 Products <span class="badge b-proc" style="margin-left:6px"><?= count($results['products']) ?></span></h3>
      <a href="products.php" class="btn btn-s sm">Manage</a>
    </div>
    <div class="tw">
      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results['products'] as $p): ?>
          <tr>
            <td><?= $p['emoji'] ?> <strong><?= htmlspecialchars($p['name']) ?></strong></td>
            <td><?= htmlspecialchars($p['cat_name']) ?></td>
            <td><?= tk($p['price']) ?></td>
            <td>
              <span style="color:<?= $p['stock'] < $p['min_stock'] ? 'var(--orange)' : 'var(--green)' ?>;font-weight:700">
                <?= $p['stock'] ?>
              </span>
            </td>
            <td><span class="badge <?= $p['active'] ? 'b-ok' : 'b-canc' ?>"><?= $p['active'] ? 'Active' : 'Inactive' ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── OUTLETS ── -->
  <?php if (!empty($results['outlets'])): ?>
  <div class="card">
    <div class="ch">
      <h3>🏪 Outlets <span class="badge b-proc" style="margin-left:6px"><?= count($results['outlets']) ?></span></h3>
      <a href="outlets.php" class="btn btn-s sm">Manage</a>
    </div>
    <div class="tw">
      <table>
        <thead>
          <tr>
            <th>Outlet</th>
            <th>Address</th>
            <th>Phone</th>
            <th>Manager</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results['outlets'] as $o): ?>
          <tr>
            <td><strong>🏪 <?= htmlspecialchars($o['name']) ?></strong></td>
            <td><?= htmlspecialchars($o['address']) ?></td>
            <td><?= htmlspecialchars($o['phone']) ?></td>
            <td><?= $o['mgr_name'] ? htmlspecialchars($o['mgr_name']) : '—' ?></td>
            <td><span class="badge <?= $o['active'] ? 'b-ok' : 'b-canc' ?>"><?= $o['active'] ? 'Active' : 'Inactive' ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── USERS ── -->
  <?php if (!empty($results['users'])): ?>
  <div class="card">
    <div class="ch">
      <h3>👥 Users <span class="badge b-proc" style="margin-left:6px"><?= count($results['users']) ?></span></h3>
      <a href="users.php" class="btn btn-s sm">Manage</a>
    </div>
    <div class="tw">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Email</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results['users'] as $u): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="uav"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                <strong><?= htmlspecialchars($u['name']) ?></strong>
              </div>
            </td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= roleBadge($u['role']) ?></td>
            <td><?= htmlspecialchars($u['email'] ?? '—') ?></td>
            <td><span class="badge <?= $u['active'] ? 'b-ok' : 'b-canc' ?>"><?= $u['active'] ? 'Active' : 'Inactive' ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

<?php else: ?>
  <!-- No search typed yet -->
  <div class="card">
    <div class="cb">
      <div class="es">
        <div class="ei">🔍</div>
        <h3>Start Searching</h3>
        <p>Enter a keyword above to search across the entire system.</p>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>