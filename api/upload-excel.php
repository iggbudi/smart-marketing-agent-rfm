<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Autentikasi wajib: hanya UMKM owner berstatus login yang boleh mengakses
requireAuthJson(['umkm_owner']);
$user = getCurrentUser();
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No business associated with your account. Please contact administrator.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['excel_file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

// Validasi terpusat (ekstensi + MIME finfo + ukuran)
$validation = \App\Upload\UploadValidator::validate($_FILES['excel_file']);
if (!$validation['ok']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $validation['message']]);
    exit;
}

try {
    $import = (new \App\Import\SpreadsheetImporter(getDB()))
        ->import($business['id'], $_FILES['excel_file']['tmp_name'], $_FILES['excel_file']['name']);

    echo json_encode([
        'success'   => $import['processed'] > 0,
        'message'   => $import['message'],
        'processed' => $import['processed'],
        'failed'    => $import['failed'],
        'errors'    => $import['errors'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal import: ' . $e->getMessage()]);
}
