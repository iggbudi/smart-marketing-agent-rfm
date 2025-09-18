<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/openai.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$segment = $input['segment'] ?? '';

if (empty($segment)) {
    echo json_encode(['success' => false, 'error' => 'Segment is required']);
    exit;
}

try {
    // Try to use OpenAI API first
    $openai = new OpenAIClient();
    $result = $openai->generateMarketingContent($segment);
    
    // Extract content from result
    $content = $result['content'] ?? '';
    
    // Save to database
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO ai_generated_content (business_id, segment, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([1, $segment, $content]); // Default business_id = 1
    
    echo json_encode([
        'success' => true,
        'content' => nl2br(htmlspecialchars($content)),
        'source' => 'openai'
    ]);
    
} catch (Exception $e) {
    // Fallback to dummy content if API fails
    $dummyContent = generateDummyContent($segment);
    
    // Save dummy content to database
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO ai_generated_content (business_id, segment, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([1, $segment, $dummyContent]);
    
    echo json_encode([
        'success' => true,
        'content' => nl2br(htmlspecialchars($dummyContent)),
        'source' => 'dummy',
        'note' => 'Generated using fallback content (OpenAI API not available)'
    ]);
}

function generateDummyContent($segment) {
    $businessName = "Batik Semarang Jaya";
    
    switch ($segment) {
        case 'Champions':
            return "🏆 STRATEGI MARKETING UNTUK CHAMPIONS\n\n" .
                   "Halo Pelanggan VIP {$businessName}!\n\n" .
                   "Sebagai customer terbaik kami, Anda berhak mendapatkan:\n" .
                   "✨ EXCLUSIVE PREVIEW koleksi batik terbaru\n" .
                   "🎁 GRATIS ongkir selamanya\n" .
                   "💎 Diskon VIP 25% untuk semua produk\n" .
                   "👥 Undang 3 teman, dapatkan voucher Rp 500.000\n\n" .
                   "Program Loyalitas Premium:\n" .
                   "- Akses early bird sale\n" .
                   "- Personal stylist consultation\n" .
                   "- Birthday special discount 50%\n\n" .
                   "Terima kasih telah mempercayai {$businessName} sebagai pilihan utama fashion batik Anda!";
                   
        case 'Loyal Customers':
            return "💙 APRESIASI UNTUK LOYAL CUSTOMERS\n\n" .
                   "Dear Pelanggan Setia {$businessName},\n\n" .
                   "Kesetiaan Anda sangat berarti bagi kami!\n\n" .
                   "Reward Loyalitas Bulan Ini:\n" .
                   "🛍️ Cashback 15% untuk pembelian berikutnya\n" .
                   "📦 Free upgrade ke packaging premium\n" .
                   "⭐ Priority customer service\n" .
                   "🎊 Surprise gift setiap 5 pembelian\n\n" .
                   "Rekomendasi Special:\n" .
                   "- Koleksi batik eksklusif limited edition\n" .
                   "- Bundle package hemat 3 pcs\n" .
                   "- Pre-order koleksi season mendatang\n\n" .
                   "Mari lanjutkan perjalanan fashion batik bersama {$businessName}!";
                   
        case 'Potential Loyalists':
            return "🌟 UNDANGAN KHUSUS POTENTIAL LOYALISTS\n\n" .
                   "Halo Fashion Enthusiast!\n\n" .
                   "Kami melihat Anda memiliki taste yang luar biasa dalam memilih batik berkualitas tinggi.\n\n" .
                   "Special Offer untuk Anda:\n" .
                   "🎯 Diskon 20% untuk pembelian kedua\n" .
                   "💝 Bonus aksesori batik eksklusif\n" .
                   "📱 Join VIP WhatsApp group untuk update terbaru\n" .
                   "🚚 Gratis ongkir untuk 3 pembelian berikutnya\n\n" .
                   "Koleksi Rekomendasi:\n" .
                   "- Batik premium collection\n" .
                   "- Couple set untuk acara special\n" .
                   "- Batik formal untuk profesional\n\n" .
                   "Jadilah bagian dari keluarga besar {$businessName}!";
                   
        case 'At Risk':
            return "⚠️ WE MISS YOU - COMEBACK CAMPAIGN\n\n" .
                   "Halo Sahabat {$businessName},\n\n" .
                   "Sudah lama tidak berjumpa... Kami merindukan Anda! 💔\n\n" .
                   "WELCOME BACK SPECIAL:\n" .
                   "🔥 MEGA DISKON 30% untuk comeback Anda\n" .
                   "🎁 Mystery gift di setiap pembelian\n" .
                   "💳 Cicilan 0% untuk pembelian minimal Rp 500.000\n" .
                   "📞 Personal assistance untuk rekomendasi produk\n\n" .
                   "Yang Baru di {$businessName}:\n" .
                   "- Koleksi batik modern casual\n" .
                   "- Technology fabric anti-wrinkle\n" .
                   "- Custom design service\n\n" .
                   "Jangan lewatkan kesempatan comeback ini! Valid hingga akhir bulan.";
                   
        case 'Lost Customers':
            return "💸 WIN-BACK SUPER CAMPAIGN\n\n" .
                   "Dear Valued Customer,\n\n" .
                   "Kami ingin meminta maaf jika ada yang kurang berkenan di masa lalu.\n\n" .
                   "FORGIVE US MEGA SALE:\n" .
                   "🚨 DISKON GILA 50% semua produk\n" .
                   "🎊 Buy 1 Get 1 untuk kategori tertentu\n" .
                   "🆓 Gratis custom design untuk 10 pembeli pertama\n" .
                   "💌 Personal apology letter dari owner\n\n" .
                   "Pembaruan {$businessName}:\n" .
                   "- Kualitas bahan premium upgrade\n" .
                   "- Layanan customer service 24/7\n" .
                   "- Garansi kepuasan 100%\n" .
                   "- Easy return policy\n\n" .
                   "Berikan kami kesempatan kedua untuk melayani Anda lebih baik!";
                   
        default:
            return "📢 MARKETING CONTENT\n\n" .
                   "Halo Customer {$businessName}!\n\n" .
                   "Terima kasih telah menjadi bagian dari perjalanan kami.\n" .
                   "Dapatkan update terbaru dan penawaran menarik hanya untuk Anda!\n\n" .
                   "Hubungi kami:\n" .
                   "📱 WhatsApp: 08123456789\n" .
                   "📧 Email: info@batiksemarang.com\n" .
                   "🏪 Alamat: Jl. Pandanaran 123, Semarang";
    }
}
?>
