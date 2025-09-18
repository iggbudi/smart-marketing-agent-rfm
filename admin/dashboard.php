<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Smart Marketing Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        }
        .stat-card.users { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card.businesses { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
        .stat-card.customers { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333; }
        .stat-card.transactions { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
</head>
<body>
    <?php
    require_once '../config/database.php';
    require_once '../config/auth.php';
    
    // Require super admin access
    requireAuth(['super_admin']);
    
    $user = getCurrentUser();
    $db = getDB();
    
    // Get platform statistics
    $stats = [];
    
    // Total pengrajin batik terdaftar
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'umkm_owner' AND is_active = 1");
    $stats['total_umkm'] = $stmt->fetch()['total'];
    
    // Total businesses
    $stmt = $db->query("SELECT COUNT(*) as total FROM businesses");
    $stats['total_businesses'] = $stmt->fetch()['total'];
    
    // Total customers across all businesses
    $stmt = $db->query("SELECT COUNT(*) as total FROM customers");
    $stats['total_customers'] = $stmt->fetch()['total'];
    
    // Total transactions
    $stmt = $db->query("SELECT COUNT(*) as total FROM transactions");
    $stats['total_transactions'] = $stmt->fetch()['total'];
    
    // Total revenue
    $stmt = $db->query("SELECT SUM(amount) as total FROM transactions");
    $stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;
    
    // Active users today (logged in today)
    $stmt = $db->query("SELECT COUNT(DISTINCT user_id) as total FROM activity_logs WHERE DATE(created_at) = CURDATE()");
    $stats['active_today'] = $stmt->fetch()['total'];
    
    // API usage today
    $stmt = $db->query("SELECT api_type, COUNT(*) as count FROM api_usage_logs WHERE DATE(created_at) = CURDATE() GROUP BY api_type");
    $api_usage = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Recent activities
    $stmt = $db->query("
        SELECT a.*, u.full_name, b.name as business_name 
        FROM activity_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        LEFT JOIN businesses b ON a.business_id = b.id 
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll();
    
    // Business growth (last 7 days)
    $stmt = $db->query("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM businesses 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date
    ");
    $business_growth = $stmt->fetchAll();
    ?>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-tachometer-alt me-2"></i> Platform Overview</h2>
            <div class="text-muted">
                <i class="fas fa-calendar me-2"></i><?= date('l, d F Y') ?>
            </div>
        </div>
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-store fa-2x text-primary mb-3"></i>
                            <h3 class="text-primary"><?= $stats['total_umkm'] ?></h3>
                            <p class="text-muted mb-0">Total UMKM Terdaftar</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-users fa-2x text-success mb-3"></i>
                            <h3 class="text-success"><?= number_format($stats['total_customers']) ?></h3>
                            <p class="text-muted mb-0">Total Customers</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-shopping-cart fa-2x text-warning mb-3"></i>
                            <h3 class="text-warning"><?= number_format($stats['total_transactions']) ?></h3>
                            <p class="text-muted mb-0">Total Transaksi</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-money-bill fa-2x text-info mb-3"></i>
                            <h3 class="text-info">Rp <?= number_format($stats['total_revenue'] / 1000000, 1) ?>M</h3>
                            <p class="text-muted mb-0">Total Revenue</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Usage & Activity -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie"></i> API Usage Hari Ini</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($api_usage)): ?>
                                <p class="text-muted text-center">Belum ada aktivitas API hari ini</p>
                            <?php else: ?>
                                <canvas id="apiUsageChart" width="400" height="200"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-line"></i> Platform Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h4 class="text-primary"><?= $stats['active_today'] ?></h4>
                                        <small class="text-muted">Active Users Hari Ini</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success"><?= $stats['total_businesses'] ?></h4>
                                    <small class="text-muted">Total Bisnis</small>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <small>OpenAI API:</small>
                                <span class="badge bg-primary"><?= $api_usage['openai'] ?? 0 ?> calls</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small>Email API:</small>
                                <span class="badge bg-success"><?= $api_usage['email'] ?? 0 ?> sent</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Aktivitas Terbaru</h5>
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
                                        <?php foreach ($recent_activities as $activity): ?>
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

        // API Usage Chart
        <?php if (!empty($api_usage)): ?>
        const apiCtx = document.getElementById('apiUsageChart').getContext('2d');
        new Chart(apiCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($api_usage)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($api_usage)) ?>,
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
