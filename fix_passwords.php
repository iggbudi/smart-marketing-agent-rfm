<?php
require_once 'config/database.php';

$db = getDB();

// Generate hash for password123
$hash = password_hash('password123', PASSWORD_DEFAULT);
echo "New hash: " . $hash . "\n";

// Update users
$stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");

// Update admin
$stmt->execute([$hash, 'admin@smartmarketing.local']);
echo "Updated admin password\n";

// Update UMKM owner
$stmt->execute([$hash, 'budi@batiksemarang.com']);
echo "Updated UMKM owner password\n";

// Test verification
echo "Testing verification: " . (password_verify('password123', $hash) ? 'SUCCESS' : 'FAILED') . "\n";

// Show updated users
$stmt = $db->query("SELECT email, LEFT(password, 30) as password_preview FROM users");
$users = $stmt->fetchAll();

echo "\nUpdated users:\n";
foreach($users as $user) {
    echo $user['email'] . " -> " . $user['password_preview'] . "...\n";
}
?>
