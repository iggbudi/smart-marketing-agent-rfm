<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

// Require super admin access
requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

$userAdmin = new \App\Admin\UserAdmin($db);

// Handle form submissions (POST + CSRF — dipertahankan apa adanya)
if ($_POST) {
    requireCsrf();
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $stmt = $db->prepare("
                    INSERT INTO users (email, password, full_name, role, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $_POST['email'],
                    password_hash($_POST['password'], PASSWORD_DEFAULT),
                    $_POST['full_name'],
                    $_POST['role']
                ]);
                $success = "User berhasil ditambahkan!";
                auth()->logActivity($_SESSION['user_id'], 'user_creation', "Created user: {$_POST['email']}");
                break;

            case 'delete_user':
                $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'super_admin'");
                $stmt->execute([$_POST['user_id']]);
                $success = "User berhasil dihapus!";
                auth()->logActivity($_SESSION['user_id'], 'user_deletion', "Deleted user ID: {$_POST['user_id']}");
                break;

            case 'edit_user':
                $query = "UPDATE users SET email = ?, full_name = ?, role = ?";
                $params = [$_POST['email'], $_POST['full_name'], $_POST['role'], $_POST['user_id']];

                if (!empty($_POST['password'])) {
                    $query .= ", password = ?";
                    array_splice($params, -1, 0, [password_hash($_POST['password'], PASSWORD_DEFAULT)]);
                }

                $query .= " WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $success = "User berhasil diupdate!";
                auth()->logActivity($_SESSION['user_id'], 'user_update', "Updated user: {$_POST['email']}");
                break;
        }
    }
}

// Data baca dari slice
$users = $userAdmin->all();
$stats = $userAdmin->getStats();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin</title>
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
            <h2><i class="fas fa-users me-2"></i> Kelola User</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-2"></i> Tambah User
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
                    <span class="kpi-icon teal"><i class="fas fa-users"></i></span>
                    <div>
                        <div class="kpi-value"><?= (int)$stats['total_users'] ?></div>
                        <div class="kpi-label">Total Pengguna</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon blue"><i class="fas fa-user-clock"></i></span>
                    <div>
                        <div class="kpi-value"><?= (int)$stats['active_sessions'] ?></div>
                        <div class="kpi-label">Sesi Aktif</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon red"><i class="fas fa-shield-alt"></i></span>
                    <div>
                        <div class="kpi-value"><?= (int)$stats['super_admins'] ?></div>
                        <div class="kpi-label">Super Admin</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="kpi-card">
                    <span class="kpi-icon amber"><i class="fas fa-store"></i></span>
                    <div>
                        <div class="kpi-value"><?= (int)$stats['umkm_owners'] ?></div>
                        <div class="kpi-label">UMKM Owner</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel User -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-table me-2"></i> Daftar User</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Peran</th>
                                <th>Bisnis</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= (int)$u['id'] ?></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $u['role'] == 'super_admin' ? 'danger' : 'primary' ?>">
                                        <?= ucfirst(str_replace('_', ' ', $u['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $u['business_name'] ? htmlspecialchars($u['business_name']) : '<em>Tidak ada bisnis</em>' ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($u['role'] != 'super_admin' || $stats['super_admins'] > 1): ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?= (int)$u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah User -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Peran</label>
                            <select class="form-select" name="role" required>
                                <option value="umkm_owner">UMKM Owner</option>
                                <option value="super_admin">Super Admin</option>
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

    <!-- Modal Edit User -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editUserForm">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password (kosongkan utk tetap memakai yg lama)</label>
                            <input type="password" class="form-control" name="password" id="edit_password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Peran</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="umkm_owner">UMKM Owner</option>
                                <option value="super_admin">Super Admin</option>
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

    <!-- Modal Hapus User -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteUserForm">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Apakah Anda yakin ingin menghapus user <strong id="delete_user_name"></strong>?</p>
                        <p class="text-danger"><small>Tindakan ini tidak dapat dibatalkan.</small></p>
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
            $('#usersTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });

        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_password').value = '';

            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function deleteUser(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_user_name').textContent = userName;

            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }
    </script>
</body>
</html>
