<?php
// ============================================================
// STEP 16: USER MANAGEMENT PAGE
// File: users.php
// ============================================================
require_once 'config.php';
requireLogin();
requireRole(['admin']);

$page_title = 'User Management';

// Handle save user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_user') {
    $id    = (int)($_POST['user_id'] ?? 0);
    $name  = $conn->real_escape_string(trim($_POST['name']));
    $role  = $conn->real_escape_string($_POST['role']);
    $uname = $conn->real_escape_string(trim($_POST['username']));
    $pass  = $conn->real_escape_string(trim($_POST['password']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));

    if (!$name || !$role || !$uname || !$pass) {
        $_SESSION['flash'] = ['msg'=>'Fill all required fields','type'=>'tw'];
    } elseif (!$id) {
        // Check duplicate username
        $dup = $conn->query("SELECT id FROM users WHERE username='$uname'")->num_rows;
        if ($dup) {
            $_SESSION['flash'] = ['msg'=>'Username already exists','type'=>'td'];
        } else {
            $conn->query("INSERT INTO users (name,role,username,password,email,phone) VALUES ('$name','$role','$uname','$pass','$email','$phone')");
            $_SESSION['flash'] = ['msg'=>'User created','type'=>'ts'];
        }
    } else {
        $conn->query("UPDATE users SET name='$name',role='$role',username='$uname',password='$pass',email='$email',phone='$phone' WHERE id=$id");
        $_SESSION['flash'] = ['msg'=>'User updated','type'=>'ts'];
    }
    header("Location: users.php"); exit;
}

// Toggle active
if (isset($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    if ($uid !== $_SESSION['user_id']) {  // can't deactivate yourself
        $conn->query("UPDATE users SET active=1-active WHERE id=$uid");
    }
    header("Location: users.php"); exit;
}

$users = $conn->query("SELECT * FROM users ORDER BY name");
include 'includes/header.php';
?>

<div class="ph">
  <div><h1>User Management</h1><p>Manage system users and roles</p></div>
  <button class="btn btn-p" onclick="openModal()">+ Add User</button>
</div>

<div class="card">
  <div class="tw">
    <table>
      <thead><tr><th>User</th><th>Role</th><th>Username</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:9px">
              <div class="uav"><?= strtoupper(substr($u['name'],0,1)) ?></div>
              <div>
                <strong><?= htmlspecialchars($u['name']) ?></strong>
                <div style="font-size:.72rem;color:var(--g600)"><?= htmlspecialchars($u['email']) ?></div>
              </div>
            </div>
          </td>
          <td><?= roleBadge($u['role']) ?></td>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['phone']) ?></td>
          <td><span class="badge <?= $u['active']?'b-ok':'b-canc' ?>"><?= $u['active']?'Active':'Inactive' ?></span></td>
          <td style="display:flex;gap:5px;padding:8px 14px;flex-wrap:wrap">
            <button class="btn btn-o xs" onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">✏ Edit</button>
            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
            <a href="users.php?toggle=<?= $u['id'] ?>" class="btn btn-s xs"><?= $u['active']?'Deactivate':'Activate' ?></a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit Modal -->
<div class="moverlay" id="modal-user">
  <div class="modal">
    <div class="mh"><h3 id="user-modal-title">Add User</h3><button class="mcl2" onclick="closeModal()">✕</button></div>
    <div class="mb2">
      <form method="POST" id="user-form">
        <input type="hidden" name="action" value="save_user">
        <input type="hidden" name="user_id" id="uf-id" value="0">
        <div class="frow">
          <div class="fg"><label class="fl">Full Name *</label><input type="text" name="name" id="uf-name" class="fc" placeholder="Full name"></div>
          <div class="fg">
            <label class="fl">Role *</label>
            <select name="role" id="uf-role" class="fc">
              <option value="admin">Admin</option>
              <option value="factory_manager">Factory Manager</option>
              <option value="outlet_manager">Outlet Manager</option>
              <option value="delivery">Delivery</option>
            </select>
          </div>
        </div>
        <div class="frow">
          <div class="fg"><label class="fl">Username *</label><input type="text" name="username" id="uf-uname" class="fc" placeholder="username"></div>
          <div class="fg"><label class="fl">Password *</label><input type="text" name="password" id="uf-pass" class="fc" placeholder="password"></div>
        </div>
        <div class="frow">
          <div class="fg"><label class="fl">Email</label><input type="email" name="email" id="uf-email" class="fc" placeholder="email@example.com"></div>
          <div class="fg"><label class="fl">Phone</label><input type="text" name="phone" id="uf-phone" class="fc" placeholder="017xxxxxxxx"></div>
        </div>
      </form>
    </div>
    <div class="mf">
      <button class="btn btn-s" onclick="closeModal()">Cancel</button>
      <button class="btn btn-p" onclick="document.getElementById('user-form').submit()">💾 Save</button>
    </div>
  </div>
</div>

<script>
function openModal() {
    document.getElementById('user-modal-title').textContent = 'Add New User';
    document.getElementById('uf-id').value = 0;
    ['uf-name','uf-uname','uf-pass','uf-email','uf-phone'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('uf-role').value = 'outlet_manager';
    document.getElementById('modal-user').classList.add('open');
}
function openEditModal(u) {
    document.getElementById('user-modal-title').textContent = 'Edit: ' + u.name;
    document.getElementById('uf-id').value = u.id;
    document.getElementById('uf-name').value = u.name;
    document.getElementById('uf-role').value = u.role;
    document.getElementById('uf-uname').value = u.username;
    document.getElementById('uf-pass').value = u.password;
    document.getElementById('uf-email').value = u.email || '';
    document.getElementById('uf-phone').value = u.phone || '';
    document.getElementById('modal-user').classList.add('open');
}
function closeModal() { document.getElementById('modal-user').classList.remove('open'); }
document.getElementById('modal-user').addEventListener('click', e => { if(e.target===this) closeModal(); });
</script>

<?php include 'includes/footer.php'; ?>