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

$generated_content = '';
$selected_segment = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['segment'])) {
    $selected_segment = $_POST['segment'];
    
    // Call the API to generate content
    $api_url = '/smart/api/generate-content.php';
    $data = json_encode(['segment' => $selected_segment]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $data
        ]
    ]);
    
    $result = file_get_contents('http://localhost' . $api_url, false, $context);
    
    if ($result !== FALSE) {
        $response = json_decode($result, true);
        if ($response && $response['success']) {
            $generated_content = $response['content'];
        } else {
            $error_message = $response['error'] ?? 'Gagal menghasilkan konten';
        }
    } else {
        $error_message = 'Tidak dapat terhubung ke layanan generator konten';
    }
}

// Get recent generated content
$stmt = $db->prepare("
    SELECT segment, content, created_at 
    FROM ai_generated_content 
    WHERE business_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$business['id']]);
$recent_content = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generator Konten AI - Smart Marketing</title>
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
                <h1><i class="fas fa-magic me-2"></i> Generator Konten AI</h1>
                <div class="text-muted">
                    Bisnis: <?= htmlspecialchars($business['name']) ?>
                </div>
            </div>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Content Generator Form -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-robot me-2"></i> Buat Konten Baru</h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Pilih Segmen Pelanggan</label>
                                    <select name="segment" class="form-select" required>
                                        <option value="">-- Pilih Segment --</option>
                                        <option value="Champions" <?= $selected_segment === 'Champions' ? 'selected' : '' ?>>Champions</option>
                                        <option value="Loyal Customers" <?= $selected_segment === 'Loyal Customers' ? 'selected' : '' ?>>Loyal Customers</option>
                                        <option value="Potential Loyalists" <?= $selected_segment === 'Potential Loyalists' ? 'selected' : '' ?>>Potential Loyalists</option>
                                        <option value="At Risk" <?= $selected_segment === 'At Risk' ? 'selected' : '' ?>>At Risk</option>
                                        <option value="Cannot Lose Them" <?= $selected_segment === 'Cannot Lose Them' ? 'selected' : '' ?>>Cannot Lose Them</option>
                                        <option value="New Customers" <?= $selected_segment === 'New Customers' ? 'selected' : '' ?>>New Customers</option>
                                        <option value="Need Attention" <?= $selected_segment === 'Need Attention' ? 'selected' : '' ?>>Need Attention</option>
                                        <option value="About to Sleep" <?= $selected_segment === 'About to Sleep' ? 'selected' : '' ?>>About to Sleep</option>
                                        <option value="Lost" <?= $selected_segment === 'Lost' ? 'selected' : '' ?>>Lost</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-magic me-2"></i> Buat Konten
                                </button>
                            </form>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    AI akan menghasilkan konten marketing yang sesuai dengan karakteristik segmen yang dipilih.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Generated Content Display -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i> Konten yang Dihasilkan</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($generated_content): ?>
                            <div class="alert alert-success">
                                <h6 class="fw-bold">Konten untuk: <?= htmlspecialchars($selected_segment) ?></h6>
                                <div class="mt-2">
                                    <?= $generated_content ?>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard()">
                                        <i class="fas fa-copy me-1"></i> Salin ke Clipboard
                                    </button>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-magic fa-3x mb-3 opacity-50"></i>
                                <p>Pilih segmen dan klik "Buat Konten" untuk membuat konten marketing dengan AI.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Generated Content -->
            <?php if (count($recent_content) > 0): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i> Konten yang Baru Dibuat</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Segmen</th>
                                            <th>Pratinjau Konten</th>
                                            <th>Dibuat pada</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_content as $content): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-info"><?= htmlspecialchars($content['segment']) ?></span>
                                            </td>
                                            <td>
                                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <?= strip_tags($content['content']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?= date('d/m/Y H:i', strtotime($content['created_at'])) ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="showFullContent('<?= htmlspecialchars(addslashes($content['content'])) ?>')">
                                                    <i class="fas fa-eye me-1"></i> Lihat
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for viewing full content -->
    <div class="modal fade" id="contentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konten yang Dihasilkan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" onclick="copyModalContent()">
                        <i class="fas fa-copy me-1"></i> Salin ke Clipboard
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard() {
            const content = document.querySelector('.alert-success div').innerText;
            navigator.clipboard.writeText(content).then(function() {
                // Show success feedback
                const button = event.target.closest('button');
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check me-1"></i> Tersalin!';
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-success');
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-primary');
                }, 2000);
            });
        }

        function showFullContent(content) {
            document.getElementById('modalContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('contentModal')).show();
        }

        function copyModalContent() {
            const content = document.getElementById('modalContent').innerText;
            navigator.clipboard.writeText(content).then(function() {
                const button = event.target;
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check me-1"></i> Tersalin!';
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-success');
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('btn-success');
                    button.classList.add('btn-outline-primary');
                }, 2000);
            });
        }
    </script>
</body>
</html>
