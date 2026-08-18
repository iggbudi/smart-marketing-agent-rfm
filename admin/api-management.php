<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

// Require super admin access
requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

$apiAdmin = new \App\Admin\ApiManagementAdmin($db);

// Handle form submissions (POST — dipertahankan apa adanya)
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'clear_logs':
                $days = intval($_POST['days']);
                $stmt = $db->prepare("DELETE FROM api_usage_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$days]);
                $success = "Log API lebih tua dari {$days} hari telah dihapus!";
                auth()->logActivity($_SESSION['user_id'], 'api_logs_cleanup', "Cleared API logs older than {$days} days");
                break;

            case 'update_settings':
                foreach ($_POST['settings'] as $key => $value) {
                    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                $success = "Pengaturan API berhasil diperbarui!";
                auth()->logActivity($_SESSION['user_id'], 'api_settings_update', "Updated API settings");
                break;
        }
    }
}

// Data baca dari slice
$apiStats = $apiAdmin->getStats();
$recentUsage = $apiAdmin->recentUsage(50);
$endpointStats = $apiAdmin->endpointStats(7);
$hourlyData = $apiAdmin->hourlyUsageToday();
$settings = $apiAdmin->settings();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen API - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
            <h2><i class="fas fa-code me-2"></i> Manajemen API</h2>
            <div class="btn-group">
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                    <i class="fas fa-trash me-2"></i> Kosongkan Log
                </button>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#settingsModal">
                    <i class="fas fa-cog me-2"></i> Pengaturan
                </button>
            </div>
        </div>

        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistik API: kartu KPI identitas -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon teal"><i class="fas fa-server"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$apiStats['total_requests']) ?></div>
                        <div class="kpi-label">Total Permintaan</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon amber"><i class="fas fa-calendar-day"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$apiStats['today_requests']) ?></div>
                        <div class="kpi-label">Permintaan Hari Ini</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon blue"><i class="fas fa-coins"></i></span>
                    <div>
                        <div class="kpi-value"><?= round((float)$apiStats['avg_tokens'], 0) ?></div>
                        <div class="kpi-label">Rata-rata Token</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon green"><i class="fas fa-dollar-sign"></i></span>
                    <div>
                        <div class="kpi-value">$<?= number_format((float)$apiStats['total_cost'], 2) ?></div>
                        <div class="kpi-label">Biaya 24 Jam</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts & Error Rate -->
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Penggunaan API (Hari Ini)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container"><canvas id="hourlyUsageChart"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Tingkat Error</h5>
                    </div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                        <h2 class="mb-0 text-<?= ($apiStats['error_rate'] ?? 0) > 10 ? 'danger' : 'success' ?>">
                            <?= round((float)$apiStats['error_rate'], 1) ?>%
                        </h2>
                        <p class="mb-0 text-muted">Tingkat Error 24 Jam</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Endpoint & Recent Requests -->
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i> Endpoint Teratas (7 Hari)</h5>
                    </div>
                    <div class="card-body">
                        <div style="max-height: 380px; overflow-y: auto;">
                            <?php foreach (array_slice($endpointStats, 0, 8) as $endpoint): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                <div>
                                    <code class="small"><?= htmlspecialchars($endpoint['endpoint']) ?></code>
                                    <br>
                                    <small class="text-muted"><?= (int)$endpoint['requests'] ?> permintaan</small>
                                </div>
                                <div class="text-end">
                                    <small class="text-success"><?= round((float)$endpoint['avg_tokens'], 1) ?> token</small>
                                    <?php if ((int)$endpoint['errors'] > 0): ?>
                                    <br><small class="text-danger"><?= (int)$endpoint['errors'] ?> error</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Permintaan API Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm" id="apiLogsTable">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Jenis API</th>
                                        <th>Endpoint</th>
                                        <th>Status</th>
                                        <th>Token</th>
                                        <th>Biaya</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsage as $log): ?>
                                    <tr>
                                        <td><small><?= date('H:i:s', strtotime($log['created_at'])) ?></small></td>
                                        <td>
                                            <span class="badge bg-<?= $log['api_type'] == 'openai' ? 'primary' : ($log['api_type'] == 'email' ? 'success' : 'warning') ?>">
                                                <?= strtoupper(htmlspecialchars($log['api_type'])) ?>
                                            </span>
                                        </td>
                                        <td><code><?= htmlspecialchars($log['endpoint']) ?></code></td>
                                        <td>
                                            <span class="badge bg-<?= $log['status'] == 'success' ? 'success' : 'danger' ?>">
                                                <?= ucfirst(htmlspecialchars($log['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format((int)$log['tokens_used']) ?> token</td>
                                        <td>$<?= number_format((float)$log['cost'], 4) ?></td>
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

    <!-- Modal Kosongkan Log -->
    <div class="modal fade" id="clearLogsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Kosongkan Log API</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="clear_logs">
                        <div class="mb-3">
                            <label class="form-label">Hapus log lebih tua dari (hari)</label>
                            <select class="form-select" name="days" required>
                                <option value="7">7 hari</option>
                                <option value="30" selected>30 hari</option>
                                <option value="90">90 hari</option>
                                <option value="365">1 tahun</option>
                            </select>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Tindakan ini tidak dapat dibatalkan. Log akan dihapus permanen.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Kosongkan Log</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Pengaturan -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Pengaturan API</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_settings">
                        <div class="mb-3">
                            <label class="form-label">Batas Permintaan (per menit)</label>
                            <input type="number" class="form-control" name="settings[rate_limit]"
                                   value="<?= htmlspecialchars($settings['rate_limit'] ?? 60) ?>" min="1" max="1000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Waktu Respons Maks (detik)</label>
                            <input type="number" class="form-control" name="settings[max_response_time]"
                                   value="<?= htmlspecialchars($settings['max_response_time'] ?? 30) ?>" min="1" max="300">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Retensi Log (hari)</label>
                            <input type="number" class="form-control" name="settings[log_retention_days]"
                                   value="<?= htmlspecialchars($settings['log_retention_days'] ?? 90) ?>" min="1" max="365">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="settings[enable_debug]"
                                       value="1" <?= ($settings['enable_debug'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label">Aktifkan Mode Debug</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
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
                labels: <?= json_encode(array_keys($hourlyData)) ?>,
                datasets: [{
                    label: 'Permintaan API',
                    data: <?= json_encode(array_values($hourlyData)) ?>,
                    borderColor: 'rgb(15, 118, 110)',
                    backgroundColor: 'rgba(15, 118, 110, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { title: { display: true, text: 'Jam' } },
                    y: { beginAtZero: true, title: { display: true, text: 'Permintaan' } }
                },
                plugins: { legend: { display: false } }
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
