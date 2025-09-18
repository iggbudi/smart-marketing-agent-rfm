<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Require super admin access
requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_business':
                $stmt = $db->prepare("
                    INSERT INTO businesses (name, business_type, address, user_id, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $_POST['business_name'],
                    $_POST['industry'],
                    $_POST['description'],
                    $_POST['owner_id']
                ]);
                $success = "Business berhasil ditambahkan!";
                auth()->logActivity($_SESSION['user_id'], 'business_creation', "Created business: {$_POST['business_name']}");
                break;
                
            case 'delete_business':
                $stmt = $db->prepare("DELETE FROM businesses WHERE id = ?");
                $stmt->execute([$_POST['business_id']]);
                $success = "Business berhasil dihapus!";
                auth()->logActivity($_SESSION['user_id'], 'business_deletion', "Deleted business ID: {$_POST['business_id']}");
                break;
                
            case 'edit_business':
                $stmt = $db->prepare("
                    UPDATE businesses 
                    SET name = ?, business_type = ?, address = ?, user_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['business_name'],
                    $_POST['industry'],
                    $_POST['description'],
                    $_POST['owner_id'],
                    $_POST['business_id']
                ]);
                $success = "Business berhasil diupdate!";
                auth()->logActivity($_SESSION['user_id'], 'business_update', "Updated business: {$_POST['business_name']}");
                break;
        }
    }
}

