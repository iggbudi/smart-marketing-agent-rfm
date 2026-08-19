<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

// Require super admin access
requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

$reports = new \App\Admin\ReportsAdmin($db);

// Rentang tanggal dari request
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // hari pertama bulan ini
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // hari ini

// Tipe laporan
$report_type = $_GET['report_type'] ?? 'users';

// Proses quick range
if (isset($_GET['quick_range'])) {
    switch ($_GET['quick_range']) {
        case 'today':
            $start_date = $end_date = date('Y-m-d');
            break;
        case 'yesterday':
            $start_date = $end_date = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'this_week':
            $start_date = date('Y-m-d', strtotime('monday this week'));
            $end_date = date('Y-m-d');
            break;
        case 'last_week':
            $start_date = date('Y-m-d', strtotime('monday last week'));
            $end_date = date('Y-m-d', strtotime('sunday last week'));
            break;
        case 'this_month':
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-d');
            break;
        case 'last_month':
            $start_date = date('Y-m-01', strtotime('first day of last month'));
            $end_date = date('Y-m-t', strtotime('last day of last month'));
            break;
    }
}

// Data laporan dari slice
$reportData = $reports->reportData($report_type, $start_date, $end_date);

// Statistik ringkas
$summaryStats = [
    'total_records' => count($reportData),
    'date_range'    => $start_date . ' s/d ' . $end_date,
];

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    if (!empty($reportData)) {
        fputcsv($output, array_keys($reportData[0]));
        foreach ($reportData as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
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
            <h2><i class="fas fa-file-alt me-2"></i> Laporan</h2>
            <div class="btn-group">
                <button class="btn btn-success" onclick="exportCSV()">
                    <i class="fas fa-download me-2"></i> Ekspor CSV
                </button>
                <button class="btn btn-primary" onclick="printReport()">
                    <i class="fas fa-print me-2"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <!-- Filter Laporan -->
        <div class="report-filter">
            <form method="GET" id="reportForm">
                <div class="row align-items-end g-2">
                    <div class="col-md-3">
                        <label class="form-label">Jenis Laporan</label>
                        <select name="report_type" class="form-select" onchange="updateReport()">
                            <option value="users" <?= $report_type == 'users' ? 'selected' : '' ?>>Laporan Pengguna</option>
                            <option value="businesses" <?= $report_type == 'businesses' ? 'selected' : '' ?>>Laporan Bisnis</option>
                            <option value="transactions" <?= $report_type == 'transactions' ? 'selected' : '' ?>>Laporan Transaksi</option>
                            <option value="activity" <?= $report_type == 'activity' ? 'selected' : '' ?>>Laporan Aktivitas</option>
                            <option value="rfm" <?= $report_type == 'rfm' ? 'selected' : '' ?>>Laporan Analisis RFM</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Rentang Cepat</label>
                        <select name="quick_range" class="form-select" onchange="updateDateRange()">
                            <option value="">Pilih Rentang</option>
                            <?php foreach ($reports->dateRangeOptions() as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= ($_GET['quick_range'] ?? '') == $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> Buat Laporan
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                            <i class="fas fa-undo me-2"></i> Atur Ulang
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Ringkasan -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="kpi-card">
                    <span class="kpi-icon teal"><i class="fas fa-list"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$summaryStats['total_records']) ?></div>
                        <div class="kpi-label">Total Data</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="kpi-card">
                    <span class="kpi-icon amber"><i class="fas fa-calendar"></i></span>
                    <div>
                        <div class="kpi-value" style="font-size:1rem;"><?= date('d/m/Y', strtotime($start_date)) ?> – <?= date('d/m/Y', strtotime($end_date)) ?></div>
                        <div class="kpi-label">Rentang Tanggal</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Laporan -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2"></i>
                    Data Laporan <?= ucfirst(htmlspecialchars($report_type)) ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reportData)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada data untuk kriteria yang dipilih</h5>
                    <p class="text-muted">Coba ubah rentang tanggal atau jenis laporan.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="reportTable">
                        <thead class="table-dark">
                            <tr>
                                <?php foreach (array_keys($reportData[0]) as $column): ?>
                                <th><?= ucwords(str_replace('_', ' ', htmlspecialchars($column))) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <?php foreach ($row as $key => $value): ?>
                                <td>
                                    <?php if (strpos($key, 'amount') !== false): ?>
                                        Rp <?= number_format((float)$value, 0, ',', '.') ?>
                                    <?php elseif (strpos($key, 'date') !== false): ?>
                                        <?= date('d/m/Y', strtotime($value)) ?>
                                    <?php elseif (is_numeric($value) && strpos($key, 'score') !== false): ?>
                                        <?= number_format((float)$value, 2) ?>
                                    <?php elseif (is_numeric($value)): ?>
                                        <?= number_format($value) ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string)$value) ?>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#reportTable').DataTable({
                pageLength: 25,
                responsive: true,
                order: [[0, 'desc']],
                dom: 'Bfrtip',
                language: {
                    search: "Cari data:",
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                    paginate: { first: "Pertama", last: "Terakhir", next: "Berikut", previous: "Sebelum" }
                }
            });
        });

        function updateReport() {
            document.getElementById('reportForm').submit();
        }

        function updateDateRange() {
            const quickRange = document.querySelector('select[name="quick_range"]').value;
            if (quickRange && quickRange !== 'custom') {
                document.getElementById('reportForm').submit();
            }
        }

        function resetFilters() {
            window.location.href = 'reports.php';
        }

        function exportCSV() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('export', 'csv');
            window.location.href = currentUrl.toString();
        }

        function printReport() {
            window.print();
        }

        // Print styles
        const printStyles = `
            <style media="print">
                .sidebar, .btn, .report-filter { display: none !important; }
                .col-md-10 { width: 100% !important; }
                body { font-size: 12px; }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', printStyles);

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
