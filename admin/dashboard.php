<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

// Require super admin access
requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

// Data platform dari slice (bukan query inline di halaman)
$platform = new \App\Admin\PlatformStats($db);
$kpis = $platform->getKpis();
$apiUsage = $platform->getApiUsageToday();
$recentActivities = $platform->getRecentActivities(10);
$businessGrowth = $platform->getBusinessGrowth(7);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Smart Marketing Agent</title>
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
            <h2><i class="fas fa-tachometer-alt me-2"></i> Ringkasan Platform</h2>
            <div class="text-muted">
                <i class="fas fa-calendar me-2"></i><?= date('l, d F Y') ?>
            </div>
        </div>

        <!-- KPI Card Row (6 kartu, desktop-first) -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon teal"><i class="fas fa-store"></i></span>
                    <div>
                        <div class="kpi-value"><?= (int)$kpis['total_umkm'] ?></div>
                        <div class="kpi-label">Total UMKM</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon amber"><i class="fas fa-building"></i></span>
                    <div>
                        <div class="kpi-value"><?= (int)$kpis['total_businesses'] ?></div>
                        <div class="kpi-label">Total Bisnis</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon green"><i class="fas fa-users"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$kpis['total_customers']) ?></div>
                        <div class="kpi-label">Total Pelanggan</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon blue"><i class="fas fa-shopping-cart"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$kpis['total_transactions']) ?></div>
                        <div class="kpi-label">Total Transaksi</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon amber"><i class="fas fa-money-bill-wave"></i></span>
                    <div>
                        <div class="kpi-value">Rp <?= number_format((float)$kpis['total_revenue'] / 1000000, 1) ?>M</div>
                        <div class="kpi-label">Total Omzet</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="kpi-card">
                    <span class="kpi-icon gray"><i class="fas fa-user-check"></i></span>
                    <div>
                        <div class="kpi-value"><?= (int)$kpis['active_today'] ?></div>
                        <div class="kpi-label">User Aktif Hari Ini</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts: Business Growth + API Usage -->
        <div class="row g-3 mb-4">
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Pertumbuhan Bisnis (7 Hari)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($businessGrowth)): ?>
                            <p class="text-muted text-center mb-0">Belum ada bisnis baru 7 hari terakhir.</p>
                        <?php else: ?>
                            <div class="chart-container"><canvas id="growthChart"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> API Usage Hari Ini</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($apiUsage)): ?>
                            <p class="text-muted text-center mb-0">Belum ada aktivitas API hari ini.</p>
                        <?php else: ?>
                            <div class="chart-container"><canvas id="apiUsageChart"></canvas></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Aktivitas Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>User</th>
                                        <th>Bisnis</th>
                                        <th>Aktivitas</th>
                                        <th>Deskripsi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td>
                                            <small><?= date('H:i', strtotime($activity['created_at'])) ?></small><br>
                                            <small class="text-muted"><?= date('d/m', strtotime($activity['created_at'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($activity['full_name'] ?? 'System') ?></td>
                                        <td>
                                            <?php if ($activity['business_name']): ?>
                                                <small class="text-primary"><?= htmlspecialchars($activity['business_name']) ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($activity['action']) ?></span>
                                        </td>
                                        <td>
                                            <small><?= htmlspecialchars($activity['description']) ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        // Business Growth Chart (bar)
        <?php if (!empty($businessGrowth)): ?>
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        new Chart(growthCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($businessGrowth, 'date')) ?>,
                datasets: [{
                    label: 'Bisnis baru',
                    data: <?= json_encode(array_map('intval', array_column($businessGrowth, 'count'))) ?>,
                    backgroundColor: 'rgba(15, 118, 110, .75)',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
        <?php endif; ?>

        // API Usage Chart (doughnut)
        <?php if (!empty($apiUsage)): ?>
        const apiCtx = document.getElementById('apiUsageChart').getContext('2d');
        new Chart(apiCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($apiUsage)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($apiUsage)) ?>,
                    backgroundColor: ['#0f766e', '#059669', '#f59e0b', '#dc2626']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
