<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Require super admin access
requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

// Get analytics data
$analytics = [
    // Platform Overview
    'platform' => [
        'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_businesses' => $db->query("SELECT COUNT(*) FROM businesses")->fetchColumn(),
        'total_customers' => $db->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
        'total_transactions' => $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
        'total_revenue' => $db->query("SELECT COALESCE(SUM(amount), 0) FROM transactions")->fetchColumn(),
        'active_sessions' => $db->query("SELECT COUNT(*) FROM user_sessions WHERE expires_at > NOW()")->fetchColumn()
    ],
    
    // User Registration Trends (Last 30 days)
    'user_trends' => $db->query("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date
    ")->fetchAll(),
    
    // Business Activity
    'business_activity' => $db->query("
        SELECT b.name as business_name, 
               COUNT(DISTINCT c.id) as customers,
               COUNT(t.id) as transactions,
               COALESCE(SUM(t.amount), 0) as revenue
        FROM businesses b
        LEFT JOIN customers c ON b.id = c.business_id
        LEFT JOIN transactions t ON c.id = t.customer_id
        GROUP BY b.id, b.name
        ORDER BY revenue DESC
        LIMIT 10
    ")->fetchAll(),
    
    // Transaction Trends (Last 30 days)
    'transaction_trends' => $db->query("
        SELECT DATE(transaction_date) as date, 
               COUNT(*) as count,
               SUM(amount) as revenue
        FROM transactions 
        WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(transaction_date) 
        ORDER BY date
    ")->fetchAll(),
    
    // RFM Segment Distribution
    'rfm_segments' => $db->query("
        SELECT rfm_segment, COUNT(*) as count 
        FROM rfm_analysis 
        GROUP BY rfm_segment 
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_KEY_PAIR),
    
    // API Usage Stats
    'api_usage' => $db->query("
        SELECT endpoint, COUNT(*) as usage_count,
               AVG(COALESCE(tokens_used, 0)) as avg_tokens,
               AVG(COALESCE(cost, 0)) as avg_response_time,
               SUM(COALESCE(cost, 0)) as total_cost
        FROM api_usage_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY endpoint 
        ORDER BY usage_count DESC
        LIMIT 10
    ")->fetchAll(),
    
    // Recent Activities
    'recent_activities' => $db->query("
        SELECT al.*, u.full_name as user_name,
               al.action as action_type
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 20
    ")->fetchAll()
];

// Calculate growth rates
$last_month_users = $db->query("
    SELECT COUNT(*) FROM users 
    WHERE created_at >= DATE_SUB(DATE_SUB(NOW(), INTERVAL 30 DAY), INTERVAL 30 DAY)
    AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetchColumn();

$current_month_users = $db->query("
    SELECT COUNT(*) FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetchColumn();

$user_growth = $last_month_users > 0 ? round((($current_month_users - $last_month_users) / $last_month_users) * 100, 1) : 0;

// Prepare chart data
$user_chart_dates = [];
$user_chart_counts = [];
foreach ($analytics['user_trends'] as $trend) {
    $user_chart_dates[] = $trend['date'];
    $user_chart_counts[] = $trend['count'];
}

$transaction_chart_dates = [];
$transaction_chart_counts = [];
$revenue_chart_data = [];
foreach ($analytics['transaction_trends'] as $trend) {
    $transaction_chart_dates[] = $trend['date'];
    $transaction_chart_counts[] = $trend['count'];
    $revenue_chart_data[] = $trend['revenue'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .stat-card {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .stat-card.users { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card.businesses { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
        .stat-card.transactions { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333; }
        .stat-card.revenue { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; }
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .activity-item.warning { border-left-color: #ffc107; }
        .activity-item.danger { border-left-color: #dc3545; }
        .activity-item.success { border-left-color: #198754; }
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
            <h2><i class="fas fa-chart-line me-2"></i> Platform Analytics</h2>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="exportData()">
                            <i class="fas fa-download me-2"></i> Export Data
                        </button>
                        <button class="btn btn-outline-success" onclick="refreshData()">
                            <i class="fas fa-sync-alt me-2"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- Key Metrics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?= number_format($analytics['platform']['total_users']) ?></h4>
                                <p class="mb-1">Total Users</p>
                                <small class="badge bg-light text-dark">
                                    <?= $user_growth >= 0 ? '+' : '' ?><?= $user_growth ?>% vs last month
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card businesses">
                            <div class="card-body text-center">
                                <i class="fas fa-building fa-2x mb-2"></i>
                                <h4><?= number_format($analytics['platform']['total_businesses']) ?></h4>
                                <p class="mb-0">Businesses</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card users">
                            <div class="card-body text-center">
                                <i class="fas fa-user-friends fa-2x mb-2"></i>
                                <h4><?= number_format($analytics['platform']['total_customers']) ?></h4>
                                <p class="mb-0">Customers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card transactions">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                <h4><?= number_format($analytics['platform']['total_transactions']) ?></h4>
                                <p class="mb-0">Transactions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card revenue">
                            <div class="card-body text-center">
                                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                                <h4>Rp <?= number_format($analytics['platform']['total_revenue'], 0, ',', '.') ?></h4>
                                <p class="mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-wifi fa-2x mb-2"></i>
                                <h4><?= number_format($analytics['platform']['active_sessions']) ?></h4>
                                <p class="mb-0">Active Sessions</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> User Registration Trends</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="userTrendsChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Transaction & Revenue Trends</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="transactionTrendsChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tables Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-building me-2"></i> Top Performing Businesses</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Business</th>
                                                <th>Customers</th>
                                                <th>Transactions</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['business_activity'] as $business): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($business['business_name']) ?></td>
                                                <td><span class="badge bg-primary"><?= $business['customers'] ?></span></td>
                                                <td><span class="badge bg-info"><?= $business['transactions'] ?></span></td>
                                                <td><span class="text-success">Rp <?= number_format($business['revenue'], 0, ',', '.') ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> RFM Segment Distribution</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="rfmChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- API Usage & Activities Row -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-code me-2"></i> API Usage (Last 7 Days)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Endpoint</th>
                                                <th>Usage</th>
                                                <th>Avg Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['api_usage'] as $api): ?>
                                            <tr>
                                                <td><code><?= htmlspecialchars($api['endpoint']) ?></code></td>
                                                <td><span class="badge bg-success"><?= $api['usage_count'] ?></span></td>
                                                <td>$<?= number_format($api['avg_response_time'] ?? 0, 4) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Activities</h5>
                            </div>
                            <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($analytics['recent_activities'] as $activity): ?>
                                <div class="activity-item <?= ($activity['action_type'] ?? 'unknown') == 'user_deletion' ? 'danger' : (($activity['action_type'] ?? 'unknown') == 'login' ? 'success' : '') ?>">
                                    <strong><?= htmlspecialchars($activity['user_name']) ?></strong>
                                    <small class="text-muted"><?= ucfirst(str_replace('_', ' ', $activity['action_type'] ?? 'unknown')) ?></small>
                                    <br>
                                    <small><?= htmlspecialchars($activity['description'] ?? 'No description') ?></small>
                                    <br>
                                    <small class="text-muted"><?= date('M j, Y H:i', strtotime($activity['created_at'])) ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // User Registration Trends Chart
        const userCtx = document.getElementById('userTrendsChart').getContext('2d');
        new Chart(userCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($user_chart_dates) ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?= json_encode($user_chart_counts) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Transaction Trends Chart
        const transactionCtx = document.getElementById('transactionTrendsChart').getContext('2d');
        new Chart(transactionCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($transaction_chart_dates) ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?= json_encode($transaction_chart_counts) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Revenue (Rp)',
                    data: <?= json_encode($revenue_chart_data) ?>,
                    type: 'line',
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgb(255, 99, 132)',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // RFM Segment Chart
        const rfmCtx = document.getElementById('rfmChart').getContext('2d');
        new Chart(rfmCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($analytics['rfm_segments'])) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($analytics['rfm_segments'])) ?>,
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        function refreshData() {
            location.reload();
        }

        function exportData() {
            // Create export data
            const exportData = {
                generated_at: new Date().toISOString(),
                platform_stats: <?= json_encode($analytics['platform']) ?>,
                business_activity: <?= json_encode($analytics['business_activity']) ?>,
                rfm_segments: <?= json_encode($analytics['rfm_segments']) ?>
            };
            
            // Download as JSON
            const dataStr = JSON.stringify(exportData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'platform_analytics_' + new Date().toISOString().split('T')[0] + '.json';
            link.click();
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
