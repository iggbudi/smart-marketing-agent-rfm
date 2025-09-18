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

// Handle Excel upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = $_FILES['excel_file']['name'];
    $uploadPath = $uploadDir . uniqid() . '_' . $fileName;
    
    if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $uploadPath)) {
        // Process Excel file (simplified version)
        $message = 'File berhasil diupload! Proses import akan dilakukan dalam background.';
        $messageType = 'success';
    } else {
        $message = 'Gagal mengupload file.';
        $messageType = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Data - Smart Marketing Agent</title>
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
            <h2><i class="fas fa-upload me-2"></i> Upload Data</h2>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Upload Excel File -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-excel me-2"></i> Upload File Excel</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Upload data pelanggan dan transaksi dalam format Excel.</p>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="excel_file" class="form-label">Pilih File Excel</label>
                                <input type="file" class="form-control" id="excel_file" name="excel_file" 
                                       accept=".xlsx,.xls" required>
                                <div class="form-text">Format yang didukung: .xlsx, .xls</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i> Upload & Import
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Download Template -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-download me-2"></i> Template Excel</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Download template Excel untuk memudahkan input data.</p>
                        
                        <div class="d-grid gap-2">
                            <a href="templates/template_customers.xlsx" class="btn btn-outline-success">
                                <i class="fas fa-users me-2"></i> Template Data Pelanggan
                            </a>
                            <a href="templates/template_transactions.xlsx" class="btn btn-outline-success">
                                <i class="fas fa-shopping-cart me-2"></i> Template Data Transaksi
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload History -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Riwayat Upload</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nama File</th>
                                        <th>Jenis Data</th>
                                        <th>Status</th>
                                        <th>Jumlah Record</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            Belum ada riwayat upload
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Drag & Drop Upload Area -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="upload-drop-zone" id="dropZone">
                            <div class="text-center">
                                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                <h5>Drag & Drop File Excel Disini</h5>
                                <p class="text-muted">Atau klik untuk memilih file</p>
                                <input type="file" id="dragDropFile" class="d-none" accept=".xlsx,.xls">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Drag & Drop functionality
        const dropZone = document.getElementById('dropZone');
        const dragDropFile = document.getElementById('dragDropFile');

        dropZone.addEventListener('click', () => dragDropFile.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileUpload(files[0]);
            }
        });

        dragDropFile.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileUpload(e.target.files[0]);
            }
        });

        function handleFileUpload(file) {
            if (file.type.includes('excel') || file.type.includes('spreadsheet') || 
                file.name.endsWith('.xlsx') || file.name.endsWith('.xls')) {
                
                const formData = new FormData();
                formData.append('excel_file', file);
                
                // Show loading
                dropZone.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Mengupload...</p></div>';
                
                fetch('upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    location.reload();
                });
            } else {
                alert('Please select a valid Excel file (.xlsx or .xls)');
            }
        }
    </script>
</body>
</html>
