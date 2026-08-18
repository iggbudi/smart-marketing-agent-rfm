<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/openai.php';

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

$input = json_decode(file_get_contents('php://input'), true);
$segment = $input['segment'] ?? '';
if ($segment === '') {
    echo json_encode(['success' => false, 'error' => 'Segment is required']);
    exit;
}

$generator = new \App\Ai\ContentGenerator(getDB(), $business['id']);
$result = $generator->generate($segment);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

$response = [
    'success' => true,
    'content' => nl2br(htmlspecialchars($result['content'])),
    'source' => $result['source'],
];
if ($result['note'] !== null) {
    $response['note'] = $result['note'];
}
echo json_encode($response);
