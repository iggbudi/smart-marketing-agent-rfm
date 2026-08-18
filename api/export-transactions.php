<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../includes/export.php';

// Check if PhpSpreadsheet is available
$hasPhpSpreadsheet = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');
if ($hasPhpSpreadsheet) {
    require_once '../vendor/autoload.php';
}

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

// Get transactions for this business
$transactions = [];
try {
    $stmt = $db->prepare("
        SELECT t.*, c.customer_name, c.phone
        FROM transactions t 
        JOIN customers c ON t.customer_id = c.id 
        WHERE t.business_id = ? 
        ORDER BY t.transaction_date DESC, t.created_at DESC
    ");
    $stmt->execute([$business['id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error loading transactions: ' . $e->getMessage()]);
    exit;
}

// Fallback CSV bila PhpSpreadsheet tidak tersedia
if (!$hasPhpSpreadsheet) {
    $filename = 'transactions_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    writeTransactionsCsv($transactions);
    exit;
}

// Export Excel (XLSX)
try {
    $spreadsheet = buildTransactionsSpreadsheet($business['business_name'], $transactions);

    $filename = 'transactions_' . $business['business_name'] . '_' . date('Y-m-d_H-i-s') . '.xlsx';
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
?>
