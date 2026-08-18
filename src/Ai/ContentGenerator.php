<?php
/**
 * src/Ai/ContentGenerator.php
 * Slice vertikal "AI Content": generate konten marketing per segmen + persist.
 * Mencoba OpenAIClient dulu; fallback dummy bila gagal/tidak dikonfigurasi.
 * OpenAIClient di-inject opsional agar bisa di-mock di test (tanpa network).
 *
 * catch(\Throwable) (bukan hanya \Exception) agar bila class OpenAIClient tidak
 * dimuat (Error) pun tetap jatuh ke dummy, tidak fatal.
 */

namespace App\Ai;

class ContentGenerator
{
    /** @var \PDO */
    private $db;
    /** @var int */
    private $businessId;

    public function __construct(\PDO $db, int $businessId)
    {
        $this->db = $db;
        $this->businessId = $businessId;
    }

    /**
     * Generate konten marketing untuk satu segmen & simpan ke ai_generated_content.
     *
     * @param \OpenAIClient|null $client Mock/instance OpenAI (default: buat sendiri).
     * @return array{success: bool, content: string, source: string, note: ?string, error: ?string}
     */
    public function generate(string $segment, ?\OpenAIClient $client = null): array
    {
        try {
            $client = $client ?? new \OpenAIClient();
            $content = $client->generateMarketingContent($segment)['content'] ?? '';
            $this->persistQuiet($segment, $content);
            return ['success' => true, 'content' => $content, 'source' => 'openai', 'note' => null, 'error' => null];
        } catch (\Throwable $e) {
            $dummy = self::dummyContent($segment);
            $this->persistQuiet($segment, $dummy);
            return [
                'success' => true,
                'content' => $dummy,
                'source' => 'dummy',
                'note' => 'Generated using fallback content (OpenAI API not available)',
                'error' => null,
            ];
        }
    }

    /** Riwayat konten terbaru utk business ini (id DESC = tiebreaker utk created_at sama). */
    public function recent(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT segment, content, created_at FROM ai_generated_content
             WHERE business_id = ? ORDER BY created_at DESC, id DESC LIMIT " . (int)$limit
        );
        $stmt->execute([$this->businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function persist(string $segment, string $content): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_generated_content (business_id, segment, content, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$this->businessId, $segment, $content]);
    }

    /**
     * Persist sebagai baris audit/history. Gagal simpan (mis. charset kolum utf8
     * tak sanggup menyimpan emoji 4-byte dari konten dummy) TIDAK boleh menggagalkan
     * generation — konten tetap dikembalikan ke user; errornya di-log.
     */
    private function persistQuiet(string $segment, string $content): void
    {
        try {
            $this->persist($segment, $content);
        } catch (\Throwable $e) {
            error_log('ContentGenerator::persist gagal: ' . $e->getMessage());
        }
    }

    /**
     * Konten dummy per segmen (fallback offline).
     * Isi = generateDummyContent() di api/generate-content.php (dipindah verbatim).
     */
    public static function dummyContent(string $segment): string
    {
        $businessName = 'Batik Semarang Jaya';

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
}
