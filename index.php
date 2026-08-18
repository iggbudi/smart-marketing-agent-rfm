<?php
// Landing page publik. Jika sudah login, arahkan ke dashboard sesuai peran.
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'super_admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

// Statistik ringkas (dinamis dari DB). Bila DB tidak tersedia, section disembunyikan.
require_once 'config/database.php';
$stats = null;
try {
    $db = getDB();
    $row = $db->query(
        "SELECT
            (SELECT COUNT(*) FROM businesses) AS businesses,
            (SELECT COUNT(*) FROM customers) AS customers,
            (SELECT COUNT(*) FROM transactions) AS transactions"
    )->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats = [
            'businesses'   => (int)$row['businesses'],
            'customers'    => (int)$row['customers'],
            'transactions' => (int)$row['transactions'],
        ];
    }
} catch (Throwable $e) {
    error_log('Landing stats gagal dimuat: ' . $e->getMessage());
    $stats = null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Marketing Agent — Analisis RFM untuk UMKM Indonesia</title>
    <meta name="description" content="Platform analisis pelanggan berbasis RFM (Recency, Frequency, Monetary) untuk UMKM Indonesia: segmentasi otomatis, insight data, dan pembuatan konten pemasaran dengan AI.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/landing.css" rel="stylesheet">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-landing fixed-top">
        <div class="container">
            <a class="navbar-brand brand-text" href="#hero">
                <i class="fas fa-chart-line me-2"></i>Smart Marketing Agent
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navLanding" aria-controls="navLanding" aria-expanded="false" aria-label="Buka menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navLanding">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="#fitur">Fitur</a></li>
                    <li class="nav-item"><a class="nav-link" href="#cara-kerja">Cara Kerja</a></li>
                    <li class="nav-item"><a class="nav-link" href="#segmen">Segmen</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-primary rounded-pill px-4" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section id="hero" class="hero-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="hero-badge mb-3"><i class="fas fa-star"></i> Dirancang untuk UMKM Indonesia</span>
                    <h1 class="hero-title display-4 fw-bold mb-3">
                        Pahami Pelanggan Anda,<br>Tumbuhkan Bisnis UMKM
                    </h1>
                    <p class="hero-subtitle lead mb-4">
                        Analisis RFM (Recency, Frequency, Monetary) otomatis: tahu siapa pelanggan terbaik,
                        siapa yang berisiko pergi, dan apa yang harus dilakukan — tanpa perlu jadi ahli data.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="login.php" class="btn-gradient"><i class="fas fa-rocket me-1"></i>Mulai Sekarang</a>
                        <a href="#cara-kerja" class="btn-ghost"><i class="fas fa-play me-1"></i>Lihat Cara Kerja</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="mockup">
                        <div class="mockup-head">
                            <strong><i class="fas fa-users me-2 text-primary"></i>Segmen Pelanggan</strong>
                            <span class="badge bg-success">Aktif</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-6"><div class="segment-chip" style="background: linear-gradient(135deg,#f59e0b,#f97316);"><i class="fas fa-trophy me-1"></i>Champions<br><small>Pelanggan terbaik</small></div></div>
                            <div class="col-6"><div class="segment-chip" style="background: linear-gradient(135deg,#10b981,#059669);"><i class="fas fa-heart me-1"></i>Loyal Customers<br><small>Rutin belanja</small></div></div>
                            <div class="col-6"><div class="segment-chip" style="background: linear-gradient(135deg,#3b82f6,#6366f1);"><i class="fas fa-star me-1"></i>Potential Loyalists<br><small>Calon setia</small></div></div>
                            <div class="col-6"><div class="segment-chip" style="background: linear-gradient(135deg,#ef4444,#dc2626);"><i class="fas fa-exclamation me-1"></i>At Risk<br><small>Perlu perhatian</small></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistik (dinamis dari DB; disembunyikan bila DB tidak tersedia) -->
    <?php if ($stats): ?>
    <section id="statistik" class="stats-section">
        <div class="container">
            <div class="row text-center g-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-number"><?= number_format($stats['businesses']) ?></div>
                    <h6 class="fw-bold mt-2 mb-0">Bisnis Terdaftar</h6>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-number"><?= number_format($stats['customers']) ?></div>
                    <h6 class="fw-bold mt-2 mb-0">Pelanggan Dikelola</h6>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-number"><?= number_format($stats['transactions']) ?></div>
                    <h6 class="fw-bold mt-2 mb-0">Transaksi Dianalisis</h6>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-number">5</div>
                    <h6 class="fw-bold mt-2 mb-0">Segmen Pelanggan</h6>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Fitur -->
    <section id="fitur" class="py-5">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">Fitur Unggulan</h2>
                <p class="section-subtitle lead">Solusi lengkap analisis pelanggan untuk UMKM</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 reveal">
                    <div class="feature-card">
                        <div class="feature-icon icon-rfm"><i class="fas fa-chart-pie"></i></div>
                        <h4 class="fw-bold mb-2">Analisis RFM Otomatis</h4>
                        <p class="text-muted mb-0">Skor Recency, Frequency, dan Monetary tiap pelanggan dihitung otomatis dari data transaksi Anda, lalu dikelompokkan ke segmen yang jelas.</p>
                    </div>
                </div>
                <div class="col-lg-4 reveal">
                    <div class="feature-card">
                        <div class="feature-icon icon-ai"><i class="fas fa-magic"></i></div>
                        <h4 class="fw-bold mb-2">Konten Pemasaran dengan AI</h4>
                        <p class="text-muted mb-0">Buat caption Instagram, pesan WhatsApp, atau email promosi yang disesuaikan dengan tiap segmen pelanggan — langsung dari platform.</p>
                    </div>
                </div>
                <div class="col-lg-4 reveal">
                    <div class="feature-card">
                        <div class="feature-icon icon-insight"><i class="fas fa-lightbulb"></i></div>
                        <h4 class="fw-bold mb-2">Dashboard & Ekspor Laporan</h4>
                        <p class="text-muted mb-0">Pantau kinerja bisnis lewat dashboard interaktif dan ekspor data pelanggan/transaksi ke CSV atau Excel untuk kebutuhan Anda.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cara Kerja -->
    <section id="cara-kerja" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">Cara Kerja</h2>
                <p class="section-subtitle lead">Tiga langkah menuju keputusan berbasis data</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 reveal">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <h5 class="fw-bold">Unggah Data Transaksi</h5>
                        <p class="text-muted mb-0">Impor data pelanggan & transaksi dari file Excel atau CSV (format .xlsx, .xls, .csv, maksimal 5 MB).</p>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <h5 class="fw-bold">Analisis RFM Otomatis</h5>
                        <p class="text-muted mb-0">Sistem menghitung skor Recency, Frequency, Monetary dan mengelompokkan pelanggan ke segmen secara otomatis.</p>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <h5 class="fw-bold">Buat Aksi Pemasaran</h5>
                        <p class="text-muted mb-0">Pilih segmen target, buat konten promosi dengan bantuan AI, lalu kirim pesan yang tepat sasaran.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Segmen RFM (5 segmen sesuai src/Rfm.php) -->
    <section id="segmen" class="py-5">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">5 Segmen Pelanggan</h2>
                <p class="section-subtitle lead">Setiap pelanggan otomatis dikelompokkan agar perlakuan pemasaran tepat sasaran</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="seg-card seg-champions">
                        <div class="seg-icon"><i class="fas fa-trophy"></i></div>
                        <h5 class="fw-bold">Champions</h5>
                        <p>Pelanggan terbaik — sering belanja, baru belanja, nilai tinggi. Pertahankan dengan reward & program member VIP.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="seg-card seg-loyal">
                        <div class="seg-icon"><i class="fas fa-heart"></i></div>
                        <h5 class="fw-bold">Loyal Customers</h5>
                        <p>Pelanggan yang rutin belanja. Pertahankan dengan program loyalitas agar naik kelas menjadi Champions.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="seg-card seg-potential">
                        <div class="seg-icon"><i class="fas fa-star"></i></div>
                        <h5 class="fw-bold">Potential Loyalists</h5>
                        <p>Pelanggan baru dengan nilai belanja tinggi. Dorong repeat purchase dengan penawaran menarik.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="seg-card seg-risk">
                        <div class="seg-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <h5 class="fw-bold">At Risk</h5>
                        <p>Dulu aktif, kini mulai jarang belanja. Hubungi segera dengan penawaran spesial sebelum benar-benar pergi.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="seg-card seg-lost">
                        <div class="seg-icon"><i class="fas fa-user-slash"></i></div>
                        <h5 class="fw-bold">Lost Customers</h5>
                        <p>Sudah lama tidak belanja. Kirim kampanye "kami rindu" atau diskon comeback untuk mengajak kembali.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 d-flex align-items-stretch reveal">
                    <div class="feature-card w-100 d-flex flex-column justify-content-center text-center">
                        <i class="fas fa-chart-line text-primary mb-3" style="font-size: 2rem;"></i>
                        <h5 class="fw-bold mb-2">Siap melihat segmen pelanggan Anda?</h5>
                        <div><a href="login.php" class="btn btn-primary rounded-pill px-4"><i class="fas fa-sign-in-alt me-1"></i>Login</a></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section id="demo" class="cta-section py-5">
        <div class="container text-center py-4">
            <h2 class="display-6 fw-bold mb-3">Siap memahami pelanggan Anda?</h2>
            <p class="lead mb-4 mx-auto" style="max-width: 640px;">
                Masuk ke platform dan mulai analisis RFM untuk bisnis Anda hari ini.
            </p>
            <a href="login.php" class="btn btn-light btn-lg rounded-pill px-5 fw-bold">
                <i class="fas fa-sign-in-alt me-2"></i>Masuk ke Platform
            </a>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">Pertanyaan Umum</h2>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true" aria-controls="faq1">
                                    Apa itu analisis RFM?
                                </button>
                            </h3>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    RFM adalah singkatan dari <strong>Recency</strong> (berapa lama sejak pembelian terakhir), <strong>Frequency</strong> (berapa sering belanja), dan <strong>Monetary</strong> (berapa besar total belanja). Platform menilai ketiganya lalu mengelompokkan pelanggan ke segmen yang mudah dipahami.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                                    Bagaimana cara mulai menggunakannya?
                                </button>
                            </h3>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Admin platform membuat akun untuk UMKM. Setelah masuk, Anda melengkapi profil bisnis, mengunggah data transaksi (Excel/CSV), dan sistem otomatis menghitung analisis RFM.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                                    Apakah data bisnis saya aman?
                                </button>
                            </h3>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Ya. Data setiap bisnis diisolasi per akun pemilik, semua query memakai prepared statement, form dilindungi CSRF, dan sesi login dikelola dengan aman. Detail lebih lanjut tersedia di dokumentasi keamanan.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                                    Bisa ekspor hasil analisis?
                                </button>
                            </h3>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Bisa. Pelanggan dan transaksi dapat diekspor ke CSV atau Excel (XLSX) langsung dari halaman data.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <h5 class="footer-head mb-3"><i class="fas fa-chart-line me-2"></i>Smart Marketing Agent</h5>
                    <p class="mb-3">Platform analisis pelanggan berbasis RFM untuk UMKM Indonesia — segmentasi otomatis, insight data, dan konten pemasaran berbantuan AI.</p>
                </div>
                <div class="col-lg-3">
                    <h6 class="footer-head mb-3">Navigasi</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><a href="#fitur">Fitur</a></li>
                        <li class="mb-2"><a href="#cara-kerja">Cara Kerja</a></li>
                        <li class="mb-2"><a href="#segmen">Segmen RFM</a></li>
                        <li class="mb-2"><a href="#faq">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h6 class="footer-head mb-3">Akses</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><a href="login.php"><i class="fas fa-sign-in-alt me-2"></i>Login</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4" style="border-color: rgba(255,255,255,0.15);">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <p class="mb-0 small">&copy; 2026 Smart Marketing Agent. Semua hak dilindungi.</p>
                <small class="d-inline-flex align-items-center"><i class="fas fa-heart text-danger me-1"></i>Dibuat untuk UMKM Indonesia</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/landing.js"></script>
</body>
</html>
