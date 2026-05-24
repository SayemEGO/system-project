<?php
// ============================================================
// STEP 15: OUTLET MANAGEMENT PAGE
// File: outlets.php
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin']);

$page_title = 'Outlet Management';

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_outlet') {
    $id    = (int)($_POST['outlet_id'] ?? 0);
    $name  = $conn->real_escape_string(trim($_POST['name']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $addr  = $conn->real_escape_string(trim($_POST['address']));
    $mgr   = (int)($_POST['manager_id'] ?? 0) ?: 'NULL';

    if (!$name) {
        $_SESSION['flash'] = ['msg'=>'Outlet name required','type'=>'tw'];
    } elseif ($id) {
        $conn->query("UPDATE outlets SET name='$name',phone='$phone',address='$addr',manager_id=$mgr WHERE id=$id");
        $_SESSION['flash'] = ['msg'=>'Outlet updated','type'=>'ts'];
    } else {
        $conn->query("INSERT INTO outlets (name,phone,address,manager_id) VALUES ('$name','$phone','$addr',$mgr)");
        $_SESSION['flash'] = ['msg'=>'Outlet added','type'=>'ts'];
    }
    header("Location: outlets.php"); exit;
}

// Toggle active
if (isset($_GET['toggle'])) {
    $conn->query("UPDATE outlets SET active=1-active WHERE id=".(int)$_GET['toggle']);
    header("Location: outlets.php"); exit;
}

$outlets  = $conn->query("SELECT o.*, u.name AS mgr_name FROM outlets o LEFT JOIN users u ON o.manager_id=u.id ORDER BY o.name");
$managers = $conn->query("SELECT id,name FROM users WHERE role='outlet_manager' AND active=1 ORDER BY name");

include 'includes/header.php';
?>

<div class="ph">
  <div><h1>Outlet Management</h1><p>Manage distribution outlets</p></div>
  <button class="btn btn-p" onclick="openModal()">+ Add Outlet</button>
</div>

<div class="card">
  <div class="tw">
    <table>
      <thead><tr><th>Outlet</th><th>Address</th><th>Phone</th><th>Manager</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php while ($o = $outlets->fetch_assoc()): ?>
        <tr>
          <td><strong>🏪 <?= htmlspecialchars($o['name']) ?></strong></td>
          <td><?= htmlspecialchars($o['address']) ?></td>
          <td><?= htmlspecialchars($o['phone']) ?></td>
          <td><?= $o['mgr_name'] ? htmlspecialchars($o['mgr_name']) : '—' ?></td>
          <td><span class="badge <?= $o['active']?'b-ok':'b-canc' ?>"><?= $o['active']?'Active':'Inactive' ?></span></td>
          <td style="display:flex;gap:5px;padding:8px 14px">
            <button class="btn btn-o xs" onclick="openEditModal(<?= htmlspecialchars(json_encode($o)) ?>)">✏ Edit</button>
            <a href="outlets.php?toggle=<?= $o['id'] ?>" class="btn btn-s xs"><?= $o['active']?'Deactivate':'Activate' ?></a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="moverlay" id="modal-outlet">
  <div class="modal">
    <div class="mh"><h3 id="outlet-modal-title">Add Outlet</h3><button class="mcl2" onclick="closeModal()">✕</button></div>
    <div class="mb2">
      <form method="POST" id="outlet-form">
        <input type="hidden" name="action" value="save_outlet">
        <input type="hidden" name="outlet_id" id="of-id" value="0">
        <div class="fg"><label class="fl">Outlet Name *</label><input type="text" name="name" id="of-name" class="fc" placeholder="e.g. Dhaka Main Outlet"></div>
        <div class="frow">
          <div class="fg"><label class="fl">Phone</label><input type="text" name="phone" id="of-phone" class="fc" placeholder="017xxxxxxxx"></div>
          <div class="fg">
            <label class="fl">Manager</label>
            <select name="manager_id" id="of-mgr" class="fc">
              <option value="">— None —</option>
              <?php $managers->data_seek(0); while ($m = $managers->fetch_assoc()): ?>
              <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
        <div class="fg"><label class="fl">Address</label><input type="text" name="address" id="of-addr" class="fc" placeholder="Full address"></div>
      </form>
    </div>
    <div class="mf">
      <button class="btn btn-s" onclick="closeModal()">Cancel</button>
      <button class="btn btn-p" onclick="document.getElementById('outlet-form').submit()">💾 Save</button>
    </div>
  </div>
</div>

<script>
function openModal() {
    document.getElementById('outlet-modal-title').textContent = 'Add New Outlet';
    document.getElementById('of-id').value = 0;
    ['of-name','of-phone','of-addr'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('of-mgr').value = '';
    document.getElementById('modal-outlet').classList.add('open');
}
function openEditModal(o) {
    document.getElementById('outlet-modal-title').textContent = 'Edit: ' + o.name;
    document.getElementById('of-id').value = o.id;
    document.getElementById('of-name').value = o.name;
    document.getElementById('of-phone').value = o.phone || '';
    document.getElementById('of-addr').value = o.address || '';
    document.getElementById('of-mgr').value = o.manager_id || '';
    document.getElementById('modal-outlet').classList.add('open');
}
function closeModal() { document.getElementById('modal-outlet').classList.remove('open'); }
document.getElementById('modal-outlet').addEventListener('click', e => { if(e.target===this) closeModal(); });
</script>

<?php include 'includes/footer.php'; ?>