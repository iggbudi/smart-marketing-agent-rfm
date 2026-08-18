<?php
// Bottom navigation mobile — SATU sumber utk halaman user.
$bnPage = basename($_SERVER['PHP_SELF'] ?? ''); // fallback utk render CLI/test
$bnItems = [
    ['dashboard.php',  'fa-tachometer-alt', 'Dashboard'],
    ['customers.php',  'fa-users',          'Data'],
    ['analysis.php',   'fa-chart-pie',      'RFM'],
    ['ai-content.php', 'fa-magic',          'AI'],
    ['profile.php',    'fa-user',           'Profil'],
];
?>
<nav class="bottom-nav" aria-label="Navigasi bawah">
    <?php foreach ($bnItems as $bnItem): ?>
    <a class="bottom-nav-item <?= $bnPage === $bnItem[0] ? 'active' : '' ?>" href="<?= $bnItem[0] ?>">
        <i class="fas <?= $bnItem[1] ?>"></i>
        <span><?= $bnItem[2] ?></span>
    </a>
    <?php endforeach; ?>
</nav>
