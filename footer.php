<?php
// ============================================================
// SHARED FOOTER
// File: includes/footer.php
// Save in: C:/xampp/htdocs/bakery/includes/footer.php
// Include this at the VERY BOTTOM of every page
// ============================================================
?>

    </div><!-- /.content -->
  </div><!-- /.main -->
</div><!-- /#app -->

<!-- Toast notification container -->
<div id="toasts"></div>

<script>
// ════════════════════════════════════════════
// TOAST NOTIFICATIONS
// Usage: showToast("Your message", "ts")
//   ts = success (green)
//   tw = warning (orange)
//   td = error   (red)
//   ti = info    (blue)
// ════════════════════════════════════════════
function showToast(msg, type = 'ts') {
    const icons = { ts: '✅', tw: '⚠️', td: '❌', ti: 'ℹ️' };
    const container = document.getElementById('toasts');
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<span>' + (icons[type] || 'ℹ️') + '</span><span>' + msg + '</span>';
    container.appendChild(toast);

    // Auto-remove after 3.2 seconds
    setTimeout(function() {
        toast.style.transition = 'all .3s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(28px)';
        setTimeout(function() { toast.remove(); }, 300);
    }, 3200);
}

// ── Show flash message from PHP session ─────────────────────
<?php if (!empty($_SESSION['flash'])): ?>
showToast(
    <?= json_encode($_SESSION['flash']['msg']) ?>,
    <?= json_encode($_SESSION['flash']['type'] ?? 'ts') ?>
);
<?php unset($_SESSION['flash']); endif; ?>

// ── Close mobile sidebar when a nav link is clicked ─────────
document.querySelectorAll('.sb-nav a').forEach(function(link) {
    link.addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('open');
    });
});

// ── Close any open modal when clicking the dark backdrop ─────
document.querySelectorAll('.moverlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('open');
        }
    });
});
</script>

</body>
</html>