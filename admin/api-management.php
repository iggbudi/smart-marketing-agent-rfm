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
            case 'clear_logs':
                $days = intval($_POST['days']);
                $stmt = $db->prepare("DELETE FROM api_usage_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$days]);
                $success = "API logs older than {$days} days have been cleared!";
                auth()->logActivity($_SESSION['user_id'], 'api_logs_cleanup', "Cleared API logs older than {$days} days");
                break;
                
            case 'update_settings':
                foreach ($_POST['settings'] as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                $success = "API settings updated successfully!";
                auth()->logActivity($_SESSION['user_id'], 'api_settings_update', "Updated API settings");
                break;
        }
    }
}

// Get API usage statistics
$api_stats = [
    'total_requests' => $db->query("SELECT COUNT(*) FROM api_usage_logs")->fetchColumn(),
    'today_requests' => $db->query("SELECT COUNT(*) FROM api_usage_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'avg_tokens' => $db->query("SELECT AVG(tokens_used) FROM api_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
    'total_cost' => $db->query("SELECT SUM(cost) FROM api_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
    'error_rate' => $db->query("SELECT (COUNT(CASE WHEN status = 'error' THEN 1 END) * 100.0 / COUNT(*)) as error_rate FROM api_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn()
];

// Get recent API usage
$recent_usage = $db->query("
    SELECT endpoint, api_type, status, tokens_used, cost, created_at
    FROM api_usage_logs 
    ORDER BY created_at DESC 
    LIMIT 50
")->fetchAll();

// Get endpoint statistics
$endpoint_stats = $db->query("
    SELECT endpoint, 
           COUNT(*) as requests,
           AVG(tokens_used) as avg_tokens,
           COUNT(CASE WHEN status = 'error' THEN 1 END) as errors
    FROM api_usage_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY endpoint 
    ORDER BY requests DESC
")->fetchAll();

// Get hourly usage for chart
$hourly_usage = $db->query("
    SELECT HOUR(created_at) as hour, COUNT(*) as requests
    FROM api_usage_logs 
    WHERE DATE(created_at) = CURDATE()
    GROUP BY HOUR(created_at)
    ORDER BY hour
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Fill missing hours with 0
$hourly_data = [];
for ($i = 0; $i < 24; $i++) {
    $hourly_data[$i] = $hourly_usage[$i] ?? 0;
}

// Get current settings
$settings = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .stat-card {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .stat-card.today { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card.response { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
        .stat-card.errors { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333; }
        .status-badge-200 { background-color: #28a745; }
        .status-badge-400 { background-color: #ffc107; }
        .status-badge-500 { background-color: #dc3545; }
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
            <h2><i class="fas fa-code me-2"></i> API Management</h2>
                    <div class="btn-group">
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                            <i class="fas fa-trash me-2"></i> Clear Logs
                        </button>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#settingsModal">
                            <i class="fas fa-cog me-2"></i> Settings
                        </button>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- API Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-server fa-2x mb-2"></i>
                                <h4><?= number_format($api_stats['total_requests']) ?></h4>
                                <p class="mb-0">Total Requests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card today">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-day fa-2x mb-2"></i>
                                <h4><?= number_format($api_stats['today_requests']) ?></h4>
                                <p class="mb-0">Today's Requests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card response">
                            <div class="card-body text-center">
                                <i class="fas fa-coins fa-2x mb-2"></i>
                                <h4><?= round($api_stats['avg_tokens'] ?? 0, 0) ?></h4>
                                <p class="mb-0">Avg Tokens/Request</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card errors">
                            <div class="card-body text-center">
                                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                                <h4>$<?= number_format($api_stats['total_cost'] ?? 0, 2) ?></h4>
                                <p class="mb-0">24h Cost</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Error Rate</h5>
                            </div>
                            <div class="card-body text-center">
                                <h3 class="text-<?= ($api_stats['error_rate'] ?? 0) > 10 ? 'danger' : 'success' ?>"><?= round($api_stats['error_rate'] ?? 0, 1) ?>%</h3>
                                <p class="mb-0">24 Hour Error Rate</p>
                            </div>
                        </div>
                    </div>
                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                <h4><?= round($api_stats['error_rate'], 1) ?>%</h4>
                                <p class="mb-0">Error Rate (24h)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> API Usage (Today)</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="hourlyUsageChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i> Top Endpoints (7 days)</h5>
                            </div>
                            <div class="card-body">
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach (array_slice($endpoint_stats, 0, 8) as $endpoint): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <div>
                                            <code class="small"><?= htmlspecialchars($endpoint['endpoint']) ?></code>
                                            <br>
                                            <small class="text-muted"><?= $endpoint['requests'] ?> requests</small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-success"><?= round($endpoint['avg_tokens'], 1) ?> tokens</small>
                                            <?php if ($endpoint['errors'] > 0): ?>
                                            <br><small class="text-danger"><?= $endpoint['errors'] ?> errors</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent API Requests -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent API Requests</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm" id="apiLogsTable">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>API Type</th>
                                        <th>Endpoint</th>
                                        <th>Status</th>
                                        <th>Tokens Used</th>
                                        <th>Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_usage as $log): ?>
                                    <tr>
                                        <td>
                                            <small><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $log['api_type'] == 'openai' ? 'primary' : ($log['api_type'] == 'email' ? 'success' : 'warning') ?>">
                                                <?= strtoupper($log['api_type']) ?>
                                            </span>
                                        </td>
                                        <td><code><?= htmlspecialchars($log['endpoint']) ?></code></td>
                                        <td>
                                            <span class="badge status-badge-<?= $log['status'] == 'success' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($log['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($log['tokens_used']) ?> tokens</td>
                                        <td>$<?= number_format($log['cost'], 4) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <!-- Clear Logs Modal -->
    <div class="modal fade" id="clearLogsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Clear API Logs</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="clear_logs">
                        <div class="mb-3">
                            <label class="form-label">Clear logs older than (days)</label>
                            <select class="form-select" name="days" required>
                                <option value="7">7 days</option>
                                <option value="30" selected>30 days</option>
                                <option value="90">90 days</option>
                                <option value="365">1 year</option>
                            </select>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action cannot be undone. Logs will be permanently deleted.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Clear Logs</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">API Settings</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_settings">
                        <div class="mb-3">
                            <label class="form-label">Rate Limit (requests per minute)</label>
                            <input type="number" class="form-control" name="settings[rate_limit]" 
                                   value="<?= $settings['rate_limit'] ?? 60 ?>" min="1" max="1000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Max Response Time (seconds)</label>
                            <input type="number" class="form-control" name="settings[max_response_time]" 
                                   value="<?= $settings['max_response_time'] ?? 30 ?>" min="1" max="300">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Log Retention (days)</label>
                            <input type="number" class="form-control" name="settings[log_retention_days]" 
                                   value="<?= $settings['log_retention_days'] ?? 90 ?>" min="1" max="365">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="settings[enable_debug]" 
                                       value="1" <?= ($settings['enable_debug'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label">Enable Debug Mode</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#apiLogsTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });

        // Hourly Usage Chart
        const ctx = document.getElementById('hourlyUsageChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($hourly_data)) ?>,
                datasets: [{
                    label: 'API Requests',
                    data: <?= json_encode(array_values($hourly_data)) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Hour of Day'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Requests'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

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
