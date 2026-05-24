<?php
// ============================================================
// EXTRA 1: MY PROFILE PAGE
// File: profile.php
// Any logged-in user can view and edit their own profile
// and change their password from here
// ============================================================
require_once 'config.php';
requireLogin();

$page_title = 'My Profile';
$uid = $_SESSION['user_id'];

// ── HANDLE UPDATE PROFILE ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update_profile') {
    $name  = $conn->real_escape_string(trim($_POST['name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));

    if (!$name) {
        $_SESSION['flash'] = ['msg' => 'Name cannot be empty', 'type' => 'tw'];
    } else {
        $conn->query("UPDATE users SET name='$name', email='$email', phone='$phone' WHERE id=$uid");
        $_SESSION['user_name'] = $name; // update session name too
        $_SESSION['flash'] = ['msg' => 'Profile updated successfully ✅', 'type' => 'ts'];
    }
    header("Location: profile.php");
    exit;
}

// ── HANDLE CHANGE PASSWORD ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'change_password') {
    $current  = trim($_POST['current_pass']);
    $new_pass = trim($_POST['new_pass']);
    $confirm  = trim($_POST['confirm_pass']);

    // Get current password from DB
    $user = $conn->query("SELECT password FROM users WHERE id=$uid")->fetch_assoc();

    if ($user['password'] !== $current) {
        $_SESSION['flash'] = ['msg' => 'Current password is incorrect', 'type' => 'td'];
    } elseif (strlen($new_pass) < 6) {
        $_SESSION['flash'] = ['msg' => 'New password must be at least 6 characters', 'type' => 'tw'];
    } elseif ($new_pass !== $confirm) {
        $_SESSION['flash'] = ['msg' => 'New passwords do not match', 'type' => 'tw'];
    } else {
        $safe = $conn->real_escape_string($new_pass);
        $conn->query("UPDATE users SET password='$safe' WHERE id=$uid");
        $_SESSION['flash'] = ['msg' => 'Password changed successfully 🔒', 'type' => 'ts'];
    }
    header("Location: profile.php");
    exit;
}

// ── LOAD USER DATA ─────────────────────────────────────────────
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();

// Get this user's outlet (if outlet manager)
$outlet = null;
if ($user['role'] === 'outlet_manager') {
    $outlet = $conn->query("SELECT * FROM outlets WHERE manager_id=$uid")->fetch_assoc();
}

// Count orders placed by this user
$order_count = $conn->query("SELECT COUNT(*) as c FROM orders WHERE placed_by=$uid")->fetch_assoc()['c'];

// Count deliveries done (if delivery staff)
$delivery_count = 0;
if ($user['role'] === 'delivery') {
    $delivery_count = $conn->query("SELECT COUNT(*) as c FROM deliveries WHERE assigned_to=$uid AND status='delivered'")->fetch_assoc()['c'];
}

$role_labels = [
    'admin'           => 'Administrator',
    'factory_manager' => 'Factory Manager',
    'outlet_manager'  => 'Outlet Manager',
    'delivery'        => 'Delivery Staff',
];

include 'includes/header.php';
?>

<div class="ph">
  <div><h1>My Profile</h1><p>View and update your account details</p></div>
</div>

<div class="g2" style="align-items:start">

  <!-- LEFT: Profile Info + Edit -->
  <div>

    <!-- Profile Card -->
    <div class="card" style="margin-bottom:18px">
      <div class="cb" style="text-align:center; padding:28px 20px">
        <div style="width:72px;height:72px;background:var(--red);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Barlow Condensed',sans-serif;font-size:2rem;font-weight:800;margin:0 auto 14px">
          <?= strtoupper(substr($user['name'],0,1)) ?>
        </div>
        <h2 style="font-size:1.35rem"><?= htmlspecialchars($user['name']) ?></h2>
        <div style="margin-top:6px"><?= roleBadge($user['role']) ?></div>
        <div style="color:var(--g600);font-size:.78rem;margin-top:8px">@<?= htmlspecialchars($user['username']) ?></div>

        <!-- Quick stats -->
        <div style="display:flex;justify-content:center;gap:24px;margin-top:18px;padding-top:18px;border-top:1px solid var(--g200)">
          <?php if ($user['role'] === 'outlet_manager'): ?>
          <div style="text-align:center">
            <div style="font-family:'Barlow Condensed';font-size:1.6rem;font-weight:800;color:var(--red)"><?= $order_count ?></div>
            <div style="font-size:.7rem;color:var(--g600)">Orders Placed</div>
          </div>
          <?php endif; ?>
          <?php if ($user['role'] === 'delivery'): ?>
          <div style="text-align:center">
            <div style="font-family:'Barlow Condensed';font-size:1.6rem;font-weight:800;color:var(--green)"><?= $delivery_count ?></div>
            <div style="font-size:.7rem;color:var(--g600)">Deliveries Done</div>
          </div>
          <?php endif; ?>
          <div style="text-align:center">
            <div style="font-family:'Barlow Condensed';font-size:1.6rem;font-weight:800;color:var(--green)"><?= $user['active'] ? '✓' : '✗' ?></div>
            <div style="font-size:.7rem;color:var(--g600)">Status</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Outlet Info (outlet manager only) -->
    <?php if ($outlet): ?>
    <div class="card">
      <div class="ch"><h3>🏪 My Outlet</h3></div>
      <div class="cb">
        <table class="itbl">
          <tr><td>Name</td><td><strong><?= htmlspecialchars($outlet['name']) ?></strong></td></tr>
          <tr><td>Address</td><td><?= htmlspecialchars($outlet['address']) ?></td></tr>
          <tr><td>Phone</td><td><?= htmlspecialchars($outlet['phone']) ?></td></tr>
          <tr><td>Status</td><td><span class="badge <?= $outlet['active']?'b-ok':'b-canc' ?>"><?= $outlet['active']?'Active':'Inactive' ?></span></td></tr>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- RIGHT: Edit Forms -->
  <div>

    <!-- Edit Profile Form -->
    <div class="card" style="margin-bottom:18px">
      <div class="ch"><h3>✏️ Edit Profile</h3></div>
      <div class="cb">
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div class="fg">
            <label class="fl">Full Name *</label>
            <input type="text" name="name" class="fc" value="<?= htmlspecialchars($user['name']) ?>" required>
          </div>
          <div class="fg">
            <label class="fl">Email</label>
            <input type="email" name="email" class="fc" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="your@email.com">
          </div>
          <div class="fg">
            <label class="fl">Phone</label>
            <input type="text" name="phone" class="fc" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="017xxxxxxxx">
          </div>
          <div class="fg">
            <label class="fl">Username</label>
            <input type="text" class="fc" value="<?= htmlspecialchars($user['username']) ?>" disabled style="background:var(--g100);color:var(--g400)">
            <small style="color:var(--g400);font-size:.7rem">Username cannot be changed. Contact admin.</small>
          </div>
          <div class="fg">
            <label class="fl">Role</label>
            <input type="text" class="fc" value="<?= $role_labels[$user['role']] ?>" disabled style="background:var(--g100);color:var(--g400)">
          </div>
          <button type="submit" class="btn btn-p" style="width:100%;justify-content:center;padding:10px">💾 Save Profile</button>
        </form>
      </div>
    </div>

    <!-- Change Password Form -->
    <div class="card">
      <div class="ch"><h3>🔒 Change Password</h3></div>
      <div class="cb">
        <form method="POST" id="pass-form">
          <input type="hidden" name="action" value="change_password">
          <div class="fg">
            <label class="fl">Current Password *</label>
            <input type="password" name="current_pass" class="fc" placeholder="Your current password" required>
          </div>
          <div class="fg">
            <label class="fl">New Password *</label>
            <input type="password" name="new_pass" id="new-pass" class="fc" placeholder="Minimum 6 characters" required>
          </div>
          <div class="fg">
            <label class="fl">Confirm New Password *</label>
            <input type="password" name="confirm_pass" id="confirm-pass" class="fc" placeholder="Repeat new password" required>
          </div>
          <div id="pass-match-msg" style="font-size:.75rem;margin-bottom:10px;display:none"></div>
          <button type="submit" class="btn btn-w" style="width:100%;justify-content:center;padding:10px">🔑 Change Password</button>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
// Live password match check
const np = document.getElementById('new-pass');
const cp = document.getElementById('confirm-pass');
const msg = document.getElementById('pass-match-msg');
function checkMatch() {
    if (!cp.value) { msg.style.display='none'; return; }
    msg.style.display = 'block';
    if (np.value === cp.value) {
        msg.textContent = '✅ Passwords match';
        msg.style.color = 'var(--green)';
    } else {
        msg.textContent = '❌ Passwords do not match';
        msg.style.color = 'var(--red)';
    }
}
np.addEventListener('input', checkMatch);
cp.addEventListener('input', checkMatch);
</script>

<?php include 'includes/footer.php'; ?>