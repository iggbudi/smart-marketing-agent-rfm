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
    die('Error: Tidak ada bisnis yang terkait dengan akun Anda. Silakan hubungi administrator.');
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $owner_name = trim($_POST['owner_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $business_type = trim($_POST['business_type'] ?? '');
    
    // Validation
    if (empty($name)) {
        $error_message = 'Nama bisnis wajib diisi';
    } elseif (empty($owner_name)) {
        $error_message = 'Nama pemilik wajib diisi';
    } elseif (empty($email)) {
        $error_message = 'Email wajib diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Format email tidak valid';
    } else {
        try {
            // Check if email already exists for other businesses
            $stmt = $db->prepare("SELECT id FROM businesses WHERE email = ? AND id != ?");
            $stmt->execute([$email, $business['id']]);
            if ($stmt->fetch()) {
                $error_message = 'Email sudah digunakan oleh bisnis lain';
            } else {
                // Update business profile
                $stmt = $db->prepare("
                    UPDATE businesses 
                    SET name = ?, owner_name = ?, email = ?, phone = ?, address = ?, business_type = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $owner_name, $email, $phone, $address, $business_type, $business['id']]);
                
                $success_message = 'Profil bisnis berhasil diperbarui';
                
                // Refresh business data
                $business = auth()->getUserBusiness($user['id']);
            }
        } catch (Exception $e) {
            $error_message = 'Terjadi kesalahan saat memperbarui profil: ' . $e->getMessage();
        }
    }
}

// Business types for dropdown
$business_types = [
    'Retail/Eceran',
    'F&B/Kuliner', 
    'Fashion/Pakaian',
    'Kecantikan/Kosmetik',
    'Elektronik',
    'Otomotif',
    'Kesehatan',
    'Pendidikan',
    'Jasa',
    'Teknologi',
    'Pertanian',
    'Lainnya'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Bisnis - Smart Marketing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/user-styles.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-building me-2"></i> Profil Bisnis</h1>
                <div class="text-muted">
                    <i class="fas fa-clock me-1"></i>
                    Terakhir diperbarui: <?= date('d/m/Y H:i', strtotime($business['updated_at'])) ?>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-edit me-2"></i> Edit Profil Bisnis</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Nama Bisnis <span class="text-danger">*</span></label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="name" 
                                                   name="name" 
                                                   value="<?= htmlspecialchars($business['name']) ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="owner_name" class="form-label">Nama Pemilik <span class="text-danger">*</span></label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="owner_name" 
                                                   name="owner_name" 
                                                   value="<?= htmlspecialchars($business['owner_name']) ?>" 
                                                   required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="email" 
                                                   name="email" 
                                                   value="<?= htmlspecialchars($business['email']) ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Nomor Telepon</label>
                                            <input type="tel" 
                                                   class="form-control" 
                                                   id="phone" 
                                                   name="phone" 
                                                   value="<?= htmlspecialchars($business['phone']) ?>"
                                                   placeholder="08xx-xxxx-xxxx">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="business_type" class="form-label">Jenis Bisnis</label>
                                    <select class="form-select" id="business_type" name="business_type">
                                        <option value="">-- Pilih Jenis Bisnis --</option>
                                        <?php foreach ($business_types as $type): ?>
                                        <option value="<?= htmlspecialchars($type) ?>" 
                                                <?= $business['business_type'] === $type ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Alamat</label>
                                    <textarea class="form-control" 
                                              id="address" 
                                              name="address" 
                                              rows="3" 
                                              placeholder="Masukkan alamat lengkap bisnis"><?= htmlspecialchars($business['address']) ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Simpan Perubahan
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Business Info Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Informasi Bisnis</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted">ID Bisnis</small>
                                <div class="fw-bold">#<?= $business['id'] ?></div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Bergabung Sejak</small>
                                <div class="fw-bold"><?= date('d/m/Y', strtotime($business['created_at'])) ?></div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Status</small>
                                <div><span class="badge bg-success">Aktif</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tips Card -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-lightbulb me-2"></i> Tips</h5>
                        </div>
                        <div class="card-body">
                            <div class="small">
                                <p><i class="fas fa-check text-success me-2"></i> Pastikan informasi kontak selalu terbaru</p>
                                <p><i class="fas fa-check text-success me-2"></i> Pilih jenis bisnis yang sesuai untuk analisis yang lebih akurat</p>
                                <p><i class="fas fa-check text-success me-2"></i> Alamat lengkap membantu dalam targeting geografis</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const ownerName = document.getElementById('owner_name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!name) {
                alert('Nama bisnis wajib diisi');
                e.preventDefault();
                return;
            }
            
            if (!ownerName) {
                alert('Nama pemilik wajib diisi');
                e.preventDefault();
                return;
            }
            
            if (!email) {
                alert('Email wajib diisi');
                e.preventDefault();
                return;
            }
            
            // Email validation
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                alert('Format email tidak valid');
                e.preventDefault();
                return;
            }
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.startsWith('0')) {
                    if (value.length <= 4) {
                        value = value;
                    } else if (value.length <= 8) {
                        value = value.substring(0, 4) + '-' + value.substring(4);
                    } else {
                        value = value.substring(0, 4) + '-' + value.substring(4, 8) + '-' + value.substring(8, 12);
                    }
                }
            }
            e.target.value = value;
        });
    </script>
</body>
</html>
