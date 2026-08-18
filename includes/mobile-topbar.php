<?php
// Mobile top bar — SATU sumber utk semua halaman user.
// Set $mobilePageTitle di halaman sebelum include; default aman utk render CLI/test.
$mobilePageTitle = $mobilePageTitle ?? 'Smart Marketing';
?>
<div class="mobile-topbar">
    <button class="mobile-menu-toggle" onclick="toggleSidebar()" aria-label="Buka menu">
        <i class="fas fa-bars"></i>
    </button>
    <span class="mobile-topbar-title"><?= htmlspecialchars($mobilePageTitle) ?></span>
    <a class="mobile-topbar-avatar" href="profile.php" aria-label="Profil">
        <i class="fas fa-user"></i>
    </a>
</div>
