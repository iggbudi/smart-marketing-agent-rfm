<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Require super admin access
requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_general':
                foreach ($_POST['settings'] as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                $success = "General settings updated successfully!";
                auth()->logActivity($_SESSION['user_id'], 'settings_update', "Updated general settings");
                break;
                
            case 'update_email':
                foreach ($_POST['email'] as $key => $value) {
                    $setting_key = 'email_' . $key;
                    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$setting_key, $value, $value]);
                }
                $success = "Email settings updated successfully!";
                auth()->logActivity($_SESSION['user_id'], 'email_settings_update', "Updated email settings");
                break;
                
            case 'update_security':
                foreach ($_POST['security'] as $key => $value) {
                    $setting_key = 'security_' . $key;
                    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$setting_key, $value, $value]);
                }
                $success = "Security settings updated successfully!";
                auth()->logActivity($_SESSION['user_id'], 'security_settings_update', "Updated security settings");
                break;
                
            case 'backup_database':
                // In a real application, you would implement actual backup functionality
                $backup_name = 'smart_marketing_backup_' . date('Y-m-d_H-i-s') . '.sql';
                $success = "Database backup initiated: {$backup_name}";
                auth()->logActivity($_SESSION['user_id'], 'database_backup', "Database backup created: {$backup_name}");
                break;
                
            case 'clear_cache':
                // In a real application, you would clear actual cache
                $success = "System cache cleared successfully!";
                auth()->logActivity($_SESSION['user_id'], 'cache_clear', "System cache cleared");
                break;
        }
    }
}

// Get current settings
$settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

// System information
$system_info = [
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database_version' => $db->query("SELECT VERSION()")->fetchColumn(),
    'disk_space' => disk_free_space('.') ? round(disk_free_space('.') / 1024 / 1024 / 1024, 2) . ' GB' : 'Unknown',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time') . 's',
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size')
];