// Get all businesses with owner info
$stmt = $db->query("
    SELECT b.*, u.full_name as owner_name, u.email as owner_email,
           (SELECT COUNT(*) FROM customers c WHERE c.business_id = b.id) as customer_count,
           (SELECT COUNT(*) FROM transactions t JOIN customers c ON t.customer_id = c.id WHERE c.business_id = b.id) as transaction_count
    FROM businesses b
    LEFT JOIN users u ON b.user_id = u.id
    ORDER BY b.created_at DESC
");
$businesses = $stmt->fetchAll();

// Get users for owner selection (UMKM owners only)
$stmt = $db->query("SELECT id, full_name, email FROM users WHERE role = 'umkm_owner' ORDER BY full_name");
$umkm_users = $stmt->fetchAll();

// Get business statistics
$stats = [
    'total_businesses' => $db->query("SELECT COUNT(*) FROM businesses")->fetchColumn(),
    'active_businesses' => $db->query("SELECT COUNT(*) FROM businesses WHERE id IN (SELECT DISTINCT business_id FROM customers)")->fetchColumn(),
    'total_customers' => $db->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
    'total_transactions' => $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
        }
        .stat-card {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .stat-card.active { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card.customers { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }
        .stat-card.transactions { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); color: #333; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar px-3 py-4">
                <div class="d-flex align-items-center mb-4">
                    <i class="fas fa-shield-alt fa-2x text-white me-2"></i>
                    <h5 class="text-white mb-0">Admin Panel</h5>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                    <a class="nav-link active" href="businesses.php">
                        <i class="fas fa-building me-2"></i> Businesses
                    </a>
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-line me-2"></i> Analytics
                    </a>
                    <a class="nav-link" href="api-management.php">
                        <i class="fas fa-code me-2"></i> API Management
                    </a>
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-file-alt me-2"></i> Reports
                    </a>
                    <hr class="text-white">
                    <a class="nav-link" href="../dashboard.php">
                        <i class="fas fa-arrow-left me-2"></i> Back to UMKM
                    </a>
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center py-4">
                    <h2><i class="fas fa-building me-2"></i> Business Management</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBusinessModal">
                        <i class="fas fa-plus me-2"></i> Add New Business
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body text-center">
                                <i class="fas fa-building fa-2x mb-2"></i>
                                <h3><?= $stats['total_businesses'] ?></h3>
                                <p class="mb-0">Total Businesses</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card active">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h3><?= $stats['active_businesses'] ?></h3>
                                <p class="mb-0">Active Businesses</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card customers">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h3><?= $stats['total_customers'] ?></h3>
                                <p class="mb-0">Total Customers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card transactions">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                <h3><?= $stats['total_transactions'] ?></h3>
                                <p class="mb-0">Total Transactions</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Businesses Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-table me-2"></i> All Businesses</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="businessesTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Business Name</th>
                                        <th>Industry</th>
                                        <th>Owner</th>
                                        <th>Customers</th>
                                        <th>Transactions</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($businesses as $business): ?>
                                    <tr>
                                        <td><?= $business['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($business['name']) ?></strong>
                                            <?php if ($business['address']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($business['address']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($business['business_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($business['owner_name']): ?>
                                                <?= htmlspecialchars($business['owner_name']) ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($business['owner_email']) ?></small>
                                            <?php else: ?>
                                                <em class="text-muted">No owner assigned</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= $business['customer_count'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?= $business['transaction_count'] ?></span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($business['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editBusiness(<?= htmlspecialchars(json_encode($business)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteBusiness(<?= $business['id'] ?>, '<?= htmlspecialchars($business['name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Add Business Modal -->
    <div class="modal fade" id="addBusinessModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Business</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_business">
                        <div class="mb-3">
                            <label class="form-label">Business Name</label>
                            <input type="text" class="form-control" name="business_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Industry</label>
                            <select class="form-select" name="industry" required>
                                <option value="">Select Industry</option>
                                <option value="Fashion">Fashion</option>
                                <option value="Food & Beverage">Food & Beverage</option>
                                <option value="Handicrafts">Handicrafts</option>
                                <option value="Technology">Technology</option>
                                <option value="Services">Services</option>
                                <option value="Retail">Retail</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Owner</label>
                            <select class="form-select" name="owner_id" required>
                                <option value="">Select Owner</option>
                                <?php foreach ($umkm_users as $umkm_user): ?>
                                <option value="<?= $umkm_user['id'] ?>">
                                    <?= htmlspecialchars($umkm_user['full_name']) ?> (<?= htmlspecialchars($umkm_user['email']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Business</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Business Modal -->
    <div class="modal fade" id="editBusinessModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editBusinessForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Business</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_business">
                        <input type="hidden" name="business_id" id="edit_business_id">
                        <div class="mb-3">
                            <label class="form-label">Business Name</label>
                            <input type="text" class="form-control" name="business_name" id="edit_business_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Industry</label>
                            <select class="form-select" name="industry" id="edit_industry" required>
                                <option value="">Select Industry</option>
                                <option value="Fashion">Fashion</option>
                                <option value="Food & Beverage">Food & Beverage</option>
                                <option value="Handicrafts">Handicrafts</option>
                                <option value="Technology">Technology</option>
                                <option value="Services">Services</option>
                                <option value="Retail">Retail</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Owner</label>
                            <select class="form-select" name="owner_id" id="edit_owner_id" required>
                                <option value="">Select Owner</option>
                                <?php foreach ($umkm_users as $umkm_user): ?>
                                <option value="<?= $umkm_user['id'] ?>">
                                    <?= htmlspecialchars($umkm_user['full_name']) ?> (<?= htmlspecialchars($umkm_user['email']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Business</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Business Modal -->
    <div class="modal fade" id="deleteBusinessModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteBusinessForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Business</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_business">
                        <input type="hidden" name="business_id" id="delete_business_id">
                        <p>Are you sure you want to delete business <strong id="delete_business_name"></strong>?</p>
                        <p class="text-danger"><small>This will also delete all associated customers and transactions. This action cannot be undone.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Business</button>
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
            $('#businessesTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });

        function editBusiness(business) {
            document.getElementById('edit_business_id').value = business.id;
            document.getElementById('edit_business_name').value = business.name;
            document.getElementById('edit_industry').value = business.business_type;
            document.getElementById('edit_description').value = business.address || '';
            document.getElementById('edit_owner_id').value = business.user_id || '';
            
            new bootstrap.Modal(document.getElementById('editBusinessModal')).show();
        }

        function deleteBusiness(businessId, businessName) {
            document.getElementById('delete_business_id').value = businessId;
            document.getElementById('delete_business_name').textContent = businessName;
            
            new bootstrap.Modal(document.getElementById('deleteBusinessModal')).show();
        }
    </script>
</body>
</html>
