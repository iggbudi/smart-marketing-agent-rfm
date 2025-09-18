<div class="sidebar">
    <div class="px-3 py-4">
        <div class="d-flex align-items-center mb-4">
            <i class="fas fa-shield-alt fa-2x text-white me-2"></i>
            <h5 class="text-white mb-0">Admin Panel</h5>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>" href="users.php">
                <i class="fas fa-users me-2"></i> Users
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'businesses.php' ? 'active' : '' ?>" href="businesses.php">
                <i class="fas fa-building me-2"></i> Businesses
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : '' ?>" href="analytics.php">
                <i class="fas fa-chart-line me-2"></i> Analytics
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'api-management.php' ? 'active' : '' ?>" href="api-management.php">
                <i class="fas fa-code me-2"></i> API Management
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" href="settings.php">
                <i class="fas fa-cog me-2"></i> Settings
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" href="reports.php">
                <i class="fas fa-file-alt me-2"></i> Reports
            </a>
            
            <hr class="text-white">
            
            <a class="nav-link" href="../dashboard.php">
                <i class="fas fa-arrow-left me-2"></i> Back to UMKM
            </a>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </nav>
    </div>
</div>
