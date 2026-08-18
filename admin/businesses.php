<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

// Require super admin access
requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

$businessAdmin = new \App\Admin\BusinessAdmin($db);

// Handle form submissions (POST + CSRF — dipertahankan apa adanya)
if ($_POST) {
    requireCsrf();
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
                $success = "Bisnis berhasil ditambahkan!";
                auth()->logActivity($_SESSION['user_id'], 'business_creation', "Created business: {$_POST['business_name']}");
                break;

            case 'delete_business':
                $stmt = $db->prepare("DELETE FROM businesses WHERE id = ?");
                $stmt->execute([$_POST['business_id']]);
                $success = "Bisnis berhasil dihapus!";
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
                $success = "Bisnis berhasil diupdate!";
                auth()->logActivity($_SESSION['user_id'], 'business_update', "Updated business: {$_POST['business_name']}");
                break;
        }
    }
}

// Data baca dari slice
$businesses = $businessAdmin->all();
$umkmUsers = $businessAdmin->umkmOwners();
$stats = $businessAdmin->getStats();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Bisnis - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
            <h2><i class="fas fa-building me-2"></i> Kelola Bisnis</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBusinessModal">
                <i class="fas fa-plus me-2"></i> Tambah Bisnis
            </button>
        </div>

        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistik: kartu KPI identitas -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon amber"><i class="fas fa-building"></i></span>
                    <div>
                        <div class="kpi-value"><?= (int)$stats['total_businesses'] ?></div>
                        <div class="kpi-label">Total Bisnis</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon green"><i class="fas fa-chart-line"></i></span>
                    <div>
                        <div class="kpi-value"><?= (int)$stats['active_businesses'] ?></div>
                        <div class="kpi-label">Bisnis Aktif</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon teal"><i class="fas fa-users"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$stats['total_customers']) ?></div>
                        <div class="kpi-label">Total Pelanggan</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon blue"><i class="fas fa-shopping-cart"></i></span>
                    <div>
                        <div class="kpi-value"><?= number_format((int)$stats['total_transactions']) ?></div>
                        <div class="kpi-label">Total Transaksi</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Bisnis -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i> Daftar Bisnis</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="businessesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Bisnis</th>
                                <th>Industri</th>
                                <th>Pemilik</th>
                                <th>Pelanggan</th>
                                <th>Transaksi</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($businesses as $business): ?>
                            <tr>
                                <td><?= (int)$business['id'] ?></td>
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
                                        <em class="text-muted">Belum ada pemilik</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= (int)$business['customer_count'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?= (int)$business['transaction_count'] ?></span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($business['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editBusiness(<?= htmlspecialchars(json_encode($business)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteBusiness(<?= (int)$business['id'] ?>, '<?= htmlspecialchars($business['name']) ?>')">
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

    <!-- Modal Tambah Bisnis -->
    <div class="modal fade" id="addBusinessModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Bisnis</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_business">
                        <div class="mb-3">
                            <label class="form-label">Nama Bisnis</label>
                            <input type="text" class="form-control" name="business_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Industri</label>
                            <select class="form-select" name="industry" required>
                                <option value="">Pilih Industri</option>
                                <option value="Fashion">Fashion</option>
                                <option value="Food &amp; Beverage">Food &amp; Beverage</option>
                                <option value="Handicrafts">Kerajinan</option>
                                <option value="Technology">Teknologi</option>
                                <option value="Services">Jasa</option>
                                <option value="Retail">Ritel</option>
                                <option value="Other">Lainnya</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pemilik</label>
                            <select class="form-select" name="owner_id" required>
                                <option value="">Pilih Pemilik</option>
                                <?php foreach ($umkmUsers as $umkmUser): ?>
                                <option value="<?= (int)$umkmUser['id'] ?>">
                                    <?= htmlspecialchars($umkmUser['full_name']) ?> (<?= htmlspecialchars($umkmUser['email']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
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

    <!-- Modal Edit Bisnis -->
    <div class="modal fade" id="editBusinessModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editBusinessForm">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Bisnis</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_business">
                        <input type="hidden" name="business_id" id="edit_business_id">
                        <div class="mb-3">
                            <label class="form-label">Nama Bisnis</label>
                            <input type="text" class="form-control" name="business_name" id="edit_business_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Industri</label>
                            <select class="form-select" name="industry" id="edit_industry" required>
                                <option value="">Pilih Industri</option>
                                <option value="Fashion">Fashion</option>
                                <option value="Food &amp; Beverage">Food &amp; Beverage</option>
                                <option value="Handicrafts">Kerajinan</option>
                                <option value="Technology">Teknologi</option>
                                <option value="Services">Jasa</option>
                                <option value="Retail">Ritel</option>
                                <option value="Other">Lainnya</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pemilik</label>
                            <select class="form-select" name="owner_id" id="edit_owner_id" required>
                                <option value="">Pilih Pemilik</option>
                                <?php foreach ($umkmUsers as $umkmUser): ?>
                                <option value="<?= (int)$umkmUser['id'] ?>">
                                    <?= htmlspecialchars($umkmUser['full_name']) ?> (<?= htmlspecialchars($umkmUser['email']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
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

    <!-- Modal Hapus Bisnis -->
    <div class="modal fade" id="deleteBusinessModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteBusinessForm">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus Bisnis</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_business">
                        <input type="hidden" name="business_id" id="delete_business_id">
                        <p>Apakah Anda yakin ingin menghapus bisnis <strong id="delete_business_name"></strong>?</p>
                        <p class="text-danger"><small>Ini juga akan menghapus seluruh pelanggan &amp; transaksi terkait. Tindakan ini tidak dapat dibatalkan.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
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
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
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
