<div class="sidebar">
    <div class="px-3 py-4">
        <div class="d-flex align-items-center mb-4">
            <i class="fas fa-chart-line fa-2x text-white me-2"></i>
            <h5 class="text-white mb-0">Smart Marketing</h5>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'upload.php' ? 'active' : '' ?>" href="upload.php">
                <i class="fas fa-upload me-2"></i> Upload Data
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : '' ?>" href="customers.php">
                <i class="fas fa-users me-2"></i> Customers
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : '' ?>" href="transactions.php">
                <i class="fas fa-shopping-cart me-2"></i> Transactions
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'analysis.php' ? 'active' : '' ?>" href="analysis.php">
                <i class="fas fa-chart-pie me-2"></i> RFM Analysis
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'ai-content.php' ? 'active' : '' ?>" href="ai-content.php">
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
        </nav>
    </div>
</div>
