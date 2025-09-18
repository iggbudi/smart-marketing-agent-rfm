<?php
require_once 'config/database.php';

$db = getDB();

echo "=== Smart Marketing Agent - Sample Data Generator ===\n\n";

// 1. Add sample businesses with proper owner relationships
echo "1. Adding sample businesses...\n";

$businesses = [
    ['name' => 'Warung Nasi Pak Budi', 'category' => 'Food & Beverage', 'user_id' => 2],
    ['name' => 'Toko Kelontong Ibu Sari', 'category' => 'Retail', 'user_id' => 3],
    ['name' => 'Bengkel Motor Jaya', 'category' => 'Service', 'user_id' => 4],
    ['name' => 'Salon Cantik Indah', 'category' => 'Beauty', 'user_id' => 5],
    ['name' => 'Bakery Fresh Morning', 'category' => 'Food & Beverage', 'user_id' => 6]
];

foreach ($businesses as $business) {
    try {
        $stmt = $db->prepare("INSERT INTO businesses (name, category, user_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$business['name'], $business['category'], $business['user_id']]);
        echo "✓ Added: {$business['name']}\n";
    } catch (Exception $e) {
        echo "- {$business['name']} already exists\n";
    }
}

// 2. Add sample customers
echo "\n2. Adding sample customers...\n";

$customers = [
    // Business 1 customers
    ['customer_name' => 'Ahmad Rahman', 'email' => 'ahmad@email.com', 'phone' => '08123456001', 'business_id' => 1],
    ['customer_name' => 'Siti Nurhaliza', 'email' => 'siti@email.com', 'phone' => '08123456002', 'business_id' => 1],
    ['customer_name' => 'Budi Santoso', 'email' => 'budi@email.com', 'phone' => '08123456003', 'business_id' => 1],
    ['customer_name' => 'Dewi Lestari', 'email' => 'dewi@email.com', 'phone' => '08123456004', 'business_id' => 1],
    ['customer_name' => 'Eko Prasetyo', 'email' => 'eko@email.com', 'phone' => '08123456005', 'business_id' => 1],
    
    // Business 2 customers
    ['customer_name' => 'Rina Wati', 'email' => 'rina@email.com', 'phone' => '08123456011', 'business_id' => 2],
    ['customer_name' => 'Joko Widodo', 'email' => 'joko@email.com', 'phone' => '08123456012', 'business_id' => 2],
    ['customer_name' => 'Maya Sari', 'email' => 'maya@email.com', 'phone' => '08123456013', 'business_id' => 2],
    ['customer_name' => 'Tono Hartono', 'email' => 'tono@email.com', 'phone' => '08123456014', 'business_id' => 2],
    
    // Business 3 customers
    ['customer_name' => 'Rudi Tabuti', 'email' => 'rudi@email.com', 'phone' => '08123456021', 'business_id' => 3],
    ['customer_name' => 'Lina Marlina', 'email' => 'lina@email.com', 'phone' => '08123456022', 'business_id' => 3],
    ['customer_name' => 'Hendra Setiawan', 'email' => 'hendra@email.com', 'phone' => '08123456023', 'business_id' => 3],
];

foreach ($customers as $customer) {
    try {
        $stmt = $db->prepare("INSERT INTO customers (customer_name, email, phone, business_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$customer['customer_name'], $customer['email'], $customer['phone'], $customer['business_id']]);
        echo "✓ Added customer: {$customer['customer_name']}\n";
    } catch (Exception $e) {
        echo "- Customer {$customer['customer_name']} already exists\n";
    }
}

// 3. Add sample transactions
echo "\n3. Adding sample transactions...\n";

$transaction_count = 0;
for ($i = 1; $i <= 12; $i++) { // Last 12 customers
    $customer_id = $i;
    $business_id = ceil($i / 4); // Distribute across businesses
    
    // Generate random transactions for each customer
    $num_transactions = rand(3, 15);
    
    for ($j = 0; $j < $num_transactions; $j++) {
        $amount = rand(10000, 500000); // 10k to 500k IDR
        $days_ago = rand(1, 365); // Within last year
        $transaction_date = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
        
        try {
            $stmt = $db->prepare("
                INSERT INTO transactions (business_id, customer_id, amount, transaction_date, product_name, quantity, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$business_id, $customer_id, $amount, $transaction_date, 'Product ' . rand(1, 10), rand(1, 5)]);
            $transaction_count++;
        } catch (Exception $e) {
            // Skip duplicates
        }
    }
}
echo "✓ Added {$transaction_count} transactions\n";

// 4. Generate RFM Analysis for all customers
echo "\n4. Generating RFM Analysis...\n";

// Get current settings
$customers_stmt = $db->query("SELECT id as customer_id, business_id FROM customers");
$customers_with_business = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($customers_with_business as $customer) {
    // Calculate RFM scores
    $customer_id = $customer['customer_id'];
    
    // Recency: Days since last transaction
    $last_transaction = $db->prepare("
        SELECT DATEDIFF(NOW(), MAX(transaction_date)) as days_since_last
        FROM transactions 
        WHERE customer_id = ?
    ");
    $last_transaction->execute([$customer_id]);
    $recency_days = $last_transaction->fetchColumn() ?: 999;
    
    // Frequency: Number of transactions
    $frequency_stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE customer_id = ?");
    $frequency_stmt->execute([$customer_id]);
    $frequency = $frequency_stmt->fetchColumn();
    
    // Monetary: Total amount spent
    $monetary_stmt = $db->prepare("SELECT SUM(amount) FROM transactions WHERE customer_id = ?");
    $monetary_stmt->execute([$customer_id]);
    $monetary = $monetary_stmt->fetchColumn() ?: 0;
    
    // Calculate scores (1-5 scale)
    $recency_score = $recency_days <= 30 ? 5 : ($recency_days <= 90 ? 4 : ($recency_days <= 180 ? 3 : ($recency_days <= 365 ? 2 : 1)));
    $frequency_score = $frequency >= 10 ? 5 : ($frequency >= 7 ? 4 : ($frequency >= 5 ? 3 : ($frequency >= 3 ? 2 : 1)));
    $monetary_score = $monetary >= 2000000 ? 5 : ($monetary >= 1000000 ? 4 : ($monetary >= 500000 ? 3 : ($monetary >= 200000 ? 2 : 1)));
    
    // Determine RFM segment
    $avg_score = ($recency_score + $frequency_score + $monetary_score) / 3;
    
    if ($avg_score >= 4.5) $segment = 'Champions';
    elseif ($avg_score >= 4.0) $segment = 'Loyal Customers';
    elseif ($avg_score >= 3.5) $segment = 'Potential Loyalists';
    elseif ($avg_score >= 3.0) $segment = 'New Customers';
    elseif ($avg_score >= 2.5) $segment = 'Promising';
    elseif ($avg_score >= 2.0) $segment = 'Customers Needing Attention';
    elseif ($avg_score >= 1.5) $segment = 'About to Sleep';
    else $segment = 'At Risk';
    
    // Insert RFM analysis
    try {
        $rfm_stmt = $db->prepare("
            INSERT INTO rfm_analysis (business_id, customer_id, recency_score, frequency_score, monetary_score, rfm_segment, 
                                    last_purchase_date, total_transactions, total_spent, analysis_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, (SELECT MAX(transaction_date) FROM transactions WHERE customer_id = ?), ?, ?, CURDATE(), NOW())
            ON DUPLICATE KEY UPDATE
            recency_score = VALUES(recency_score),
            frequency_score = VALUES(frequency_score),
            monetary_score = VALUES(monetary_score),
            rfm_segment = VALUES(rfm_segment),
            total_transactions = VALUES(total_transactions),
            total_spent = VALUES(total_spent),
            analysis_date = VALUES(analysis_date),
            created_at = NOW()
        ");
        $rfm_stmt->execute([$customer['business_id'], $customer_id, $recency_score, $frequency_score, $monetary_score, $segment, $customer_id, $frequency, $monetary]);
        echo "✓ RFM analysis for customer {$customer_id}: {$segment}\n";
    } catch (Exception $e) {
        echo "- Error analyzing customer {$customer_id}: " . $e->getMessage() . "\n";
    }
}

// 5. Add some activity logs
echo "\n5. Adding activity logs...\n";

$activities = [
    ['user_id' => 1, 'action' => 'login', 'details' => 'Super admin logged in'],
    ['user_id' => 2, 'action' => 'rfm_analysis', 'details' => 'Generated RFM analysis for business'],
    ['user_id' => 3, 'action' => 'customer_add', 'details' => 'Added new customer'],
    ['user_id' => 1, 'action' => 'user_create', 'details' => 'Created new user account'],
    ['user_id' => 4, 'action' => 'transaction_import', 'details' => 'Imported transaction data'],
];

foreach ($activities as $activity) {
    try {
        // Check if user exists first
        $user_check = $db->prepare("SELECT id FROM users WHERE id = ?");
        $user_check->execute([$activity['user_id']]);
        if ($user_check->fetchColumn()) {
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$activity['user_id'], $activity['action'], $activity['details']]);
            echo "✓ Added activity log: {$activity['action']}\n";
        } else {
            echo "- Skipped activity for non-existent user {$activity['user_id']}\n";
        }
    } catch (Exception $e) {
        echo "- Activity log error: " . $e->getMessage() . "\n";
    }
}

// 6. Add some API usage logs
echo "\n6. Adding API usage logs...\n";

$api_endpoints = ['/api/customers', '/api/transactions', '/api/rfm-analysis', '/api/reports'];
$api_types = ['openai', 'email', 'whatsapp', 'sms'];
for ($i = 0; $i < 50; $i++) {
    $endpoint = $api_endpoints[array_rand($api_endpoints)];
    $api_type = $api_types[array_rand($api_types)];
    $tokens_used = rand(100, 5000);
    $cost = $tokens_used * 0.0001; // $0.0001 per token
    $status = rand(0, 10) > 8 ? 'error' : 'success'; // 20% error rate
    $hours_ago = rand(1, 24);
    
    try {
        $stmt = $db->prepare("
            INSERT INTO api_usage_logs (business_id, api_type, endpoint, tokens_used, cost, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))
        ");
        $stmt->execute([rand(1, 5), $api_type, $endpoint, $tokens_used, $cost, $status, $hours_ago]);
    } catch (Exception $e) {
        // Skip errors
    }
}
echo "✓ Added 50 API usage logs\n";

// 7. Add system settings
echo "\n7. Adding system settings...\n";

$settings = [
    ['platform_name', 'Smart Marketing Agent'],
    ['platform_description', 'RFM Analysis Platform for Indonesian UMKM'],
    ['contact_email', 'admin@smartmarketing.local'],
    ['default_language', 'id'],
    ['timezone', 'Asia/Jakarta'],
    ['maintenance_mode', '0'],
    ['email_smtp_host', 'smtp.gmail.com'],
    ['email_smtp_port', '587'],
    ['email_from_name', 'Smart Marketing Agent'],
    ['security_session_timeout', '1440'],
    ['security_max_login_attempts', '5'],
    ['security_password_min_length', '8'],
    ['security_require_strong_password', '1'],
    ['security_log_user_activity', '1']
];

foreach ($settings as $setting) {
    try {
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        $stmt->execute($setting);
        echo "✓ Added setting: {$setting[0]}\n";
    } catch (Exception $e) {
        echo "- Setting error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Sample Data Generation Complete! ===\n";
echo "✓ Businesses: 5 added\n";
echo "✓ Customers: 12 added\n";
echo "✓ Transactions: {$transaction_count} added\n";
echo "✓ RFM Analysis: Generated for all customers\n";
echo "✓ Activity Logs: 5 added\n";
echo "✓ API Logs: 50 added\n";
echo "✓ System Settings: 15 added\n";
echo "\nYou can now login with:\n";
echo "- Super Admin: admin / admin123\n";
echo "- UMKM Owner: umkm1 / umkm123 (or umkm2, umkm3, etc.)\n";
echo "\nAdmin Panel: http://localhost/smart/admin/\n";
echo "Landing Page: http://localhost/smart/\n";
?>
