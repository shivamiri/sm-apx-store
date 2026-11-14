<?php
/**
 * Task 5.2: admin dashboard on the shared layout.
 * The gatekeeper, sidebar and metric cards all come from
 * admin/common/header.php. Task 5.3 replaces the queue placeholder below
 * with the real pending-submissions table (keep its id="queue").
 */
$admin_page = 'dashboard';
$page_title = 'Dashboard';
require_once __DIR__ . '/common/header.php';
?>
      <section class="admin-card" id="queue" aria-labelledby="dashboardTitle">
        <p class="admin-eyebrow">AUTHENTICATED</p>
        <h1 id="dashboardTitle">Admin Dashboard</h1>
        <p class="admin-intro">Signed in as <strong><?php echo adminEscape($_SESSION['admin_username'] ?? 'Admin'); ?></strong>. Admin sign-out lives in the sidebar and never touches your store account.</p>
        <div class="admin-placeholder">
          <strong>Task 5.2 layout ready</strong>
          <p>The pending-submissions queue with Approve / Reject actions arrives in Task 5.3.</p>
        </div>
        <a class="admin-back" href="../index.php">Back to store</a>
      </section>
<?php require_once __DIR__ . '/common/bottom.php';