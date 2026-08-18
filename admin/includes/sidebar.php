<?php
// Wrapper: seluruh halaman (user & admin) memakai satu sumber sidebar.
// Termasuk dari halaman di direktori admin/, string include 'includes/sidebar.php'
// akan meresolve ke file ini, yang kemudian memunculkan includes/sidebar.php.
include dirname(__DIR__) . '/includes/sidebar.php';
