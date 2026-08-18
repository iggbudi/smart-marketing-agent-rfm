<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

// Require super admin access
requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

$admin = new \App\Admin\AnalyticsAdmin($db);

// Data analytics dari slice (bukan query inline)
$analytics = [
    'platform'           => $admin->platform(),
    'user_trends'        => $admin->userTrends(30),
    'business_activity'  => $admin->businessActivity(10),
    'transaction_trends' => $admin->transactionTrends(30),
    'rfm_segments'       => $admin->rfmSegments(),
    'api_usage'          => $admin->apiUsage(7, 10),
    'recent_activities'  => $admin->recentActivities(20),
];
$userGrowth = $admin->userGrowthRate();

// Siapkan data chart
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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analitik Platform - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <h2><i class="fas fa-chart-line me-2"></i> Analitik Platform</h2>
            <div class="btn-group">
                <button class="btn btn-outline-primary" onclick="exportData()">
                    <i class="fas fa-download me-2"></i> Ekspor Data
                </button>
                <button class="btn btn-outline-success" onclick="refreshData()">
                    <i class="fas fa-sync-alt me-2"></i> Muat Ulang
                </button>
            </div>
        </div>

        <!-- Metrik Kunci: KPI card identitas -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon teal"><i class="fas fa-users"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$analytics['platform']['total_users']) ?></div>
                        <div class="kpi-label">Total Pengguna</div>
                        <small class="text-muted"><?= $userGrowth >= 0 ? '+' : '' ?><?= $userGrowth ?>% vs bulan lalu</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon amber"><i class="fas fa-building"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$analytics['platform']['total_businesses']) ?></div>
                        <div class="kpi-label">Total Bisnis</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon green"><i class="fas fa-user-friends"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$analytics['platform']['total_customers']) ?></div>
                        <div class="kpi-label">Total Pelanggan</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon blue"><i class="fas fa-shopping-cart"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$analytics['platform']['total_transactions']) ?></div>
                        <div class="kpi-label">Total Transaksi</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon amber"><i class="fas fa-money-bill-wave"></i></span>
                    <div>
                        <div class="kpi-value">Rp <?= number_format((float)$analytics['platform']['total_revenue'], 0, ',', '.') ?></div>
                        <div class="kpi-label">Total Omzet</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon gray"><i class="fas fa-wifi"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$analytics['platform']['active_sessions']) ?></div>
                        <div class="kpi-label">Sesi Aktif</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Tren Registrasi User</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="userTrendsChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i> Tren Transaksi &amp; Omzet</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="transactionTrendsChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-building me-2"></i> Bisnis Terbaik</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Bisnis</th>
                                        <th>Pelanggan</th>
                                        <th>Transaksi</th>
                                        <th>Omzet</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analytics['business_activity'] as $business): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($business['business_name']) ?></td>
                                        <td><span class="badge bg-primary"><?= (int)$business['customers'] ?></span></td>
                                        <td><span class="badge bg-info"><?= (int)$business['transactions'] ?></span></td>
                                        <td><span class="text-success">Rp <?= number_format((float)$business['revenue'], 0, ',', '.') ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> Distribusi Segmen RFM</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="rfmChart"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Usage & Activities Row -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-code me-2"></i> Penggunaan API (7 Hari)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Endpoint</th>
                                        <th>Penggunaan</th>
                                        <th>Rata-rata Biaya</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analytics['api_usage'] as $api): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($api['endpoint']) ?></code></td>
                                        <td><span class="badge bg-success"><?= (int)$api['usage_count'] ?></span></td>
                                        <td>$<?= number_format((float)($api['avg_response_time'] ?? 0), 4) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Aktivitas Terbaru</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($analytics['recent_activities'] as $activity): ?>
                        <div class="activity-item <?= ($activity['action_type'] ?? 'unknown') == 'user_deletion' ? 'danger' : (($activity['action_type'] ?? 'unknown') == 'login' ? 'success' : '') ?>">
                            <strong><?= htmlspecialchars($activity['user_name']) ?></strong>
                            <small class="text-muted"><?= ucfirst(str_replace('_', ' ', $activity['action_type'] ?? 'unknown')) ?></small>
                            <br>
                            <small><?= htmlspecialchars($activity['description'] ?? 'Tidak ada deskripsi') ?></small>
                            <br>
                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($activity['created_at'])) ?></small>
                        </div>
                        <?php endforeach; ?>
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
                    label: 'User Baru',
                    data: <?= json_encode($user_chart_counts) ?>,
                    borderColor: 'rgb(15, 118, 110)',
                    backgroundColor: 'rgba(15, 118, 110, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
            }
        });

        // Transaction Trends Chart
        const transactionCtx = document.getElementById('transactionTrendsChart').getContext('2d');
        new Chart(transactionCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($transaction_chart_dates) ?>,
                datasets: [{
                    label: 'Transaksi',
                    data: <?= json_encode($transaction_chart_counts) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Omzet (Rp)',
                    data: <?= json_encode($revenue_chart_data) ?>,
                    type: 'line',
                    backgroundColor: 'rgba(245, 158, 11, 0.4)',
                    borderColor: 'rgb(245, 158, 11)',
                    borderWidth: 2,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { type: 'linear', display: true, position: 'left', beginAtZero: true },
                    y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }
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
                    backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#6b7280', '#6366f1']
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
            const exportData = {
                generated_at: new Date().toISOString(),
                platform_stats: <?= json_encode($analytics['platform']) ?>,
                business_activity: <?= json_encode($analytics['business_activity']) ?>,
                rfm_segments: <?= json_encode($analytics['rfm_segments']) ?>
            };
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