// Get platform statistics
$platform_stats = [
    'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_businesses' => $db->query("SELECT COUNT(*) FROM businesses")->fetchColumn(),
    'total_customers' => $db->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
    'total_transactions' => $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
    'database_size' => $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'db_size' FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .system-info-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-cog me-2"></i> System Settings</h2>
            <div class="btn-group">
                <button class="btn btn-outline-success" onclick="backupDatabase()">
                            <i class="fas fa-download me-2"></i> Backup Database
                        </button>
                        <button class="btn btn-outline-warning" onclick="clearCache()">
                            <i class="fas fa-broom me-2"></i> Clear Cache
                        </button>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Settings Tabs -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <ul class="nav nav-pills card-header-pills" id="settingsTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="general-tab" data-bs-toggle="pill" data-bs-target="#general" type="button" role="tab">
                                            <i class="fas fa-cogs me-2"></i> General
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="email-tab" data-bs-toggle="pill" data-bs-target="#email" type="button" role="tab">
                                            <i class="fas fa-envelope me-2"></i> Email
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab">
                                            <i class="fas fa-shield-alt me-2"></i> Security
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content" id="settingsTabContent">
                                    <!-- General Settings -->
                                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_general">
                                            <div class="mb-3">
                                                <label class="form-label">Platform Name</label>
                                                <input type="text" class="form-control" name="settings[platform_name]" 
                                                       value="<?= $settings['platform_name'] ?? 'Smart Marketing Agent' ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Platform Description</label>
                                                <textarea class="form-control" name="settings[platform_description]" rows="3"><?= $settings['platform_description'] ?? 'RFM Analysis Platform for Indonesian UMKM' ?></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Contact Email</label>
                                                <input type="email" class="form-control" name="settings[contact_email]" 
                                                       value="<?= $settings['contact_email'] ?? 'admin@smartmarketing.local' ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Default Language</label>
                                                <select class="form-select" name="settings[default_language]">
                                                    <option value="id" <?= ($settings['default_language'] ?? 'id') == 'id' ? 'selected' : '' ?>>Bahasa Indonesia</option>
                                                    <option value="en" <?= ($settings['default_language'] ?? 'id') == 'en' ? 'selected' : '' ?>>English</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Timezone</label>
                                                <select class="form-select" name="settings[timezone]">
                                                    <option value="Asia/Jakarta" <?= ($settings['timezone'] ?? 'Asia/Jakarta') == 'Asia/Jakarta' ? 'selected' : '' ?>>Asia/Jakarta</option>
                                                    <option value="Asia/Makassar" <?= ($settings['timezone'] ?? 'Asia/Jakarta') == 'Asia/Makassar' ? 'selected' : '' ?>>Asia/Makassar</option>
                                                    <option value="Asia/Jayapura" <?= ($settings['timezone'] ?? 'Asia/Jakarta') == 'Asia/Jayapura' ? 'selected' : '' ?>>Asia/Jayapura</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="settings[maintenance_mode]" 
                                                           value="1" <?= ($settings['maintenance_mode'] ?? 0) ? 'checked' : '' ?>>
                                                    <label class="form-check-label">Maintenance Mode</label>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i> Save General Settings
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Email Settings -->
                                    <div class="tab-pane fade" id="email" role="tabpanel">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_email">
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" name="email[smtp_host]" 
                                                       value="<?= $settings['email_smtp_host'] ?? '' ?>" placeholder="smtp.gmail.com">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Port</label>
                                                <input type="number" class="form-control" name="email[smtp_port]" 
                                                       value="<?= $settings['email_smtp_port'] ?? 587 ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Username</label>
                                                <input type="text" class="form-control" name="email[smtp_username]" 
                                                       value="<?= $settings['email_smtp_username'] ?? '' ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">SMTP Password</label>
                                                <input type="password" class="form-control" name="email[smtp_password]" 
                                                       value="<?= $settings['email_smtp_password'] ?? '' ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">From Email</label>
                                                <input type="email" class="form-control" name="email[from_email]" 
                                                       value="<?= $settings['email_from_email'] ?? '' ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">From Name</label>
                                                <input type="text" class="form-control" name="email[from_name]" 
                                                       value="<?= $settings['email_from_name'] ?? 'Smart Marketing Agent' ?>">
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="email[smtp_secure]" 
                                                           value="1" <?= ($settings['email_smtp_secure'] ?? 1) ? 'checked' : '' ?>>
                                                    <label class="form-check-label">Use TLS/SSL</label>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i> Save Email Settings
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Security Settings -->
                                    <div class="tab-pane fade" id="security" role="tabpanel">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_security">
                                            <div class="mb-3">
                                                <label class="form-label">Session Timeout (minutes)</label>
                                                <input type="number" class="form-control" name="security[session_timeout]" 
                                                       value="<?= $settings['security_session_timeout'] ?? 1440 ?>" min="30" max="10080">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Max Login Attempts</label>
                                                <input type="number" class="form-control" name="security[max_login_attempts]" 
                                                       value="<?= $settings['security_max_login_attempts'] ?? 5 ?>" min="3" max="20">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Login Lockout Duration (minutes)</label>
                                                <input type="number" class="form-control" name="security[lockout_duration]" 
                                                       value="<?= $settings['security_lockout_duration'] ?? 15 ?>" min="5" max="1440">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Password Min Length</label>
                                                <input type="number" class="form-control" name="security[password_min_length]" 
                                                       value="<?= $settings['security_password_min_length'] ?? 8 ?>" min="6" max="50">
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="security[require_strong_password]" 
                                                           value="1" <?= ($settings['security_require_strong_password'] ?? 1) ? 'checked' : '' ?>>
                                                    <label class="form-check-label">Require Strong Password</label>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="security[enable_2fa]" 
                                                           value="1" <?= ($settings['security_enable_2fa'] ?? 0) ? 'checked' : '' ?>>
                                                    <label class="form-check-label">Enable Two-Factor Authentication</label>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="security[log_user_activity]" 
                                                           value="1" <?= ($settings['security_log_user_activity'] ?? 1) ? 'checked' : '' ?>>
                                                    <label class="form-check-label">Log User Activity</label>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i> Save Security Settings
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-server me-2"></i> System Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="system-info-item">
                                    <strong>PHP Version:</strong><br>
                                    <span class="text-muted"><?= $system_info['php_version'] ?></span>
                                </div>
                                <div class="system-info-item">
                                    <strong>Database Version:</strong><br>
                                    <span class="text-muted"><?= $system_info['database_version'] ?></span>
                                </div>
                                <div class="system-info-item">
                                    <strong>Server Software:</strong><br>
                                    <span class="text-muted"><?= $system_info['server_software'] ?></span>
                                </div>
                                <div class="system-info-item">
                                    <strong>Free Disk Space:</strong><br>
                                    <span class="text-muted"><?= $system_info['disk_space'] ?></span>
                                </div>
                                <div class="system-info-item">
                                    <strong>Memory Limit:</strong><br>
                                    <span class="text-muted"><?= $system_info['memory_limit'] ?></span>
                                </div>
                                <div class="system-info-item">
                                    <strong>Upload Max Size:</strong><br>
                                    <span class="text-muted"><?= $system_info['upload_max_filesize'] ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Platform Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="system-info-item">
                                    <strong>Total Users:</strong><br>
                                    <span class="text-primary"><?= number_format($platform_stats['total_users']) ?></span>
                                </div>
                                <div class="system-info-item">
                                    <strong>Total Businesses:</strong><br>
                                    <span class="text-success"><?= number_format($platform_stats['total_businesses']) ?></span>
                                </div>
                                <div class="system-info-item">
                                    <strong>Total Customers:</strong><br>
                                    <span class="text-info"><?= number_format($platform_stats['total_customers']) ?></span>
                                </div>
                                <div class="system-info-item">
                                    <strong>Total Transactions:</strong><br>
                                    <span class="text-warning"><?= number_format($platform_stats['total_transactions']) ?></span>
                                </div>
                                <div class="system-info-item">
                                    <strong>Database Size:</strong><br>
                                    <span class="text-muted"><?= $platform_stats['database_size'] ?> MB</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function backupDatabase() {
            if (confirm('Are you sure you want to create a database backup?')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'backup_database';
                
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function clearCache() {
            if (confirm('Are you sure you want to clear the system cache?')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'clear_cache';
                
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Mobile menu toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                !toggle.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });
    </script>
</body>
</html>
