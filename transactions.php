<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/pagination.php';

// Require UMKM owner access
requireAuth(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    die('Error: No business associated with your account. Please contact administrator.');
}

$repo = new \App\Transactions\TransactionRepository($db);
$customerRepo = new \App\Customers\CustomerRepository($db);
$message = '';
$messageType = '';

// Lapisan HTTP + CSRF; logika data & validasi di repository
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (($_POST['action'] ?? '') === 'add') {
        try {
            $repo->add(
                $business['id'],
                (int)trim($_POST['customer_id'] ?? ''),
                trim($_POST['transaction_date'] ?? ''),
                (float)trim($_POST['amount'] ?? ''),
                trim($_POST['product_name'] ?? '') !== '' ? trim($_POST['product_name']) : null,
                (int)($_POST['quantity'] ?? 1)
            );
            $message = 'Transaksi berhasil ditambahkan!';
            $messageType = 'success';
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
            $messageType = 'warning';
        } catch (\PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif (($_POST['action'] ?? '') === 'delete' && isset($_POST['transaction_id'])) {
        try {
            $repo->delete($business['id'], (int)$_POST['transaction_id']);
            $message = 'Transaksi berhasil dihapus!';
            $messageType = 'success';
        } catch (\PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Dropdown pelanggan (dari slice Customers)
$customers = $customerRepo->listForDropdown($business['id']);

// Statistik kartu (agregat penuh, bukan dari halaman aktif)
$totalTransactions = 0;
$totalRevenue = 0;
$activeCustomers = 0;
try {
    $totalTransactions = $repo->count($business['id']);
    $totalRevenue = $repo->totalRevenue($business['id']);
    $activeCustomers = $repo->countActiveCustomers($business['id']);
} catch (\PDOException $e) {
    $message = 'Error loading statistics: ' . $e->getMessage();
    $messageType = 'danger';
}
$avgTransaction = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

// Pencarian server-side + pagination (LIMIT/OFFSET di-cast (int) di repository)
$search = trim($_GET['q'] ?? '');
$totalRows = 0;
try {
    $totalRows = $repo->countSearch($business['id'], $search);
} catch (\PDOException $e) {
    $message = 'Error loading transactions: ' . $e->getMessage();
    $messageType = 'danger';
}

[$page, $perPage, $offset, $totalPages] = paginate($totalRows);

$transactions = [];
try {
    $transactions = $repo->search($business['id'], $search, $perPage, $offset);
} catch (\PDOException $e) {
    $message = 'Error loading transactions: ' . $e->getMessage();
    $messageType = 'danger';
}
$mobilePageTitle = 'Data Transaksi';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Transaksi - Smart Marketing Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/user-styles.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Top Bar (hamburger + judul + avatar) -->
    <?php include 'includes/mobile-topbar.php'; ?>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-shopping-cart me-2"></i> Data Transaksi</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                <i class="fas fa-plus me-2"></i> Tambah Transaksi
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
                <div class="card stats-card transactions">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= number_format($totalTransactions, 0, ',', '.') ?></h3>
                                <p class="mb-0">Total Transaksi</p>
                            </div>
                            <i class="fas fa-shopping-cart fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card loyal">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0">Rp <?= number_format($totalRevenue, 0, ',', '.') ?></h3>
                                <p class="mb-0">Total Pendapatan</p>
                            </div>
                            <i class="fas fa-money-bill-wave fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card potential">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0">Rp <?= number_format($avgTransaction, 0, ',', '.') ?></h3>
                                <p class="mb-0">Rata-rata Transaksi</p>
                            </div>
                            <i class="fas fa-chart-line fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card revenue">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="mb-0"><?= number_format($activeCustomers, 0, ',', '.') ?></h3>
                                <p class="mb-0">Pelanggan Aktif</p>
                            </div>
                            <i class="fas fa-users fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card shadow-sm">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Transaksi</h5>
                    <div class="d-flex gap-2">
                        <button onclick="exportTransactions()" class="btn btn-success btn-sm">
                            <i class="fas fa-file-excel me-1"></i> Export Excel
                        </button>
                        <form method="get" class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm" name="q"
                                   placeholder="Cari transaksi..." style="width: 200px;"
                                   value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Produk</th>
                                <th>Qty</th>
                                <th>Jumlah</th>
                                <th>Total</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="transactionTableBody">
                            <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    Belum ada data transaksi. <a href="#" data-bs-toggle="modal" data-bs-target="#addTransactionModal">Tambah transaksi pertama</a>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $index => $transaction): ?>
                                <tr>
                                    <td><?= $offset + $index + 1 ?></td>
                                    <td><?= date('d/m/Y', strtotime($transaction['transaction_date'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($transaction['customer_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($transaction['phone']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($transaction['product_name'] ?: '-') ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $transaction['quantity'] ?></span>
                                    </td>
                                    <td>Rp <?= number_format($transaction['amount'], 0, ',', '.') ?></td>
                                    <td>
                                        <strong>Rp <?= number_format($transaction['amount'] * $transaction['quantity'], 0, ',', '.') ?></strong>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="editTransaction(<?= htmlspecialchars(json_encode($transaction)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="deleteTransaction(<?= $transaction['id'] ?>, '<?= htmlspecialchars($transaction['customer_name']) ?>')">
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
                <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted">Menampilkan <?= $offset + 1 ?>–<?= min($offset + count($transactions), $totalRows) ?> dari <?= number_format($totalRows, 0, ',', '.') ?> transaksi</small>
                    <?= renderPagination($totalPages, $page) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Transaction Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Transaksi Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="customer_id" class="form-label">Pelanggan *</label>
                            <select class="form-select" id="customer_id" name="customer_id" required>
                                <option value="">Pilih Pelanggan</option>
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['id'] ?>">
                                    <?= htmlspecialchars($customer['customer_name']) ?> (<?= htmlspecialchars($customer['phone']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="transaction_date" class="form-label">Tanggal Transaksi *</label>
                            <input type="date" class="form-control" id="transaction_date" name="transaction_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="product_name" class="form-label">Nama Produk</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" 
                                   placeholder="Contoh: Batik Kawung">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Jumlah</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           value="1" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Harga Satuan (Rp) *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           placeholder="150000" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Total: Rp <span id="totalAmount">0</span></strong>
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

    <!-- Delete Transaction Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="transaction_id" id="deleteTransactionId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/mobile.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Search dilakukan server-side via form GET (?q=...)

        // Calculate total amount
        function calculateTotal() {
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            const amount = parseInt(document.getElementById('amount').value) || 0;
            const total = quantity * amount;
            document.getElementById('totalAmount').textContent = total.toLocaleString('id-ID');
        }

        // Add event listeners for calculation
        document.getElementById('quantity').addEventListener('input', calculateTotal);
        document.getElementById('amount').addEventListener('input', calculateTotal);

        function editTransaction(transaction) {
            // For now, just show an alert. You can implement edit functionality later
            alert('Edit functionality will be implemented soon for transaction ID: ' + transaction.id);
        }

        function deleteTransaction(transactionId, customerName) {
            if (confirm('Apakah Anda yakin ingin menghapus transaksi untuk pelanggan "' + customerName + '"?')) {
                document.getElementById('deleteTransactionId').value = transactionId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Export functionality
        function exportTransactions() {
            const exportBtn = event.target;
            const originalText = exportBtn.innerHTML;
            
            // Show loading state
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Exporting...';
            exportBtn.disabled = true;
            
            // Trigger download
            window.location.href = 'api/export-transactions.php';
            
            // Reset button after a delay
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                exportBtn.disabled = false;
            }, 3000);
        }
    </script>

    <!-- Bottom Navigation (mobile) -->
    <?php include 'includes/bottom-nav.php'; ?>
</body>
</html>

