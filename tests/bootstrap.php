<?php
/**
 * tests/bootstrap.php
 * Bootstrap PHPUnit.
 *
 * Mengarahkan koneksi DB ke database TEST (smart_marketing_rfm_test) via env var,
 * SEBELUM config/database.php dibaca (prioritas env() di config/env.php:
 * getenv > .env > default). Jangan pernah menjalankan test ke DB produksi.
 */

putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_NAME=smart_marketing_rfm_test');
putenv('DB_USER=root');
putenv('DB_PASSWORD=');

require dirname(__DIR__) . '/vendor/autoload.php';

// Memuat definisi AuthManager & fungsi global (config/auth.php) agar bisa
// diuji langsung. Autoload composer menangani class App\* (repository/exporter/import).
require_once dirname(__DIR__) . '/config/auth.php';
