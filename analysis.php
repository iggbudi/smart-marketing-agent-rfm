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

// RFM dihitung ulang hanya saat diminta eksplisit (POST+CSRF) atau first-run (belum ada data)
require_once 'includes/rfm.php';

$rfmMessage = '';
$rfmMessageType = '';

$rfmCount = (int)$db->query("SELECT COUNT(*) FROM rfm_analysis WHERE business_id = " . (int)$business['id'])->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recalculate') {
    requireCsrf();
    recalculateRFM($db, $business['id'], $_SESSION['user_id']);
    $rfmMessage = 'RFM berhasil dihitung ulang (' . date('d/m/Y H:i') . ').';
    $rfmMessageType = 'success';
} elseif ($rfmCount === 0) {
    // First-run only: hitung sekali agar halaman & dashboard segera terisi
    recalculateRFM($db, $business['id'], $_SESSION['user_id']);
    $rfmMessage = 'RFM dihitung otomatis (belum ada data analisis).';
    $rfmMessageType = 'info';
}
    
    // Get RFM results for this business only
    $stmt = $db->prepare("
        SELECT 
            c.customer_name as name,
            c.email,
            r.recency_score,
            r.frequency_score,
            r.monetary_score,
            r.rfm_segment as segment,
            r.total_transactions,
            r.total_spent,
            r.last_purchase_date as last_transaction
        FROM rfm_analysis r
        JOIN customers c ON r.customer_id = c.id
        WHERE r.business_id = ?
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
                <form method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="recalculate">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calculator"></i> Hitung Ulang RFM
                    </button>
                </form>
            </div>
        </div>

        <?php if ($rfmMessage): ?>
        <div class="alert alert-<?= htmlspecialchars($rfmMessageType) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($rfmMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

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
