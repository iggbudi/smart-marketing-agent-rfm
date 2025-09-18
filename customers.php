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

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = trim($_POST['name']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);
            
            if (!empty($name) && !empty($phone)) {
                try {
                    $stmt = $db->prepare("INSERT INTO customers (business_id, customer_name, phone, email, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$business['id'], $name, $phone, $email]);
                    $message = 'Pelanggan berhasil ditambahkan!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            } else {
                $message = 'Nama dan nomor HP harus diisi!';
                $messageType = 'warning';
            }
        } elseif ($_POST['action'] === 'delete' && isset($_POST['customer_id'])) {
            try {
                $stmt = $db->prepare("DELETE FROM customers WHERE id = ? AND business_id = ?");
                $stmt->execute([$_POST['customer_id'], $business['id']]);
                $message = 'Pelanggan berhasil dihapus!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Get customers for this business
$customers = [];
try {
    $stmt = $db->prepare("
        SELECT c.*, 
               COUNT(t.id) as total_transactions,
               COALESCE(SUM(t.amount), 0) as total_spent,
               MAX(t.transaction_date) as last_transaction
        FROM customers c 
        LEFT JOIN transactions t ON c.id = t.customer_id 
        WHERE c.business_id = ? 
        GROUP BY c.id 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$business['id']]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Error loading customers: ' . $e->getMessage();
    $messageType = 'danger';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelanggan - Smart Marketing Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/user-styles.css" rel="stylesheet">
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
            <h2><i class="fas fa-users me-2"></i> Data Pelanggan</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="fas fa-plus me-2"></i> Tambah Pelanggan
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= count($customers) ?></h3>
                                <p class="mb-0">Total Pelanggan</p>
                            </div>
                            <i class="fas fa-users fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= count(array_filter($customers, function($c) { return $c['total_transactions'] > 0; })) ?></h3>
                                <p class="mb-0">Pelanggan Aktif</p>
                            </div>
                            <i class="fas fa-user-check fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= count(array_filter($customers, function($c) { return $c['total_transactions'] == 0; })) ?></h3>
                                <p class="mb-0">Belum Transaksi</p>
                            </div>
                            <i class="fas fa-user-clock fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0">Rp <?= number_format(array_sum(array_column($customers, 'total_spent')), 0, ',', '.') ?></h3>
                                <p class="mb-0">Total Penjualan</p>
                            </div>
                            <i class="fas fa-money-bill-wave fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customers Table -->
        <div class="card shadow-sm">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Pelanggan</h5>
                    <div class="d-flex gap-2">
                        <button onclick="exportCustomers()" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                        <input type="text" class="form-control form-control-sm" id="searchCustomer" 
                               placeholder="Cari pelanggan..." style="width: 200px;">
                        <button class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>No HP</th>
                                <th>Email</th>
                                <th>Total Transaksi</th>
                                <th>Total Belanja</th>
                                <th>Transaksi Terakhir</th>
                                <th>Tanggal Registrasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="customerTableBody">
                            <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">
                                    Belum ada data pelanggan. <a href="#" data-bs-toggle="modal" data-bs-target="#addCustomerModal">Tambah pelanggan pertama</a>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $index => $customer): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($customer['customer_name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($customer['phone']) ?></td>
                                    <td><?= htmlspecialchars($customer['email'] ?: '-') ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= $customer['total_transactions'] ?></span>
                                    </td>
                                    <td>Rp <?= number_format($customer['total_spent'], 0, ',', '.') ?></td>
                                    <td>
                                        <?php if ($customer['last_transaction']): ?>
                                            <?= date('d/m/Y', strtotime($customer['last_transaction'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($customer['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editCustomer(<?= htmlspecialchars(json_encode($customer)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="deleteCustomer(<?= $customer['id'] ?>, '<?= htmlspecialchars($customer['customer_name']) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pelanggan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Pelanggan *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Nomor HP *</label>
                            <input type="text" class="form-control" id="phone" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Customer Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="customer_id" id="deleteCustomerId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Search functionality
        document.getElementById('searchCustomer').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#customerTableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        function editCustomer(customer) {
            // For now, just show an alert. You can implement edit functionality later
            alert('Edit functionality will be implemented soon for: ' + customer.customer_name);
        }

        function deleteCustomer(customerId, customerName) {
            if (confirm('Apakah Anda yakin ingin menghapus pelanggan "' + customerName + '"?')) {
                document.getElementById('deleteCustomerId').value = customerId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Export functionality
        function exportCustomers() {
            const exportBtn = event.target;
            const originalText = exportBtn.innerHTML;
            
            // Show loading state
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Exporting...';
            exportBtn.disabled = true;
            
            // Trigger download
            window.location.href = 'api/export-customers.php';
            
            // Reset button after a delay
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 3000);
        }
    </script>
</body>
</html>
