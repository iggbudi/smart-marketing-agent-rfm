<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFM Analysis - Smart Marketing Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="assets/user-styles.css" rel="stylesheet">
</head>
<body>
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
    
    // Calculate RFM Analysis for this business only
    function calculateRFM($businessId) {
        global $db;
        
        // Clear existing RFM analysis for this business
        $stmt = $db->prepare("DELETE FROM rfm_analysis WHERE business_id = ?");
        $stmt->execute([$businessId]);
        
        // Calculate RFM scores for this business
        $query = "
            INSERT INTO rfm_analysis (business_id, customer_id, recency_score, frequency_score, monetary_score, rfm_segment, analysis_date, created_at)
            SELECT 
                c.business_id,
                c.id,
                CASE 
                    WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 30 THEN 5
                    WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 90 THEN 4
                    WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 180 THEN 3
                    WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 365 THEN 2
                    ELSE 1
                END as recency_score,
                CASE 
                    WHEN COUNT(t.id) >= 10 THEN 5
                    WHEN COUNT(t.id) >= 7 THEN 4
                    WHEN COUNT(t.id) >= 5 THEN 3
                    WHEN COUNT(t.id) >= 3 THEN 2
                    ELSE 1
                END as frequency_score,
                CASE 
                    WHEN AVG(t.amount) >= 500000 THEN 5
                    WHEN AVG(t.amount) >= 300000 THEN 4
                    WHEN AVG(t.amount) >= 200000 THEN 3
                    WHEN AVG(t.amount) >= 100000 THEN 2
                    ELSE 1
                END as monetary_score,
                CASE 
                    WHEN 
                        (CASE 
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 30 THEN 5
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 90 THEN 4
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 180 THEN 3
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 365 THEN 2
                            ELSE 1
                        END) >= 4 AND 
                        (CASE 
                            WHEN COUNT(t.id) >= 10 THEN 5
                            WHEN COUNT(t.id) >= 7 THEN 4
                            WHEN COUNT(t.id) >= 5 THEN 3
                            WHEN COUNT(t.id) >= 3 THEN 2
                            ELSE 1
                        END) >= 4 AND 
                        (CASE 
                            WHEN AVG(t.amount) >= 500000 THEN 5
                            WHEN AVG(t.amount) >= 300000 THEN 4
                            WHEN AVG(t.amount) >= 200000 THEN 3
                            WHEN AVG(t.amount) >= 100000 THEN 2
                            ELSE 1
                        END) >= 4 
                    THEN 'Champions'
                    WHEN 
                        (CASE 
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 30 THEN 5
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 90 THEN 4
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 180 THEN 3
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 365 THEN 2
                            ELSE 1
                        END) >= 3 AND 
                        (CASE 
                            WHEN COUNT(t.id) >= 10 THEN 5
                            WHEN COUNT(t.id) >= 7 THEN 4
                            WHEN COUNT(t.id) >= 5 THEN 3
                            WHEN COUNT(t.id) >= 3 THEN 2
                            ELSE 1
                        END) >= 3 
                    THEN 'Loyal Customers'
                    WHEN 
                        (CASE 
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 30 THEN 5
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 90 THEN 4
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 180 THEN 3
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 365 THEN 2
                            ELSE 1
                        END) >= 3 AND 
                        (CASE 
                            WHEN AVG(t.amount) >= 500000 THEN 5
                            WHEN AVG(t.amount) >= 300000 THEN 4
                            WHEN AVG(t.amount) >= 200000 THEN 3
                            WHEN AVG(t.amount) >= 100000 THEN 2
                            ELSE 1
                        END) >= 3 
                    THEN 'Potential Loyalists'
                    WHEN 
                        (CASE 
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 30 THEN 5
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 90 THEN 4
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 180 THEN 3
                            WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 365 THEN 2
                            ELSE 1
                        END) <= 2 
                    THEN 'At Risk'
                    ELSE 'Lost Customers'
                END as rfm_segment,
                CURDATE(),
                NOW()
            FROM customers c
            LEFT JOIN transactions t ON c.id = t.customer_id
            WHERE c.business_id = ?
            GROUP BY c.id, c.business_id
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$businessId]);
        
        // Log activity
        auth()->logActivity($_SESSION['user_id'], 'rfm_calculation', 'RFM analysis calculated', $businessId);
    }
    
    // Run RFM calculation for this business
    calculateRFM($business['id']);
    
    // Get RFM results for this business only
    $stmt = $db->prepare("
        SELECT 
            c.customer_name as name,
            c.email,
            r.recency_score,
            r.frequency_score,
            r.monetary_score,
            r.rfm_segment as segment,
            COUNT(t.id) as total_transactions,
            COALESCE(SUM(t.amount), 0) as total_spent,
            MAX(t.transaction_date) as last_transaction
        FROM rfm_analysis r
        JOIN customers c ON r.customer_id = c.id
        LEFT JOIN transactions t ON c.id = t.customer_id
        WHERE r.business_id = ?
        GROUP BY r.id, c.customer_name, c.email, r.recency_score, r.frequency_score, r.monetary_score, r.rfm_segment
        ORDER BY r.recency_score DESC, r.frequency_score DESC, r.monetary_score DESC
    ");
    $stmt->execute([$business['id']]);
    $rfmResults = $stmt->fetchAll();
    
    // Get segment summary for this business
    $stmt = $db->prepare("SELECT rfm_segment as segment, COUNT(*) as count FROM rfm_analysis WHERE business_id = ? GROUP BY rfm_segment");
    $stmt->execute([$business['id']]);
    $segmentSummary = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
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
            <div>
                <h2><i class="fas fa-chart-pie me-2"></i> RFM Customer Analysis</h2>
                <p class="text-muted">Recency, Frequency, Monetary analysis untuk segmentasi pelanggan</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="location.reload()">
                    <i class="fas fa-sync"></i> Refresh Analysis
                </button>
            </div>
        </div>

        <!-- Segment Summary -->
        <div class="row mb-4">
            <?php foreach ($segmentSummary as $segment => $count): ?>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><?= $count ?></h5>
                        <p class="card-text small"><?= $segment ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- RFM Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-table"></i> Customer Segments</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="rfmTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>R Score</th>
                                <th>F Score</th>
                                <th>M Score</th>
                                <th>Segment</th>
                                <th>Total Transactions</th>
                                <th>Total Spent</th>
                                <th>Last Transaction</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rfmResults as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><span class="badge bg-primary"><?= $row['recency_score'] ?></span></td>
                                <td><span class="badge bg-success"><?= $row['frequency_score'] ?></span></td>
                                <td><span class="badge bg-warning"><?= $row['monetary_score'] ?></span></td>
                                <td>
                                    <span class="badge <?= getSegmentBadgeClass($row['segment']) ?>">
                                        <?= $row['segment'] ?>
                                    </span>
                                </td>
                                <td><?= $row['total_transactions'] ?></td>
                                <td>Rp <?= number_format($row['total_spent']) ?></td>
                                <td><?= $row['last_transaction'] ? date('d/m/Y', strtotime($row['last_transaction'])) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php
    function getSegmentBadgeClass($segment) {
        switch ($segment) {
            case 'Champions': return 'bg-success';
            case 'Loyal Customers': return 'bg-primary';
            case 'Potential Loyalists': return 'bg-info';
            case 'At Risk': return 'bg-warning';
            case 'Lost Customers': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#rfmTable').DataTable({
                pageLength: 25,
                order: [[5, 'asc']],
                columnDefs: [
                    { targets: [2, 3, 4], className: 'text-center' },
                    { targets: [6, 7], className: 'text-end' }
                ]
            });
        });

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
    </script>
    </div> <!-- End main-content -->
</body>
</html>
