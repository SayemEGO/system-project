<?php
// ============================================================
// STEP 3: LOGIN PAGE
// File: login.php
// ============================================================
require_once 'config.php';

// If already logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role'] ?? '');

    if ($username && $password) {
        // Find user in database
        $stmt = $conn->prepare("SELECT * FROM users WHERE username=? AND password=? AND active=1");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            // Check role if selected
            if ($role && $user['role'] !== $role) {
                $error = "This account does not have the selected role.";
            } else {
                // Save user info in session
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['username']  = $user['username'];

                // If outlet manager, find their outlet and save it
                if ($user['role'] === 'outlet_manager') {
                    $oid = $conn->prepare("SELECT id FROM outlets WHERE manager_id=? AND active=1 LIMIT 1");
                    $oid->bind_param("i", $user['id']);
                    $oid->execute();
                    $outlet = $oid->get_result()->fetch_assoc();
                    if ($outlet) {
                        $_SESSION['outlet_id'] = $outlet['id'];
                    }
                    header("Location: my_orders.php");
                } elseif ($user['role'] === 'delivery') {
                    header("Location: deliveries.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please enter username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Bakery Management System</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
<style>
  /* Password field wrapper */
  .pwd-wrapper {
    position: relative;
  }
  .pwd-wrapper input {
    width: 100%;
    box-sizing: border-box;
    padding-right: 44px; /* leave room for the eye button */
  }
  .pwd-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 0;
    color: inherit;
    opacity: 0.6;
    transition: opacity 0.2s;
  }
  .pwd-toggle:hover {
    opacity: 1;
  }
</style>
</head>
<body class="login-body">

<div id="login-screen">
<div class="lcard">
  <div class="lbrand">
    <div class="lico">🍞</div>
    <h1>Bakery System</h1>
    <span>Smart Inventory &amp; Distribution</span>
  </div>

  <?php if ($error): ?>
    <div class="lerr" style="display:block">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="lfg">
      <label>Username</label>
      <input type="text" name="username" placeholder="Enter username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
    </div>

    <!-- PASSWORD FIELD WITH SHOW/HIDE TOGGLE -->
    <div class="lfg">
      <label>Password</label>
      <div class="pwd-wrapper">
        <input type="password" name="password" id="password" placeholder="Enter password" required>
        <button type="button" class="pwd-toggle" id="togglePwd" onclick="togglePassword()" title="Show / hide password" aria-label="Toggle password visibility">
          👁️
        </button>
      </div>
    </div>

    <div class="lfg">
      <label>Login As</label>
      <select name="role">
        <option value="">— Any Role —</option>
        <option value="admin">Administrator</option>
        <option value="factory_manager">Factory Manager</option>
        <option value="outlet_manager">Outlet Manager</option>
        <option value="delivery">Delivery Staff</option>
      </select>
    </div>
    <button type="submit" class="lbtn">🔐 Sign In</button>
  </form>

  <!-- Quick demo login buttons -->
  <div class="demo-box">
    <p>QUICK LOGIN — DEMO ACCOUNTS</p>
    <div class="demo-grid">
      <button class="dbtn" onclick="fl('admin','admin123','admin')">
        <strong>Admin</strong>admin / admin123
      </button>
      <button class="dbtn" onclick="fl('factory','factory123','factory_manager')">
        <strong>Factory Mgr</strong>factory / factory123
      </button>
      <button class="dbtn" onclick="fl('dhaka_mgr','outlet123','outlet_manager')">
        <strong>Dhaka Outlet</strong>dhaka_mgr / outlet123
      </button>
      <button class="dbtn" onclick="fl('ctg_mgr','outlet456','outlet_manager')">
        <strong>Ctg Outlet</strong>ctg_mgr / outlet456
      </button>
      <button class="dbtn" onclick="fl('delivery','delivery123','delivery')">
        <strong>Delivery</strong>delivery / delivery123
      </button>
    </div>
  </div>
</div>
</div>

<script>
// Fill demo login credentials into the form
function fl(u, p, r) {
    document.querySelector('input[name=username]').value = u;
    document.querySelector('input[name=password]').value = p;
    document.querySelector('select[name=role]').value    = r;
}

// Toggle password visibility
function togglePassword() {
    var input = document.getElementById('password');
    var btn   = document.getElementById('togglePwd');
    if (input.type === 'password') {
        input.type       = 'text';
        btn.textContent  = '🙈';
        btn.title        = 'Hide password';
    } else {
        input.type       = 'password';
        btn.textContent  = '👁️';
        btn.title        = 'Show password';
    }
}
</script>
</body>
</html>