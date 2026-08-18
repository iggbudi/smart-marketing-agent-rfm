<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once '../config/database.php';
require_once '../config/auth.php';

// Require UMKM owner access
requireAuthJson(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No business associated with your account. Please contact administrator.']);
    exit;
}

// Data dari slice Transactions
try {
    $transactions = (new \App\Transactions\TransactionRepository($db))->allWithCustomer($business['id']);
} catch (\PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error loading transactions: ' . $e->getMessage()]);
    exit;
}

// Fallback CSV bila PhpSpreadsheet tidak tersedia
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    $filename = 'transactions_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    \App\Export\TransactionsExporter::writeCsv($transactions);
    exit;
}

try {
    $spreadsheet = \App\Export\TransactionsExporter::buildSpreadsheet($business['name'], $transactions);

    $filename = 'transactions_' . $business['name'] . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error creating Excel file: ' . $e->getMessage()]);
    exit;
}
