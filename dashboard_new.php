<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Require UMKM owner access
requireAuth(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    die('Error: No business associated with your account. Please contact administrator.');
}

// Get statistics for this business only
$stats = [];
$stmt = $db->prepare("SELECT COUNT(*) as total_customers FROM customers WHERE business_id = ?");
$stmt->execute([$business['id']]);
$stats['total_customers'] = $stmt->fetch()['total_customers'];

$stmt = $db->prepare("SELECT COUNT(*) as total_transactions FROM transactions WHERE business_id = ?");
$stmt->execute([$business['id']]);
$stats['total_transactions'] = $stmt->fetch()['total_transactions'];

$stmt = $db->prepare("SELECT SUM(amount) as total_revenue FROM transactions WHERE business_id = ?");
$stmt->execute([$business['id']]);
$stats['total_revenue'] = $stmt->fetch()['total_revenue'] ?? 0;

// Get recent transactions
$stmt = $db->prepare("
    SELECT t.*, c.customer_name 
    FROM transactions t 
    JOIN customers c ON t.customer_id = c.id 
    WHERE t.business_id = ? 
    ORDER BY t.transaction_date DESC 
    LIMIT 10
");
$stmt->execute([$business['id']]);
$recent_transactions = $stmt->fetchAll();

// Get RFM data for charts
$stmt = $db->prepare("
    SELECT rfm_segment, COUNT(*) as count 
    FROM rfm_analysis 
    WHERE business_id = ? 
    GROUP BY rfm_segment
");
$stmt->execute([$business['id']]);
$rfm_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get monthly revenue trend
$stmt = $db->prepare("
    SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(amount) as revenue 
    FROM transactions 
    WHERE business_id = ? AND transaction_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m') 
    ORDER BY month
");
$stmt->execute([$business['id']]);
$revenue_trend = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Marketing Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/user-styles.css" rel="stylesheet">
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
            <h2><i class="fas fa-tachometer-alt me-2"></i> Dashboard</h2>
            <div class="text-muted">
                <i class="fas fa-calendar me-2"></i><?= date('l, d F Y') ?>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card customers">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x mb-3"></i>
                        <h3><?= number_format($stats['total_customers']) ?></h3>
                        <p class="mb-0">Total Customers</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card transactions">
                    <div class="card-body">
                        <i class="fas fa-shopping-cart fa-2x mb-3"></i>
                        <h3><?= number_format($stats['total_transactions']) ?></h3>
                        <p class="mb-0">Total Transactions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card revenue">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign fa-2x mb-3"></i>
                        <h3>Rp <?= number_format($stats['total_revenue'], 0, ',', '.') ?></h3>
                        <p class="mb-0">Total Revenue</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> RFM Segments Distribution</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($rfm_data)): ?>
                            <canvas id="rfmChart"></canvas>
                        <?php else: ?>
                            <p class="text-muted text-center">No RFM data available. Upload customer data first.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Revenue Trend (6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($revenue_trend)): ?>
                            <canvas id="revenueChart"></canvas>
                        <?php else: ?>
                            <p class="text-muted text-center">No revenue data available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-upload me-2"></i> Upload Data Excel</h5>
                    </div>
                    <div class="card-body">
                        <p>Pilih file Excel (.xlsx)</p>
                        <form action="upload.php" method="post" enctype="multipart/form-data">
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <p class="mb-2">Drag & drop file atau klik untuk pilih</p>
                                <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls" style="display: none;" id="fileInput">
                                <small class="text-muted">Format: Customer Name, Email, Transaction Date, Amount</small>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="fas fa-upload me-2"></i> Upload & Process
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-magic me-2"></i> AI Content Generator</h5>
                    </div>
                    <div class="card-body">
                        <p>Generate content for your customer segments</p>
                        <form action="ai-content.php" method="post">
                            <div class="mb-3">
                                <label class="form-label">Pilih Segment</label>
                                <select name="segment" class="form-select">
                                    <option value="Champions">Champions</option>
                                    <option value="Loyal Customers">Loyal Customers</option>
                                    <option value="Potential Loyalists">Potential Loyalists</option>
                                    <option value="At Risk">At Risk</option>
                                    <option value="Cannot Lose Them">Cannot Lose Them</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-magic me-2"></i> Generate Content
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i> Recent Transactions</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_transactions)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Amount</th>
                                    <th>Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($transaction['transaction_date'])) ?></td>
                                    <td><?= htmlspecialchars($transaction['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($transaction['product_name']) ?></td>
                                    <td>Rp <?= number_format($transaction['amount'], 0, ',', '.') ?></td>
                                    <td><?= $transaction['quantity'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No transactions yet. Upload your data to get started.</p>
                <?php endif; ?>
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

        // File upload drag & drop
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');

        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
        });

        // RFM Chart
        <?php if (!empty($rfm_data)): ?>
        const rfmCtx = document.getElementById('rfmChart').getContext('2d');
        new Chart(rfmCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($rfm_data)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($rfm_data)) ?>,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                        '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>

        // Revenue Trend Chart
        <?php if (!empty($revenue_trend)): ?>
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($revenue_trend, 'month')) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode(array_column($revenue_trend, 'revenue')) ?>,
                    borderColor: '#36A2EB',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
