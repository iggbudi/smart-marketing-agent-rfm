<?php
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

$file = $_FILES['excel_file'];

// Validasi ekstensi (parsing isi dilakukan di includes/import.php)
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validasi MIME asli via finfo (bukan hanya klaim dari client)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowedMimes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
    'application/vnd.ms-excel',                                          // .xls
    'application/octet-stream',                                          // beberapa .xls lama
    'text/csv',
    'text/plain',
];

if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ekstensi tidak didukung. Gunakan .xlsx, .xls, atau .csv.']);
    exit;
}
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipe file tidak valid. Pastikan file benar-benar spreadsheet/CSV.']);
    exit;
}

require_once '../includes/import.php';

try {
    $db = getDB();
    $import = importCustomerSpreadsheet($db, $business['id'], $file['tmp_name'], $file['name']);

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
?>
