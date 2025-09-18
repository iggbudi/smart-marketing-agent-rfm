<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Require super admin access
requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

// Get date range from request
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Generate report data based on type
$report_type = $_GET['report_type'] ?? 'users';

// Helper function to get date range options
function getDateRangeOptions() {
    return [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'this_week' => 'This Week',
        'last_week' => 'Last Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_quarter' => 'This Quarter',
        'this_year' => 'This Year',
        'custom' => 'Custom Range'
    ];
}

// Quick date range processing
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

// Generate reports based on type
$report_data = [];
switch ($report_type) {
    case 'users':
        $stmt = $db->prepare("
            SELECT 
                DATE(u.created_at) as date,
                COUNT(*) as count,
                u.role,
                GROUP_CONCAT(u.full_name) as usernames
            FROM users u 
            WHERE DATE(u.created_at) BETWEEN ? AND ? 
            GROUP BY DATE(u.created_at), u.role
            ORDER BY date DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'businesses':
        $stmt = $db->prepare("
            SELECT 
                DATE(b.created_at) as date,
                COUNT(*) as count,
                b.category,
                GROUP_CONCAT(b.name) as business_names
            FROM businesses b 
            WHERE DATE(b.created_at) BETWEEN ? AND ? 
            GROUP BY DATE(b.created_at), b.category
            ORDER BY date DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'transactions':
        $stmt = $db->prepare("
            SELECT 
                DATE(t.transaction_date) as date,
                COUNT(*) as transaction_count,
                SUM(t.amount) as total_amount,
                AVG(t.amount) as avg_amount,
                b.name as business_name
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            JOIN businesses b ON c.business_id = b.id
            WHERE DATE(t.transaction_date) BETWEEN ? AND ? 
            GROUP BY DATE(t.transaction_date), b.id
            ORDER BY date DESC, total_amount DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'activity':
        $stmt = $db->prepare("
            SELECT 
                DATE(al.created_at) as date,
                al.action,
                COUNT(*) as count,
                u.full_name,
                u.role
            FROM activity_logs al
            JOIN users u ON al.user_id = u.id
            WHERE DATE(al.created_at) BETWEEN ? AND ? 
            GROUP BY DATE(al.created_at), al.action, u.id
            ORDER BY date DESC, count DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'rfm':
        $stmt = $db->prepare("
            SELECT 
                DATE(r.created_at) as date,
                r.rfm_segment,
                COUNT(*) as customer_count,
                AVG(r.recency_score) as avg_recency,
                AVG(r.frequency_score) as avg_frequency,
                AVG(r.monetary_score) as avg_monetary,
                b.name as business_name
            FROM rfm_analysis r
            JOIN customers c ON r.customer_id = c.id
            JOIN businesses b ON c.business_id = b.id
            WHERE DATE(r.created_at) BETWEEN ? AND ? 
            GROUP BY DATE(r.created_at), r.rfm_segment, b.id
            ORDER BY date DESC, customer_count DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}

// Summary statistics
$summary_stats = [
    'total_records' => count($report_data),
    'date_range' => $start_date . ' to ' . $end_date
];

// Export functionality
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write CSV headers
    if (!empty($report_data)) {
        fputcsv($output, array_keys($report_data[0]));
        
        // Write data rows
        foreach ($report_data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .report-filter {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .report-type-btn {
            margin: 2px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            padding: 1rem;
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
            <h2><i class="fas fa-file-alt me-2"></i> Reports</h2>
            <div class="btn-group">
                <button class="btn btn-success" onclick="exportCSV()">
                    <i class="fas fa-download me-2"></i> Export CSV
                </button>
                <button class="btn btn-primary" onclick="printReport()">
                    <i class="fas fa-print me-2"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Report Filters -->
                <div class="report-filter">
                    <form method="GET" id="reportForm">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Report Type</label>
                                <select name="report_type" class="form-select" onchange="updateReport()">
                                    <option value="users" <?= $report_type == 'users' ? 'selected' : '' ?>>Users Report</option>
                                    <option value="businesses" <?= $report_type == 'businesses' ? 'selected' : '' ?>>Businesses Report</option>
                                    <option value="transactions" <?= $report_type == 'transactions' ? 'selected' : '' ?>>Transactions Report</option>
                                    <option value="activity" <?= $report_type == 'activity' ? 'selected' : '' ?>>Activity Report</option>
                                    <option value="rfm" <?= $report_type == 'rfm' ? 'selected' : '' ?>>RFM Analysis Report</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Quick Range</label>
                                <select name="quick_range" class="form-select" onchange="updateDateRange()">
                                    <option value="">Select Range</option>
                                    <?php foreach (getDateRangeOptions() as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($_GET['quick_range'] ?? '') == $value ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i> Generate Report
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                    <i class="fas fa-undo me-2"></i> Reset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Summary Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="stat-card">
                            <h5><i class="fas fa-list me-2"></i> Total Records</h5>
                            <h3><?= number_format($summary_stats['total_records']) ?></h3>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card">
                            <h5><i class="fas fa-calendar me-2"></i> Date Range</h5>
                            <h6><?= date('M d, Y', strtotime($start_date)) ?> - <?= date('M d, Y', strtotime($end_date)) ?></h6>
                        </div>
                    </div>
                </div>

                <!-- Report Data -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i> 
                            <?= ucfirst($report_type) ?> Report Data
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($report_data)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No data found for the selected criteria</h5>
                                <p class="text-muted">Try adjusting your date range or report type.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="reportTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <?php if (!empty($report_data)): ?>
                                                <?php foreach (array_keys($report_data[0]) as $column): ?>
                                                    <th><?= ucwords(str_replace('_', ' ', $column)) ?></th>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $key => $value): ?>
                                                    <td>
                                                        <?php if (strpos($key, 'amount') !== false): ?>
                                                            Rp <?= number_format($value, 0, ',', '.') ?>
                                                        <?php elseif (strpos($key, 'date') !== false): ?>
                                                            <?= date('M d, Y', strtotime($value)) ?>
                                                        <?php elseif (is_numeric($value) && strpos($key, 'score') !== false): ?>
                                                            <?= number_format($value, 2) ?>
                                                        <?php elseif (is_numeric($value)): ?>
                                                            <?= number_format($value) ?>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($value) ?>
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
                    search: "Search records:",
                    lengthMenu: "Show _MENU_ records per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ records",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
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
                .card { box-shadow: none; border: 1px solid #ddd; }
                .table { font-size: 11px; }
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
