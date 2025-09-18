<?php
require_once 'config/database.php';
require_once 'config/auth.php';

echo "<h3>Testing Authentication System</h3>";

// Test database connection
$db = getDB();
if ($db) {
    echo "✅ Database connection: OK<br>";
} else {
    echo "❌ Database connection: FAILED<br>";
    exit;
}

// Test user data
$stmt = $db->prepare("SELECT id, email, full_name, role, password FROM users WHERE email = ?");
$stmt->execute(['admin@smartmarketing.local']);
$user = $stmt->fetch();

if ($user) {
    echo "✅ User found: " . $user['email'] . "<br>";
    echo "📧 Email: " . $user['email'] . "<br>";
    echo "👤 Name: " . $user['full_name'] . "<br>";
    echo "🔑 Role: " . $user['role'] . "<br>";
    echo "🔒 Password hash: " . substr($user['password'], 0, 20) . "...<br>";
    
    // Test password verification
    $password = 'password123';
    if (password_verify($password, $user['password'])) {
        echo "✅ Password verification: OK<br>";
    } else {
        echo "❌ Password verification: FAILED<br>";
    }
    
    // Test auth login
    echo "<br><h4>Testing Auth Login</h4>";
    $result = auth()->login('admin@smartmarketing.local', 'password123');
    if ($result['success']) {
        echo "✅ Auth login: SUCCESS<br>";
        echo "👤 User data: " . print_r($result['user'], true) . "<br>";
    } else {
        echo "❌ Auth login: FAILED<br>";
        echo "📝 Message: " . $result['message'] . "<br>";
    }
} else {
    echo "❌ User not found<br>";
}

// Test session table
$stmt = $db->query("SELECT COUNT(*) as count FROM user_sessions");
$count = $stmt->fetch();
echo "<br>📊 Session table records: " . $count['count'] . "<br>";
?>
