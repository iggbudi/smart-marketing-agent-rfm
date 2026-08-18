<?php
// Sidebar tunggal (user + admin) — disesuaikan dengan role session.
$navRole = $_SESSION['user_role'] ?? '';
$navIsAdmin = ($navRole === 'super_admin');
$navPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="px-3 py-4">
        <div class="d-flex align-items-center mb-4">
            <i class="fas <?= $navIsAdmin ? 'fa-shield-alt' : 'fa-chart-line' ?> fa-2x text-white me-2"></i>
            <h5 class="text-white mb-0"><?= $navIsAdmin ? 'Admin Panel' : 'Smart Marketing' ?></h5>
        </div>

        <nav class="nav flex-column">
            <?php if ($navIsAdmin): ?>
                <a class="nav-link <?= $navPage == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
                <a class="nav-link <?= $navPage == 'users.php' ? 'active' : '' ?>" href="users.php">
                    <i class="fas fa-users me-2"></i> Users
                </a>
                <a class="nav-link <?= $navPage == 'businesses.php' ? 'active' : '' ?>" href="businesses.php">
                    <i class="fas fa-building me-2"></i> Businesses
                </a>
                <a class="nav-link <?= $navPage == 'analytics.php' ? 'active' : '' ?>" href="analytics.php">
                    <i class="fas fa-chart-line me-2"></i> Analytics
                </a>
                <a class="nav-link <?= $navPage == 'api-management.php' ? 'active' : '' ?>" href="api-management.php">
                    <i class="fas fa-code me-2"></i> API Management
                </a>
                <a class="nav-link <?= $navPage == 'settings.php' ? 'active' : '' ?>" href="settings.php">
                    <i class="fas fa-cog me-2"></i> Settings
                </a>
                <a class="nav-link <?= $navPage == 'reports.php' ? 'active' : '' ?>" href="reports.php">
                    <i class="fas fa-file-alt me-2"></i> Reports
                </a>

                <hr class="text-white">

                <a class="nav-link" href="../dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i> Back to UMKM
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            <?php else: ?>
                <a class="nav-link <?= $navPage == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
                <a class="nav-link <?= $navPage == 'upload.php' ? 'active' : '' ?>" href="upload.php">
                    <i class="fas fa-upload me-2"></i> Upload Data
                </a>
                <a class="nav-link <?= $navPage == 'customers.php' ? 'active' : '' ?>" href="customers.php">
                    <i class="fas fa-users me-2"></i> Customers
                </a>
                <a class="nav-link <?= $navPage == 'transactions.php' ? 'active' : '' ?>" href="transactions.php">
                    <i class="fas fa-shopping-cart me-2"></i> Transactions
                </a>
                <a class="nav-link <?= $navPage == 'analysis.php' ? 'active' : '' ?>" href="analysis.php">
                    <i class="fas fa-chart-pie me-2"></i> RFM Analysis
                </a>
                <a class="nav-link <?= $navPage == 'ai-content.php' ? 'active' : '' ?>" href="ai-content.php">
                    <i class="fas fa-magic me-2"></i> AI Content
                </a>

                <hr class="text-white">

                <div class="px-3 py-2">
                    <small class="text-white-50">Business</small>
                    <div class="text-white">
                        <i class="fas fa-store me-1"></i>
                        <?= htmlspecialchars(isset($business['name']) ? $business['name'] : 'No Business') ?>
                    </div>
                </div>

                <div class="px-3 py-2">
                    <small class="text-white-50">User</small>
                    <div class="text-white">
                        <i class="fas fa-user me-1"></i>
                        <?= htmlspecialchars(isset($user['full_name']) ? $user['full_name'] : 'User') ?>
                    </div>
                </div>

                <a class="nav-link" href="profile.php">
                    <i class="fas fa-user-edit me-2"></i> Profile
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            <?php endif; ?>
        </nav>
    </div>
</div>
