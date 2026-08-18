<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === 'super_admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi';
    } else {
        $result = auth()->login($email, $password);
        if ($result['success']) {
            $user = $result['user'];
            if ($user['role'] === 'super_admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Marketing Agent RFM</title>
    <meta name="description" content="Masuk ke Smart Marketing Agent — platform analisis pelanggan RFM untuk UMKM Indonesia.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/login.css" rel="stylesheet">
</head>
<body>

    <div class="login-page">
        <!-- Panel branding -->
        <aside class="brand-panel">
            <div class="container-inner">
                <div class="brand-logo"><i class="fas fa-chart-line"></i></div>
                <h1>Smart Marketing Agent</h1>
                <p class="brand-tagline mb-0">
                    Analisis RFM otomatis untuk UMKM Indonesia — kenali pelanggan terbaik
                    Anda dan tumbuhkan bisnis dengan keputusan berbasis data.
                </p>
                <ul class="brand-features">
                    <li><i class="fas fa-layer-group"></i> Segmentasi pelanggan RFM otomatis</li>
                    <li><i class="fas fa-chart-pie"></i> Insight data penjualan real-time</li>
                    <li><i class="fas fa-robot"></i> Konten pemasaran berbasis AI</li>
                </ul>
                <div class="brand-footer">
                    <i class="fas fa-shield-alt me-1"></i> Data Anda aman &amp; hanya untuk analisis bisnis Anda.
                </div>
            </div>
        </aside>

        <!-- Panel form -->
        <main class="form-panel">
            <div class="login-card">
                <div class="text-center mb-4 d-lg-none">
                    <i class="fas fa-chart-line fa-2x mb-2" style="background: linear-gradient(135deg,#667eea,#764ba2); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;"></i>
                </div>

                <h2 class="form-heading mb-2">Selamat Datang Kembali 👋</h2>
                <p class="form-subtitle mb-4">Masuk ke akun Anda untuk melanjutkan.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check me-1"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email"
                                   placeholder="nama@bisnis.com">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                   required autocomplete="current-password" placeholder="••••••••">
                            <button type="button" class="btn btn-password-toggle" onclick="togglePassword()"
                                    aria-label="Tampilkan/sembunyikan password">
                                <i class="fas fa-eye" id="password-icon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login w-100 mb-3">
                        <i class="fas fa-sign-in-alt me-1"></i> Masuk
                    </button>
                </form>

                <div class="text-center">
                    <a href="index.php" class="back-home">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke beranda
                    </a>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        }
    </script>
</body>
</html>
